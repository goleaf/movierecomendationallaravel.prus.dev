<?php

declare(strict_types=1);

namespace App\Http\Requests;

class ApiSearchRequest extends SearchFiltersRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'per' => ['sometimes', 'nullable', 'integer', 'between:1,50'],
        ]);
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if ($this->has('per')) {
            $value = $this->input('per');
            $this->merge(['per' => $value === '' ? null : $value]);
        }
    }

    public function limit(): int
    {
        $perPage = (int) ($this->validated()['per'] ?? 20);

        return min(50, max(1, $perPage));
    }
}
