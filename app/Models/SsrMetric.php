<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SsrMetricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\CarbonImmutable;

/**
 * @property int $id
 * @property string $path
 * @property int $score
 * @property array<string, mixed>|null $payload
 * @property CarbonImmutable|null $recorded_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static SsrMetricFactory factory($count = null, $state = [])
 */
class SsrMetric extends Model
{
    use HasFactory;

    protected $table = 'ssr_metrics';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'recorded_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): SsrMetricFactory
    {
        return SsrMetricFactory::new();
    }
}
