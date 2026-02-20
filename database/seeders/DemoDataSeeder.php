<?php

namespace Database\Seeders;

use App\Enums\AssetStatus;
use App\Enums\EncodingMode;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetImage;
use App\Models\AssetTag;
use App\Models\AssetTagValue;
use App\Models\Department;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    private Organization $org;

    private array $locationMap = [];

    private array $departmentMap = [];

    private array $categoryMap = [];

    private array $tagMap = [];

    private array $manufacturerMap = [];

    public function run(): void
    {
        $this->org = Organization::where('slug', 'my-company')->firstOrFail();

        $this->seedLocations();
        $this->seedDepartments();
        $this->seedCategories();
        $this->loadManufacturers();
        $this->seedAssets();

        $this->command->info('Demo data seeded successfully!');
    }

    private function seedLocations(): void
    {
        $locations = [
            ['name' => 'Siège - Lomé', 'city' => 'Lomé', 'country' => 'Togo', 'address' => 'Boulevard du 13 Janvier, Lomé'],
            ['name' => 'Agence Kara', 'city' => 'Kara', 'country' => 'Togo'],
            ['name' => 'Agence Sokodé', 'city' => 'Sokodé', 'country' => 'Togo'],
            ['name' => 'Agence Atakpamé', 'city' => 'Atakpamé', 'country' => 'Togo'],
            ['name' => 'Agence Dapaong', 'city' => 'Dapaong', 'country' => 'Togo'],
            ['name' => 'Entrepôt Lomé', 'city' => 'Lomé', 'country' => 'Togo', 'address' => 'Zone Portuaire'],
        ];

        foreach ($locations as $data) {
            $location = Location::withoutGlobalScopes()
                ->where('organization_id', $this->org->id)
                ->where('name', $data['name'])
                ->first();

            if (! $location) {
                $location = Location::withoutGlobalScopes()->create([
                    'organization_id' => $this->org->id,
                    ...$data,
                ]);
            }

            $this->locationMap[$data['name']] = $location;
        }

        $this->command->info('Locations: ' . count($this->locationMap));
    }

    private function seedDepartments(): void
    {
        $siege = $this->locationMap['Siège - Lomé'];

        $departments = [
            'Direction Générale',
            'Ressources Humaines',
            'Finance & Comptabilité',
            'Informatique / IT',
            'Marketing & Communication',
            'Commercial / Ventes',
            'Logistique & Approvisionnement',
            'Juridique',
            'Service Client',
            'Administration',
        ];

        foreach ($departments as $name) {
            $department = Department::withoutGlobalScopes()
                ->where('organization_id', $this->org->id)
                ->where('name', $name)
                ->first();

            if (! $department) {
                $department = Department::withoutGlobalScopes()->create([
                    'organization_id' => $this->org->id,
                    'name' => $name,
                    'location_id' => $siege->id,
                ]);
            }

            $this->departmentMap[$name] = $department;
        }

        $this->command->info('Departments: ' . count($this->departmentMap));
    }

    private function seedCategories(): void
    {
        $categories = [
            ['name' => 'Ordinateurs portables', 'icon' => 'heroicon-o-computer-desktop'],
            ['name' => 'Ordinateurs de bureau', 'icon' => 'heroicon-o-computer-desktop'],
            ['name' => 'Écrans / Moniteurs', 'icon' => 'heroicon-o-tv'],
            ['name' => 'Imprimantes', 'icon' => 'heroicon-o-printer'],
            ['name' => 'Projecteurs', 'icon' => 'heroicon-o-film'],
            ['name' => 'Téléphones', 'icon' => 'heroicon-o-phone'],
            ['name' => 'Tablettes', 'icon' => 'heroicon-o-device-tablet'],
            ['name' => 'Serveurs & Réseau', 'icon' => 'heroicon-o-server-stack'],
            ['name' => 'Climatiseurs', 'icon' => 'heroicon-o-bolt'],
            ['name' => 'Mobilier de bureau', 'icon' => 'heroicon-o-home'],
            ['name' => 'Véhicules', 'icon' => 'heroicon-o-truck'],
            ['name' => 'Équipement de sécurité', 'icon' => 'heroicon-o-shield-check'],
        ];

        foreach ($categories as $data) {
            $category = AssetCategory::withoutGlobalScopes()
                ->where('organization_id', $this->org->id)
                ->where('name', $data['name'])
                ->first();

            if (! $category) {
                $category = AssetCategory::withoutGlobalScopes()->create([
                    'organization_id' => $this->org->id,
                    ...$data,
                ]);
            }

            $this->categoryMap[$data['name']] = $category;

            // Create "Numéro de série" tag for each category
            $tag = AssetTag::withoutGlobalScopes()
                ->where('organization_id', $this->org->id)
                ->where('category_id', $category->id)
                ->where('name', 'Numéro de série')
                ->first();

            if (! $tag) {
                $tag = AssetTag::withoutGlobalScopes()->create([
                    'organization_id' => $this->org->id,
                    'category_id' => $category->id,
                    'name' => 'Numéro de série',
                    'is_required' => true,
                    'encoding_mode' => EncodingMode::QrCode,
                ]);
            }

            $this->tagMap[$data['name']] = $tag;
        }

        $this->command->info('Categories: ' . count($this->categoryMap));
    }

    private function loadManufacturers(): void
    {
        $names = [
            'Apple', 'Dell', 'HP', 'Lenovo', 'Samsung', 'Cisco',
            'Sony', 'LG', 'Brother', 'Canon', 'Epson', 'Xerox',
        ];

        foreach ($names as $name) {
            $manufacturer = Manufacturer::withoutGlobalScopes()
                ->where('name', $name)
                ->first();

            if ($manufacturer) {
                $this->manufacturerMap[$name] = $manufacturer;
            }
        }

        // Create missing manufacturers needed for demo assets
        $extraManufacturers = [
            ['name' => 'Toyota', 'website' => 'https://www.toyota.com'],
            ['name' => 'Hikvision', 'website' => 'https://www.hikvision.com'],
            ['name' => 'APC', 'website' => 'https://www.apc.com'],
        ];

        foreach ($extraManufacturers as $data) {
            $manufacturer = Manufacturer::withoutGlobalScopes()
                ->where('name', $data['name'])
                ->first();

            if (! $manufacturer) {
                $manufacturer = Manufacturer::withoutGlobalScopes()->create([
                    'organization_id' => null,
                    ...$data,
                ]);
            }

            $this->manufacturerMap[$data['name']] = $manufacturer;
        }

        $this->command->info('Manufacturers loaded: ' . count($this->manufacturerMap));
    }

    private function seedAssets(): void
    {
        $assets = $this->getAssetsData();

        $created = 0;
        $skipped = 0;

        foreach ($assets as $data) {
            $category = $this->categoryMap[$data['category']];
            $location = $this->locationMap[$data['location']];
            $department = $this->departmentMap[$data['department']];
            $manufacturer = isset($data['manufacturer']) ? ($this->manufacturerMap[$data['manufacturer']] ?? null) : null;

            // Check if asset already exists (name + category + location + department)
            $exists = Asset::withoutGlobalScopes()
                ->where('organization_id', $this->org->id)
                ->where('name', $data['name'])
                ->where('category_id', $category->id)
                ->where('location_id', $location->id)
                ->where('department_id', $department->id)
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            $serialNumber = strtoupper(Str::random(4) . '-' . Str::random(4) . '-' . Str::random(4));

            $asset = Asset::withoutGlobalScopes()->create([
                'organization_id' => $this->org->id,
                'name' => $data['name'],
                'category_id' => $category->id,
                'location_id' => $location->id,
                'department_id' => $department->id,
                'manufacturer_id' => $manufacturer?->id,
                'serial_number' => $serialNumber,
                'status' => $data['status'],
                'purchase_date' => $data['purchase_date'],
                'purchase_cost' => $data['purchase_cost'],
            ]);

            // Create AssetTagValue for serial number
            $tag = $this->tagMap[$data['category']];
            AssetTagValue::withoutGlobalScopes()->create([
                'organization_id' => $this->org->id,
                'asset_id' => $asset->id,
                'asset_tag_id' => $tag->id,
                'value' => $serialNumber,
                'encoding_mode' => EncodingMode::QrCode,
            ]);

            // Download image
            $this->downloadImage($asset);

            $created++;
        }

        $this->command->info("Assets created: {$created}, skipped: {$skipped}");
    }

    private function downloadImage(Asset $asset): void
    {
        try {
            $response = Http::withOptions([
                'allow_redirects' => true,
                'timeout' => 15,
            ])->get('https://picsum.photos/400/300');

            if ($response->successful()) {
                $directory = 'public/assets';
                Storage::makeDirectory($directory);

                $filename = "assets/{$asset->id}.jpg";
                Storage::disk('public')->put($filename, $response->body());

                AssetImage::withoutGlobalScopes()->create([
                    'organization_id' => $this->org->id,
                    'asset_id' => $asset->id,
                    'file_path' => $filename,
                    'is_primary' => true,
                    'sort_order' => 0,
                ]);
            }
        } catch (\Exception $e) {
            $this->command->warn("Could not download image for asset '{$asset->name}': " . $e->getMessage());
        }
    }

    private function getAssetsData(): array
    {
        $siege = 'Siège - Lomé';
        $kara = 'Agence Kara';
        $sokode = 'Agence Sokodé';
        $atakpame = 'Agence Atakpamé';
        $dapaong = 'Agence Dapaong';
        $entrepot = 'Entrepôt Lomé';

        $dg = 'Direction Générale';
        $rh = 'Ressources Humaines';
        $finance = 'Finance & Comptabilité';
        $it = 'Informatique / IT';
        $marketing = 'Marketing & Communication';
        $commercial = 'Commercial / Ventes';
        $logistique = 'Logistique & Approvisionnement';
        $juridique = 'Juridique';
        $serviceClient = 'Service Client';
        $admin = 'Administration';

        return [
            // === Ordinateurs portables (8) ===
            [
                'name' => 'MacBook Pro 14" M3',
                'category' => 'Ordinateurs portables',
                'manufacturer' => 'Apple',
                'location' => $siege,
                'department' => $dg,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2024-06-15',
                'purchase_cost' => 1250000,
            ],
            [
                'name' => 'MacBook Pro 14" M3',
                'category' => 'Ordinateurs portables',
                'manufacturer' => 'Apple',
                'location' => $siege,
                'department' => $marketing,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2024-07-20',
                'purchase_cost' => 1250000,
            ],
            [
                'name' => 'Dell Latitude 5540',
                'category' => 'Ordinateurs portables',
                'manufacturer' => 'Dell',
                'location' => $siege,
                'department' => $finance,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2024-03-10',
                'purchase_cost' => 650000,
            ],
            [
                'name' => 'Dell Latitude 5540',
                'category' => 'Ordinateurs portables',
                'manufacturer' => 'Dell',
                'location' => $kara,
                'department' => $commercial,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2024-04-05',
                'purchase_cost' => 650000,
            ],
            [
                'name' => 'Lenovo ThinkPad T14 Gen 4',
                'category' => 'Ordinateurs portables',
                'manufacturer' => 'Lenovo',
                'location' => $siege,
                'department' => $it,
                'status' => AssetStatus::Available,
                'purchase_date' => '2024-09-01',
                'purchase_cost' => 580000,
            ],
            [
                'name' => 'HP EliteBook 840 G10',
                'category' => 'Ordinateurs portables',
                'manufacturer' => 'HP',
                'location' => $siege,
                'department' => $rh,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2023-11-20',
                'purchase_cost' => 620000,
            ],
            [
                'name' => 'MacBook Air 15" M2',
                'category' => 'Ordinateurs portables',
                'manufacturer' => 'Apple',
                'location' => $siege,
                'department' => $marketing,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2024-01-15',
                'purchase_cost' => 950000,
            ],
            [
                'name' => 'HP EliteBook 840 G10',
                'category' => 'Ordinateurs portables',
                'manufacturer' => 'HP',
                'location' => $sokode,
                'department' => $admin,
                'status' => AssetStatus::UnderMaintenance,
                'purchase_date' => '2023-08-10',
                'purchase_cost' => 620000,
            ],

            // === Ordinateurs de bureau (5) ===
            [
                'name' => 'Dell OptiPlex 7010',
                'category' => 'Ordinateurs de bureau',
                'manufacturer' => 'Dell',
                'location' => $siege,
                'department' => $finance,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2023-06-20',
                'purchase_cost' => 450000,
            ],
            [
                'name' => 'Dell OptiPlex 7010',
                'category' => 'Ordinateurs de bureau',
                'manufacturer' => 'Dell',
                'location' => $siege,
                'department' => $serviceClient,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2023-07-15',
                'purchase_cost' => 450000,
            ],
            [
                'name' => 'HP ProDesk 400 G9',
                'category' => 'Ordinateurs de bureau',
                'manufacturer' => 'HP',
                'location' => $kara,
                'department' => $admin,
                'status' => AssetStatus::Available,
                'purchase_date' => '2024-02-28',
                'purchase_cost' => 380000,
            ],
            [
                'name' => 'Lenovo ThinkCentre M70q',
                'category' => 'Ordinateurs de bureau',
                'manufacturer' => 'Lenovo',
                'location' => $siege,
                'department' => $it,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2023-12-01',
                'purchase_cost' => 420000,
            ],
            [
                'name' => 'HP ProDesk 400 G9',
                'category' => 'Ordinateurs de bureau',
                'manufacturer' => 'HP',
                'location' => $atakpame,
                'department' => $commercial,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2024-05-10',
                'purchase_cost' => 380000,
            ],

            // === Écrans / Moniteurs (5) ===
            [
                'name' => 'Dell UltraSharp U2723QE 27"',
                'category' => 'Écrans / Moniteurs',
                'manufacturer' => 'Dell',
                'location' => $siege,
                'department' => $dg,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2024-06-15',
                'purchase_cost' => 350000,
            ],
            [
                'name' => 'Dell UltraSharp U2723QE 27"',
                'category' => 'Écrans / Moniteurs',
                'manufacturer' => 'Dell',
                'location' => $siege,
                'department' => $marketing,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2024-07-20',
                'purchase_cost' => 350000,
            ],
            [
                'name' => 'Samsung 27" Curved LS27C390',
                'category' => 'Écrans / Moniteurs',
                'manufacturer' => 'Samsung',
                'location' => $siege,
                'department' => $it,
                'status' => AssetStatus::Available,
                'purchase_date' => '2024-08-05',
                'purchase_cost' => 180000,
            ],
            [
                'name' => 'LG 24" IPS 24MP400',
                'category' => 'Écrans / Moniteurs',
                'manufacturer' => 'LG',
                'location' => $kara,
                'department' => $admin,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2023-10-10',
                'purchase_cost' => 120000,
            ],
            [
                'name' => 'LG 24" IPS 24MP400',
                'category' => 'Écrans / Moniteurs',
                'manufacturer' => 'LG',
                'location' => $siege,
                'department' => $finance,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2023-10-10',
                'purchase_cost' => 120000,
            ],

            // === Imprimantes (4) ===
            [
                'name' => 'HP LaserJet Pro M404dn',
                'category' => 'Imprimantes',
                'manufacturer' => 'HP',
                'location' => $siege,
                'department' => $admin,
                'status' => AssetStatus::Available,
                'purchase_date' => '2023-05-15',
                'purchase_cost' => 280000,
            ],
            [
                'name' => 'Canon imageRUNNER 2630i',
                'category' => 'Imprimantes',
                'manufacturer' => 'Canon',
                'location' => $siege,
                'department' => $admin,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2023-03-20',
                'purchase_cost' => 1500000,
            ],
            [
                'name' => 'Brother MFC-L8900CDW',
                'category' => 'Imprimantes',
                'manufacturer' => 'Brother',
                'location' => $kara,
                'department' => $admin,
                'status' => AssetStatus::Available,
                'purchase_date' => '2024-01-10',
                'purchase_cost' => 450000,
            ],
            [
                'name' => 'Xerox VersaLink C405',
                'category' => 'Imprimantes',
                'manufacturer' => 'Xerox',
                'location' => $sokode,
                'department' => $admin,
                'status' => AssetStatus::UnderMaintenance,
                'purchase_date' => '2023-09-25',
                'purchase_cost' => 520000,
            ],

            // === Projecteurs (2) ===
            [
                'name' => 'Epson EB-W52',
                'category' => 'Projecteurs',
                'manufacturer' => 'Epson',
                'location' => $siege,
                'department' => $dg,
                'status' => AssetStatus::Available,
                'purchase_date' => '2024-02-14',
                'purchase_cost' => 350000,
            ],
            [
                'name' => 'Sony VPL-EW575',
                'category' => 'Projecteurs',
                'manufacturer' => 'Sony',
                'location' => $siege,
                'department' => $marketing,
                'status' => AssetStatus::Available,
                'purchase_date' => '2023-11-30',
                'purchase_cost' => 680000,
            ],

            // === Téléphones (5) ===
            [
                'name' => 'iPhone 15 Pro',
                'category' => 'Téléphones',
                'manufacturer' => 'Apple',
                'location' => $siege,
                'department' => $dg,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2024-10-01',
                'purchase_cost' => 750000,
            ],
            [
                'name' => 'iPhone 15',
                'category' => 'Téléphones',
                'manufacturer' => 'Apple',
                'location' => $siege,
                'department' => $commercial,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2024-10-15',
                'purchase_cost' => 550000,
            ],
            [
                'name' => 'Samsung Galaxy S24',
                'category' => 'Téléphones',
                'manufacturer' => 'Samsung',
                'location' => $siege,
                'department' => $marketing,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2024-03-01',
                'purchase_cost' => 480000,
            ],
            [
                'name' => 'Samsung Galaxy S24',
                'category' => 'Téléphones',
                'manufacturer' => 'Samsung',
                'location' => $kara,
                'department' => $commercial,
                'status' => AssetStatus::Available,
                'purchase_date' => '2024-04-20',
                'purchase_cost' => 480000,
            ],
            [
                'name' => 'Cisco IP Phone 8845',
                'category' => 'Téléphones',
                'manufacturer' => 'Cisco',
                'location' => $siege,
                'department' => $serviceClient,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2023-06-01',
                'purchase_cost' => 250000,
            ],

            // === Tablettes (3) ===
            [
                'name' => 'iPad Air M2 11"',
                'category' => 'Tablettes',
                'manufacturer' => 'Apple',
                'location' => $siege,
                'department' => $commercial,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2024-08-10',
                'purchase_cost' => 420000,
            ],
            [
                'name' => 'iPad Air M2 11"',
                'category' => 'Tablettes',
                'manufacturer' => 'Apple',
                'location' => $siege,
                'department' => $dg,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2024-08-10',
                'purchase_cost' => 420000,
            ],
            [
                'name' => 'Samsung Galaxy Tab S9',
                'category' => 'Tablettes',
                'manufacturer' => 'Samsung',
                'location' => $kara,
                'department' => $logistique,
                'status' => AssetStatus::Available,
                'purchase_date' => '2024-05-25',
                'purchase_cost' => 350000,
            ],

            // === Serveurs & Réseau (3) ===
            [
                'name' => 'Dell PowerEdge R750',
                'category' => 'Serveurs & Réseau',
                'manufacturer' => 'Dell',
                'location' => $siege,
                'department' => $it,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2023-04-15',
                'purchase_cost' => 4500000,
            ],
            [
                'name' => 'Cisco Catalyst 9200 Switch',
                'category' => 'Serveurs & Réseau',
                'manufacturer' => 'Cisco',
                'location' => $siege,
                'department' => $it,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2023-04-15',
                'purchase_cost' => 1800000,
            ],
            [
                'name' => 'APC Smart-UPS 3000VA',
                'category' => 'Serveurs & Réseau',
                'manufacturer' => 'APC',
                'location' => $siege,
                'department' => $it,
                'status' => AssetStatus::Available,
                'purchase_date' => '2023-05-01',
                'purchase_cost' => 950000,
            ],

            // === Climatiseurs (4) ===
            [
                'name' => 'Samsung Wind-Free 12000 BTU',
                'category' => 'Climatiseurs',
                'manufacturer' => 'Samsung',
                'location' => $siege,
                'department' => $dg,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2023-12-20',
                'purchase_cost' => 450000,
            ],
            [
                'name' => 'Samsung Wind-Free 12000 BTU',
                'category' => 'Climatiseurs',
                'manufacturer' => 'Samsung',
                'location' => $siege,
                'department' => $finance,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2023-12-20',
                'purchase_cost' => 450000,
            ],
            [
                'name' => 'LG Dual Inverter 18000 BTU',
                'category' => 'Climatiseurs',
                'manufacturer' => 'LG',
                'location' => $kara,
                'department' => $admin,
                'status' => AssetStatus::Available,
                'purchase_date' => '2024-03-15',
                'purchase_cost' => 580000,
            ],
            [
                'name' => 'LG Dual Inverter 24000 BTU',
                'category' => 'Climatiseurs',
                'manufacturer' => 'LG',
                'location' => $siege,
                'department' => $serviceClient,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2024-01-10',
                'purchase_cost' => 720000,
            ],

            // === Mobilier de bureau (8) ===
            [
                'name' => 'Bureau de direction en bois massif',
                'category' => 'Mobilier de bureau',
                'location' => $siege,
                'department' => $dg,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2023-01-15',
                'purchase_cost' => 850000,
            ],
            [
                'name' => 'Bureau standard 120x60cm',
                'category' => 'Mobilier de bureau',
                'location' => $siege,
                'department' => $finance,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2023-02-20',
                'purchase_cost' => 150000,
            ],
            [
                'name' => 'Bureau standard 120x60cm',
                'category' => 'Mobilier de bureau',
                'location' => $siege,
                'department' => $rh,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2023-02-20',
                'purchase_cost' => 150000,
            ],
            [
                'name' => 'Chaise ergonomique avec accoudoirs',
                'category' => 'Mobilier de bureau',
                'location' => $siege,
                'department' => $dg,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2023-01-15',
                'purchase_cost' => 350000,
            ],
            [
                'name' => 'Chaise ergonomique avec accoudoirs',
                'category' => 'Mobilier de bureau',
                'location' => $siege,
                'department' => $it,
                'status' => AssetStatus::Available,
                'purchase_date' => '2024-06-01',
                'purchase_cost' => 350000,
            ],
            [
                'name' => 'Table de réunion 10 places',
                'category' => 'Mobilier de bureau',
                'location' => $siege,
                'department' => $dg,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2023-01-15',
                'purchase_cost' => 1200000,
            ],
            [
                'name' => 'Table de réunion 6 places',
                'category' => 'Mobilier de bureau',
                'location' => $kara,
                'department' => $admin,
                'status' => AssetStatus::Available,
                'purchase_date' => '2024-02-10',
                'purchase_cost' => 650000,
            ],
            [
                'name' => 'Armoire de rangement métallique',
                'category' => 'Mobilier de bureau',
                'location' => $siege,
                'department' => $admin,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2023-03-01',
                'purchase_cost' => 280000,
            ],

            // === Véhicules (2) ===
            [
                'name' => 'Toyota Hilux 2024 Double Cabine',
                'category' => 'Véhicules',
                'manufacturer' => 'Toyota',
                'location' => $entrepot,
                'department' => $logistique,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2024-01-20',
                'purchase_cost' => 25000000,
            ],
            [
                'name' => 'Toyota Corolla 2023 Sedan',
                'category' => 'Véhicules',
                'manufacturer' => 'Toyota',
                'location' => $siege,
                'department' => $dg,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2023-06-10',
                'purchase_cost' => 15000000,
            ],

            // === Équipement de sécurité (2) ===
            [
                'name' => 'Caméra Hikvision DS-2CD2143G2-I',
                'category' => 'Équipement de sécurité',
                'manufacturer' => 'Hikvision',
                'location' => $siege,
                'department' => $admin,
                'status' => AssetStatus::Assigned,
                'purchase_date' => '2023-08-15',
                'purchase_cost' => 85000,
            ],
            [
                'name' => 'Caméra Hikvision DS-2CD2143G2-I',
                'category' => 'Équipement de sécurité',
                'manufacturer' => 'Hikvision',
                'location' => $kara,
                'department' => $admin,
                'status' => AssetStatus::Available,
                'purchase_date' => '2024-02-01',
                'purchase_cost' => 85000,
            ],
        ];

        return $assets;
    }
}
