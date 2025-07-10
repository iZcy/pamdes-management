<?php
// app/Console/Commands/CreateVillage.php - New command to create villages locally

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\VillageService;
use Illuminate\Support\Str;

class CreateVillage extends Command
{
    protected $signature = 'pamdes:create-village
{name : Village name}
{slug? : Village slug (auto-generated if not provided)}
{--phone= : Phone number}
{--email= : Email address}
{--address= : Village address}';

    protected $description = 'Create a new village in the PAMDes system';

    public function handle(VillageService $villageService)
    {
        $name = $this->argument('name');
        $slug = $this->argument('slug') ?: Str::slug($name);

        $villageData = [
            'id' => Str::uuid()->toString(),
            'name' => $name,
            'slug' => $slug,
            'phone_number' => $this->option('phone'),
            'email' => $this->option('email'),
            'address' => $this->option('address'),
            'is_active' => true,
            'established_at' => now(),
            'pamdes_settings' => [
                'default_admin_fee' => 5000,
                'default_maintenance_fee' => 2000,
                'auto_generate_bills' => true,
                'overdue_threshold_days' => 30,
            ],
        ];

        try {
            $village = $villageService->createOrUpdateVillage($villageData);

            $this->info("Village '{$name}' created successfully!");
            $this->table(
                ['Field', 'Value'],
                [
                    ['ID', $village->id],
                    ['Name', $village->name],
                    ['Slug', $village->slug],
                    ['Status', $village->is_active ? 'Active' : 'Inactive'],
                ]
            );
        } catch (\Exception $e) {
            $this->error("Failed to create village: " . $e->getMessage());
        }
    }
}
