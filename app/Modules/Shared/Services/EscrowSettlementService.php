<?php

namespace App\Modules\Shared\Services;

use App\Modules\Shared\Models\AdmEscrowLedger;
use App\Modules\Shared\Models\AdmInvoice;
use App\Modules\Shared\Models\AdmRevenueSplit;
use App\Modules\Shared\Models\OpsCargoBooking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EscrowSettlementService
{
    /**
     * Inisialisasi dan kunci dana ke escrow ledger saat invoice customer dibayar lunas.
     */
    public function fundEscrowFromPaidInvoice(AdmInvoice $invoice): ?AdmEscrowLedger
    {
        if (! $invoice->cargo_booking_id || (float) $invoice->total_invoice_amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($invoice) {
            $total = (float) $invoice->total_invoice_amount;

            // Alokasi 3 tahap milestone: 30% Gate-In, 40% Sailing, 30% Delivered
            $m1 = round($total * 0.30, 2);
            $m2 = round($total * 0.40, 2);
            $m3 = round($total - $m1 - $m2, 2);

            $escrow = AdmEscrowLedger::updateOrCreate(
                ['cargo_booking_id' => $invoice->cargo_booking_id],
                [
                    'total_paid_amount' => $total,
                    'milestone_1_pickup_amount' => $m1,
                    'milestone_2_sailing_amount' => $m2,
                    'milestone_3_delivered_amount' => $m3,
                    'escrow_status' => AdmEscrowLedger::STATUS_HELD_IN_ESCROW,
                ]
            );

            Log::info('Escrow ledger successfully funded from settled invoice', [
                'invoice_id' => $invoice->id,
                'booking_id' => $invoice->cargo_booking_id,
                'total_amount' => $total,
            ]);

            return $escrow;
        });
    }

    /**
     * Evaluasi dan rilis milestone escrow otomatis berdasarkan update status kargo operasional.
     */
    public function checkAndReleaseMilestone(OpsCargoBooking $booking, string $triggerStatus): ?AdmEscrowLedger
    {
        $escrow = AdmEscrowLedger::where('cargo_booking_id', $booking->id)->first();
        if (! $escrow) {
            return null;
        }

        if ($escrow->escrow_status === AdmEscrowLedger::STATUS_DISPUTE_FROZEN) {
            Log::warning('Escrow milestone release skipped: Escrow is FROZEN under dispute', [
                'booking_id' => $booking->id,
                'escrow_id' => $escrow->id,
            ]);

            return $escrow;
        }

        $now = now();
        $statusUpper = strtoupper($triggerStatus);
        $dirty = false;

        // Milestone 1: 30% saat GATE_IN / PICKUP
        if (in_array($statusUpper, ['GATE_IN', 'CUSTOMS_INSPECTION', 'SAILING', 'IN_TRANSIT', 'DELIVERED'], true)) {
            if ($escrow->milestone_1_released_at === null) {
                $escrow->milestone_1_released_at = $now;
                $escrow->escrow_status = AdmEscrowLedger::STATUS_PARTIALLY_RELEASED;
                $dirty = true;
                Log::info('Escrow Milestone 1 (30% Terminal Gate-In) released to operator pool', ['booking_id' => $booking->id]);
            }
        }

        // Milestone 2: 40% saat SAILING / IN_TRANSIT
        if (in_array($statusUpper, ['SAILING', 'IN_TRANSIT', 'DELIVERED'], true)) {
            if ($escrow->milestone_2_released_at === null) {
                $escrow->milestone_2_released_at = $now;
                $escrow->escrow_status = AdmEscrowLedger::STATUS_PARTIALLY_RELEASED;
                $dirty = true;
                Log::info('Escrow Milestone 2 (40% Sailing Departure) released to operator pool', ['booking_id' => $booking->id]);
            }
        }

        // Milestone 3: 30% saat DELIVERED (ePOD)
        if ($statusUpper === 'DELIVERED') {
            if ($escrow->milestone_3_released_at === null) {
                $escrow->milestone_3_released_at = $now;
                $escrow->escrow_status = AdmEscrowLedger::STATUS_FULLY_SETTLED;
                $dirty = true;
                Log::info('Escrow Milestone 3 (30% ePOD Delivered) fully settled', ['booking_id' => $booking->id]);

                // Eksekusi revenue split akhir
                $this->executeRevenueSplit($booking, (float) $escrow->total_paid_amount);
            }
        }

        if ($dirty) {
            $escrow->save();
        }

        return $escrow;
    }

    /**
     * Hitung dan simpan pembagian pendapatan (Revenue Split) platform vs operator.
     */
    public function executeRevenueSplit(OpsCargoBooking $booking, float $totalPaid): AdmRevenueSplit
    {
        $platformFee = round($totalPaid * 0.12, 2); // 12% Platform Fee
        $taxAndPort = round($totalPaid * 0.10, 2);   // 10% Tax & Port Dues
        $operatorShare = round($totalPaid - $platformFee - $taxAndPort, 2); // 78% Operator Share
        $netProfit = round($platformFee * 0.75, 2); // Net profit setelah biaya payment gateway & server

        return AdmRevenueSplit::updateOrCreate(
            ['cargo_booking_id' => $booking->id],
            [
                'platform_fee_amount' => $platformFee,
                'operator_share_amount' => $operatorShare,
                'tax_and_port_dues' => $taxAndPort,
                'net_platform_profit' => $netProfit,
            ]
        );
    }
}
