<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Movie;
use App\Search\DSL;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SearchDslTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $matching
     * @param  array<string, mixed>  $nonMatching
     */
    #[DataProvider('searchCases')]
    public function test_search_handles_fields_case_and_languages(string $term, array $matching, array $nonMatching): void
    {
        $match = $this->createMovie($matching);
        $this->createMovie(array_merge([
            'title' => 'Interstellar Voyage',
            'plot' => 'A scientist explores distant galaxies.',
        ], $nonMatching));

        $builder = DSL::for(Movie::query())->search([
            'title',
            'plot',
            'translations->title->en',
            'translations->title->ru',
        ], $term);

        $this->assertSame([$match->id], $builder->pluck('id')->all());
    }

    /**
     * @return iterable<string, array{0: string, 1: array<string, mixed>, 2: array<string, mixed>}>
     */
    public static function searchCases(): iterable
    {
        return [
            'title case insensitive' => [
                'matrix',
                [
                    'title' => 'MATRIX RESURGENCE',
                    'plot' => 'A hacker dives back into the Matrix.',
                    'translations' => [
                        'title' => [
                            'en' => 'Matrix Resurgence',
                            'ru' => 'Матрица Возрождение',
                        ],
                    ],
                ],
                [
                    'title' => 'Arrival',
                    'plot' => 'Louise deciphers alien language.',
                    'translations' => [
                        'title' => [
                            'en' => 'Arrival',
                            'ru' => 'Прибытие',
                        ],
                    ],
                ],
            ],
            'plot with diacritics' => [
                'deja',
                [
                    'title' => 'The Matrix Echo',
                    'plot' => 'Neo experiences déjà vu inside the Matrix.',
                    'translations' => [
                        'title' => [
                            'en' => 'The Matrix Echo',
                        ],
                    ],
                ],
                [
                    'title' => 'Dreamcatcher',
                    'plot' => 'An unrelated story about dreams.',
                    'translations' => [
                        'title' => [
                            'en' => 'Dreamcatcher',
                        ],
                    ],
                ],
            ],
            'english translation accents' => [
                'amelie',
                [
                    'title' => 'Le Fabuleux Destin d’Amélie',
                    'plot' => 'A shy waitress transforms Paris through kindness.',
                    'translations' => [
                        'title' => [
                            'en' => 'Amélie',
                        ],
                    ],
                ],
                [
                    'title' => 'Avatar',
                    'plot' => 'A marine travels to Pandora.',
                    'translations' => [
                        'title' => [
                            'en' => 'Avatar',
                        ],
                    ],
                ],
            ],
            'russian translation matching' => [
                'матрица',
                [
                    'title' => 'Матрица',
                    'plot' => 'Нео открывает истинную природу Матрицы.',
                    'translations' => [
                        'title' => [
                            'en' => 'The Matrix',
                            'ru' => 'Матрица',
                        ],
                    ],
                ],
                [
                    'title' => 'Гладиатор',
                    'plot' => 'Римский генерал ищет мести.',
                    'translations' => [
                        'title' => [
                            'en' => 'Gladiator',
                            'ru' => 'Гладиатор',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_tokens_are_combined_with_where_all(): void
    {
        $match = $this->createMovie([
            'title' => 'The Matrix Reloaded',
            'plot' => 'Neo and Trinity return to the Matrix.',
        ]);

        $this->createMovie([
            'title' => 'The Matrix Revolutions',
            'plot' => 'Morpheus plans the final assault on the machines.',
        ]);

        $builder = DSL::for(Movie::query())->search([
            'title',
            'plot',
        ], 'matrix neo');

        $this->assertSame([$match->id], $builder->pluck('id')->all());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createMovie(array $attributes): Movie
    {
        $attributes = array_replace([
            'title' => 'Placeholder Title',
            'plot' => 'Placeholder plot content.',
        ], $attributes);

        $title = $attributes['title'];
        $plot = $attributes['plot'];

        $attributes['translations'] = array_replace_recursive([
            'title' => [
                'en' => $title,
            ],
            'plot' => [
                'en' => $plot,
            ],
        ], $attributes['translations'] ?? []);

        return Movie::factory()->create($attributes);
    }
}
