<?php

declare(strict_types=1);

namespace App\Support;

final class PhpCompatRuleResult
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        private readonly string $label,
        private readonly string $summary,
        private readonly array $details,
    ) {}

    public function label(): string
    {
        return $this->label;
    }

    public function summary(): string
    {
        return $this->summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }
}
