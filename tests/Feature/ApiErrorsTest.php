<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiErrorsTest extends TestCase
{
    public function test_unauthenticated_requests_receive_standard_response(): void
    {
        Route::middleware('api')->get('/api/testing/auth-error', function (): void {
            throw new AuthenticationException;
        });

        $response = $this->getJson('/api/testing/auth-error');

        $response->assertStatus(401)->assertExactJson([
            'error' => [
                'status' => 401,
                'code' => 'AUTHENTICATION_REQUIRED',
                'message' => 'Authentication is required to access this resource.',
                'documentation_url' => 'https://docs.prus.dev/api/errors/401',
            ],
        ]);
    }

    public function test_forbidden_requests_receive_standard_response(): void
    {
        Route::middleware('api')->get('/api/testing/forbidden-error', function (): void {
            throw new AuthorizationException;
        });

        $response = $this->getJson('/api/testing/forbidden-error');

        $response->assertStatus(403)->assertExactJson([
            'error' => [
                'status' => 403,
                'code' => 'FORBIDDEN',
                'message' => 'You are not authorized to perform this action.',
                'documentation_url' => 'https://docs.prus.dev/api/errors/403',
            ],
        ]);
    }

    public function test_not_found_requests_receive_standard_response(): void
    {
        $response = $this->getJson('/api/testing/not-found');

        $response->assertStatus(404)->assertExactJson([
            'error' => [
                'status' => 404,
                'code' => 'RESOURCE_NOT_FOUND',
                'message' => 'The requested resource could not be found.',
                'documentation_url' => 'https://docs.prus.dev/api/errors/404',
            ],
        ]);
    }

    public function test_throttled_requests_receive_standard_response(): void
    {
        Route::middleware('api')->get('/api/testing/throttle-error', function (): void {
            throw new ThrottleRequestsException('Too many attempts.');
        });

        $response = $this->getJson('/api/testing/throttle-error');

        $response->assertStatus(429)->assertExactJson([
            'error' => [
                'status' => 429,
                'code' => 'TOO_MANY_REQUESTS',
                'message' => 'You have sent too many requests. Please try again later.',
                'documentation_url' => 'https://docs.prus.dev/api/errors/429',
            ],
        ]);
    }

    public function test_unexpected_errors_receive_standard_response(): void
    {
        Route::middleware('api')->get('/api/testing/server-error', function (): void {
            throw new \RuntimeException('Boom');
        });

        $response = $this->getJson('/api/testing/server-error');

        $response->assertStatus(500)->assertExactJson([
            'error' => [
                'status' => 500,
                'code' => 'INTERNAL_SERVER_ERROR',
                'message' => 'An unexpected error occurred. Please try again later.',
                'documentation_url' => 'https://docs.prus.dev/api/errors/500',
            ],
        ]);
    }
}
