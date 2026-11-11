<?php

namespace Database\Seeders;

use App\Modules\Shared\Models\Port;
use Illuminate\Database\Seeder;

class PortSeeder extends Seeder
{
    public function run(): void
    {
        // ~50 port ASEAN: 15 seaport + 15 airport + 10 land hub + 10 warehouse hub
        $ports = [
            // ----------------------------------------------------------------
            // SEAPORTS (15)
            // ----------------------------------------------------------------
            ['id' => 'IDJKT', 'name' => 'Tanjung Priok Port', 'city' => 'Jakarta', 'country_code' => 'ID', 'port_type' => 'SEAPORT', 'latitude' => -6.1050, 'longitude' => 106.8847],
            ['id' => 'IDSUB', 'name' => 'Tanjung Perak Port', 'city' => 'Surabaya', 'country_code' => 'ID', 'port_type' => 'SEAPORT', 'latitude' => -7.1963, 'longitude' => 112.7340],
            ['id' => 'IDBTM', 'name' => 'Batu Ampar Port', 'city' => 'Batam', 'country_code' => 'ID', 'port_type' => 'SEAPORT', 'latitude' => 1.1206, 'longitude' => 104.0147],
            ['id' => 'IDMAK', 'name' => 'Makassar New Port', 'city' => 'Makassar', 'country_code' => 'ID', 'port_type' => 'SEAPORT', 'latitude' => -5.1130, 'longitude' => 119.4094],
            ['id' => 'IDMED', 'name' => 'Belawan Port', 'city' => 'Medan', 'country_code' => 'ID', 'port_type' => 'SEAPORT', 'latitude' => 3.7940, 'longitude' => 98.6872],
            ['id' => 'SGSIN', 'name' => 'Port of Singapore (PSA)', 'city' => 'Singapore', 'country_code' => 'SG', 'port_type' => 'SEAPORT', 'latitude' => 1.2654, 'longitude' => 103.8198],
            ['id' => 'MYPKG', 'name' => 'Port Klang', 'city' => 'Klang', 'country_code' => 'MY', 'port_type' => 'SEAPORT', 'latitude' => 3.0019, 'longitude' => 101.4053],
            ['id' => 'MYPGU', 'name' => 'Penang Port', 'city' => 'Penang', 'country_code' => 'MY', 'port_type' => 'SEAPORT', 'latitude' => 5.4164, 'longitude' => 100.3511],
            ['id' => 'THBKK', 'name' => 'Laem Chabang Port', 'city' => 'Chonburi', 'country_code' => 'TH', 'port_type' => 'SEAPORT', 'latitude' => 13.0804, 'longitude' => 100.8812],
            ['id' => 'VNSGN', 'name' => 'Cat Lai Port', 'city' => 'Ho Chi Minh City', 'country_code' => 'VN', 'port_type' => 'SEAPORT', 'latitude' => 10.7764, 'longitude' => 106.7513],
            ['id' => 'VNHAN', 'name' => 'Hai Phong Port', 'city' => 'Hai Phong', 'country_code' => 'VN', 'port_type' => 'SEAPORT', 'latitude' => 20.8449, 'longitude' => 106.6881],
            ['id' => 'PHMNL', 'name' => 'Manila International Container Terminal', 'city' => 'Manila', 'country_code' => 'PH', 'port_type' => 'SEAPORT', 'latitude' => 14.5783, 'longitude' => 120.9622],
            ['id' => 'MMRGN', 'name' => 'Thilawa Port', 'city' => 'Yangon', 'country_code' => 'MM', 'port_type' => 'SEAPORT', 'latitude' => 16.7244, 'longitude' => 96.2617],
            ['id' => 'KHPNH', 'name' => 'Phnom Penh Port', 'city' => 'Phnom Penh', 'country_code' => 'KH', 'port_type' => 'SEAPORT', 'latitude' => 11.5564, 'longitude' => 104.9282],
            ['id' => 'BNDWN', 'name' => 'Muara Port', 'city' => 'Brunei Muara', 'country_code' => 'BN', 'port_type' => 'SEAPORT', 'latitude' => 5.0170, 'longitude' => 115.0630],

            // ----------------------------------------------------------------
            // AIRPORTS (15)
            // ----------------------------------------------------------------
            ['id' => 'APCGK', 'name' => 'Soekarno-Hatta International Airport', 'city' => 'Tangerang', 'country_code' => 'ID', 'port_type' => 'AIRPORT', 'latitude' => -6.1256, 'longitude' => 106.6559],
            ['id' => 'APJOG', 'name' => 'Yogyakarta International Airport', 'city' => 'Yogyakarta', 'country_code' => 'ID', 'port_type' => 'AIRPORT', 'latitude' => -7.9008, 'longitude' => 110.0569],
            ['id' => 'APKNO', 'name' => 'Ngurah Rai International Airport', 'city' => 'Denpasar', 'country_code' => 'ID', 'port_type' => 'AIRPORT', 'latitude' => -8.7482, 'longitude' => 115.1672],
            ['id' => 'APKDI', 'name' => 'Juanda International Airport', 'city' => 'Surabaya', 'country_code' => 'ID', 'port_type' => 'AIRPORT', 'latitude' => -7.3798, 'longitude' => 112.7872],
            ['id' => 'APKUL', 'name' => 'Kuala Lumpur International Airport', 'city' => 'Sepang', 'country_code' => 'MY', 'port_type' => 'AIRPORT', 'latitude' => 2.7456, 'longitude' => 101.7072],
            ['id' => 'APSIN', 'name' => 'Singapore Changi Airport', 'city' => 'Singapore', 'country_code' => 'SG', 'port_type' => 'AIRPORT', 'latitude' => 1.3644, 'longitude' => 103.9915],
            ['id' => 'APBKK', 'name' => 'Suvarnabhumi Airport', 'city' => 'Bangkok', 'country_code' => 'TH', 'port_type' => 'AIRPORT', 'latitude' => 13.6900, 'longitude' => 100.7501],
            ['id' => 'APSGN', 'name' => 'Tan Son Nhat International Airport', 'city' => 'Ho Chi Minh City', 'country_code' => 'VN', 'port_type' => 'AIRPORT', 'latitude' => 10.8188, 'longitude' => 106.6520],
            ['id' => 'APHAN', 'name' => 'Noi Bai International Airport', 'city' => 'Hanoi', 'country_code' => 'VN', 'port_type' => 'AIRPORT', 'latitude' => 21.2212, 'longitude' => 105.8072],
            ['id' => 'APMNL', 'name' => 'Ninoy Aquino International Airport', 'city' => 'Manila', 'country_code' => 'PH', 'port_type' => 'AIRPORT', 'latitude' => 14.5086, 'longitude' => 121.0197],
            ['id' => 'APRGN', 'name' => 'Yangon International Airport', 'city' => 'Yangon', 'country_code' => 'MM', 'port_type' => 'AIRPORT', 'latitude' => 16.9073, 'longitude' => 96.1332],
            ['id' => 'APPNH', 'name' => 'Phnom Penh International Airport', 'city' => 'Phnom Penh', 'country_code' => 'KH', 'port_type' => 'AIRPORT', 'latitude' => 11.5466, 'longitude' => 104.8440],
            ['id' => 'APVTE', 'name' => 'Wattay International Airport', 'city' => 'Vientiane', 'country_code' => 'LA', 'port_type' => 'AIRPORT', 'latitude' => 17.9883, 'longitude' => 102.5633],
            ['id' => 'APBWN', 'name' => 'Brunei International Airport', 'city' => 'Bandar Seri Begawan', 'country_code' => 'BN', 'port_type' => 'AIRPORT', 'latitude' => 4.9442, 'longitude' => 114.9284],
            ['id' => 'APMED', 'name' => 'Kualanamu International Airport', 'city' => 'Medan', 'country_code' => 'ID', 'port_type' => 'AIRPORT', 'latitude' => 3.6422, 'longitude' => 98.8853],

            // ----------------------------------------------------------------
            // LAND HUBS / BORDER CROSSINGS (10)
            // ----------------------------------------------------------------
            ['id' => 'LHBMP', 'name' => 'Batam Industrial Estate Hub', 'city' => 'Batam', 'country_code' => 'ID', 'port_type' => 'LAND_HUB', 'latitude' => 1.0938, 'longitude' => 104.0192],
            ['id' => 'LHJHB', 'name' => 'Johor Bahru Customs Complex', 'city' => 'Johor Bahru', 'country_code' => 'MY', 'port_type' => 'LAND_HUB', 'latitude' => 1.4927, 'longitude' => 103.7414],
            ['id' => 'LHSAD', 'name' => 'Sadao Border Checkpoint', 'city' => 'Sadao', 'country_code' => 'TH', 'port_type' => 'LAND_HUB', 'latitude' => 6.6361, 'longitude' => 100.4280],
            ['id' => 'LHMAL', 'name' => 'Malacca Inland Port', 'city' => 'Malacca', 'country_code' => 'MY', 'port_type' => 'LAND_HUB', 'latitude' => 2.1897, 'longitude' => 102.2501],
            ['id' => 'LHMWS', 'name' => 'Myawaddy Border Trade Zone', 'city' => 'Myawaddy', 'country_code' => 'MM', 'port_type' => 'LAND_HUB', 'latitude' => 16.6835, 'longitude' => 98.4967],
            ['id' => 'LHPOI', 'name' => 'Poipet Border Special Economic Zone', 'city' => 'Poipet', 'country_code' => 'KH', 'port_type' => 'LAND_HUB', 'latitude' => 13.6566, 'longitude' => 102.5571],
            ['id' => 'LHMOS', 'name' => 'Moc Bai Border Gate', 'city' => 'Moc Bai', 'country_code' => 'VN', 'port_type' => 'LAND_HUB', 'latitude' => 11.1778, 'longitude' => 106.0028],
            ['id' => 'LHSUB', 'name' => 'Surabaya Inland Container Depot', 'city' => 'Surabaya', 'country_code' => 'ID', 'port_type' => 'LAND_HUB', 'latitude' => -7.2575, 'longitude' => 112.7521],
            ['id' => 'LHBGK', 'name' => 'Bangkok Inland Container Depot', 'city' => 'Bangkok', 'country_code' => 'TH', 'port_type' => 'LAND_HUB', 'latitude' => 13.7563, 'longitude' => 100.5018],
            ['id' => 'LHKVL', 'name' => 'Vientiane Logistics Center', 'city' => 'Vientiane', 'country_code' => 'LA', 'port_type' => 'LAND_HUB', 'latitude' => 17.9757, 'longitude' => 102.6331],

            // ----------------------------------------------------------------
            // WAREHOUSE HUBS (10)
            // ----------------------------------------------------------------
            ['id' => 'WHCBK', 'name' => 'Cibitung Logistics Center', 'city' => 'Bekasi', 'country_code' => 'ID', 'port_type' => 'LAND_HUB', 'latitude' => -6.3297, 'longitude' => 107.1527],
            ['id' => 'WHSBY', 'name' => 'Rungkut Industrial Warehouse Hub', 'city' => 'Surabaya', 'country_code' => 'ID', 'port_type' => 'LAND_HUB', 'latitude' => -7.3261, 'longitude' => 112.7778],
            ['id' => 'WHBTM', 'name' => 'Batamindo Warehouse Complex', 'city' => 'Batam', 'country_code' => 'ID', 'port_type' => 'LAND_HUB', 'latitude' => 1.0507, 'longitude' => 104.0100],
            ['id' => 'WHSHA', 'name' => 'Shah Alam Logistics Hub', 'city' => 'Shah Alam', 'country_code' => 'MY', 'port_type' => 'LAND_HUB', 'latitude' => 3.0738, 'longitude' => 101.5183],
            ['id' => 'WHSGS', 'name' => 'Singapore Jurong Logistics Park', 'city' => 'Singapore', 'country_code' => 'SG', 'port_type' => 'LAND_HUB', 'latitude' => 1.3391, 'longitude' => 103.7073],
            ['id' => 'WHBKK', 'name' => 'Lat Krabang Industrial Estate', 'city' => 'Bangkok', 'country_code' => 'TH', 'port_type' => 'LAND_HUB', 'latitude' => 13.7417, 'longitude' => 100.7748],
            ['id' => 'WHSGN', 'name' => 'VSIP II Logistics Hub', 'city' => 'Binh Duong', 'country_code' => 'VN', 'port_type' => 'LAND_HUB', 'latitude' => 10.9803, 'longitude' => 106.7222],
            ['id' => 'WHMNL', 'name' => 'Clark Freeport Logistics Hub', 'city' => 'Pampanga', 'country_code' => 'PH', 'port_type' => 'LAND_HUB', 'latitude' => 15.1784, 'longitude' => 120.5620],
            ['id' => 'WHRGN', 'name' => 'Thilawa SEZ Warehouse', 'city' => 'Yangon', 'country_code' => 'MM', 'port_type' => 'LAND_HUB', 'latitude' => 16.7073, 'longitude' => 96.2602],
            ['id' => 'WHPNH', 'name' => 'Phnom Penh Special Economic Zone', 'city' => 'Phnom Penh', 'country_code' => 'KH', 'port_type' => 'LAND_HUB', 'latitude' => 11.5731, 'longitude' => 104.9139],
        ];

        foreach ($ports as $port) {
            Port::updateOrCreate(['id' => $port['id']], $port);
        }
    }
}
