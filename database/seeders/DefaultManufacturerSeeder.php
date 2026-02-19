<?php

namespace Database\Seeders;

use App\Models\Manufacturer;
use Illuminate\Database\Seeder;

class DefaultManufacturerSeeder extends Seeder
{
    public function run(): void
    {
        $manufacturers = [
            ['name' => 'Apple', 'website' => 'https://www.apple.com'],
            ['name' => 'Dell', 'website' => 'https://www.dell.com'],
            ['name' => 'HP', 'website' => 'https://www.hp.com'],
            ['name' => 'Lenovo', 'website' => 'https://www.lenovo.com'],
            ['name' => 'Samsung', 'website' => 'https://www.samsung.com'],
            ['name' => 'Microsoft', 'website' => 'https://www.microsoft.com'],
            ['name' => 'Cisco', 'website' => 'https://www.cisco.com'],
            ['name' => 'Asus', 'website' => 'https://www.asus.com'],
            ['name' => 'Acer', 'website' => 'https://www.acer.com'],
            ['name' => 'Sony', 'website' => 'https://www.sony.com'],
            ['name' => 'LG', 'website' => 'https://www.lg.com'],
            ['name' => 'Intel', 'website' => 'https://www.intel.com'],
            ['name' => 'Toshiba', 'website' => 'https://www.toshiba.com'],
            ['name' => 'Brother', 'website' => 'https://www.brother.com'],
            ['name' => 'Canon', 'website' => 'https://www.canon.com'],
            ['name' => 'Epson', 'website' => 'https://www.epson.com'],
            ['name' => 'Xerox', 'website' => 'https://www.xerox.com'],
            ['name' => 'Logitech', 'website' => 'https://www.logitech.com'],
        ];

        foreach ($manufacturers as $manufacturer) {
            Manufacturer::withoutGlobalScopes()->updateOrCreate(
                ['name' => $manufacturer['name'], 'organization_id' => null],
                $manufacturer
            );
        }

        $this->command->info('Default manufacturers seeded: ' . count($manufacturers));
    }
}
