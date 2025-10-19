<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $title
 * @property string|null $poster_url
 * @property int|null $year
 * @property string $type
 * @property float|null $imdb_rating
 * @property int|null $imdb_votes
 * @property int|null $clicks
 */
class TrendResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'title' => $this->title,
            'poster_url' => proxy_image_url($this->poster_url),
            'year' => $this->year !== null ? (int) $this->year : null,
            'type' => $this->type,
            'imdb_rating' => $this->imdb_rating !== null ? (float) $this->imdb_rating : null,
            'imdb_votes' => $this->imdb_votes !== null ? (int) $this->imdb_votes : null,
            'clicks' => $this->clicks !== null ? (int) $this->clicks : null,
        ];
    }
}
