<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
 * @property array<int,string>|null $genres
 * @property string|null $poster_url
 * @property string|null $backdrop_url
 * @property array<string,array{title?:string,plot?:string}>|null $translations
 * @property array|null $raw
 * @property-read float $weighted_score
 */
class Movie extends Model
{
    use HasFactory;
    protected $guarded = [];
    protected $casts = [
        'release_date'=>'immutable_datetime:Y-m-d',
        'imdb_rating'=>'float','imdb_votes'=>'integer','runtime_min'=>'integer',
        'genres'=>'array','translations'=>'array','raw'=>'array',
    ];
    protected $appends=['weighted_score'];

    public function getWeightedScoreAttribute(): float
    {
        $R=(float)($this->imdb_rating ?? 0.0);
        $v=(int)($this->imdb_votes ?? 0);
        $m=1000; $C=6.8;
        if($v<=0) return 0.0;
        return round((($v/($v+$m))*$R + ($m/($v+$m))*$C),4);
    }
}
