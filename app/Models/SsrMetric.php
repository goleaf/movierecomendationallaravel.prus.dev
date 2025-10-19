<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SsrMetric extends Model
{
    use HasFactory;

    protected $table = 'ssr_metrics';

    protected $guarded = [];
}
