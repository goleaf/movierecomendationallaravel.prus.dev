<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class CtrRangeRequest extends FormRequest
{
    private ?CarbonImmutable $from = null;

    private ?CarbonImmutable $to = null;

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
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    public function range(): array
    {
        return [
            'from' => $this->from(),
            'to' => $this->to(),
        ];
    }

    public function from(): CarbonImmutable
    {
        if ($this->from === null) {
            $this->from = CarbonImmutable::parse($this->validated()['from']);
        }

        return $this->from;
    }

    public function to(): CarbonImmutable
    {
        if ($this->to === null) {
            $this->to = CarbonImmutable::parse($this->validated()['to']);
        }

        return $this->to;
    }

    public function placement(): ?string
    {
        $value = $this->validated()['p'] ?? null;

        return $value === '' ? null : $value;
    }

    public function variant(): ?string
    {
        $value = $this->validated()['v'] ?? null;

        return $value === '' ? null : $value;
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
