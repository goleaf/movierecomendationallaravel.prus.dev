<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\TranslationPayload;
use PHPUnit\Framework\TestCase;

class TranslationPayloadMappingTest extends TestCase
{
    public function test_tmdb_style_payload_is_normalized(): void
    {
        $payload = [
            'en-US' => ['title' => 'Quantum Echo', 'plot' => 'A ripple in spacetime.'],
            'ru' => ['title' => 'Квантовое эхо', 'plot' => 'Волна во времени.'],
            'es_mx' => ['title' => 'Eco Cuántico', 'plot' => 'Una ondulación en el tiempo.'],
        ];

        $normalized = TranslationPayload::normalize($payload);

        $this->assertSame([
            'title' => [
                'en-us' => 'Quantum Echo',
                'es-mx' => 'Eco Cuántico',
                'ru' => 'Квантовое эхо',
            ],
            'plot' => [
                'en-us' => 'A ripple in spacetime.',
                'es-mx' => 'Una ondulación en el tiempo.',
                'ru' => 'Волна во времени.',
            ],
        ], $normalized);
    }

    public function test_omdb_style_payload_is_mapped_to_iso_codes(): void
    {
        $payload = [
            'title' => ['eng' => 'Silent Orbit', 'fre' => 'Orbites Silencieuses'],
            'plot' => ['eng' => 'Crew on a silent mission.', 'fre' => 'Équipage en mission silencieuse.'],
        ];

        $prepared = TranslationPayload::prepare($payload);

        $this->assertSame([
            'title' => ['en' => 'Silent Orbit', 'fr' => 'Orbites Silencieuses'],
            'plot' => ['en' => 'Crew on a silent mission.', 'fr' => 'Équipage en mission silencieuse.'],
        ], $prepared);
    }
}
