<?php

namespace App\Modules\Shared\WhatsApp;

use App\Jobs\SendWhatsAppNotification;
use App\Modules\Shared\Models\AdmInvoice;
use App\Modules\Shared\Models\AdmOperatorInvitation;
use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\OpsIncidentEscalation;

/**
 * Layanan terpusat untuk memformat & mengirim notifikasi WhatsApp operasional via queue.
 */
class WaNotificationService
{
    public static function trackingUrl(string $trackingNumber): string
    {
        $base = rtrim((string) config('app.portal_urls.landing', 'http://fastcargo.local:8080'), '/');

        return "{$base}/track/{$trackingNumber}";
    }

    public static function notifyBookingCreated(OpsCargoBooking $booking, ?AdmInvoice $invoice = null): void
    {
        $booking->loadMissing(['shipper', 'company', 'route.originPort', 'route.destinationPort']);
        $phone = self::resolveBookingPhone($booking);
        if ($phone === null) {
            return;
        }

        $origin = $booking->route?->originPort?->id ?? 'ORIGIN';
        $dest = $booking->route?->destinationPort?->id ?? 'DEST';
        $invoiceLine = $invoice
            ? "\nInvoice: *{$invoice->invoice_number}* (IDR ".number_format((float) $invoice->total_invoice_amount, 0, ',', '.').')'
            : '';

        $message = "*[FastCargo Booking Confirmed]*\n"
            ."Waybill: *{$booking->tracking_number}*\n"
            ."Corridor: {$origin} -> {$dest} ({$booking->booking_type})\n"
            ."Status: *{$booking->cargo_status}*{$invoiceLine}\n"
            .'Track: '.self::trackingUrl($booking->tracking_number);

        self::dispatchMessage($phone, $message, 'booking_created');
    }

    public static function notifyBookingStatusUpdated(OpsCargoBooking $booking, string $eventTitle, ?string $details = null): void
    {
        $booking->loadMissing(['shipper', 'company', 'route.originPort', 'route.destinationPort']);
        $phone = self::resolveBookingPhone($booking);
        if ($phone === null) {
            return;
        }

        $origin = $booking->route?->originPort?->id ?? 'ORIGIN';
        $dest = $booking->route?->destinationPort?->id ?? 'DEST';
        $detailLine = $details ? "\nNote: {$details}" : '';

        $message = "*[FastCargo Tracking Update]*\n"
            ."Waybill: *{$booking->tracking_number}*\n"
            ."Corridor: {$origin} -> {$dest}\n"
            ."Status: *{$booking->cargo_status}*\n"
            ."Milestone: {$eventTitle}{$detailLine}\n"
            .'Track: '.self::trackingUrl($booking->tracking_number);

        self::dispatchMessage($phone, $message, 'booking_status_updated');
    }

    public static function notifyDelivered(OpsCargoBooking $booking, string $recipientName, ?string $recipientPhone = null): void
    {
        $booking->loadMissing(['shipper', 'company']);
        $trackingUrl = self::trackingUrl($booking->tracking_number);

        $message = "*[FastCargo Delivery Completed]*\n"
            ."Waybill: *{$booking->tracking_number}*\n"
            ."Status: *DELIVERED*\n"
            ."Signed by: {$recipientName}\n"
            ."Receipt & timeline: {$trackingUrl}";

        $shipperPhone = self::resolveBookingPhone($booking);
        if ($shipperPhone !== null) {
            self::dispatchMessage($shipperPhone, $message, 'booking_delivered_shipper');
        }

        if ($recipientPhone !== null && WaGatewayClient::normalizePhone($recipientPhone) !== WaGatewayClient::normalizePhone((string) $shipperPhone)) {
            self::dispatchMessage($recipientPhone, $message, 'booking_delivered_recipient');
        }
    }

    public static function notifyIncidentUpdate(OpsIncidentEscalation $incident, string $decisionLabel): void
    {
        $incident->loadMissing(['cargoBooking', 'reporter.user']);
        $operatorPhone = $incident->reporter?->user?->phone;
        if (! is_string($operatorPhone) || trim($operatorPhone) === '') {
            return;
        }

        $trackingNo = $incident->cargoBooking?->tracking_number ?? '-';
        $message = "*[FastCargo Incident Governance]*\n"
            ."Ticket: *{$incident->incident_ticket_no}*\n"
            ."Waybill: {$trackingNo}\n"
            ."Decision: *{$decisionLabel}*";

        self::dispatchMessage($operatorPhone, $message, 'incident_review');
    }

    public static function notifyOperatorApplicationApproved(AdmOperatorInvitation $invitation, string $registrationUrl): void
    {
        $message = "*[FastCargo Operator Onboarding]*\n"
            ."Hello {$invitation->name}, your FastCargo Terminal Operator application for *{$invitation->hub_name}* has been *APPROVED*.\n"
            ."Complete 2FA onboarding here:\n{$registrationUrl}";

        self::dispatchMessage($invitation->phone, $message, 'operator_approved');
    }

    private static function resolveBookingPhone(OpsCargoBooking $booking): ?string
    {
        $phone = $booking->shipper?->phone ?: $booking->company?->pic_phone;

        return is_string($phone) && trim($phone) !== '' ? trim($phone) : null;
    }

    private static function dispatchMessage(?string $phone, string $message, string $tag): void
    {
        if (! is_string($phone) || trim($phone) === '') {
            return;
        }

        $normalized = WaGatewayClient::normalizePhone($phone);
        if (strlen($normalized) < 8) {
            return;
        }

        SendWhatsAppNotification::dispatch($normalized, $message, $tag)->afterCommit();
    }
}
