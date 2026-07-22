<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [
            [
                'name'       => 'backend_admin',
                'email'      => 'admin@enterprise.internal',
                'password'   => Hash::make('DevSecureAdminPass2026!'),
                'first_name' => 'Laravel',
                'last_name'  => 'Admin',
            ]
        ];

        foreach ($profiles as $profile) {
            User::updateOrCreate(
                ['name' => $profile['name']],
                $profile
            );

        }
    }
}