<?php

namespace Database\Seeders;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;

class MessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Retrieve the admin user created by UserSeeder to link messages properly
        $adminUser = User::where('name', 'backend_admin')->first();

        if (!$adminUser) {
            return;
        }

        $messages = [
            [
                'sender_id' => $adminUser->id,
                'auth_id'   => $adminUser->id,
                'message'   => 'System initialized successfully via Laravel database seeder.',
            ]
        ];

        foreach ($messages as $messageData) {
            Message::create($messageData);
        }
    }
}