<?php

namespace App\Modules\Shared\Services;

use App\Modules\Shared\Models\OpsCargoBooking;
use App\Modules\Shared\Models\OpsManifest;
use App\Modules\Shared\Models\OpsOperatorProfile;
use App\Modules\Shared\Models\Port;
use App\Modules\Shared\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds role-scoped spatial marker & corridor payloads for Admin, Operator, and Customer cockpits.
 */
class CockpitMapService
{
    /**
     * Curated coordinate lookup for ASEAN timezones when no user address or port is recorded.
     *
     * @var array<string, array{lat: float, lng: float, label: string}>
     */
    private const TIMEZONE_COORDS = [
        'Asia/Jakarta' => ['lat' => -6.2088, 'lng' => 106.8456, 'label' => 'Jakarta'],
        'Asia/Pontianak' => ['lat' => -0.0263, 'lng' => 109.3425, 'label' => 'Pontianak'],
        'Asia/Makassar' => ['lat' => -5.1477, 'lng' => 119.4327, 'label' => 'Makassar'],
        'Asia/Jayapura' => ['lat' => -2.5916, 'lng' => 140.6690, 'label' => 'Jayapura'],
        'Asia/Singapore' => ['lat' => 1.3521, 'lng' => 103.8198, 'label' => 'Singapore'],
        'Asia/Kuala_Lumpur' => ['lat' => 3.1390, 'lng' => 101.6869, 'label' => 'Kuala Lumpur'],
        'Asia/Kuching' => ['lat' => 1.5533, 'lng' => 110.3592, 'label' => 'Kuching'],
        'Asia/Bangkok' => ['lat' => 13.7563, 'lng' => 100.5018, 'label' => 'Bangkok'],
        'Asia/Ho_Chi_Minh' => ['lat' => 10.8231, 'lng' => 106.6297, 'label' => 'Ho Chi Minh City'],
        'Asia/Hanoi' => ['lat' => 21.0278, 'lng' => 105.8342, 'label' => 'Hanoi'],
        'Asia/Manila' => ['lat' => 14.5995, 'lng' => 120.9842, 'label' => 'Manila'],
        'Asia/Yangon' => ['lat' => 16.8409, 'lng' => 96.1735, 'label' => 'Yangon'],
        'Asia/Phnom_Penh' => ['lat' => 11.5564, 'lng' => 104.9282, 'label' => 'Phnom Penh'],
        'Asia/Vientiane' => ['lat' => 17.9757, 'lng' => 102.6331, 'label' => 'Vientiane'],
        'Asia/Brunei' => ['lat' => 4.9031, 'lng' => 114.9398, 'label' => 'Bandar Seri Begawan'],
    ];

    /**
     * Statuses excluded from the "running cargo" operational layer.
     *
     * @var list<string>
     */
    private const TERMINAL_BOOKING_STATUSES = ['DELIVERED', 'CANCELLED'];

    /**
     * Build map layers for the Admin Executive Governance Cockpit.
     *
     * @return array{
     *   role: string,
     *   default_layer: string,
     *   generated_at: string,
     *   hub_anchor: null,
     *   layers: array<string, array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     unmapped_count: int,
     *     items: list<array<string, mixed>>
     *   }>
     * }
     */
    public function buildAdminPayload(): array
    {
        $activeBookings = OpsCargoBooking::with([
            'shipper:id,name',
            'route.originPort',
            'route.destinationPort',
            'trackingEvents' => function ($query): void {
                $query->latest('event_timestamp')->limit(1);
            },
        ])
            ->whereNotIn('cargo_status', self::TERMINAL_BOOKING_STATUSES)
            ->latest()
            ->limit(60)
            ->get();

        [$cargoItems, $cargoUnmapped] = $this->mapBookingsToMarkers(
            $activeBookings,
            fn (OpsCargoBooking $booking): string => route('tracking.public', ['trackingNumber' => $booking->tracking_number], false),
            'Open Public Tracking'
        );

        $operators = OpsOperatorProfile::with(['user:id,name,email,status', 'hub'])
            ->latest()
            ->get();

        [$opsItems, $opsUnmapped] = $this->mapOperatorsToMarkers($operators);

        $users = User::with([
            'addresses',
            'companies',
            'operatorProfile.hub',
            'bookings' => function ($query): void {
                $query->with('route.originPort')->latest()->limit(1);
            },
        ])
            ->orderBy('name')
            ->limit(150)
            ->get();

        [$userItems, $userUnmapped] = $this->mapUsersToMarkers($users);

        return [
            'role' => 'admin',
            'default_layer' => 'cargo',
            'generated_at' => now()->format('H:i:s'),
            'hub_anchor' => null,
            'layers' => [
                'cargo' => [
                    'key' => 'cargo',
                    'label' => 'Cargo',
                    'description' => 'Active cargo shipments & ASEAN cross-border corridors',
                    'unmapped_count' => $cargoUnmapped,
                    'items' => $cargoItems,
                ],
                'ops' => [
                    'key' => 'ops',
                    'label' => 'Ops',
                    'description' => 'Registered station operators & assigned terminal hubs',
                    'unmapped_count' => $opsUnmapped,
                    'items' => $opsItems,
                ],
                'user' => [
                    'key' => 'user',
                    'label' => 'User',
                    'description' => 'Registered users resolved via address, company, booking port, or timezone',
                    'unmapped_count' => $userUnmapped,
                    'items' => $userItems,
                ],
            ],
        ];
    }

    /**
     * Build map layers for the Operator Dispatch Terminal Cockpit.
     *
     * @return array{
     *   role: string,
     *   default_layer: string,
     *   generated_at: string,
     *   hub_anchor: ?array{id: string, name: string, city: string, lat: float, lng: float},
     *   layers: array<string, array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     unmapped_count: int,
     *     items: list<array<string, mixed>>
     *   }>
     * }
     */
    public function buildOperatorPayload(User $user): array
    {
        $profile = $user->operatorProfile()->with('hub')->first();
        $hubAnchor = null;

        if ($profile?->hub) {
            $hubAnchor = [
                'id' => $profile->hub->id,
                'name' => $profile->hub->name,
                'city' => $profile->hub->city,
                'lat' => (float) $profile->hub->latitude,
                'lng' => (float) $profile->hub->longitude,
            ];
        }

        $manifests = OpsManifest::with([
            'originPort',
            'destinationPort',
            'fleetTelemetries' => function ($query): void {
                $query->latest('recorded_at')->limit(1);
            },
        ])
            ->whereIn('status', [
                OpsManifest::STATUS_PREPARING,
                OpsManifest::STATUS_LOCKED_SEALED,
                OpsManifest::STATUS_SAILING,
                OpsManifest::STATUS_ARRIVED,
            ])
            ->latest()
            ->limit(60)
            ->get();

        [$manifestItems, $manifestUnmapped] = $this->mapManifestsToMarkers($manifests);

        $activeBookings = OpsCargoBooking::with([
            'shipper:id,name',
            'route.originPort',
            'route.destinationPort',
            'trackingEvents' => function ($query): void {
                $query->latest('event_timestamp')->limit(1);
            },
        ])
            ->whereNotIn('cargo_status', self::TERMINAL_BOOKING_STATUSES)
            ->latest()
            ->limit(60)
            ->get();

        [$cargoItems, $cargoUnmapped] = $this->mapBookingsToMarkers(
            $activeBookings,
            fn (OpsCargoBooking $booking): string => route('operator.bookings.show', ['booking' => $booking->id], false),
            'Review Booking'
        );

        return [
            'role' => 'operator',
            'default_layer' => 'manifest',
            'generated_at' => now()->format('H:i:s'),
            'hub_anchor' => $hubAnchor,
            'layers' => [
                'manifest' => [
                    'key' => 'manifest',
                    'label' => 'Manifest',
                    'description' => 'Active station manifests, fleet telemetry, and hub corridors',
                    'unmapped_count' => $manifestUnmapped,
                    'items' => $manifestItems,
                ],
                'cargo' => [
                    'key' => 'cargo',
                    'label' => 'Cargo',
                    'description' => 'Consignments awaiting approval, customs check, or transit',
                    'unmapped_count' => $cargoUnmapped,
                    'items' => $cargoItems,
                ],
            ],
        ];
    }

    /**
     * Build map layers for the Customer Cargo Cockpit.
     *
     * @return array{
     *   role: string,
     *   default_layer: string,
     *   generated_at: string,
     *   hub_anchor: null,
     *   layers: array<string, array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     unmapped_count: int,
     *     items: list<array<string, mixed>>
     *   }>
     * }
     */
    public function buildCustomerPayload(User $user): array
    {
        $allBookings = OpsCargoBooking::where('shipper_id', $user->id)
            ->with([
                'shipper:id,name',
                'route.originPort',
                'route.destinationPort',
                'trackingEvents' => function ($query): void {
                    $query->latest('event_timestamp')->limit(1);
                },
            ])
            ->latest()
            ->limit(50)
            ->get();

        $activeBookings = $allBookings->reject(
            fn (OpsCargoBooking $booking): bool => in_array($booking->cargo_status, self::TERMINAL_BOOKING_STATUSES, true)
        )->values();

        [$activeItems, $activeUnmapped] = $this->mapBookingsToMarkers(
            $activeBookings,
            fn (OpsCargoBooking $booking): string => route('customer.tracking.show', ['booking' => $booking->id], false),
            'Open Telemetry'
        );

        [$allItems, $allUnmapped] = $this->mapBookingsToMarkers(
            $allBookings,
            fn (OpsCargoBooking $booking): string => route('customer.tracking.show', ['booking' => $booking->id], false),
            'Open Telemetry'
        );

        return [
            'role' => 'customer',
            'default_layer' => 'active',
            'generated_at' => now()->format('H:i:s'),
            'hub_anchor' => null,
            'layers' => [
                'active' => [
                    'key' => 'active',
                    'label' => 'Aktif',
                    'description' => 'Active shipments currently in customs, terminal, or transit',
                    'unmapped_count' => $activeUnmapped,
                    'items' => $activeItems,
                ],
                'all' => [
                    'key' => 'all',
                    'label' => 'Semua',
                    'description' => 'All recent customer consignments including delivered orders',
                    'unmapped_count' => $allUnmapped,
                    'items' => $allItems,
                ],
            ],
        ];
    }

    /**
     * Transform cargo bookings into unified map marker + corridor items.
     *
     * @param  Collection<int, OpsCargoBooking>  $bookings
     * @param  callable(OpsCargoBooking): string  $urlResolver
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function mapBookingsToMarkers(Collection $bookings, callable $urlResolver, string $detailLabel): array
    {
        $rawItems = [];
        $unmapped = 0;

        foreach ($bookings as $booking) {
            $originPort = $booking->route?->originPort;
            $destPort = $booking->route?->destinationPort;
            $latestEvent = $booking->trackingEvents->first();

            $lat = $latestEvent ? (float) $latestEvent->latitude : ($originPort ? (float) $originPort->latitude : null);
            $lng = $latestEvent ? (float) $latestEvent->longitude : ($originPort ? (float) $originPort->longitude : null);

            if ($lat === null || $lng === null) {
                $unmapped++;

                continue;
            }

            $originData = $originPort ? [
                'id' => $originPort->id,
                'city' => $originPort->city,
                'lat' => (float) $originPort->latitude,
                'lng' => (float) $originPort->longitude,
            ] : null;

            $destData = $destPort ? [
                'id' => $destPort->id,
                'city' => $destPort->city,
                'lat' => (float) $destPort->latitude,
                'lng' => (float) $destPort->longitude,
            ] : null;

            $corridor = ($originPort?->id ?? 'Origin').' -> '.($destPort?->id ?? 'Dest');
            $shipperName = $booking->shipper?->name ?? 'Shipper';

            $rawItems[] = [
                'id' => $booking->id,
                'kind' => 'cargo',
                'marker_type' => $booking->cargo_status === OpsCargoBooking::STATUS_DELIVERED ? 'delivered' : 'cargo',
                'title' => $booking->tracking_number,
                'subtitle' => $shipperName.' · '.$booking->booking_type,
                'status' => $booking->cargo_status,
                'meta' => $corridor.' · '.($latestEvent?->event_name ?? 'Awaiting telemetry'),
                'location_source' => $latestEvent ? 'Latest Tracking Event' : 'Origin Port',
                'lat' => $lat,
                'lng' => $lng,
                'origin' => $originData,
                'destination' => $destData,
                'detail_url' => $urlResolver($booking),
                'detail_label' => $detailLabel,
            ];
        }

        return [$this->applyOverlapOffsets($rawItems), $unmapped];
    }

    /**
     * Transform voyage manifests into unified map markers with corridor polylines.
     *
     * @param  Collection<int, OpsManifest>  $manifests
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function mapManifestsToMarkers(Collection $manifests): array
    {
        $rawItems = [];
        $unmapped = 0;

        foreach ($manifests as $manifest) {
            $originPort = $manifest->originPort;
            $destPort = $manifest->destinationPort;
            $telemetry = $manifest->fleetTelemetries->first();

            $lat = null;
            $lng = null;
            $source = 'Origin Hub';

            if ($telemetry) {
                $lat = (float) $telemetry->latitude;
                $lng = (float) $telemetry->longitude;
                $source = 'Live Telemetry';
            } elseif ($manifest->status === OpsManifest::STATUS_SAILING && $originPort && $destPort) {
                $lat = ((float) $originPort->latitude + (float) $destPort->latitude) / 2;
                $lng = ((float) $originPort->longitude + (float) $destPort->longitude) / 2;
                $source = 'Corridor Midpoint';
            } elseif ($manifest->status === OpsManifest::STATUS_ARRIVED && $destPort) {
                $lat = (float) $destPort->latitude;
                $lng = (float) $destPort->longitude;
                $source = 'Destination Port';
            } elseif ($originPort) {
                $lat = (float) $originPort->latitude;
                $lng = (float) $originPort->longitude;
            }

            if ($lat === null || $lng === null) {
                $unmapped++;

                continue;
            }

            $originData = $originPort ? [
                'id' => $originPort->id,
                'city' => $originPort->city,
                'lat' => (float) $originPort->latitude,
                'lng' => (float) $originPort->longitude,
            ] : null;

            $destData = $destPort ? [
                'id' => $destPort->id,
                'city' => $destPort->city,
                'lat' => (float) $destPort->latitude,
                'lng' => (float) $destPort->longitude,
            ] : null;

            $corridor = ($originPort?->id ?? '-').' -> '.($destPort?->id ?? '-');

            $etaLabel = $manifest->arrival_eta ? $manifest->arrival_eta->format('d M H:i') : 'TBD';

            $rawItems[] = [
                'id' => $manifest->id,
                'kind' => 'manifest',
                'marker_type' => 'manifest',
                'title' => $manifest->manifest_number,
                'subtitle' => $manifest->vessel_or_vehicle_name.' ('.$manifest->voyage_number.')',
                'status' => $manifest->status,
                'meta' => $corridor.' · ETA '.$etaLabel,
                'location_source' => $source,
                'lat' => $lat,
                'lng' => $lng,
                'origin' => $originData,
                'destination' => $destData,
                'detail_url' => route('operator.manifests.show', ['manifest' => $manifest->id], false),
                'detail_label' => 'Manage Manifest',
            ];
        }

        return [$this->applyOverlapOffsets($rawItems), $unmapped];
    }

    /**
     * Transform registered operator profiles onto their assigned hub coordinates.
     *
     * @param  Collection<int, OpsOperatorProfile>  $operators
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function mapOperatorsToMarkers(Collection $operators): array
    {
        $rawItems = [];
        $unmapped = 0;

        foreach ($operators as $operator) {
            if (! $operator->hub) {
                $unmapped++;

                continue;
            }

            $rawItems[] = [
                'id' => $operator->id,
                'kind' => 'ops',
                'marker_type' => $operator->is_on_duty ? 'ops_active' : 'ops_off',
                'title' => $operator->user?->name ?? $operator->employee_id,
                'subtitle' => $operator->employee_id.' · '.$operator->hub->name,
                'status' => $operator->is_on_duty ? 'ON_DUTY' : 'OFF_DUTY',
                'meta' => $operator->role_tier.' · KPI '.number_format((float) $operator->kpi_rating, 2),
                'location_source' => 'Assigned Hub ('.$operator->hub->id.')',
                'lat' => (float) $operator->hub->latitude,
                'lng' => (float) $operator->hub->longitude,
                'origin' => null,
                'destination' => null,
                'detail_url' => route('admin.users.show', ['user' => $operator->user_id], false),
                'detail_label' => 'View User Profile',
            ];
        }

        return [$this->applyOverlapOffsets($rawItems), $unmapped];
    }

    /**
     * Transform registered users onto resolved coordinates using real DB fields or timezone fallback.
     *
     * @param  Collection<int, User>  $users
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function mapUsersToMarkers(Collection $users): array
    {
        $ports = Port::all(['id', 'name', 'city', 'country_code', 'latitude', 'longitude']);
        $rawItems = [];
        $unmapped = 0;

        foreach ($users as $user) {
            $resolved = $this->resolveUserCoordinates($user, $ports);

            if ($resolved === null) {
                $unmapped++;

                continue;
            }

            $markerType = match (true) {
                $user->isAdmin() => 'user_admin',
                $user->isOperator() => 'user_operator',
                default => 'user_customer',
            };

            $rawItems[] = [
                'id' => $user->id,
                'kind' => 'user',
                'marker_type' => $markerType,
                'title' => $user->name,
                'subtitle' => $user->user_type.' · KYC '.$user->kyc_aml_status,
                'status' => $user->status,
                'meta' => $resolved['label'].' ('.$resolved['source'].')',
                'location_source' => $resolved['source'],
                'lat' => $resolved['lat'],
                'lng' => $resolved['lng'],
                'origin' => null,
                'destination' => null,
                'detail_url' => route('admin.users.show', ['user' => $user->id], false),
                'detail_label' => 'View User Account',
            ];
        }

        return [$this->applyOverlapOffsets($rawItems), $unmapped];
    }

    /**
     * Resolve a single user's best verifiable coordinates without inventing random positions.
     *
     * @param  Collection<int, Port>  $ports
     * @return ?array{lat: float, lng: float, label: string, source: string}
     */
    private function resolveUserCoordinates(User $user, Collection $ports): ?array
    {
        $address = $user->addresses->first();
        if ($address) {
            $portMatch = $this->matchPortByText(trim(($address->city ?? '').' '.($address->region ?? '')), $address->country_code, $ports);
            if ($portMatch !== null) {
                return [
                    'lat' => (float) $portMatch->latitude,
                    'lng' => (float) $portMatch->longitude,
                    'label' => $portMatch->city.', '.$portMatch->country_code,
                    'source' => 'Address',
                ];
            }
        }

        $company = $user->companies->first();
        if ($company && $company->company_address) {
            $portMatch = $this->matchPortByText($company->company_address, null, $ports);
            if ($portMatch !== null) {
                return [
                    'lat' => (float) $portMatch->latitude,
                    'lng' => (float) $portMatch->longitude,
                    'label' => $portMatch->city.', '.$portMatch->country_code,
                    'source' => 'Company Address',
                ];
            }
        }

        $operatorHub = $user->operatorProfile?->hub;
        if ($operatorHub) {
            return [
                'lat' => (float) $operatorHub->latitude,
                'lng' => (float) $operatorHub->longitude,
                'label' => $operatorHub->city.' ('.$operatorHub->id.')',
                'source' => 'Assigned Hub',
            ];
        }

        $bookingOrigin = $user->bookings->first()?->route?->originPort;
        if ($bookingOrigin) {
            return [
                'lat' => (float) $bookingOrigin->latitude,
                'lng' => (float) $bookingOrigin->longitude,
                'label' => $bookingOrigin->city.' ('.$bookingOrigin->id.')',
                'source' => 'Latest Booking Port',
            ];
        }

        if ($user->timezone && isset(self::TIMEZONE_COORDS[$user->timezone])) {
            $tzCoord = self::TIMEZONE_COORDS[$user->timezone];

            return [
                'lat' => $tzCoord['lat'],
                'lng' => $tzCoord['lng'],
                'label' => $tzCoord['label'],
                'source' => 'Timezone ('.$user->timezone.')',
            ];
        }

        return null;
    }

    /**
     * Match city/country text against seeded ASEAN ports without hardcoded duplicates.
     *
     * @param  Collection<int, Port>  $ports
     */
    private function matchPortByText(string $text, ?string $countryCode, Collection $ports): ?Port
    {
        $needle = mb_strtolower($text);

        if ($needle !== '') {
            // Sort by city name length descending so multi-word cities match before shorter tokens.
            $sortedPorts = $ports->sortByDesc(fn (Port $port): int => mb_strlen($port->city));

            foreach ($sortedPorts as $port) {
                if (str_contains($needle, mb_strtolower($port->city))) {
                    return $port;
                }
            }
        }

        if ($countryCode !== null && $countryCode !== '') {
            $normalizedCountry = strtoupper($countryCode);

            return $ports->first(fn (Port $port): bool => strtoupper($port->country_code) === $normalizedCountry);
        }

        return null;
    }

    /**
     * Spread markers sharing exact coordinates cleanly around a small radius so each pin stays clickable.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function applyOverlapOffsets(array $items): array
    {
        $buckets = [];
        foreach ($items as $index => $item) {
            $key = sprintf('%.4f,%.4f', $item['lat'], $item['lng']);
            $buckets[$key][] = $index;
        }

        foreach ($buckets as $indices) {
            $count = count($indices);
            if ($count <= 1) {
                continue;
            }

            $radius = 0.045;
            foreach ($indices as $position => $itemIndex) {
                $angle = (2 * M_PI * $position) / $count;
                $items[$itemIndex]['lat'] = round($items[$itemIndex]['lat'] + ($radius * sin($angle)), 6);
                $items[$itemIndex]['lng'] = round($items[$itemIndex]['lng'] + ($radius * cos($angle)), 6);
            }
        }

        return $items;
    }
}
