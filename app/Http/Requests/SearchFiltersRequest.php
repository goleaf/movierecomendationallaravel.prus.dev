<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\MovieSearchFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SearchFiltersRequest extends FormRequest
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
            'q' => ['required', 'string'],
            'type' => ['nullable', 'string', Rule::in(MovieSearchFilters::ALLOWED_TYPES)],
            'genre' => ['nullable', 'string'],
            'yf' => ['nullable', 'integer', 'between:1870,2100'],
            'yt' => ['nullable', 'integer', 'between:1870,2100'],
            'per' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function filters(): MovieSearchFilters
    {
        $validated = $this->validated();

        return MovieSearchFilters::fromArray([
            'q' => $validated['q'],
            'type' => $validated['type'] ?? null,
            'genre' => $validated['genre'] ?? null,
            'yf' => $validated['yf'] ?? null,
            'yt' => $validated['yt'] ?? null,
        ]);
    }

    public function perPage(): int
    {
        return (int) ($this->validated()['per'] ?? 20);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'q' => $this->prepareQuery($this->query('q')),
            'type' => $this->prepareNullableString($this->query('type')),
            'genre' => $this->prepareNullableString($this->query('genre')),
            'yf' => $this->prepareYear($this->query('yf')),
            'yt' => $this->prepareYear($this->query('yt')),
            'per' => $this->preparePer($this->query('per')),
        ]);
    }

    private function prepareQuery(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function prepareNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function prepareYear(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $year = (int) $value;

        if ($year < 1870 || $year > 2100) {
            return null;
        }

        return $year;
    }

    private function preparePer(mixed $value): int
    {
        $per = (int) ($value ?? 20);

        if ($per < 1) {
            return 1;
        }

        if ($per > 50) {
            return 50;
        }

        return $per;
    }
}
