<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class CreateAdminSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::create([
            'name' => 'My Company',
            'slug' => 'my-company',
        ]);

        $user = User::create([
            'organization_id' => $org->id,
            'name' => 'Admin',
            'email' => 'admin@admin.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $org->update(['owner_id' => $user->id]);

        $this->command->info("Organization: {$org->name} ({$org->slug})");
        $this->command->info("User: {$user->email} / password");
    }
}
