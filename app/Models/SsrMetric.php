<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $path
 * @property int $score
 * @property int|null $first_byte_ms
 * @property int|null $size
 * @property int|null $html_bytes
 * @property int $meta_count
 * @property int $og_count
 * @property int $ldjson_count
 * @property int $img_count
 * @property int $blocking_scripts
 * @property bool $has_json_ld
 * @property bool $has_open_graph
 * @property Carbon|null $collected_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SsrMetric extends Model
{
    use HasFactory;

    protected $table = 'ssr_metrics';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'path',
        'score',
        'collected_at',
        'first_byte_ms',
        'size',
        'html_bytes',
        'meta_count',
        'og_count',
        'ldjson_count',
        'img_count',
        'blocking_scripts',
        'has_json_ld',
        'has_open_graph',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'collected_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'has_json_ld' => 'boolean',
            'has_open_graph' => 'boolean',
        ];
    }
}
