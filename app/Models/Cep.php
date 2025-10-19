<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\CepFormatter;
use Database\Factories\CepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $cep
 * @property string|null $state
 * @property string|null $city
 * @property string|null $neighborhood
 * @property string|null $street
 *
 * @method static CepFactory factory($count = null, $state = [])
 */
class Cep extends Model
{
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

    protected function casts(): array
    {
        return [
            'cep' => 'string',
            'state' => 'string',
            'city' => 'string',
            'neighborhood' => 'string',
            'street' => 'string',
        ];
    }

    /**
     * Persist the provided address information keyed by the normalized CEP code.
     */
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

    protected static function newFactory(): CepFactory
    {
        return CepFactory::new();
    }
}
