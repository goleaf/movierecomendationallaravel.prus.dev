<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler
{
    private const DOCS_BASE_URL = 'https://docs.prus.dev/api/errors';

    private const ERROR_DEFINITIONS = [
        401 => [
            'code' => 'AUTHENTICATION_REQUIRED',
            'message' => 'Authentication is required to access this resource.',
            'documentation_url' => 'https://docs.prus.dev/api/errors/401',
        ],
        403 => [
            'code' => 'FORBIDDEN',
            'message' => 'You are not authorized to perform this action.',
            'documentation_url' => 'https://docs.prus.dev/api/errors/403',
        ],
        404 => [
            'code' => 'RESOURCE_NOT_FOUND',
            'message' => 'The requested resource could not be found.',
            'documentation_url' => 'https://docs.prus.dev/api/errors/404',
        ],
        429 => [
            'code' => 'TOO_MANY_REQUESTS',
            'message' => 'You have sent too many requests. Please try again later.',
            'documentation_url' => 'https://docs.prus.dev/api/errors/429',
        ],
        500 => [
            'code' => 'INTERNAL_SERVER_ERROR',
            'message' => 'An unexpected error occurred. Please try again later.',
            'documentation_url' => 'https://docs.prus.dev/api/errors/500',
        ],
    ];

    public static function register(Exceptions $exceptions): void
    {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $throwable): bool {
            return self::shouldRenderAsJson($request);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            return self::respond($request, 401);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            return self::respond($request, 403);
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $exception, Request $request) {
            return self::respond($request, 404);
        });

        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            return self::respond($request, 429);
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();

                if (! array_key_exists($status, self::ERROR_DEFINITIONS)) {
                    return null;
                }

                return self::respond($request, $status);
            }

            return self::respond($request, 500);
        });
    }

    public static function formatErrorResponse(Request $request, int $status): array
    {
        $definition = self::ERROR_DEFINITIONS[$status] ?? self::ERROR_DEFINITIONS[500];

        $payload = [
            'error' => [
                'status' => $status,
                'code' => $definition['code'],
                'message' => $definition['message'],
                'documentation_url' => $definition['documentation_url'] ?? self::documentationUrlFor($status),
            ],
        ];

        $requestId = self::requestIdFrom($request);

        if ($requestId !== null) {
            $payload['meta'] = [
                'request_id' => $requestId,
            ];
        }

        return $payload;
    }

    private static function respond(Request $request, int $status)
    {
        if (self::shouldRenderAsJson($request)) {
            return self::jsonErrorResponse($request, $status);
        }

        return self::htmlErrorResponse($request, $status);
    }

    private static function jsonErrorResponse(Request $request, int $status): JsonResponse
    {
        return response()->json(self::formatErrorResponse($request, $status), $status);
    }

    private static function htmlErrorResponse(Request $request, int $status)
    {
        $payload = self::formatErrorResponse($request, $status);

        return response()->view('errors.generic', [
            'status' => $payload['error']['status'],
            'code' => $payload['error']['code'],
            'message' => $payload['error']['message'],
            'documentationUrl' => $payload['error']['documentation_url'],
            'requestId' => $payload['meta']['request_id'] ?? null,
        ], $status);
    }

    private static function documentationUrlFor(int $status): string
    {
        return rtrim(self::DOCS_BASE_URL, '/').'/'.$status;
    }

    private static function shouldRenderAsJson(Request $request): bool
    {
        return $request->expectsJson() || $request->is('api/*');
    }

    private static function requestIdFrom(Request $request): ?string
    {
        $requestId = $request->attributes->get('request_id', $request->headers->get('X-Request-ID'));

        if (is_string($requestId) && $requestId !== '') {
            return $requestId;
        }

        return null;
    }
}
