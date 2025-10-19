<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Movie;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class RssFeedsTest extends TestCase
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

    public function test_new_releases_feed_supports_pagination_and_conditional_requests(): void
    {
        $now = CarbonImmutable::now('UTC');

        foreach (range(1, 35) as $index) {
            Movie::factory()->create([
                'release_date' => $now->subDays($index)->format('Y-m-d'),
                'created_at' => $now->subDays($index),
                'updated_at' => $now->subDays($index),
            ]);
        }

        $response = $this->get('/rss/new?page=2');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
        $response->assertHeader('ETag');
        $response->assertHeader('Last-Modified');

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $this->assertSame('Новые релизы', (string) $xml->channel->title);

        $items = collect($xml->channel->item);
        $this->assertCount(5, $items);

        $pubDates = $items->map(function ($item): CarbonImmutable {
            return CarbonImmutable::parse((string) $item->pubDate);
        });

        $this->assertTrue($this->isSortedDescending($pubDates));

        $etag = $response->headers->get('ETag');
        $lastModified = $response->headers->get('Last-Modified');

        $cached = $this->withHeaders([
            'If-None-Match' => $etag,
            'If-Modified-Since' => $lastModified,
        ])->get('/rss/new?page=2');

        $cached->assertStatus(304);
        $cached->assertHeader('ETag', $etag);
        $cached->assertHeader('Last-Modified', $lastModified);
        $this->assertSame('', $cached->getContent());
    }

    public function test_upcoming_feed_lists_future_releases_in_chronological_order(): void
    {
        $now = CarbonImmutable::now('UTC');

        $schedule = [
            ['title' => 'Орбита', 'release' => $now->addDays(3)],
            ['title' => 'Лунное эхо', 'release' => $now->addDays(10)],
            ['title' => 'Звёздный рассвет', 'release' => $now->addDays(15)],
        ];

        foreach ($schedule as $row) {
            Movie::factory()->create([
                'title' => $row['title'],
                'release_date' => $row['release']->format('Y-m-d'),
                'created_at' => $row['release']->subDays(7),
                'updated_at' => $row['release']->subDays(7),
            ]);
        }

        $response = $this->get('/rss/upcoming');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $this->assertSame('Скоро выйдут', (string) $xml->channel->title);

        $items = collect($xml->channel->item);
        $this->assertCount(3, $items);

        $titles = $items->map(fn ($item): string => (string) $item->title)->toArray();
        $this->assertSame(['Орбита', 'Лунное эхо', 'Звёздный рассвет'], $titles);

        $pubDates = $items->map(function ($item): CarbonImmutable {
            return CarbonImmutable::parse((string) $item->pubDate);
        });

        $this->assertTrue($this->isSortedAscending($pubDates));
    }

    private function isSortedDescending(Collection $dates): bool
    {
        $values = $dates->values();

        for ($index = 1; $index < $values->count(); $index++) {
            if ($values[$index]->greaterThan($values[$index - 1])) {
                return false;
            }
        }

        return true;
    }

    private function isSortedAscending(Collection $dates): bool
    {
        $values = $dates->values();

        for ($index = 1; $index < $values->count(); $index++) {
            if ($values[$index]->lessThan($values[$index - 1])) {
                return false;
            }
        }

        return true;
    }
}
