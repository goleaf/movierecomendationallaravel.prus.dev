<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class HttpErrorsTest extends TestCase
{
    private const SUPPORTED_STATUS_CODES = [401, 403, 404, 429, 500];

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->group(function (): void {
            Route::get('/testing/errors/{status}', function (int $status) {
                if ($status === 500) {
                    throw new RuntimeException('Synthetic failure');
                }

                abort($status);
            })->whereIn('status', self::SUPPORTED_STATUS_CODES);
        });

        Route::middleware('api')->group(function (): void {
            Route::get('/testing/api/errors/{status}', function (int $status) {
                if ($status === 500) {
                    throw new RuntimeException('Synthetic failure');
                }

                abort($status);
            })->whereIn('status', self::SUPPORTED_STATUS_CODES);
        });
    }

    #[DataProvider('statusProvider')]
    public function test_json_errors_include_request_metadata(int $status): void
    {
        $response = $this->withHeaders(['Accept' => 'application/json'])->get("/testing/api/errors/{$status}");

        $response->assertStatus($status);
        $response->assertHeader('X-Request-ID');

        $payload = $response->json();

        $response->assertExactJson([
            'error' => [
                'status' => $status,
                'code' => $this->expectedCode($status),
                'message' => $this->expectedMessage($status),
                'documentation_url' => $this->expectedDocumentationUrl($status),
            ],
            'meta' => [
                'request_id' => $response->headers->get('X-Request-ID'),
            ],
        ]);

        $this->assertIsString($payload['meta']['request_id']);
        $this->assertNotEmpty($payload['meta']['request_id']);
    }

    #[DataProvider('statusProvider')]
    public function test_blade_errors_render_shared_template(int $status): void
    {
        $response = $this->get("/testing/errors/{$status}");

        $response->assertStatus($status);
        $response->assertHeader('X-Request-ID');

        $response->assertSee((string) $status);
        $response->assertSee($this->expectedCode($status));
        $response->assertSee($this->expectedMessage($status));
        $response->assertSee($this->expectedDocumentationUrl($status));
        $response->assertSee($response->headers->get('X-Request-ID'));
    }

    /**
     * @return array<int, array<int>>
     */
    public static function statusProvider(): array
    {
        return array_map(static fn (int $status) => [$status], self::SUPPORTED_STATUS_CODES);
    }

    private function expectedCode(int $status): string
    {
        return match ($status) {
            401 => 'AUTHENTICATION_REQUIRED',
            403 => 'FORBIDDEN',
            404 => 'RESOURCE_NOT_FOUND',
            429 => 'TOO_MANY_REQUESTS',
            default => 'INTERNAL_SERVER_ERROR',
        };
    }

    private function expectedMessage(int $status): string
    {
        return match ($status) {
            401 => 'Authentication is required to access this resource.',
            403 => 'You are not authorized to perform this action.',
            404 => 'The requested resource could not be found.',
            429 => 'You have sent too many requests. Please try again later.',
            default => 'An unexpected error occurred. Please try again later.',
        };
    }

    private function expectedDocumentationUrl(int $status): string
    {
        return sprintf('https://docs.prus.dev/api/errors/%d', $status);
    }
}
