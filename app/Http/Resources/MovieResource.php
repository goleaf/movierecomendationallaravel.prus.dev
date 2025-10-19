<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\TranslationPayload;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $imdb_tt
 * @property string $title
 * @property string|null $plot
 * @property string $type
 * @property int|null $year
 * @property \Carbon\CarbonImmutable|null $release_date
 * @property float|null $imdb_rating
 * @property int|null $imdb_votes
 * @property int|null $runtime_min
 * @property Collection<int, string>|null $genres
 * @property string|null $poster_url
 * @property string|null $backdrop_url
 * @property array{title?: array<string, string>, plot?: array<string, string>}|null $translations
 */
class MovieResource extends JsonResource
{
    public function toArray($request): array
    {
        $lang = $request->query('lang');
        $title = $this->title;
        $plot = $this->plot;
        if (is_string($lang) && $lang !== '') {
            $translations = TranslationPayload::normalize($this->translations);
            $title = $translations['title'][$lang] ?? $title;
            $plot = $translations['plot'][$lang] ?? $plot;
        }

        $genres = $this->genres instanceof Collection
            ? $this->genres->values()->all()
            : $this->genres;

        return [
            'id' => $this->id, 'imdb_tt' => $this->imdb_tt, 'title' => $title, 'plot' => $plot, 'type' => $this->type,
            'year' => $this->year, 'release_date' => optional($this->release_date)->format('Y-m-d'),
            'imdb_rating' => $this->imdb_rating, 'imdb_votes' => $this->imdb_votes, 'runtime_min' => $this->runtime_min,
            'genres' => $genres, 'poster_url' => $this->poster_url, 'backdrop_url' => $this->backdrop_url,
        ];
    }
}
