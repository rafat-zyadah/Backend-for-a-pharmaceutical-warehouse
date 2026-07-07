<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Optional demo / sample data for local development and testing.
     * Mandatory system data is installed via: php artisan system:install
     */
    public function run(): void
    {
        $demoUsers = [
            [
                'username' => 'invoicer',
                'name' => 'Demo Invoicer',
                'phone' => '0500000002',
                'role' => UserRole::Invoicer,
                'password' => 'password',
            ],
            [
                'username' => 'rep',
                'name' => 'Demo Rep',
                'phone' => '0500000003',
                'role' => UserRole::Rep,
                'password' => 'password',
            ],
        ];

        foreach ($demoUsers as $row) {
            if (User::query()->where('username', $row['username'])->exists()) {
                continue;
            }

            User::query()->create([
                'username' => $row['username'],
                'name' => $row['name'],
                'phone' => $row['phone'],
                'role' => $row['role'],
                'status' => UserStatus::Active,
                'password' => $row['password'],
            ]);
        }
    }
}
