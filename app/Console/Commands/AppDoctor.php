<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\AppDoctor\PhpCompatRule;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class AppDoctor extends Command
{
    protected $signature = 'app:doctor';

    protected $description = 'Diagnose application health and upcoming platform compatibility concerns.';

    public function handle(PhpCompatRule $phpCompatRule): int
    {
        $extensionRequirements = $this->gatherExtensionRequirements();
        $deprecatedFunctionUsage = $this->scanForDeprecatedFunctions();

        $warnings = $phpCompatRule->analyze($extensionRequirements, $deprecatedFunctionUsage);

        $status = empty($warnings) ? '<fg=green>ready</>' : '<fg=yellow>warnings</>';

        $this->line('');
        $this->components->twoColumnDetail('PHP 9 readiness', $status);

        if (empty($warnings)) {
            $this->line('  • No PHP 9 compatibility issues detected.');
        } else {
            foreach ($warnings as $warning) {
                $this->warn('  • '.$warning);

                if ($this->runningInGithubActions()) {
                    $this->line(sprintf('::warning::%s', $warning));
                }
            }
        }

        return static::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function gatherExtensionRequirements(): array
    {
        $composerPath = base_path('composer.json');

        if (! is_file($composerPath)) {
            return [];
        }

        $payload = json_decode((string) file_get_contents($composerPath), true);

        if (! is_array($payload)) {
            return [];
        }

        $sections = ['require', 'require-dev'];
        $extensions = [];

        foreach ($sections as $section) {
            $requirements = $payload[$section] ?? [];

            if (! is_array($requirements)) {
                continue;
            }

            foreach ($requirements as $package => $constraint) {
                if (! is_string($package) || ! str_starts_with($package, 'ext-')) {
                    continue;
                }

                $extensions[$package] = is_string($constraint) ? $constraint : '*';
            }
        }

        ksort($extensions);

        return $extensions;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function scanForDeprecatedFunctions(): array
    {
        $directories = [
            base_path('app'),
            base_path('config'),
            base_path('routes'),
            base_path('resources/views'),
        ];

        $functions = array_keys(PhpCompatRule::deprecatedFunctionReasons());
        $usages = [];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    continue;
                }

                $extension = strtolower($file->getExtension());

                if (! in_array($extension, ['php', 'phtml'], true)) {
                    continue;
                }

                $contents = @file_get_contents($file->getPathname());

                if ($contents === false) {
                    continue;
                }

                foreach ($functions as $function) {
                    if (! preg_match_all('/(?<!->)(?<!::)\\b'.preg_quote($function, '/').'\s*\(/', $contents)) {
                        continue;
                    }

                    $relativePath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $usages[$function] ??= [];

                    if (! in_array($relativePath, $usages[$function], true)) {
                        $usages[$function][] = $relativePath;
                    }
                }
            }
        }

        ksort($usages);

        return $usages;
    }

    private function runningInGithubActions(): bool
    {
        return getenv('GITHUB_ACTIONS') === 'true';
    }
}
