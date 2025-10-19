<?php

declare(strict_types=1);

namespace App\Casts;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

use function sprintf;

/**
 * @implements CastsAttributes<CarbonImmutable, CarbonImmutable|null>
 */
final class ReleaseDateCast implements CastsAttributes
{
    public bool $withoutObjectCaching = true;

    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        if (! is_string($value)) {
            throw ValidationException::withMessages([
                $key => sprintf('The %s field must be a valid date.', $key),
            ]);
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (InvalidFormatException) {
            throw ValidationException::withMessages([
                $key => sprintf('The %s field must be a valid date.', $key),
            ]);
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value->toDateString();
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->toDateString();
        }

        if (! is_string($value)) {
            throw ValidationException::withMessages([
                $key => sprintf('The %s field must be a valid date.', $key),
            ]);
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (InvalidFormatException) {
            throw ValidationException::withMessages([
                $key => sprintf('The %s field must be a valid date.', $key),
            ]);
        }
    }
}
