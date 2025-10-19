<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $variant
 * @property string $captured_on
 * @property int $item_count
 * @property float $pop_total
 * @property float $recent_total
 * @property float $pref_total
 * @property float $score_total
 * @property float $weight_pop_total
 * @property float $weight_recent_total
 * @property float $weight_pref_total
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
class RecVariantSnapshot extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'captured_on' => 'date',
            'pop_total' => 'float',
            'recent_total' => 'float',
            'pref_total' => 'float',
            'score_total' => 'float',
            'weight_pop_total' => 'float',
            'weight_recent_total' => 'float',
            'weight_pref_total' => 'float',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
