<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Cep;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            MovieSeeder::class,
            RecAbLogSeeder::class,
            RecClickSeeder::class,
            DeviceHistorySeeder::class,
        ]);

        $address = $this->generateBrazilianAddress();

        Cep::upsertFromAddress(
            cep: $address['cep'],
            state: $address['state'],
            city: $address['city'],
            neighborhood: $address['neighborhood'],
            street: $address['street'],
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            array_merge(
                [
                    'name' => 'Demo Admin',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
                $address,
            ),
        );

        User::factory()->count(5)->create();
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
