<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            MovieSeeder::class,
            RecAbLogSeeder::class,
            RecClickSeeder::class,
            DeviceHistorySeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Demo Admin',
            'email' => 'admin@example.com',
        ]);
    }
}
