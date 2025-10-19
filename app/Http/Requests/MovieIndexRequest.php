<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\MovieIndexFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MovieIndexRequest extends FormRequest
{
    private ?MovieIndexFilters $filters = null;

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
            'q' => ['nullable', 'string'],
            'genres' => ['array'],
            'genres.*' => ['string'],
            'year_from' => ['nullable', 'integer', 'between:1870,2100'],
            'year_to' => ['nullable', 'integer', 'between:1870,2100'],
            'sort' => ['required', 'string', Rule::in(MovieIndexFilters::ALLOWED_SORTS)],
            'page' => ['required', 'integer', 'min:1'],
        ];
    }

    public function filters(): MovieIndexFilters
    {
        if ($this->filters === null) {
            $this->filters = MovieIndexFilters::fromArray($this->validated());
        }

        return $this->filters;
    }

    public function search(): ?string
    {
        return $this->filters()->query;
    }

    /**
     * @return array<int, string>
     */
    public function genres(): array
    {
        return $this->filters()->genres;
    }

    public function yearFrom(): ?int
    {
        return $this->filters()->yearFrom;
    }

    public function yearTo(): ?int
    {
        return $this->filters()->yearTo;
    }

    public function sort(): string
    {
        return $this->filters()->sort;
    }

    public function page(): int
    {
        return $this->filters()->page;
    }

    protected function prepareForValidation(): void
    {
        $filters = MovieIndexFilters::fromArray([
            'q' => $this->query('q'),
            'genres' => $this->query('genres', []),
            'year_from' => $this->query('year_from'),
            'year_to' => $this->query('year_to'),
            'sort' => $this->query('sort'),
            'page' => $this->query('page'),
        ]);

        $this->merge($filters->toRequestPayload());
        $this->filters = $filters;
    }
}
