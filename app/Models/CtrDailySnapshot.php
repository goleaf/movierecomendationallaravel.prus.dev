<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property \Carbon\CarbonImmutable $snapshot_date
 * @property string $variant
 * @property int $impressions
 * @property int $clicks
 * @property int $views
 * @property float $ctr
 * @property float $view_rate
 */
class CtrDailySnapshot extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'ctr_daily_snapshots';

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'immutable_date',
            'impressions' => 'integer',
            'clicks' => 'integer',
            'views' => 'integer',
            'ctr' => 'float',
            'view_rate' => 'float',
        ];
    }
}
