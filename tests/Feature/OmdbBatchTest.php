<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\MovieApis\OmdbClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class OmdbBatchTest extends TestCase
{
    public function test_batch_find_by_imdb_ids_respects_defaults(): void
    {
        $envPath = base_path('.env');
        $createdEnv = false;

        if (! file_exists($envPath)) {
            file_put_contents($envPath, 'APP_KEY=base64:'.base64_encode(str_repeat('b', 32)));
            $createdEnv = true;
        }

        try {
            Http::fake(function (Request $request) {
                if ($request->data()['i'] === 'tt111') {
                    return Http::response(['Response' => 'True', 'Title' => 'One']);
                }

                return Http::response(['Response' => 'True', 'Title' => 'Two']);
            });

            Log::shouldReceive('channel')->times(2)->with('ingestion')->andReturnSelf();
            Log::shouldReceive('info')->times(2);

            config()->set('services.omdb', [
                'key' => 'test-key',
                'base_url' => 'https://omdb.batch/',
                'timeout' => 5.0,
                'retry' => ['attempts' => 0, 'delay_ms' => 0],
                'backoff' => ['multiplier' => 1, 'max_delay_ms' => 0],
                'rate_limit' => ['window' => 60, 'allowance' => 60],
                'default_params' => ['r' => 'json'],
                'batch' => [
                    'concurrency' => 2,
                    'retry' => ['attempts' => 0, 'delay_ms' => 0],
                    'headers' => ['X-Batch' => 'omdb'],
                ],
            ]);

            RateLimiter::clear('omdb:'.md5('test-key'));

            $client = $this->app->make(OmdbClient::class);

            $results = $client->batchFindByImdbIds([
                'first' => 'tt111',
                'second' => 'tt222',
            ]);

            Http::assertSentCount(2);

            $this->assertSame('One', $results['first']['Title']);
            $this->assertSame('Two', $results['second']['Title']);
        } finally {
            if ($createdEnv) {
                @unlink($envPath);
            }
        }
    }
}
