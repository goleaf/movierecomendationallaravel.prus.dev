<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Movie;
use App\Services\RecAb;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecAbTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('recs', [
            'A' => [
                'pop' => 0.7,
                'recent' => 0.3,
                'pref' => 0.0,
            ],
            'B' => [
                'pop' => 0.2,
                'recent' => 0.8,
                'pref' => 0.0,
            ],
        ]);

        Schema::dropIfExists('movies');

        Schema::create('movies', function (Blueprint $table): void {
            $table->id();
            $table->string('imdb_tt')->unique();
            $table->string('title');
            $table->string('type', 32);
            $table->unsignedSmallInteger('year')->nullable();
            $table->decimal('imdb_rating', 4, 2)->nullable();
            $table->unsignedInteger('imdb_votes')->nullable();
            $table->timestamps();
        });
    }

    public function test_variant_a_prioritizes_popularity(): void
    {
        $this->createSampleMovies();

        $this->app->instance('request', Request::create('/', 'GET', [], ['ab_variant' => 'A']));
        $service = app(RecAb::class);

        [$variant, $movies] = $service->forDevice('device-even-2', 2);

        $this->assertSame('A', $variant);
        $this->assertSame([
            'tt-classic-hit',
            'tt-breakout-indie',
        ], $movies->pluck('imdb_tt')->all());
    }

    public function test_variant_b_prioritizes_recency(): void
    {
        $this->createSampleMovies();

        $this->app->instance('request', Request::create('/', 'GET', [], ['ab_variant' => 'B']));
        $service = app(RecAb::class);

        [$variant, $movies] = $service->forDevice('device-odd', 2);

        $this->assertSame('B', $variant);
        $this->assertSame([
            'tt-breakout-indie',
            'tt-classic-hit',
        ], $movies->pluck('imdb_tt')->all());
    }

    protected function createSampleMovies(): void
    {
        Movie::query()->create([
            'imdb_tt' => 'tt-classic-hit',
            'title' => 'Classic Hit',
            'type' => 'movie',
            'year' => now()->year - 20,
            'imdb_rating' => 9.0,
            'imdb_votes' => 1_000_000,
        ]);

        Movie::query()->create([
            'imdb_tt' => 'tt-breakout-indie',
            'title' => 'Breakout Indie',
            'type' => 'movie',
            'year' => now()->year,
            'imdb_rating' => 7.0,
            'imdb_votes' => 10_000,
        ]);
    }
}
