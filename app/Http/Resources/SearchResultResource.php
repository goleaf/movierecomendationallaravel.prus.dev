<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

use function proxy_image_url;

/**
 * @property int $id
 * @property string $imdb_tt
 * @property string $title
 * @property string $type
 * @property int|null $year
 * @property float|null $imdb_rating
 * @property int|null $imdb_votes
 * @property array<int,string>|null $genres
 * @property string|null $poster_url
 */
class SearchResultResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'imdb_tt' => $this->imdb_tt,
            'title' => $this->title,
            'type' => $this->type,
            'year' => $this->year,
            'imdb_rating' => $this->imdb_rating,
            'imdb_votes' => $this->imdb_votes,
            'genres' => $this->genres,
            'poster_url' => proxy_image_url($this->poster_url),
        ];
    }
}
