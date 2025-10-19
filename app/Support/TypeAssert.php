<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

function assert_string(mixed $value, ?string $message = null): string
{
    if (! is_string($value)) {
        throw new InvalidArgumentException($message ?? sprintf('Expected string, %s given.', get_debug_type($value)));
    }

    return $value;
}

function assert_non_empty_string(mixed $value, ?string $message = null): string
{
    $string = assert_string($value, $message);

    if ($string === '') {
        throw new InvalidArgumentException($message ?? 'Expected non-empty string.');
    }

    return $string;
}

function assert_array(mixed $value, ?string $message = null): array
{
    if (! is_array($value)) {
        throw new InvalidArgumentException($message ?? sprintf('Expected array, %s given.', get_debug_type($value)));
    }

    return $value;
}

/**
 * @template T of object
 *
 * @param mixed $value
 * @param class-string<T> $className
 * @param string|null $message
 * @return T
 */
function assert_instanceof(mixed $value, string $className, ?string $message = null): object
{
    if (! $value instanceof $className) {
        throw new InvalidArgumentException($message ?? sprintf('Expected instance of %s, %s given.', $className, get_debug_type($value)));
    }

    return $value;
}
