<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\MovieSearchFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SearchRequest extends FormRequest
{
    private ?MovieSearchFilters $filters = null;

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
            'q' => ['nullable', 'required_without_all:type,genre,yf,yt', 'string'],
            'type' => ['nullable', 'string', Rule::in(MovieSearchFilters::ALLOWED_TYPES)],
            'genre' => ['nullable', 'string'],
            'yf' => ['nullable', 'integer', 'between:1870,2100'],
            'yt' => ['nullable', 'integer', 'between:1870,2100'],
            'per' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function filters(): MovieSearchFilters
    {
        if ($this->filters === null) {
            $validated = $this->validated();

            $this->filters = MovieSearchFilters::fromArray([
                'q' => $validated['q'] ?? null,
                'type' => $validated['type'] ?? null,
                'genre' => $validated['genre'] ?? null,
                'yf' => $validated['yf'] ?? null,
                'yt' => $validated['yt'] ?? null,
            ]);
        }

        return $this->filters;
    }

    public function searchTerm(): string
    {
        return $this->filters()->query;
    }

    public function type(): ?string
    {
        return $this->filters()->type;
    }

    public function genre(): ?string
    {
        return $this->filters()->genre;
    }

    public function yearFrom(): ?int
    {
        return $this->filters()->yearFrom;
    }

    public function yearTo(): ?int
    {
        return $this->filters()->yearTo;
    }

    public function perPage(): int
    {
        return (int) ($this->validated()['per'] ?? 20);
    }

    protected function prepareForValidation(): void
    {
        $filters = MovieSearchFilters::fromArray([
            'q' => $this->query('q'),
            'type' => $this->query('type'),
            'genre' => $this->query('genre'),
            'yf' => $this->query('yf'),
            'yt' => $this->query('yt'),
        ]);

        $this->merge([
            'q' => $filters->query === '' ? null : $filters->query,
            'type' => $filters->type,
            'genre' => $filters->genre,
            'yf' => $filters->yearFrom,
            'yt' => $filters->yearTo,
            'per' => $this->preparePer($this->query('per')),
        ]);
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
