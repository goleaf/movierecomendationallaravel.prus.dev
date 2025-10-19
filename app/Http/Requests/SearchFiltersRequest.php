<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\MovieSearchFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'string', 'max:200'],
            'type' => ['sometimes', 'nullable', 'string', Rule::in(MovieSearchFilters::ALLOWED_TYPES)],
            'genre' => ['sometimes', 'nullable', 'string', Rule::in(MovieSearchFilters::ALLOWED_GENRES)],
            'yf' => ['sometimes', 'nullable', 'integer', 'between:1870,2100'],
            'yt' => ['sometimes', 'nullable', 'integer', 'between:1870,2100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['q', 'type', 'genre'] as $key) {
            if (! $this->has($key)) {
                continue;
            }

            $value = $this->input($key);

            if (is_string($value)) {
                $value = trim($value);

                if ($value === '' && $key !== 'q') {
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

    public function filters(): MovieSearchFilters
    {
        $validated = $this->validated();

        return MovieSearchFilters::fromArray([
            'q' => $validated['q'] ?? '',
            'type' => $validated['type'] ?? null,
            'genre' => $validated['genre'] ?? null,
            'yf' => $validated['yf'] ?? null,
            'yt' => $validated['yt'] ?? null,
        ]);
    }
}
