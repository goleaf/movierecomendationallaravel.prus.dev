<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MovieCast;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MovieCast>
 */
class MovieCastFactory extends Factory
{
    protected $model = MovieCast::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'character' => $this->faker->name(),
            'order_column' => $this->faker->numberBetween(0, 100),
        ];
    }
}
