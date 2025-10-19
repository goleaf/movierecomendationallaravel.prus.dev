<?php

declare(strict_types=1);

namespace App\Support\AppDoctor;

class PhpCompatRule
{
    /**
     * @var array<string, string>
     */
    private const INCOMPATIBLE_EXTENSIONS = [
        'ext-mcrypt' => 'The mcrypt extension has been removed from PHP core. Use the Sodium or OpenSSL extension instead.',
        'ext-mysql' => 'The mysql extension has been removed from PHP for years. Switch to mysqli or PDO.',
        'ext-ereg' => 'The ereg extension was removed and its functions are unavailable. Use the PCRE (preg_*) functions.',
    ];

    /**
     * @var array<string, string>
     */
    private const DEPRECATED_FUNCTIONS = [
        'utf8_encode' => 'Replace with mb_convert_encoding($string, "UTF-8", "ISO-8859-1").',
        'utf8_decode' => 'Replace with mb_convert_encoding($string, "ISO-8859-1", "UTF-8").',
        'libxml_disable_entity_loader' => 'This function has been removed. Configure libxml via libxml_set_external_entity_loader instead.',
    ];

    /**
     * Analyse extension and function usage for PHP 9 compatibility.
     *
     * @param  array<string, string>  $extensionRequirements
     * @param  array<string, array<int, string>>  $deprecatedFunctionUsage
     * @return array<int, string>
     */
    public function analyze(array $extensionRequirements, array $deprecatedFunctionUsage): array
    {
        $warnings = [];

        foreach ($extensionRequirements as $package => $constraint) {
            $normalized = strtolower($package);

            if (! str_starts_with($normalized, 'ext-')) {
                continue;
            }

            $reason = self::INCOMPATIBLE_EXTENSIONS[$normalized] ?? null;

            if ($reason === null) {
                continue;
            }

            $warnings[] = sprintf(
                '%s requirement "%s" is incompatible with PHP 9: %s',
                $package,
                $constraint,
                $reason
            );
        }

        foreach ($deprecatedFunctionUsage as $function => $locations) {
            $reason = self::DEPRECATED_FUNCTIONS[$function] ?? null;

            if ($reason === null) {
                continue;
            }

            $warnings[] = sprintf(
                'Function %s is removed in PHP 9 (%s). Found in: %s',
                $function,
                $reason,
                implode(', ', array_values(array_unique($locations)))
            );
        }

        sort($warnings);

        return $warnings;
    }

    /**
     * @return array<string, string>
     */
    public static function incompatibleExtensionReasons(): array
    {
        return self::INCOMPATIBLE_EXTENSIONS;
    }

    /**
     * @return array<string, string>
     */
    public static function deprecatedFunctionReasons(): array
    {
        return self::DEPRECATED_FUNCTIONS;
    }
}
