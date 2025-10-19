<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SsrMetricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $path
 * @property int $score
 * @property int $size
 * @property int $meta_count
 * @property int $og_count
 * @property int $ldjson_count
 * @property int $img_count
 * @property int $blocking_scripts
 * @property int $first_byte_ms
 * @property array|null $payload
 * @property array|null $normalized_payload
 * @property Carbon|null $recorded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static SsrMetricFactory factory($count = null, $state = [])
 */
class SsrMetric extends Model
{
    use HasFactory;

    protected $table = 'ssr_metrics';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'raw_payload' => 'array',
        'normalized_payload' => 'array',
        'payload_normalized' => 'array',
        'recorded_at' => 'datetime',
    ];

    protected static function newFactory(): SsrMetricFactory
    {
        return SsrMetricFactory::new();
    }
}
