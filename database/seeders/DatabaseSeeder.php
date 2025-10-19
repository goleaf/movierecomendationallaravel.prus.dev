<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Movie;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RecommendationWeightsSeeder::class,
            MovieSeeder::class,
            IngestionRunSeeder::class,
            RecAbLogSeeder::class,
            RecClickSeeder::class,
            DeviceHistorySeeder::class,
        ]);

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Demo Admin',
                'password' => Hash::make('password'),
            ],
        );

        $featuredMovie = Movie::query()->orderByDesc('imdb_votes')->first();

        if ($featuredMovie !== null && ! $featuredMovie->comments()->exists()) {
            $featuredMovie->comment('Thanks for checking out MovieRec! What did you think of this recommendation?', $admin);
            $featuredMovie->subscribe($admin);
        }
    }
}
