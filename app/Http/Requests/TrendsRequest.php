<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\AnalyticsFilters;
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

            $type = $validated['type'] ?? null;
            $genre = $validated['genre'] ?? null;
            $yearFrom = $validated['yf'] ?? null;
            $yearTo = $validated['yt'] ?? null;

            $this->filters = [
                'days' => (int) $validated['days'],
                'type' => is_string($type) ? $type : '',
                'genre' => is_string($genre) ? $genre : '',
                'yf' => is_int($yearFrom) ? $yearFrom : 0,
                'yt' => is_int($yearTo) ? $yearTo : 0,
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
        $yearRange = AnalyticsFilters::normalizeYearRange($this->query('yf'), $this->query('yt'));

        $this->merge([
            'days' => AnalyticsFilters::clampDays($this->query('days')),
            'type' => AnalyticsFilters::normalizeNullableString($this->query('type')),
            'genre' => AnalyticsFilters::normalizeNullableString($this->query('genre')),
            'yf' => $yearRange['from'],
            'yt' => $yearRange['to'],
        ]);
    }
}
