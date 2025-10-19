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

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cep = fake()->numerify('########');

        return [
            'cep' => substr($cep, 0, 5).'-'.substr($cep, 5, 3),
            'state' => fake()->randomElement(['SP', 'RJ', 'MG', 'RS']),
            'city' => fake()->city(),
            'neighborhood' => fake()->streetName(),
            'street' => fake()->streetAddress(),
        ];
    }
}
