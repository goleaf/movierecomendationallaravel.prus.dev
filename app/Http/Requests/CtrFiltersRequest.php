<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\FilterOptions;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

class CtrFiltersRequest extends DateRangeRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'p' => ['sometimes', 'nullable', 'string', 'max:32', Rule::in(FilterOptions::placements())],
            'v' => ['sometimes', 'nullable', 'string', 'max:32', Rule::in(FilterOptions::variants())],
        ]);
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['p', 'v'] as $key) {
            if (! $this->has($key)) {
                continue;
            }

            $value = $this->input($key);

            if (is_string($value)) {
                $value = trim($value);

                if ($value === '') {
                    $data[$key] = null;

                    continue;
                }
            }

            $data[$key] = $value;
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    public function placement(): ?string
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return $validated['p'] ?? null;
    }

    public function variant(): ?string
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return $validated['v'] ?? null;
    }

    protected function defaultFrom(): CarbonImmutable
    {
        return now()->subDays(7)->toImmutable();
    }

    protected function defaultTo(): CarbonImmutable
    {
        return now()->toImmutable();
    }
}
