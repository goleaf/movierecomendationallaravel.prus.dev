<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\AnalyticsFilters;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CtrRangeRequest extends FormRequest
{
    private ?CarbonImmutable $from = null;

    private ?CarbonImmutable $to = null;

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
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
            'p' => ['nullable', 'string', Rule::in(AnalyticsFilters::allowedPlacements())],
            'v' => ['nullable', 'string', Rule::in(AnalyticsFilters::allowedVariants())],
        ];
    }

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    public function range(): array
    {
        return [
            'from' => $this->from(),
            'to' => $this->to(),
        ];
    }

    public function from(): CarbonImmutable
    {
        if ($this->from === null) {
            $this->from = CarbonImmutable::parse($this->validated()['from']);
        }

        return $this->from;
    }

    public function to(): CarbonImmutable
    {
        if ($this->to === null) {
            $this->to = CarbonImmutable::parse($this->validated()['to']);
        }

        return $this->to;
    }

    public function placement(): ?string
    {
        $value = $this->validated()['p'] ?? null;

        return $value === '' ? null : $value;
    }

    public function variant(): ?string
    {
        $value = $this->validated()['v'] ?? null;

        return $value === '' ? null : $value;
    }

    protected function prepareForValidation(): void
    {
        $now = now()->toImmutable();
        $range = AnalyticsFilters::normalizeDateRange(
            $this->query('from'),
            $this->query('to'),
            $now->subDays(7),
            $now,
        );

        $this->merge([
            'from' => $range['from'],
            'to' => $range['to'],
            'p' => AnalyticsFilters::normalizePlacement($this->query('p')),
            'v' => AnalyticsFilters::normalizeVariant($this->query('v')),
        ]);
    }
}
