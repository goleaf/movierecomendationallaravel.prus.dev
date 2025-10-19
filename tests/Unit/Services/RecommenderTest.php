<?php

namespace Tests\Unit\Services;

use App\Models\Movie;
use App\Services\RecAb;
use App\Services\Recommender;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class RecommenderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_recommend_for_device_returns_collection_from_recab(): void
    {
        $movies = Collection::make([
            Movie::factory()->make([
                'id' => 101,
                'imdb_tt' => 'tt9100001',
                'title' => 'Signal Lost',
            ]),
            Movie::factory()->make([
                'id' => 102,
                'imdb_tt' => 'tt9100002',
                'title' => 'Echo Ridge',
            ]),
        ]);

        $recAb = Mockery::mock(RecAb::class);
        $recAb->shouldReceive('forDevice')
            ->once()
            ->with('device-even-2', 5)
            ->andReturn(['B', $movies]);

        $service = new Recommender($recAb);

        $result = $service->recommendForDevice('device-even-2', 5);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertSame(['Signal Lost', 'Echo Ridge'], $result->pluck('title')->all());
    }
}
