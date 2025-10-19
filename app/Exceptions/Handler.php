<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler
{
    /**
     * Render a centralized HTTP error response.
     */
    public static function render(Request $request, Throwable $throwable): Response|JsonResponse|null
    {
        $status = self::resolveStatusCode($throwable);

        if (! in_array($status, [401, 403, 404, 419, 422, 429, 500], true)) {
            return null;
        }

        $requestId = self::resolveRequestId($request);
        $metadata = self::resolveMetadata($status, $throwable);

        $payload = array_merge($metadata, [
            'status' => $status,
            'request_id' => $requestId,
        ]);

        if ($throwable instanceof ValidationException) {
            $payload['errors'] = $throwable->errors();
        }

        if ($retryAfter = self::resolveRetryAfter($throwable)) {
            $payload['retry_after'] = $retryAfter;
        }

        self::logException($request, $throwable, $status, $payload);

        if ($request->expectsJson()) {
            return response()->json($payload, $status);
        }

        $view = self::resolveView($status);

        return response()->view($view, [
            'status' => $status,
            'title' => $payload['title'],
            'headline' => $payload['headline'],
            'message' => $payload['message'],
            'error' => $payload['error'],
            'requestId' => $requestId,
            'errors' => $payload['errors'] ?? [],
            'retryAfter' => $payload['retry_after'] ?? null,
        ], $status);
    }

    protected static function resolveStatusCode(Throwable $throwable): int
    {
        return match (true) {
            $throwable instanceof ValidationException => $throwable->status,
            $throwable instanceof AuthenticationException => 401,
            $throwable instanceof AuthorizationException => 403,
            $throwable instanceof TokenMismatchException => 419,
            $throwable instanceof HttpExceptionInterface => $throwable->getStatusCode(),
            default => 500,
        };
    }

    /**
     * @return array{error: string, title: string, headline: string, message: string}
     */
    protected static function resolveMetadata(int $status, Throwable $throwable): array
    {
        return match ($status) {
            401 => self::formatMetadata('unauthorized', __('Unauthorized'), __('Authentication required'), self::resolveMessage($throwable, __('You need to sign in to continue.'))),
            403 => self::formatMetadata('forbidden', __('Forbidden'), __('You do not have access'), self::resolveMessage($throwable, __('You are not allowed to perform this action.'))),
            404 => self::formatMetadata('not_found', __('Not Found'), __('Page not found'), self::resolveMessage($throwable, __('The requested resource could not be located.'))),
            419 => self::formatMetadata('page_expired', __('Page Expired'), __('Your session expired'), self::resolveMessage($throwable, __('Please refresh the page and try again.'))),
            422 => self::formatMetadata('validation_error', __('Unprocessable Content'), __('Validation failed'), self::resolveMessage($throwable, __('Please review the highlighted fields and try again.'))),
            429 => self::formatMetadata('too_many_requests', __('Too Many Requests'), __('Slow down'), self::resolveMessage($throwable, __('You have sent too many requests. Please wait before retrying.'))),
            default => self::formatMetadata('server_error', __('Server Error'), __('Something went wrong'), __('We are looking into the issue. Please try again in a moment.')),
        };
    }

    /**
     * @return array{error: string, title: string, headline: string, message: string}
     */
    protected static function formatMetadata(string $error, string $title, string $headline, string $message): array
    {
        return compact('error', 'title', 'headline', 'message');
    }

    protected static function resolveMessage(Throwable $throwable, string $default): string
    {
        $message = trim($throwable->getMessage());

        return $message === '' ? $default : $message;
    }

    protected static function resolveRetryAfter(Throwable $throwable): ?string
    {
        if (! $throwable instanceof HttpExceptionInterface) {
            return null;
        }

        $headers = $throwable->getHeaders();

        return $headers['Retry-After'] ?? $headers['retry-after'] ?? null;
    }

    protected static function resolveView(int $status): string
    {
        $view = "errors.$status";

        if (view()->exists($view)) {
            return $view;
        }

        return 'errors.error';
    }

    protected static function resolveRequestId(Request $request): ?string
    {
        $attribute = $request->attributes->get('request_id');
        if (is_string($attribute) && $attribute !== '') {
            return $attribute;
        }

        $header = $request->headers->get('X-Request-ID', $request->headers->get('X-Request-Id'));
        if (is_string($header) && $header !== '') {
            return $header;
        }

        return null;
    }

    /**
     * @param  array{status: int, request_id: string|null, error: string, title: string, headline: string, message: string, errors?: array<string, array<int, string>>, retry_after?: string}  $payload
     */
    protected static function logException(Request $request, Throwable $throwable, int $status, array $payload): void
    {
        $level = $status >= 500 ? 'error' : 'warning';
        $message = sprintf('HTTP %s request to %s resulted in status %d', $request->method(), $request->fullUrl(), $status);

        $context = array_filter([
            'request_id' => $payload['request_id'] ?? null,
            'status' => $status,
            'method' => $request->method(),
            'path' => $request->path(),
        ], static fn ($value) => $value !== null && $value !== '');

        Log::{$level}($message, $context + ['exception' => $throwable]);
    }
}
