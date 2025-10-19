<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class PhpCompatRule
{
    /**
     * @param  Closure(array<string, mixed>): array<string, mixed>  $detector
     * @param  Closure(array<string, mixed>): string  $messageResolver
     */
    private function __construct(
        private readonly string $label,
        private readonly Closure $detector,
        private readonly Closure $messageResolver,
    ) {}

    /**
     * @param  array<string, array{since: string, replacement?: string|null, description?: string|null}>  $extensionMetadata
     */
    public static function deprecatedExtensions(array $extensionMetadata): self
    {
        return new self(
            label: 'Deprecated extension requirements',
            detector: static function (array $context) use ($extensionMetadata): array {
                $requirements = $context['extension_requirements'] ?? [];

                $matches = [];

                foreach ($extensionMetadata as $extension => $metadata) {
                    if (! isset($requirements[$extension])) {
                        continue;
                    }

                    $matches[$extension] = [
                        'packages' => $requirements[$extension],
                        'metadata' => $metadata,
                    ];
                }

                return $matches;
            },
            messageResolver: static function (array $matches): string {
                $messages = [];

                foreach ($matches as $extension => $data) {
                    $metadata = $data['metadata'];
                    $packages = implode(', ', $data['packages']);
                    $replacement = $metadata['replacement'] ?? null;
                    $description = $metadata['description'] ?? null;

                    $suffix = $replacement !== null && $replacement !== ''
                        ? sprintf(' – replace with %s', $replacement)
                        : '';

                    if ($description !== null && $description !== '') {
                        $suffix .= sprintf(' (%s)', $description);
                    }

                    $messages[] = sprintf(
                        '%s required by %s is deprecated since PHP %s%s',
                        $extension,
                        $packages,
                        $metadata['since'],
                        $suffix,
                    );
                }

                return implode(PHP_EOL, $messages);
            },
        );
    }

    /**
     * @param  array<string, array{since: string, replacement?: string|null, description?: string|null}>  $functionMetadata
     */
    public static function deprecatedFunctions(array $functionMetadata): self
    {
        $normalised = [];

        foreach ($functionMetadata as $function => $metadata) {
            $normalised[$function] = [
                'since' => $metadata['since'],
                'replacement' => $metadata['replacement'] ?? null,
                'description' => $metadata['description'] ?? null,
            ];
        }

        return new self(
            label: 'Deprecated function usage',
            detector: static function (array $context) use ($normalised): array {
                $filesystem = $context['filesystem'] ?? new Filesystem;
                $basePath = $context['base_path'] ?? dirname(__DIR__, 2);

                if (! isset($context['paths'])) {
                    $paths = [
                        $basePath.'/app',
                        $basePath.'/bootstrap',
                        $basePath.'/config',
                        $basePath.'/routes',
                        $basePath.'/resources/views',
                    ];
                } else {
                    $paths = $context['paths'];
                }

                $results = [];

                foreach ($paths as $path) {
                    if (! $filesystem->exists($path)) {
                        continue;
                    }

                    foreach ($filesystem->allFiles($path) as $file) {
                        if (! in_array($file->getExtension(), ['php', 'stub', 'phtml'], true)) {
                            continue;
                        }

                        $contents = $filesystem->get($file->getPathname());

                        foreach ($normalised as $function => $metadata) {
                            if (! str_contains($contents, $function.'(')) {
                                continue;
                            }

                            $relativePath = Str::of($file->getPathname())
                                ->after($basePath.DIRECTORY_SEPARATOR)
                                ->toString();

                            $results[$function]['files'][] = $relativePath;
                            $results[$function]['metadata'] = $metadata;
                        }
                    }
                }

                foreach ($results as $function => $result) {
                    $results[$function]['files'] = array_values(array_unique($result['files']));
                    sort($results[$function]['files']);
                }

                ksort($results);

                return $results;
            },
            messageResolver: static function (array $matches): string {
                $messages = [];

                foreach ($matches as $function => $data) {
                    $metadata = $data['metadata'];
                    $replacement = $metadata['replacement'] ?? null;
                    $description = $metadata['description'] ?? null;

                    $suffix = $replacement !== null && $replacement !== ''
                        ? sprintf(' – replace with %s', $replacement)
                        : '';

                    if ($description !== null && $description !== '') {
                        $suffix .= sprintf(' (%s)', $description);
                    }

                    $messages[] = sprintf(
                        '%s() deprecated since PHP %s used in %s%s',
                        $function,
                        $metadata['since'],
                        implode(', ', $data['files']),
                        $suffix,
                    );
                }

                return implode(PHP_EOL, $messages);
            },
        );
    }

    public function label(): string
    {
        return $this->label;
    }

    public function evaluate(array $context = []): ?PhpCompatRuleResult
    {
        /** @var array<string, mixed> $matches */
        $matches = ($this->detector)($context);

        if ($matches === [] || $matches === null) {
            return null;
        }

        return new PhpCompatRuleResult(
            label: $this->label,
            summary: ($this->messageResolver)($matches),
            details: $matches,
        );
    }
}
