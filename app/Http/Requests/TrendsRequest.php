<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class TrendsRequest extends FormRequest
{
    /**
     * @var array{days:int,type:string,genre:string,yf:int,yt:int}|null
     */
    private ?array $filters = null;

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
            'days' => ['required', 'integer', 'min:1', 'max:30'],
            'type' => ['nullable', 'string'],
            'genre' => ['nullable', 'string'],
            'yf' => ['nullable', 'integer', 'between:1870,2100'],
            'yt' => ['nullable', 'integer', 'between:1870,2100'],
        ];
    }

    /**
     * @return array{days:int,type:string,genre:string,yf:int,yt:int}
     */
    public function filters(): array
    {
        if ($this->filters === null) {
            $validated = $this->validated();

            $yearFrom = $validated['yf'] ?? null;
            $yearTo = $validated['yt'] ?? null;

            if ($yearFrom !== null && $yearTo !== null && $yearFrom > $yearTo) {
                [$yearFrom, $yearTo] = [$yearTo, $yearFrom];
            }

            $this->filters = [
                'days' => (int) $validated['days'],
                'type' => $validated['type'] ?? '',
                'genre' => $validated['genre'] ?? '',
                'yf' => $yearFrom ?? 0,
                'yt' => $yearTo ?? 0,
            ];
        }

        return $this->filters;
    }

    public function days(): int
    {
        return $this->filters()['days'];
    }

    public function type(): string
    {
        return $this->filters()['type'];
    }

    public function genre(): string
    {
        return $this->filters()['genre'];
    }

    public function yearFrom(): int
    {
        return $this->filters()['yf'];
    }

    public function yearTo(): int
    {
        return $this->filters()['yt'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'days' => $this->prepareDays($this->query('days')),
            'type' => $this->prepareNullableString($this->query('type')),
            'genre' => $this->prepareNullableString($this->query('genre')),
            'yf' => $this->prepareYear($this->query('yf')),
            'yt' => $this->prepareYear($this->query('yt')),
        ]);
    }

    private function prepareDays(mixed $value): int
    {
        $days = (int) ($value ?? 7);

        if ($days < 1) {
            return 1;
        }

        if ($days > 30) {
            return 30;
        }

        return $days;
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
}
