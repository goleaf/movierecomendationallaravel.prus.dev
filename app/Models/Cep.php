<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\CepFormatter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cep extends Model
{
    /** @use HasFactory<\Database\Factories\CepFactory> */
    use HasFactory;

    protected $table = 'cep';

    protected $primaryKey = 'cep';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'cep',
        'state',
        'city',
        'neighborhood',
        'street',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'cep' => 'string',
        'state' => 'string',
        'city' => 'string',
        'neighborhood' => 'string',
        'street' => 'string',
    ];

    public static function upsertFromAddress(
        ?string $cep,
        ?string $state,
        ?string $city,
        ?string $neighborhood,
        ?string $street
    ): void {
        $normalizedCep = CepFormatter::strip($cep);

        if ($normalizedCep === null) {
            return;
        }

        static::query()->updateOrCreate(
            ['cep' => $normalizedCep],
            [
                'state' => CepFormatter::uppercaseState($state),
                'city' => $city,
                'neighborhood' => $neighborhood,
                'street' => $street,
            ],
        );
    }
}
