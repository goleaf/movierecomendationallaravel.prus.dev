<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\PhpCompatRule;
use App\Support\PhpCompatRuleResult;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhpCompatRule::class)]
#[CoversClass(PhpCompatRuleResult::class)]
final class PhpCompatRuleTest extends TestCase
{
    public function test_deprecated_extensions_ignore_missing_requirements(): void
    {
        $rule = PhpCompatRule::deprecatedExtensions([
            'ext/mysql' => ['since' => '9.0'],
        ]);

        self::assertNull($rule->evaluate(['extension_requirements' => []]));
    }

    public function test_deprecated_extensions_report_packages(): void
    {
        $rule = PhpCompatRule::deprecatedExtensions([
            'ext/mysql' => [
                'since' => '9.0',
                'replacement' => 'pdo_mysql',
            ],
        ]);

        $result = $rule->evaluate([
            'extension_requirements' => [
                'ext/mysql' => ['project', 'vendor/package'],
            ],
        ]);

        self::assertInstanceOf(PhpCompatRuleResult::class, $result);
        self::assertSame('Deprecated extension requirements', $result->label());
        self::assertStringContainsString(
            'ext/mysql required by project, vendor/package is deprecated since PHP 9.0',
            $result->summary(),
        );

        $details = $result->details();

        self::assertArrayHasKey('ext/mysql', $details);
        self::assertSame(['project', 'vendor/package'], $details['ext/mysql']['packages']);
        self::assertSame('9.0', $details['ext/mysql']['metadata']['since']);
        self::assertSame('pdo_mysql', $details['ext/mysql']['metadata']['replacement']);
    }

    public function test_deprecated_function_usage_reports_locations(): void
    {
        $filesystem = new Filesystem;
        $workingPath = sys_get_temp_dir().'/php-compat-'.uniqid('', true);
        $filesystem->ensureDirectoryExists($workingPath.'/app');

        $filesystem->put($workingPath.'/app/example.php', "<?php\nutf8_encode('value');\n");

        $rule = PhpCompatRule::deprecatedFunctions([
            'utf8_encode' => [
                'since' => '8.2',
                'replacement' => "mb_convert_encoding('value', 'UTF-8', 'ISO-8859-1')",
                'description' => 'Use mbstring equivalents instead.',
            ],
        ]);

        $result = $rule->evaluate([
            'filesystem' => $filesystem,
            'base_path' => $workingPath,
            'paths' => [$workingPath.'/app'],
        ]);

        try {
            self::assertInstanceOf(PhpCompatRuleResult::class, $result);
            self::assertSame('Deprecated function usage', $result->label());
            self::assertStringContainsString('utf8_encode() deprecated since PHP 8.2', $result->summary());
            self::assertStringContainsString('app/example.php', $result->summary());

            $details = $result->details();

            self::assertArrayHasKey('utf8_encode', $details);
            self::assertSame(['app/example.php'], $details['utf8_encode']['files']);
            self::assertSame('8.2', $details['utf8_encode']['metadata']['since']);
        } finally {
            $filesystem->deleteDirectory($workingPath);
        }
    }
}
