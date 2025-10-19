<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Movie;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CtrSvgSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        Carbon::setTestNow(CarbonImmutable::parse('2025-02-15 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_daily_ctr_svg_matches_snapshot(): void
    {
        [$movieA, $movieB] = $this->seedMovies();

        $days = [
            ['variant' => 'A', 'date' => -2, 'imps' => 10, 'clks' => 2],
            ['variant' => 'B', 'date' => -2, 'imps' => 8, 'clks' => 1],
            ['variant' => 'A', 'date' => -1, 'imps' => 12, 'clks' => 3],
            ['variant' => 'B', 'date' => -1, 'imps' => 6, 'clks' => 2],
        ];

        $this->seedCtrData($days, $movieA->id, $movieB->id);

        $response = $this->get(route('admin.ctr.svg', [
            'from' => CarbonImmutable::now('UTC')->subDays(2)->toDateString(),
            'to' => CarbonImmutable::now('UTC')->toDateString(),
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');

        $this->assertSnapshotEquals('tests/Feature/Admin/__snapshots__/ctr_line.svg', $response->getContent());
    }

    public function test_placement_ctr_svg_matches_snapshot(): void
    {
        [$movieA, $movieB] = $this->seedMovies();

        $days = [
            ['variant' => 'A', 'date' => -3, 'imps' => 18, 'clks' => 3, 'placement' => 'home'],
            ['variant' => 'B', 'date' => -3, 'imps' => 12, 'clks' => 2, 'placement' => 'home'],
            ['variant' => 'A', 'date' => -2, 'imps' => 10, 'clks' => 1, 'placement' => 'trends'],
            ['variant' => 'B', 'date' => -2, 'imps' => 16, 'clks' => 4, 'placement' => 'trends'],
        ];

        $this->seedCtrData($days, $movieA->id, $movieB->id);

        $response = $this->get(route('admin.ctr.bars.svg', [
            'from' => CarbonImmutable::now('UTC')->subDays(3)->toDateString(),
            'to' => CarbonImmutable::now('UTC')->toDateString(),
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');

        $this->assertSnapshotEquals('tests/Feature/Admin/__snapshots__/ctr_bars.svg', $response->getContent());
    }

    private function seedMovies(): array
    {
        $movieA = Movie::factory()->movie()->create([
            'title' => 'Эксперимент A',
            'imdb_rating' => 8.1,
            'imdb_votes' => 150_000,
        ]);
        $movieB = Movie::factory()->movie()->create([
            'title' => 'Эксперимент B',
            'imdb_rating' => 7.8,
            'imdb_votes' => 140_000,
        ]);

        return [$movieA, $movieB];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function seedCtrData(array $rows, int $movieA, int $movieB): void
    {
        foreach ($rows as $row) {
            $date = CarbonImmutable::now('UTC')->addDays($row['date']);
            $variant = $row['variant'];
            $placement = $row['placement'] ?? 'home';

            for ($i = 0; $i < $row['imps']; $i++) {
                DB::table('rec_ab_logs')->insert([
                    'movie_id' => $i % 2 === 0 ? $movieA : $movieB,
                    'device_id' => sprintf('device-%s-%d', $variant, $i),
                    'variant' => $variant,
                    'placement' => $placement,
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);
            }

            for ($i = 0; $i < $row['clks']; $i++) {
                DB::table('rec_clicks')->insert([
                    'movie_id' => $i % 2 === 0 ? $movieA : $movieB,
                    'variant' => $variant,
                    'placement' => $placement,
                    'created_at' => $date->addHours(1),
                    'updated_at' => $date->addHours(1),
                ]);
            }
        }
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
