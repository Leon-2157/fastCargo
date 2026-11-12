<?php

namespace App\Modules\Admin\Controllers;

use App\Modules\Shared\Models\AdmEscrowLedger;
use App\Modules\Shared\Models\AdmInvoice;
use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\OpsManifest;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    /**
     * Display the managerial reports and data export center.
     */
    public function index(Request $request): View
    {
        $counts = [
            'invoices' => AdmInvoice::count(),
            'manifests' => OpsManifest::count(),
            'escrow' => AdmEscrowLedger::count(),
            'bookings' => OpsCargoBooking::count(),
            'audit' => DB::table('adm_immutable_audit_trails')->count(),
        ];

        return view('admin.report.index', [
            'role' => 'admin',
            'counts' => $counts,
        ]);
    }

    /**
     * Stream CSV export with UTF-8 BOM encoding for Excel/Sheets compatibility.
     */
    public function export(Request $request, string $category): StreamedResponse
    {
        abort_unless(in_array($category, ['invoices', 'manifests', 'escrow', 'bookings', 'audit'], true), 404);

        $dateRange = $this->resolveDateRange($request);
        $status = $request->query('status');

        $timestamp = now()->format('Ymd_His');
        $filename = "FastCargo_{$category}_report_{$timestamp}.csv";

        // Log immutable governance audit trail
        DB::table('adm_immutable_audit_trails')->insert([
            'actor_id' => auth('admin')->id(),
            'actor_type' => 'ADMIN',
            'action' => 'REPORT_EXPORTED_CSV',
            'target_type' => 'reports',
            'target_id' => $category,
            'before' => json_encode([
                'category' => $category,
                'preset' => $request->query('preset'),
                'start_date' => $dateRange['start']?->toDateString(),
                'end_date' => $dateRange['end']?->toDateString(),
                'status' => $status,
            ]),
            'after' => json_encode(['filename' => $filename, 'exported_at' => now()->toIso8601String()]),
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return new StreamedResponse(function () use ($category, $dateRange, $status) {
            $handle = fopen('php://output', 'w');

            // Write UTF-8 BOM for Microsoft Excel / Google Sheets auto-formatting
            fwrite($handle, "\xEF\xBB\xBF");

            match ($category) {
                'invoices' => $this->streamInvoices($handle, $dateRange, $status),
                'manifests' => $this->streamManifests($handle, $dateRange, $status),
                'escrow' => $this->streamEscrow($handle, $dateRange, $status),
                'bookings' => $this->streamBookings($handle, $dateRange, $status),
                'audit' => $this->streamAudit($handle, $dateRange, $status),
            };

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Resolve date filtering range based on preset or custom calendar inputs.
     *
     * @return array{start: ?Carbon, end: ?Carbon}
     */
    private function resolveDateRange(Request $request): array
    {
        $preset = $request->query('preset');

        if ($preset === 'today') {
            return ['start' => now()->startOfDay(), 'end' => now()->endOfDay()];
        }

        if ($preset === 'last_7_days') {
            return ['start' => now()->subDays(7)->startOfDay(), 'end' => now()->endOfDay()];
        }

        if ($preset === 'this_month') {
            return ['start' => now()->startOfMonth(), 'end' => now()->endOfMonth()];
        }

        $start = $request->filled('start_date') ? Carbon::parse($request->query('start_date'))->startOfDay() : null;
        $end = $request->filled('end_date') ? Carbon::parse($request->query('end_date'))->endOfDay() : null;

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Stream invoices report rows.
     *
     * @param  resource  $handle
     * @param  array{start: ?Carbon, end: ?Carbon}  $dates
     */
    private function streamInvoices($handle, array $dates, ?string $status): void
    {
        fputcsv($handle, [
            'Invoice Number',
            'Tracking / Waybill Resi',
            'Customer Name',
            'Customer Email',
            'Currency',
            'Total Invoice Amount',
            'Due Date',
            'Payment Status',
            'Issued Date',
            'Paid Date',
            'Gateway Partner Reference',
        ]);

        $query = AdmInvoice::with(['booking.shipper', 'paymentTransactions'])->latest('created_at');

        if ($dates['start']) {
            $query->where('created_at', '>=', $dates['start']);
        }
        if ($dates['end']) {
            $query->where('created_at', '<=', $dates['end']);
        }
        if (! empty($status)) {
            $query->where('payment_status', $status);
        }

        foreach ($query->cursor() as $invoice) {
            $tx = $invoice->paymentTransactions->first();
            fputcsv($handle, [
                $invoice->invoice_number,
                $invoice->booking?->tracking_number ?? 'N/A',
                $invoice->booking?->shipper?->name ?? 'Guest/Unknown',
                $invoice->booking?->shipper?->email ?? 'N/A',
                $invoice->currency ?? 'IDR',
                number_format((float) $invoice->total_invoice_amount, 2, '.', ''),
                $invoice->due_date?->format('Y-m-d') ?? 'N/A',
                $invoice->payment_status,
                $invoice->created_at->format('Y-m-d H:i:s'),
                $invoice->paid_at?->format('Y-m-d H:i:s') ?? 'Unpaid',
                $tx?->transaction_ref ?? 'N/A',
            ]);
        }
    }

    /**
     * Stream manifests and vessel/vehicle operations rows.
     *
     * @param  resource  $handle
     * @param  array{start: ?Carbon, end: ?Carbon}  $dates
     */
    private function streamManifests($handle, array $dates, ?string $status): void
    {
        fputcsv($handle, [
            'Manifest Number',
            'Vessel / Vehicle Name',
            'Voyage / Trip Number',
            'Modality',
            'Origin Hub',
            'Destination Hub',
            'Operational Status',
            'Total Consignment Items',
            'Estimated Departure (ETD)',
            'Estimated Arrival (ETA)',
            'Created Date',
        ]);

        $query = OpsManifest::with(['originPort', 'destinationPort'])->withCount('items')->latest('created_at');

        if ($dates['start']) {
            $query->where('created_at', '>=', $dates['start']);
        }
        if ($dates['end']) {
            $query->where('created_at', '<=', $dates['end']);
        }
        if (! empty($status)) {
            $query->where('status', $status);
        }

        foreach ($query->cursor() as $manifest) {
            fputcsv($handle, [
                $manifest->manifest_number,
                $manifest->vessel_or_vehicle_name,
                $manifest->voyage_number,
                $manifest->modality ?? 'SEA',
                "{$manifest->origin_port_id} - ".($manifest->originPort?->name ?? 'Hub'),
                "{$manifest->destination_port_id} - ".($manifest->destinationPort?->name ?? 'Hub'),
                $manifest->status,
                $manifest->items_count ?? 0,
                $manifest->etd?->format('Y-m-d H:i:s') ?? 'N/A',
                $manifest->eta?->format('Y-m-d H:i:s') ?? 'N/A',
                $manifest->created_at->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Stream escrow ledgers and revenue split distributions.
     *
     * @param  resource  $handle
     * @param  array{start: ?Carbon, end: ?Carbon}  $dates
     */
    private function streamEscrow($handle, array $dates, ?string $status): void
    {
        fputcsv($handle, [
            'Escrow Ledger ID',
            'Tracking / Waybill Resi',
            'Total Escrow Held (IDR)',
            'M1 Gate-In 30% Amount',
            'M1 Released At',
            'M2 Sailing 40% Amount',
            'M2 Released At',
            'M3 Delivered 30% Amount',
            'M3 Released At',
            'Escrow Status',
            'Operator Share (78%)',
            'Platform Fee (12%)',
            'Port & Tax Dues (10%)',
        ]);

        $query = AdmEscrowLedger::with(['cargoBooking'])->latest('created_at');

        if ($dates['start']) {
            $query->where('created_at', '>=', $dates['start']);
        }
        if ($dates['end']) {
            $query->where('created_at', '<=', $dates['end']);
        }
        if (! empty($status)) {
            $query->where('escrow_status', $status);
        }

        foreach ($query->cursor() as $escrow) {
            $split = DB::table('adm_revenue_splits')->where('cargo_booking_id', $escrow->cargo_booking_id)->first();

            fputcsv($handle, [
                $escrow->id,
                $escrow->cargoBooking?->tracking_number ?? 'N/A',
                number_format((float) $escrow->total_paid_amount, 2, '.', ''),
                number_format((float) $escrow->milestone_1_pickup_amount, 2, '.', ''),
                $escrow->milestone_1_released_at?->format('Y-m-d H:i:s') ?? 'Pending',
                number_format((float) $escrow->milestone_2_sailing_amount, 2, '.', ''),
                $escrow->milestone_2_released_at?->format('Y-m-d H:i:s') ?? 'Pending',
                number_format((float) $escrow->milestone_3_delivered_amount, 2, '.', ''),
                $escrow->milestone_3_released_at?->format('Y-m-d H:i:s') ?? 'Pending',
                $escrow->escrow_status,
                $split ? number_format((float) $split->operator_share_amount, 2, '.', '') : 'N/A',
                $split ? number_format((float) $split->platform_fee_amount, 2, '.', '') : 'N/A',
                $split ? number_format((float) $split->tax_and_port_dues, 2, '.', '') : 'N/A',
            ]);
        }
    }

    /**
     * Stream consignment bookings and tracking events.
     *
     * @param  resource  $handle
     * @param  array{start: ?Carbon, end: ?Carbon}  $dates
     */
    private function streamBookings($handle, array $dates, ?string $status): void
    {
        fputcsv($handle, [
            'Waybill Resi Number',
            'Customer / Shipper Name',
            'Origin Hub',
            'Destination Hub',
            'Modality',
            'Cargo Status',
            'Total Weight (KG)',
            'Declared Value (IDR)',
            'Latest Milestone',
            'Latest Coordinates (Lat,Lng)',
            'Booking Created Date',
        ]);

        $query = OpsCargoBooking::with([
            'shipper',
            'route.originPort',
            'route.destinationPort',
            'trackingEvents' => fn ($q) => $q->latest('event_timestamp')->take(1),
        ])->latest('created_at');

        if ($dates['start']) {
            $query->where('created_at', '>=', $dates['start']);
        }
        if ($dates['end']) {
            $query->where('created_at', '<=', $dates['end']);
        }
        if (! empty($status)) {
            $query->where('cargo_status', $status);
        }

        foreach ($query->cursor() as $booking) {
            $latestEvent = $booking->trackingEvents->first();
            $coordinates = ($latestEvent && $latestEvent->latitude)
                ? "{$latestEvent->latitude}, {$latestEvent->longitude}"
                : 'N/A';

            fputcsv($handle, [
                $booking->tracking_number,
                $booking->shipper?->name ?? 'Guest Shipper',
                $booking->route?->origin_port_id ?? 'N/A',
                $booking->route?->destination_port_id ?? 'N/A',
                $booking->route?->modality ?? 'SEA',
                $booking->cargo_status,
                number_format((float) ($booking->total_weight_kg ?? 0), 2, '.', ''),
                number_format((float) ($booking->declared_goods_value ?? 0), 2, '.', ''),
                $latestEvent?->status_code ?? $booking->cargo_status,
                $coordinates,
                $booking->created_at->format('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Stream immutable audit trail rows.
     *
     * @param  resource  $handle
     * @param  array{start: ?Carbon, end: ?Carbon}  $dates
     */
    private function streamAudit($handle, array $dates, ?string $status): void
    {
        fputcsv($handle, [
            'Audit ID',
            'Timestamp (UTC)',
            'Actor ID',
            'Actor Name',
            'Actor Role',
            'Action Code',
            'Target Module',
            'Target ID',
            'IP Address',
            'Before State JSON',
            'After State JSON',
        ]);

        $query = DB::table('adm_immutable_audit_trails')
            ->leftJoin('users', 'adm_immutable_audit_trails.actor_id', '=', 'users.id')
            ->select('adm_immutable_audit_trails.*', 'users.name as actor_name')
            ->latest('adm_immutable_audit_trails.created_at');

        if ($dates['start']) {
            $query->where('adm_immutable_audit_trails.created_at', '>=', $dates['start']);
        }
        if ($dates['end']) {
            $query->where('adm_immutable_audit_trails.created_at', '<=', $dates['end']);
        }
        if (! empty($status)) {
            $query->where('adm_immutable_audit_trails.action', $status);
        }

        foreach ($query->cursor() as $row) {
            fputcsv($handle, [
                $row->id,
                $row->created_at,
                $row->actor_id ?? 'SYSTEM',
                $row->actor_name ?? 'Automated Daemon',
                $row->actor_type ?? 'SYSTEM',
                $row->action,
                $row->target_type ?? 'N/A',
                $row->target_id ?? 'N/A',
                $row->ip_address ?? '127.0.0.1',
                $row->before ?? '{}',
                $row->after ?? '{}',
            ]);
        }
    }
}
