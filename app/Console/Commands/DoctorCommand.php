<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\PhpCompatRule;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

final class DoctorCommand extends Command
{
    protected $signature = 'app:doctor';

    protected $description = 'Inspect PHP 9 readiness and surface compatibility warnings.';

    public function handle(): int
    {
        $this->components->info('Running PHP 9 readiness diagnostics...');

        $context = [
            'extension_requirements' => $this->gatherExtensionRequirements(),
        ];

        $rules = [
            PhpCompatRule::deprecatedExtensions($this->php9DeprecatedExtensions()),
            PhpCompatRule::deprecatedFunctions($this->php9DeprecatedFunctions()),
        ];

        $warnings = 0;

        foreach ($rules as $rule) {
            $result = $rule->evaluate($context);

            if ($result === null) {
                $this->components->twoColumnDetail($rule->label(), '<fg=green>OK</>');

                continue;
            }

            $warnings++;

            $this->components->warn($result->label());
            $this->line($result->summary());
            $this->newLine();
        }

        if ($warnings === 0) {
            $this->components->info('No PHP 9 compatibility warnings detected.');
        } else {
            $this->components->warn(sprintf('%d PHP 9 compatibility warning(s) detected.', $warnings));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function gatherExtensionRequirements(): array
    {
        $requirements = [];

        $composerJson = $this->readJson(base_path('composer.json'));

        if ($composerJson !== null) {
            foreach (['require', 'require-dev'] as $section) {
                foreach ($composerJson[$section] ?? [] as $package => $constraint) {
                    if (! Str::startsWith($package, 'ext-')) {
                        continue;
                    }

                    $requirements[$package][] = 'project';
                }
            }
        }

        $composerLock = $this->readJson(base_path('composer.lock'));

        if ($composerLock !== null) {
            foreach (['packages', 'packages-dev'] as $section) {
                foreach ($composerLock[$section] ?? [] as $package) {
                    foreach ($package['require'] ?? [] as $name => $constraint) {
                        if (! Str::startsWith($name, 'ext-')) {
                            continue;
                        }

                        $requirements[$name][] = $package['name'] ?? 'unknown';
                    }
                }
            }
        }

        foreach ($requirements as $extension => $packages) {
            $requirements[$extension] = array_values(array_unique($packages));
            sort($requirements[$extension]);
        }

        ksort($requirements);

        return $requirements;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

            return $decoded;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, array{since: string, replacement?: string, description?: string}>
     */
    private function php9DeprecatedExtensions(): array
    {
        return [
            'ext/mysql' => [
                'since' => '7.0',
                'replacement' => 'ext/mysqli or PDO MySQL',
                'description' => 'Removed ahead of PHP 9 builds.',
            ],
            'ext/mcrypt' => [
                'since' => '7.1',
                'replacement' => 'sodium or OpenSSL APIs',
                'description' => 'The mcrypt extension is no longer available in PHP 9.',
            ],
            'ext/ereg' => [
                'since' => '7.0',
                'replacement' => 'preg_* APIs',
                'description' => 'POSIX regex extension removed before PHP 9.',
            ],
        ];
    }

    /**
     * @return array<string, array{since: string, replacement?: string, description?: string}>
     */
    private function php9DeprecatedFunctions(): array
    {
        return [
            'utf8_encode' => [
                'since' => '8.2',
                'replacement' => "mb_convert_encoding(\$value, 'UTF-8', 'ISO-8859-1')",
                'description' => 'Leverage mbstring for forward-compatible conversions.',
            ],
            'utf8_decode' => [
                'since' => '8.2',
                'replacement' => "mb_convert_encoding(\$value, 'ISO-8859-1', 'UTF-8')",
                'description' => 'Leverage mbstring for forward-compatible conversions.',
            ],
        ];
    }
}
