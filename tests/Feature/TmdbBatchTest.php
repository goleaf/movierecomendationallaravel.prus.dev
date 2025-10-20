<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\MovieApis\TmdbClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class TmdbBatchTest extends TestCase
{
    public function test_batch_find_by_imdb_ids_uses_configured_client(): void
    {
        $envPath = base_path('.env');
        $createdEnv = false;

        if (! file_exists($envPath)) {
            file_put_contents($envPath, 'APP_KEY=base64:'.base64_encode(str_repeat('a', 32)));
            $createdEnv = true;
        }

        try {
            Http::fake(function (Request $request) {
                if ($request->data()['external_source'] === 'imdb_id' && str_contains($request->url(), 'tt111')) {
                    return Http::response([
                        'movie_results' => [['id' => 111]],
                    ]);
                }

                return Http::response([
                    'movie_results' => [['id' => 222]],
                ]);
            });

            Log::shouldReceive('channel')->times(2)->with('ingestion')->andReturnSelf();
            Log::shouldReceive('info')->times(2);

            config()->set('services.tmdb', [
                'key' => 'abc123',
                'base_url' => 'https://batch.example/',
                'timeout' => 5.0,
                'retry' => ['attempts' => 0, 'delay_ms' => 0],
                'backoff' => ['multiplier' => 1, 'max_delay_ms' => 0],
                'rate_limit' => ['window' => 60, 'allowance' => 60],
                'batch' => [
                    'concurrency' => 1,
                    'retry' => ['attempts' => 0, 'delay_ms' => 0],
                    'headers' => ['X-Batch' => 'tmdb'],
                ],
                'accepted_locales' => ['en-US', 'pt-BR'],
                'default_locale' => 'en-US',
            ]);

            RateLimiter::clear('tmdb:'.md5('abc123'));

            $client = $this->app->make(TmdbClient::class);

            $results = $client->batchFindByImdbIds([
                'alpha' => 'tt111',
                'beta' => 'tt222',
            ]);

            Http::assertSentCount(2);

            $this->assertSame(111, $results['alpha']['movie_results'][0]['id']);
            $this->assertSame(222, $results['beta']['movie_results'][0]['id']);
        } finally {
            if ($createdEnv) {
                @unlink($envPath);
            }
        }
    }
}
