<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class CtrRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'p' => ['nullable', 'string'],
            'v' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'from' => $this->normalizeDate($this->query('from')),
            'to' => $this->normalizeDate($this->query('to')),
            'p' => $this->normalizeString($this->query('p')),
            'v' => $this->normalizeString($this->query('v')),
        ]);
    }

    public function fromDate(CarbonImmutable $fallback): CarbonImmutable
    {
        return $this->parseDate($this->input('from'), $fallback);
    }

    public function toDate(CarbonImmutable $fallback): CarbonImmutable
    {
        return $this->parseDate($this->input('to'), $fallback);
    }

    public function placement(): ?string
    {
        return $this->string('p')->value();
    }

    public function variant(): ?string
    {
        return $this->string('v')->value();
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function parseDate(?string $value, CarbonImmutable $fallback): CarbonImmutable
    {
        if ($value === null) {
            return $fallback;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
