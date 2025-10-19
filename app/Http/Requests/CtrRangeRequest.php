<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\AnalyticsFilters;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'p' => ['nullable', 'string'],
            'v' => ['nullable', 'string'],
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
        [$from, $to] = AnalyticsFilters::parseDateRange(
            $this->query('from'),
            $this->query('to'),
            CarbonImmutable::now()->subDays(7),
            CarbonImmutable::now(),
        );

        $this->merge([
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'p' => $this->prepareNullableString($this->query('p')),
            'v' => $this->prepareNullableString($this->query('v')),
        ]);
    }

    private function prepareNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
