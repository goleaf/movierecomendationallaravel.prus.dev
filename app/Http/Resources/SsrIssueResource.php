<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $path
 * @property float $avg_score
 * @property array<int, string> $hints
 */
class SsrIssueResource extends JsonResource
{
    public function toArray($request): array
    {
        $hints = $this['hints'] ?? $this->hints ?? [];

        return [
            'path' => (string) ($this['path'] ?? $this->path),
            'avg_score' => (float) ($this['avg_score'] ?? $this->avg_score),
            'hints' => is_array($hints) ? array_values(array_map('strval', $hints)) : [],
        ];
    }
}
