<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TrendsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'type' => ['nullable', 'string'],
            'genre' => ['nullable', 'string'],
            'yf' => ['nullable', 'integer', 'between:1870,2100'],
            'yt' => ['nullable', 'integer', 'between:1870,2100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'days' => $this->normalizeInt($this->query('days')),
            'type' => $this->normalizeString($this->query('type')),
            'genre' => $this->normalizeString($this->query('genre')),
            'yf' => $this->normalizeYear($this->query('yf')),
            'yt' => $this->normalizeYear($this->query('yt')),
        ]);
    }

    public function days(): int
    {
        return $this->integer('days') ?? 7;
    }

    public function type(): string
    {
        return $this->string('type')->value() ?? '';
    }

    public function genre(): string
    {
        return $this->string('genre')->value() ?? '';
    }

    public function yearFrom(): int
    {
        return $this->integer('yf') ?? 0;
    }

    public function yearTo(): int
    {
        return $this->integer('yt') ?? 0;
    }

    private function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function normalizeInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function normalizeYear(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $year = (int) $value;

        if ($year < 1870 || $year > 2100) {
            return null;
        }

        return $year;
    }
}
