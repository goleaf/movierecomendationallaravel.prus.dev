<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchRequest extends FormRequest
{
    private const ALLOWED_TYPES = ['movie', 'series', 'animation'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string'],
            'type' => ['nullable', Rule::in(self::ALLOWED_TYPES)],
            'genre' => ['nullable', 'string'],
            'yf' => ['nullable', 'integer', 'between:1870,2100'],
            'yt' => ['nullable', 'integer', 'between:1870,2100'],
            'per' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'q' => $this->normalizeString($this->query('q')),
            'type' => $this->normalizeType($this->query('type')),
            'genre' => $this->normalizeString($this->query('genre')),
            'yf' => $this->normalizeYear($this->query('yf')),
            'yt' => $this->normalizeYear($this->query('yt')),
            'per' => $this->normalizePerPage($this->query('per')),
        ]);
    }

    public function perPage(): int
    {
        return $this->integer('per') ?? 20;
    }

    private function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function normalizeType(mixed $value): ?string
    {
        $type = $this->normalizeString($value);

        if ($type === null) {
            return null;
        }

        return in_array($type, self::ALLOWED_TYPES, true) ? $type : null;
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

    private function normalizePerPage(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $perPage = (int) $value;

        if ($perPage < 1) {
            return 1;
        }

        if ($perPage > 50) {
            return 50;
        }

        return $perPage;
    }
}
