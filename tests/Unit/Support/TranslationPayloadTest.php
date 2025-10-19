<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\TranslationPayload;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TranslationPayload::class)]
final class TranslationPayloadTest extends TestCase
{
    public function test_three_letter_codes_are_normalized(): void
    {
        $payload = [
            'title' => [
                'ENG' => 'Hello',
                'Rus' => 'Привет',
                'es' => 'Hola',
            ],
            'plot' => [
                'ENG' => 'Plot',
            ],
        ];

        $normalized = TranslationPayload::normalize($payload);

        self::assertSame(
            ['en' => 'Hello', 'es' => 'Hola', 'ru' => 'Привет'],
            $normalized['title'],
        );

        self::assertSame(
            ['en' => 'Plot'],
            $normalized['plot'],
        );
    }

    public function test_nested_payloads_are_normalized(): void
    {
        $payload = [
            'eng' => [
                'title' => 'Title',
                'plot' => 'Plot',
            ],
            'FRA' => [
                'title' => 'Titre',
            ],
            'en_US' => [
                'title' => 'Regional Title',
            ],
        ];

        $normalized = TranslationPayload::normalize($payload);

        self::assertSame(
            ['en' => 'Title', 'en-us' => 'Regional Title', 'fr' => 'Titre'],
            $normalized['title'],
        );

        self::assertSame(
            ['en' => 'Plot'],
            $normalized['plot'],
        );
    }

    public function test_prepare_discards_empty_payload(): void
    {
        $payload = [
            'title' => [
                'ENG' => '',
            ],
            'plot' => [],
        ];

        self::assertNull(TranslationPayload::prepare($payload));
    }
}
