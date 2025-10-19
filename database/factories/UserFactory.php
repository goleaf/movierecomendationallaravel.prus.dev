<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Cep;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return array_merge([
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ], $this->generateBrazilianAddress());
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if ($user->cep === null) {
                return;
            }

            Cep::upsertFromAddress(
                cep: $user->cep,
                state: $user->state,
                city: $user->city,
                neighborhood: $user->neighborhood,
                street: $user->street,
            );
        });
    }

    /**
     * @return array{
     *     cep: ?string,
     *     street: string,
     *     neighborhood: string,
     *     city: string,
     *     state: string
     * }
     */
    private function generateBrazilianAddress(): array
    {
        $faker = fake('pt_BR');

        $cepDigits = preg_replace('/\D/', '', $faker->postcode()) ?? '';
        $cep = substr($cepDigits, 0, 8);

        if ($cep === '') {
            $cep = null;
        }

        $neighborhood = trim($faker->citySuffix());

        if ($neighborhood === '') {
            $neighborhood = $faker->words(2, true);
        }

        return [
            'cep' => $cep,
            'street' => $faker->streetName(),
            'neighborhood' => $neighborhood,
            'city' => $faker->city(),
            'state' => mb_strtoupper($faker->stateAbbr()),
        ];
    }
}
