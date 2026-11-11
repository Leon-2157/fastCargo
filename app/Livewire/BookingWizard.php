<?php

namespace App\Livewire;

use App\Modules\Shared\Models\AdmInvoice;
use App\Modules\Shared\Models\Company;
use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\OpsCargoItem;
use App\Modules\Shared\Models\OpsTrackingEvent;
use App\Modules\Shared\Models\Port;
use App\Modules\Shared\Models\Route;
use App\Modules\Shared\WhatsApp\WaNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

class BookingWizard extends Component
{
    public int $currentStep = 1;

    // Step 1: Route & Mode
    public ?string $originPortId = 'IDJKT';

    public ?string $destinationPortId = 'SGSIN';

    public string $transportMode = 'SEA';

    public ?string $selectedRouteId = null;

    public ?float $baseRate = 0.0;

    public int $transitDays = 2;

    // Step 2: Consignment Profile
    public string $bookingType = 'RETAIL_LCL';

    public string $cargoType = 'GENERAL_CARGO';

    public ?string $hsCode = '8471.30';

    public float $declaredValue = 15000000.0;

    public string $currency = 'IDR';

    // Step 3: Packing List Items
    public array $items = [];

    // Summary calculations
    public float $totalWeight = 0.0;

    public float $totalVolume = 0.0;

    public float $estimatedFreight = 0.0;

    public float $estimatedInsurance = 0.0;

    public float $handlingFee = 350000.0;

    public float $grandTotal = 0.0;

    public function mount()
    {
        $this->items = [
            [
                'item_name' => 'Electronics & Component Spares',
                'package_type' => 'CARTON',
                'quantity' => 2,
                'weight_kg' => 45.0,
                'length_cm' => 50.0,
                'width_cm' => 40.0,
                'height_cm' => 30.0,
                'volume_cbm' => 0.12,
                'is_dangerous_good' => false,
            ],
        ];

        $this->updateRouteInfo();
        $this->recalculateSummary();
    }

    public function updatedOriginPortId()
    {
        $this->updateRouteInfo();
        $this->recalculateSummary();
    }

    public function updatedDestinationPortId()
    {
        $this->updateRouteInfo();
        $this->recalculateSummary();
    }

    public function updatedTransportMode()
    {
        $this->updateRouteInfo();
        $this->recalculateSummary();
    }

    public function updatedDeclaredValue()
    {
        $this->recalculateSummary();
    }

    public function updateRouteInfo()
    {
        $route = Route::where('origin_port_id', $this->originPortId)
            ->where('destination_port_id', $this->destinationPortId)
            ->where('transport_mode', $this->transportMode)
            ->where('is_active', true)
            ->first();

        if ($route) {
            $this->selectedRouteId = $route->id;
            $this->baseRate = (float) $route->base_rate_idr;
            $this->transitDays = (int) $route->transit_days;
        } else {
            // Check reverse or fallback estimation
            $fallback = Route::where('origin_port_id', $this->originPortId)
                ->where('destination_port_id', $this->destinationPortId)
                ->first();

            if ($fallback) {
                $this->selectedRouteId = $fallback->id;
                $this->baseRate = (float) $fallback->base_rate_idr;
                $this->transitDays = (int) $fallback->transit_days;
            } else {
                $this->selectedRouteId = null;
                $this->baseRate = 3500000.0;
                $this->transitDays = 3;
            }
        }
    }

    public function addItem()
    {
        $this->items[] = [
            'item_name' => '',
            'package_type' => 'CARTON',
            'quantity' => 1,
            'weight_kg' => 10.0,
            'length_cm' => 30.0,
            'width_cm' => 20.0,
            'height_cm' => 20.0,
            'volume_cbm' => 0.012,
            'is_dangerous_good' => false,
        ];

        $this->recalculateSummary();
    }

    public function removeItem(int $index)
    {
        if (count($this->items) > 1) {
            unset($this->items[$index]);
            $this->items = array_values($this->items);
            $this->recalculateSummary();
        }
    }

    public function recalculateSummary()
    {
        $totalWeight = 0.0;
        $totalVolume = 0.0;
        $hasDg = false;

        foreach ($this->items as $i => $item) {
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $l = (float) ($item['length_cm'] ?? 0);
            $w = (float) ($item['width_cm'] ?? 0);
            $h = (float) ($item['height_cm'] ?? 0);
            $wt = (float) ($item['weight_kg'] ?? 0);

            $cbm = ($l * $w * $h * $qty) / 1000000.0;
            $this->items[$i]['volume_cbm'] = round($cbm, 4);

            $totalWeight += ($wt * $qty);
            $totalVolume += $cbm;

            if (! empty($item['is_dangerous_good'])) {
                $hasDg = true;
            }
        }

        $this->totalWeight = round($totalWeight, 2);
        $this->totalVolume = round($totalVolume, 4);

        if ($hasDg) {
            $this->cargoType = 'DANGEROUS_GOODS';
        }

        // Calculation logic
        $base = $this->baseRate ?: 2500000.0;
        $weightCost = max($this->totalWeight * 2000.0, $this->totalVolume * 350000.0);
        $dgMultiplier = $hasDg ? 1.35 : 1.0;

        $this->estimatedFreight = round(($base + $weightCost) * $dgMultiplier, 2);
        $this->estimatedInsurance = round(max(25000.0, ($this->declaredValue ?: 0) * 0.002), 2);
        $this->grandTotal = round($this->estimatedFreight + $this->estimatedInsurance + $this->handlingFee, 2);
    }

    public function nextStep()
    {
        $this->validateCurrentStep();
        $this->recalculateSummary();
        $this->currentStep = min(4, $this->currentStep + 1);
    }

    public function prevStep()
    {
        $this->currentStep = max(1, $this->currentStep - 1);
    }

    public function validateCurrentStep()
    {
        if ($this->currentStep === 1) {
            $this->validate([
                'originPortId' => 'required|string|exists:ports,id',
                'destinationPortId' => 'required|string|different:originPortId|exists:ports,id',
                'transportMode' => 'required|in:SEA,AIR,LAND',
            ]);
        } elseif ($this->currentStep === 2) {
            $this->validate([
                'bookingType' => 'required|in:RETAIL_LCL,CORPORATE_FCL,CHARTER_TRUCK',
                'cargoType' => 'required|string',
                'declaredValue' => 'required|numeric|min:100000',
            ]);
        } elseif ($this->currentStep === 3) {
            $this->validate([
                'items' => 'required|array|min:1',
                'items.*.item_name' => 'required|string|max:255',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.weight_kg' => 'required|numeric|min:0.1',
            ]);
        }
    }

    public function submitBooking()
    {
        $this->validateCurrentStep();
        $this->recalculateSummary();

        $user = Auth::guard('customer')->user();

        // Ensure user has a company
        $company = Company::where('user_id', $user->id)->first();
        if (! $company) {
            $company = Company::create([
                'id' => Str::uuid()->toString(),
                'user_id' => $user->id,
                'company_name' => $user->name.' (Personal Freight)',
                'company_address' => 'Jakarta, Indonesia',
                'pic_name' => $user->name,
                'pic_phone' => $user->phone ?? '+62811000000',
                'email' => $user->email,
                'industry' => 'Retail & General Commerce',
            ]);
        }

        // Find or create Route
        $route = Route::where('origin_port_id', $this->originPortId)
            ->where('destination_port_id', $this->destinationPortId)
            ->where('transport_mode', $this->transportMode)
            ->first();

        if (! $route) {
            $route = Route::create([
                'id' => Str::uuid()->toString(),
                'origin_port_id' => $this->originPortId,
                'destination_port_id' => $this->destinationPortId,
                'transport_mode' => $this->transportMode,
                'transit_days' => $this->transitDays ?: 2,
                'base_rate_idr' => $this->baseRate ?: 3000000.0,
                'is_active' => true,
            ]);
        }

        $trackingNumber = 'RPN-'.date('Ymd').'-'.strtoupper(Str::random(4));
        $initialStatus = ($this->bookingType === 'CORPORATE_FCL') ? 'DRAFT' : 'BOOKED';
        $createdBooking = null;
        $createdInvoice = null;

        DB::transaction(function () use ($trackingNumber, $user, $company, $route, $initialStatus, &$createdBooking, &$createdInvoice) {
            // 1. Create Booking (HasUuids generates the primary UUID)
            $createdBooking = OpsCargoBooking::create([
                'tracking_number' => $trackingNumber,
                'shipper_id' => $user->id,
                'company_id' => $company->id,
                'route_id' => $route->id,
                'hs_code' => $this->hsCode ?: '8471.30',
                'booking_type' => $this->bookingType,
                'cargo_type' => $this->cargoType,
                'total_weight_kg' => $this->totalWeight,
                'total_volume_cbm' => $this->totalVolume,
                'declared_goods_value' => $this->declaredValue,
                'currency' => $this->currency,
                'cargo_status' => $initialStatus,
                'customs_channel' => 'GREEN_CHANNEL',
            ]);

            // 2. Create Items
            foreach ($this->items as $item) {
                OpsCargoItem::create([
                    'cargo_booking_id' => $createdBooking->id,
                    'item_name' => $item['item_name'],
                    'hs_code' => $this->hsCode,
                    'package_type' => $item['package_type'] ?? 'CARTON',
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'weight_kg' => (float) ($item['weight_kg'] ?? 1),
                    'length_cm' => (float) ($item['length_cm'] ?? 0),
                    'width_cm' => (float) ($item['width_cm'] ?? 0),
                    'height_cm' => (float) ($item['height_cm'] ?? 0),
                    'volume_cbm' => (float) ($item['volume_cbm'] ?? 0),
                    'is_dangerous_good' => ! empty($item['is_dangerous_good']),
                ]);
            }

            // 3. Create Initial Tracking Event
            $originPort = Port::find($this->originPortId);
            OpsTrackingEvent::create([
                'cargo_booking_id' => $createdBooking->id,
                'event_name' => 'Consignment Created & Document Intake Verified',
                'current_status' => $initialStatus,
                'latitude' => $originPort->latitude ?? -6.1050,
                'longitude' => $originPort->longitude ?? 106.8847,
                'event_timestamp' => now(),
            ]);

            // 4. Generate Invoice
            $invoiceNumber = 'INV-'.date('Ymd').'-'.strtoupper(Str::random(4));
            $createdInvoice = AdmInvoice::create([
                'id' => Str::uuid()->toString(),
                'invoice_number' => $invoiceNumber,
                'company_id' => $company->id,
                'cargo_booking_id' => $createdBooking->id,
                'total_invoice_amount' => $this->grandTotal,
                'currency' => 'IDR',
                'exchange_rate_locked' => 1.0000,
                'due_date' => now()->addDays(7)->toDateString(),
                'payment_status' => 'UNPAID',
            ]);
        });

        if ($createdBooking !== null) {
            WaNotificationService::notifyBookingCreated($createdBooking, $createdInvoice);
        }

        session()->flash('success', "Consignment created successfully! Tracking Reference: {$trackingNumber}");

        return redirect()->route('customer.bookings.show', $createdBooking->id);
    }

    public function render()
    {
        $ports = Port::orderBy('name')->get();
        $hsCodes = DB::table('hs_codes')->get();

        return view('livewire.booking-wizard', [
            'ports' => $ports,
            'hsCodes' => $hsCodes,
        ]);
    }
}
