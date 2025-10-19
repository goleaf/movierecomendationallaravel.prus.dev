<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

abstract class DateRangeRequest extends FormRequest
{
    private ?array $resolvedRange = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'string', 'date_format:Y-m-d'],
            'to' => ['nullable', 'string', 'date_format:Y-m-d'],
        ];
    }

    public function fromDate(): CarbonImmutable
    {
        return $this->dateRange()[0];
    }

    public function toDate(): CarbonImmutable
    {
        return $this->dateRange()[1];
    }

    abstract protected function defaultFrom(): CarbonImmutable;

    abstract protected function defaultTo(): CarbonImmutable;

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function dateRange(): array
    {
        if ($this->resolvedRange === null) {
            $validated = $this->validated();

            $from = $this->parseDate($validated['from'] ?? null, $this->defaultFrom());
            $to = $this->parseDate($validated['to'] ?? null, $this->defaultTo());

            if ($from->greaterThan($to)) {
                [$from, $to] = [$to, $from];
            }

            $this->resolvedRange = [$from, $to];
        }

        return $this->resolvedRange;
    }

    private function parseDate(mixed $value, CarbonImmutable $fallback): CarbonImmutable
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        if (is_string($value)) {
            $parsed = CarbonImmutable::createFromFormat('Y-m-d', $value);

            if ($parsed !== false) {
                return $parsed;
            }
        }

        return $fallback;
    }
}
