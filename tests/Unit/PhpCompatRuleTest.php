<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AppDoctor\PhpCompatRule;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhpCompatRuleTest extends TestCase
{
    #[Test]
    public function it_flags_incompatible_extension_requirements(): void
    {
        $rule = new PhpCompatRule;

        $warnings = $rule->analyze([
            'ext-json' => '*',
            'ext-mcrypt' => '^1.0',
            'ext-mysql' => '*',
        ], []);

        $this->assertCount(2, $warnings);
        $this->assertSame(
            'ext-mcrypt requirement "^1.0" is incompatible with PHP 9: The mcrypt extension has been removed from PHP core. Use the Sodium or OpenSSL extension instead.',
            $warnings[0]
        );
        $this->assertSame(
            'ext-mysql requirement "*" is incompatible with PHP 9: The mysql extension has been removed from PHP for years. Switch to mysqli or PDO.',
            $warnings[1]
        );
    }

    #[Test]
    public function it_reports_deprecated_function_usage(): void
    {
        $rule = new PhpCompatRule;

        $warnings = $rule->analyze([], [
            'utf8_decode' => ['app/Legacy/LegacyHelper.php:12', 'app/Legacy/LegacyHelper.php:12', 'app/Legacy/Other.php:55'],
            'trim' => ['app/Other/File.php:1'],
        ]);

        $this->assertSame(1, count($warnings));
        $this->assertSame(
            'Function utf8_decode is removed in PHP 9 (Replace with mb_convert_encoding($string, "ISO-8859-1", "UTF-8").). Found in: app/Legacy/LegacyHelper.php:12, app/Legacy/Other.php:55',
            $warnings[0]
        );
    }

    #[Test]
    public function it_returns_empty_warnings_when_everything_is_ready(): void
    {
        $rule = new PhpCompatRule;

        $this->assertSame([], $rule->analyze(['ext-json' => '*'], []));
    }
}
