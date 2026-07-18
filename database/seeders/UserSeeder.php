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
                'name'   => 'backend_admin',
                'email'      => 'admin@enterprise.internal',
                'password'   => 'DevSecureAdminPass2026!',
                'first_name' => 'Shohidullah',
                'last_name'  => 'Admin',
            ],
            [
                'name'   => 'qa_tester',
                'email'      => 'tester@enterprise.internal',
                'password'   => 'TestAutomationPass2026!',
                'first_name' => 'Quality',
                'last_name'  => 'Assurance',
            ]
        ];

        foreach ($profiles as $profile) {
            User::updateOrCreate(
                ['name' => $profile['name']],
                [
                    'email'      => $profile['email'],
                    'password'   => Hash::make($profile['password']),
                    'first_name' => $profile['first_name'],
                    'last_name'  => $profile['last_name'],
                ]
            );
        }
    }
}