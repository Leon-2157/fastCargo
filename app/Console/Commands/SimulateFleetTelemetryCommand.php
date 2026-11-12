<?php

namespace App\Console\Commands;

use App\Events\FleetLocationUpdated;
use App\Modules\Shared\Models\OpsFleetTelemetry;
use App\Modules\Shared\Models\OpsManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SimulateFleetTelemetryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telemetry:simulate 
                            {--manifest= : Specific manifest UUID or code}
                            {--steps=5 : Number of telemetry steps to stream}
                            {--delay=1 : Delay in seconds between steps}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Simulate live IoT AIS GPS telemetry streaming for ASEAN maritime fleet via Soketi WebSockets';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $manifest = null;
        if ($manifestId = $this->option('manifest')) {
            $manifest = OpsManifest::where('id', $manifestId)
                ->orWhere('manifest_code', $manifestId)
                ->first();
        }

        if (! $manifest) {
            $manifest = OpsManifest::latest()->first();
        }

        $manifestId = $manifest?->id;
        $carrierName = $manifest?->carrier_name ?? 'MV FastCargo Horizon';

        // Koordinat lintasan maritim Selat Malaka - Selat Sunda - Tanjung Priok
        $routeWaypoints = [
            ['lat' => 1.2654, 'lng' => 103.8198, 'heading' => 145.0, 'speed' => 14.5, 'name' => 'Departing Port of Singapore (SGSIN)'],
            ['lat' => 1.1520, 'lng' => 103.9500, 'heading' => 135.0, 'speed' => 16.2, 'name' => 'Navigating Singapore Strait Traffic Separation'],
            ['lat' => 0.5830, 'lng' => 104.5500, 'heading' => 155.0, 'speed' => 18.0, 'name' => 'Cruising Riau Archipelago Maritime Corridor'],
            ['lat' => -1.2500, 'lng' => 105.8000, 'heading' => 165.0, 'speed' => 17.8, 'name' => 'Bangka Strait Outer Anchorage Approach'],
            ['lat' => -3.1000, 'lng' => 106.9000, 'heading' => 175.0, 'speed' => 18.5, 'name' => 'Open Java Sea Corridor Transiting Southbound'],
            ['lat' => -5.8500, 'lng' => 106.8500, 'heading' => 180.0, 'speed' => 12.0, 'name' => 'Entering Jakarta Bay Vessel Traffic Service'],
            ['lat' => -6.1025, 'lng' => 106.8825, 'heading' => 190.0, 'speed' => 6.5, 'name' => 'Approaching Tanjung Priok Container Terminal (IDTPP)'],
        ];

        $steps = min((int) $this->option('steps'), count($routeWaypoints));
        $delay = max(0, (int) $this->option('delay'));

        $this->info("Starting live IoT telemetry simulation for {$carrierName} (Steps: {$steps})...");

        for ($i = 0; $i < $steps; $i++) {
            $wp = $routeWaypoints[$i];

            $telemetry = OpsFleetTelemetry::create([
                'manifest_id' => $manifestId,
                'device_id' => 'IOT-AIS-'.substr(md5($carrierName), 0, 8),
                'latitude' => $wp['lat'],
                'longitude' => $wp['lng'],
                'speed_knots' => $wp['speed'],
                'heading_degrees' => $wp['heading'],
                'recorded_at' => now(),
            ]);

            try {
                event(new FleetLocationUpdated($telemetry, [
                    'carrier_name' => $carrierName,
                    'corridor' => $wp['name'],
                    'status' => 'IN_TRANSIT',
                ]));
            } catch (\Throwable $e) {
                Log::debug('Telemetry broadcast skipped', ['error' => $e->getMessage()]);
            }

            $this->line(sprintf(
                '[%s] Step %d/%d: %s | Lat: %.4f, Lng: %.4f | Speed: %.1f kts | Heading: %.1f deg',
                now()->format('H:i:s'),
                $i + 1,
                $steps,
                $wp['name'],
                $wp['lat'],
                $wp['lng'],
                $wp['speed'],
                $wp['heading']
            ));

            if ($delay > 0 && $i < $steps - 1) {
                sleep($delay);
            }
        }

        $this->info("Live IoT telemetry broadcast successfully sent to WebSocket channel 'cargo-fleet-telemetry'!");

        return self::SUCCESS;
    }
}
