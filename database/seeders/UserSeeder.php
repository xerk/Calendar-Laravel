<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Admin
        Admin::create([
            'name' => 'Admin',
            'email' => 'admin@grandcalendar.io',
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
        ]);

        $english = ['id' => "en", "name" => "English", "localName" => "English", "countries" => ["United Kingdom", "Nigeria", "Philippines", "Bangladesh", "India"]];

        // Test user 1
        $user = User::create([
            'name' => 'User 1',
            'email' => 'user1@grandcalendar.io',
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'languages' => [$english],
            'email_verify_otp' => md5(111111),
            'last_online_at' => now(),
        ]);
        UserProfile::create([
            'user_id' => $user->id,
        ]);
    }
}
