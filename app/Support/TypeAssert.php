<?php

namespace App\Support;

use InvalidArgumentException;

function assert_string_non_empty(mixed $value): string
{
    if (! is_string($value) || $value === '') {
        throw new InvalidArgumentException('Expected non-empty string.');
    }

    return $value;
}

/**
 * @template T of object
 *
 * @param  class-string<T>  $class
 * @return T
 */
function assert_instanceof(mixed $value, string $class)
{
    if (! $value instanceof $class) {
        throw new InvalidArgumentException('Expected instance of '.$class.'.');
    }

    return $value;
}

/**
 * @return array<array-key, mixed>
 */
function assert_array(mixed $value): array
{
    if (! is_array($value)) {
        throw new InvalidArgumentException('Expected array.');
    }

    return $value;
}
