<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Request as HttpRequest;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class HttpErrorsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->group(function (): void {
            Route::get('/http-errors/unauthorized', fn () => abort(401));
            Route::get('/http-errors/forbidden', fn () => abort(403));
            Route::get('/http-errors/server-error', fn () => throw new RuntimeException('Unexpected failure.'));
            Route::post('/http-errors/validation', function (HttpRequest $request) {
                $request->validate([
                    'email' => ['required', 'email'],
                ]);

                return response()->noContent();
            });
            Route::post('/http-errors/page-expired', function (): never {
                throw new TokenMismatchException('CSRF token mismatch.');
            });
            Route::get('/http-errors/rate-limit', function () {
                abort(429, 'Slow down please', ['Retry-After' => '60']);
            });
        });
    }

    #[DataProvider('jsonErrorProvider')]
    public function test_json_responses_provide_consistent_shape(string $method, string $uri, int $status, string $errorKey, array $payload = []): void
    {
        Event::fake([MessageLogged::class]);

        $response = match ($method) {
            'postJson' => $this->{$method}($uri, $payload),
            default => $this->{$method}($uri, $payload),
        };

        $response->assertStatus($status)
            ->assertJson([
                'status' => $status,
                'error' => $errorKey,
            ])
            ->assertJsonStructure([
                'status',
                'error',
                'title',
                'headline',
                'message',
                'request_id',
            ]);

        if ($status === 422) {
            $response->assertJsonStructure([
                'errors' => ['email'],
            ]);
        }

        if ($status === 429) {
            $response->assertJsonPath('retry_after', '60');
        }

        if ($status === 500) {
            $this->assertSame('We are looking into the issue. Please try again in a moment.', $response->json('message'));
        }

        $requestId = $response->headers->get('X-Request-ID');
        $this->assertNotEmpty($requestId);
        $this->assertSame($requestId, $response->json('request_id'));

        $expectedLevel = $status >= 500 ? 'error' : 'warning';

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $event) use ($expectedLevel, $requestId, $status) {
            return $event->level === $expectedLevel
                && ($event->context['request_id'] ?? null) === $requestId
                && ($event->context['status'] ?? null) === $status;
        });
    }

    public function test_blade_response_renders_with_request_metadata(): void
    {
        Event::fake([MessageLogged::class]);

        $response = $this->get('/http-errors/server-error');

        $response->assertStatus(500)
            ->assertViewIs('errors.500')
            ->assertSee('Something went wrong')
            ->assertSee('Request ID:');

        $requestId = $response->headers->get('X-Request-ID');
        $this->assertNotEmpty($requestId);

        Event::assertDispatched(MessageLogged::class, function (MessageLogged $event) use ($requestId) {
            return $event->level === 'error'
                && ($event->context['request_id'] ?? null) === $requestId;
        });
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int, 3: string, 4?: array}>
     */
    public static function jsonErrorProvider(): array
    {
        return [
            'unauthorized' => ['getJson', '/http-errors/unauthorized', 401, 'unauthorized'],
            'forbidden' => ['getJson', '/http-errors/forbidden', 403, 'forbidden'],
            'not_found' => ['getJson', '/http-errors/missing', 404, 'not_found'],
            'page_expired' => ['postJson', '/http-errors/page-expired', 419, 'page_expired', []],
            'validation' => ['postJson', '/http-errors/validation', 422, 'validation_error', []],
            'rate_limit' => ['getJson', '/http-errors/rate-limit', 429, 'too_many_requests'],
            'server_error' => ['getJson', '/http-errors/server-error', 500, 'server_error'],
        ];
    }
}
