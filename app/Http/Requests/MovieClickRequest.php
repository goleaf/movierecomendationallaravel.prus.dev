<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class MovieClickRequest extends FormRequest
{
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
            'placement' => ['required', 'string', 'max:255'],
            'variant' => ['required', 'string', 'max:255'],
        ];
    }

    public function placement(): string
    {
        return (string) $this->validated()['placement'];
    }

    public function variant(): string
    {
        return (string) $this->validated()['variant'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'placement' => $this->prepareString($this->query('placement'), 'unknown'),
            'variant' => $this->prepareString($this->query('variant'), 'unknown'),
        ]);
    }

    private function prepareString(mixed $value, string $fallback): string
    {
        $string = trim((string) ($value ?? $fallback));

        return $string === '' ? $fallback : $string;
    }
}
