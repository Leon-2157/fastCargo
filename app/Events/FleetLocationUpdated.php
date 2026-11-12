<?php

namespace App\Events;

use App\Modules\Shared\Models\OpsFleetTelemetry;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FleetLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public OpsFleetTelemetry $telemetry,
        public array $metadata = []
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('cargo-fleet-telemetry'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'fleet.location.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->telemetry->id,
            'manifest_id' => $this->telemetry->manifest_id,
            'device_id' => $this->telemetry->device_id,
            'latitude' => (float) $this->telemetry->latitude,
            'longitude' => (float) $this->telemetry->longitude,
            'speed_knots' => (float) $this->telemetry->speed_knots,
            'heading_degrees' => (float) $this->telemetry->heading_degrees,
            'recorded_at' => $this->telemetry->recorded_at?->toIso8601String() ?? now()->toIso8601String(),
            'carrier_name' => $this->metadata['carrier_name'] ?? 'MV FastCargo Horizon',
            'corridor' => $this->metadata['corridor'] ?? 'SGSIN -> IDTPP (Singapore Strait)',
            'status' => $this->metadata['status'] ?? 'IN_TRANSIT',
        ];
    }
}
