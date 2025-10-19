<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MovieIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'genres' => ['sometimes', 'array', 'min:1'],
            'genres.*' => ['filled', 'string', 'min:2', 'max:30'],
            'countries' => ['sometimes', 'array', 'min:1'],
            'countries.*' => ['filled', 'string', 'size:2', 'alpha'],
            'filters' => ['sometimes', 'array'],
            'filters.runtime' => ['sometimes', 'array'],
            'filters.runtime.min' => [
                'sometimes',
                'integer',
                'min:0',
                Rule::when($this->filled('filters.runtime.max'), 'lte:filters.runtime.max'),
            ],
            'filters.runtime.max' => [
                'sometimes',
                'integer',
                'min:0',
                Rule::when($this->filled('filters.runtime.min'), 'gte:filters.runtime.min'),
            ],
            'filters.release' => ['sometimes', 'array'],
            'filters.release.from' => [
                'sometimes',
                'date',
                Rule::when($this->filled('filters.release.to'), 'before_or_equal:filters.release.to'),
            ],
            'filters.release.to' => [
                'sometimes',
                'date',
                Rule::when($this->filled('filters.release.from'), 'after_or_equal:filters.release.from'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'genres.array' => 'Genres must be provided as an array.',
            'genres.min' => 'Select at least one genre.',
            'genres.*.filled' => 'Each genre must be at least 2 characters.',
            'genres.*.string' => 'Each genre must be a valid string.',
            'genres.*.min' => 'Each genre must be at least 2 characters.',
            'genres.*.max' => 'Genres may not exceed 30 characters.',
            'countries.array' => 'Countries must be provided as an array of ISO codes.',
            'countries.min' => 'Select at least one country.',
            'countries.*.filled' => 'Country codes must be exactly 2 letters (ISO 3166-1 alpha-2).',
            'countries.*.string' => 'Each country code must be a string.',
            'countries.*.size' => 'Country codes must be exactly 2 letters (ISO 3166-1 alpha-2).',
            'countries.*.alpha' => 'Country codes may only contain letters.',
            'filters.array' => 'Filters must be provided as an object.',
            'filters.runtime.array' => 'Runtime filters must be provided as an array.',
            'filters.runtime.min.integer' => 'Runtime minimum must be a whole number of minutes.',
            'filters.runtime.min.min' => 'Runtime minimum must be zero or greater.',
            'filters.runtime.min.lte' => 'Runtime minimum must be less than or equal to the maximum.',
            'filters.runtime.max.integer' => 'Runtime maximum must be a whole number of minutes.',
            'filters.runtime.max.min' => 'Runtime maximum must be zero or greater.',
            'filters.runtime.max.gte' => 'Runtime maximum must be greater than or equal to the minimum.',
            'filters.release.array' => 'Release filters must be provided as an array.',
            'filters.release.from.date' => 'Release start date must be a valid date.',
            'filters.release.from.before_or_equal' => 'Release start date must be before or equal to the end date.',
            'filters.release.to.date' => 'Release end date must be a valid date.',
            'filters.release.to.after_or_equal' => 'Release end date must be on or after the start date.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $genres = $this->input('genres');
        $countries = $this->input('countries');

        if (is_array($genres)) {
            $normalizedGenres = array_values(array_map(
                static function ($genre) {
                    if (is_string($genre)) {
                        return trim($genre);
                    }

                    return $genre;
                },
                $genres
            ));

            $this->merge(['genres' => $normalizedGenres]);
        }

        if (is_array($countries)) {
            $normalizedCountries = array_values(array_map(
                static function ($country) {
                    if (is_string($country)) {
                        return strtoupper(trim($country));
                    }

                    return $country;
                },
                $countries
            ));

            $this->merge(['countries' => $normalizedCountries]);
        }
    }
}
