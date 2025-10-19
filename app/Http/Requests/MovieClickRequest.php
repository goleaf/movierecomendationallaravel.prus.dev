<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\FilterOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MovieClickRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'placement' => ['sometimes', 'nullable', 'string', 'max:32', Rule::in(FilterOptions::placements())],
            'variant' => ['sometimes', 'nullable', 'string', 'max:32', Rule::in(FilterOptions::variants())],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['placement', 'variant'] as $key) {
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

        if ($data !== []) {
            $this->merge($data);
        }
    }

    public function placement(): string
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return (string) ($validated['placement'] ?? 'unknown');
    }

    public function variant(): string
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return (string) ($validated['variant'] ?? 'unknown');
    }
}
