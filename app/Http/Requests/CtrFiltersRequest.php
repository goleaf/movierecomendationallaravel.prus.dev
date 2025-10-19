<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class CtrFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
            'p' => ['nullable', 'string'],
            'v' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array{0:CarbonImmutable,1:CarbonImmutable}
     */
    public function dateRange(): array
    {
        $validated = $this->validated();

        return [
            CarbonImmutable::parse($validated['from']),
            CarbonImmutable::parse($validated['to']),
        ];
    }

    public function placement(): ?string
    {
        return $this->validated()['p'] ?? null;
    }

    public function variant(): ?string
    {
        return $this->validated()['v'] ?? null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'from' => $this->prepareDate($this->query('from'), now()->subDays(7)),
            'to' => $this->prepareDate($this->query('to'), now()),
            'p' => $this->prepareNullableString($this->query('p')),
            'v' => $this->prepareNullableString($this->query('v')),
        ]);
    }

    private function prepareDate(mixed $value, CarbonImmutable $fallback): string
    {
        if (is_string($value) && $value !== '') {
            try {
                return CarbonImmutable::parse($value)->format('Y-m-d');
            } catch (\Throwable) {
                // Ignore and fall back
            }
        }

        return $fallback->format('Y-m-d');
    }

    private function prepareNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
