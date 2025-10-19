<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Cep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cep>
 */
class CepFactory extends Factory
{
    protected $model = Cep::class;

    public function definition(): array
    {
        return [
            'cep' => fake()->regexify('[0-9]{8}'),
            'state' => fake()->randomElement(['SP', 'RJ', 'MG', 'RS', 'BA']),
            'city' => fake()->city(),
            'neighborhood' => fake()->optional()->words(2, true),
            'street' => fake()->streetName(),
        ];
    }
}
