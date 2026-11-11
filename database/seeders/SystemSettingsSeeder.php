<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'ai_provider',
                'value' => '9router',
                'type' => 'string',
                'description' => 'Active AI provider: 9router or gemini',
            ],
            [
                'key' => 'operator_vpn_whitelist',
                'value' => '["10.0.0.0/8", "172.16.0.0/12", "192.168.0.0/16"]',
                'type' => 'json',
                'description' => 'Allowed IP CIDR ranges for operator VPN access',
            ],
            [
                'key' => 'wag_connected',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'WhatsApp gateway connection status',
            ],
            [
                'key' => 'platform_fee_percent',
                'value' => '12',
                'type' => 'integer',
                'description' => 'Platform fee percentage from cargo booking value',
            ],
            [
                'key' => 'escrow_milestone_1_percent',
                'value' => '30',
                'type' => 'integer',
                'description' => 'Escrow release percentage at GATE_IN milestone',
            ],
            [
                'key' => 'escrow_milestone_2_percent',
                'value' => '40',
                'type' => 'integer',
                'description' => 'Escrow release percentage at SAILING milestone',
            ],
            [
                'key' => 'escrow_milestone_3_percent',
                'value' => '30',
                'type' => 'integer',
                'description' => 'Escrow release percentage at DELIVERED milestone',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('adm_system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, ['updated_at' => now(), 'created_at' => now()])
            );
        }
    }
}
