<?php

declare(strict_types=1);

namespace Tests\Feature\Rss;

use App\Models\Movie;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RssFeedSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(CarbonImmutable::parse('2025-03-01 10:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_new_releases_feed_matches_snapshot(): void
    {
        $today = CarbonImmutable::now('UTC');

        $recentMovies = [
            ['title' => 'Квантовый вихрь', 'tt' => 'tt8100001', 'offsetDays' => 1],
            ['title' => 'Рубиновые небеса', 'tt' => 'tt8100002', 'offsetDays' => 3],
        ];

        foreach ($recentMovies as $index => $data) {
            Movie::factory()->movie()->create([
                'title' => $data['title'],
                'imdb_tt' => $data['tt'],
                'release_date' => $today->subDays($data['offsetDays'])->format('Y-m-d'),
                'created_at' => $today->subDays($data['offsetDays']),
                'updated_at' => $today->subDays($data['offsetDays']),
                'poster_url' => sprintf('https://img.example.com/poster-%d.jpg', $index + 1),
                'genres' => ['science fiction', 'thriller'],
                'plot' => 'Исследование временных аномалий приводит к неожиданным открытиям.',
                'translations' => [
                    'title' => ['ru' => $data['title'].' RU'],
                    'plot' => ['ru' => 'Переведённый сюжет'],
                ],
            ]);
        }

        $response = $this->get(route('rss.new'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

        $this->assertSnapshotEquals('tests/Feature/Rss/__snapshots__/new_releases.xml', $response->getContent());
    }

    public function test_upcoming_feed_matches_snapshot(): void
    {
        $today = CarbonImmutable::now('UTC');

        $upcoming = [
            ['title' => 'Неоновый рассвет', 'tt' => 'tt8200001', 'offsetDays' => 5],
            ['title' => 'Утро Марса', 'tt' => 'tt8200002', 'offsetDays' => 12],
        ];

        foreach ($upcoming as $index => $data) {
            Movie::factory()->movie()->create([
                'title' => $data['title'],
                'imdb_tt' => $data['tt'],
                'release_date' => $today->addDays($data['offsetDays'])->format('Y-m-d'),
                'created_at' => $today->addDays($data['offsetDays'])->subWeek(),
                'updated_at' => $today->addDays($data['offsetDays'])->subWeek(),
                'poster_url' => sprintf('https://img.example.com/upcoming-%d.jpg', $index + 1),
                'genres' => ['adventure'],
                'plot' => 'Команда исследователей готовится к новой миссии.',
            ]);
        }

        $response = $this->get(route('rss.upcoming'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

        $this->assertSnapshotEquals('tests/Feature/Rss/__snapshots__/upcoming.xml', $response->getContent());
    }

    private function assertSnapshotEquals(string $path, string $actual): void
    {
        $fullPath = base_path($path);

        if (! File::exists($fullPath)) {
            File::ensureDirectoryExists(dirname($fullPath));
            File::put($fullPath, $this->normalize($actual)."\n");
        }

        $expected = File::get($fullPath);
        $this->assertSame(
            $this->normalize($expected),
            $this->normalize($actual),
            sprintf('Snapshot mismatch for %s', $path)
        );
    }

    private function normalize(string $value): string
    {
        return trim(str_replace("\r\n", "\n", $value));
    }
}
