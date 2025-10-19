<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Cep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cep>
 */
final class CepFactory extends Factory
{
    protected $model = Cep::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $digits = fake()->unique()->numerify('########');

        return [
            'cep' => $digits,
            'state' => mb_strtoupper(fake()->lexify('??')),
            'city' => fake()->city(),
            'neighborhood' => fake()->streetName(),
            'street' => fake()->streetAddress(),
        ];
    }
}
