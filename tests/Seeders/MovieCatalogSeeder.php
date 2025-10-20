<?php

declare(strict_types=1);

namespace Tests\Seeders;

use App\Models\Movie;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class MovieCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now()->toImmutable();

        Movie::factory()->movie()->create([
            'title' => 'Сердце Сингулярности',
            'imdb_tt' => 'tt9000001',
            'year' => 2024,
            'release_date' => $now->subMonths(2)->format('Y-m-d'),
            'imdb_rating' => 8.6,
            'imdb_votes' => 240_000,
            'genres' => ['drama', 'thriller'],
            'plot' => 'Команда физиков пытается предотвратить коллапс квантовой сети.',
            'type' => 'movie',
            'runtime_min' => 126,
            'poster_url' => 'https://images.test/singularity-heart.jpg',
            'backdrop_url' => 'https://images.test/singularity-heart-bg.jpg',
            'translations' => [
                'title' => ['en' => 'Heart of Singularity'],
                'plot' => ['en' => 'A physics team races to stabilise the quantum grid.'],
            ],
            'raw' => ['source' => 'tests'],
            'created_at' => $now->subMonths(2),
            'updated_at' => $now->subMonths(2),
        ]);

        Movie::factory()->series()->create([
            'title' => 'Хроники Перезагрузки',
            'imdb_tt' => 'tt9000002',
            'year' => 2021,
            'release_date' => $now->subYears(1)->format('Y-m-d'),
            'imdb_rating' => 7.9,
            'imdb_votes' => 180_000,
            'genres' => ['science fiction', 'mystery'],
            'plot' => 'Подпольные хакеры перезапускают мегаполис каждую ночь.',
            'type' => 'series',
            'runtime_min' => 54,
            'poster_url' => 'https://images.test/reboot-chronicles.jpg',
            'backdrop_url' => 'https://images.test/reboot-chronicles-bg.jpg',
            'translations' => [
                'title' => ['en' => 'Reboot Chronicles'],
                'plot' => ['en' => 'Hackers reboot a megacity every night to survive.'],
            ],
            'raw' => ['source' => 'tests'],
            'created_at' => $now->subYears(1),
            'updated_at' => $now->subYears(1),
        ]);

        Movie::factory()->animation()->create([
            'title' => 'Галактические Хвосты',
            'imdb_tt' => 'tt9000003',
            'year' => 2018,
            'release_date' => $now->subYears(5)->format('Y-m-d'),
            'imdb_rating' => 8.2,
            'imdb_votes' => 95_000,
            'genres' => ['animation', 'family'],
            'plot' => 'Космические зверята собирают звёздную пыль ради спасения галактики.',
            'type' => 'movie',
            'runtime_min' => 102,
            'poster_url' => 'https://images.test/galactic-tails.jpg',
            'backdrop_url' => 'https://images.test/galactic-tails-bg.jpg',
            'translations' => [
                'title' => ['en' => 'Galactic Tails'],
                'plot' => ['en' => 'Space critters collect stardust to save the galaxy.'],
            ],
            'raw' => ['source' => 'tests'],
            'created_at' => $now->subYears(5),
            'updated_at' => $now->subYears(5),
        ]);
    }
}
