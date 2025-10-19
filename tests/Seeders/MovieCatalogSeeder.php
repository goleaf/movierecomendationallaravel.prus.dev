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
            'created_at' => $now->subYears(5),
            'updated_at' => $now->subYears(5),
        ]);
    }
}
