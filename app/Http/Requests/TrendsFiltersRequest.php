<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\MovieSearchFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrendsFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'days' => ['sometimes', 'nullable', 'integer', 'between:1,30'],
            'type' => ['sometimes', 'nullable', 'string', Rule::in(MovieSearchFilters::ALLOWED_TYPES)],
            'genre' => ['sometimes', 'nullable', 'string', Rule::in(MovieSearchFilters::ALLOWED_GENRES)],
            'yf' => ['sometimes', 'nullable', 'integer', 'between:1870,2100'],
            'yt' => ['sometimes', 'nullable', 'integer', 'between:1870,2100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('days')) {
            $value = $this->input('days');
            $data['days'] = $value === '' ? null : $value;
        }

        foreach (['type', 'genre'] as $key) {
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

        foreach (['yf', 'yt'] as $key) {
            if ($this->has($key)) {
                $value = $this->input($key);
                $data[$key] = $value === '' ? null : $value;
            }
        }

        if ($data !== []) {
            $this->merge($data);
        }
    }

    /**
     * @return array{days: int, type: string, genre: string, year_from: int, year_to: int}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'days' => (int) ($validated['days'] ?? 7),
            'type' => (string) ($validated['type'] ?? ''),
            'genre' => (string) ($validated['genre'] ?? ''),
            'year_from' => (int) ($validated['yf'] ?? 0),
            'year_to' => (int) ($validated['yt'] ?? 0),
        ];
    }
}
