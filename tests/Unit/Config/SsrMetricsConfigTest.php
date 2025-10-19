<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

class SsrMetricsConfigTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private array $modifiedEnv = [];

    private bool $createdEnvFile = false;

    private string $projectBasePath = '';

    protected function setUp(): void
    {
        $this->projectBasePath = dirname(__DIR__, 3);

        $envPath = $this->projectBasePath.'/.env';

        if (! file_exists($envPath)) {
            touch($envPath);
            $this->createdEnvFile = true;
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        foreach (array_unique($this->modifiedEnv) as $key) {
            $this->clearEnv($key);
        }

        $this->modifiedEnv = [];

        if ($this->createdEnvFile) {
            $envPath = $this->projectBasePath.'/.env';

            if (file_exists($envPath)) {
                unlink($envPath);
            }

            $this->createdEnvFile = false;
        }

        config()->set('ssrmetrics', require config_path('ssrmetrics.php'));

        parent::tearDown();
    }

    public function test_it_exposes_default_configuration(): void
    {
        $config = $this->reloadConfig();

        $this->assertSame(['/', '/trends', '/analytics/ctr'], $config['paths']);
        $this->assertSame('ssrmetrics', $config['storage']['primary']['disk']);
        $this->assertSame('local', $config['storage']['fallback']['disk']);
        $this->assertSame('ssr-metrics.jsonl', $config['storage']['primary']['files']['incoming']);
        $this->assertSame('ssr-metrics-summary.json', $config['storage']['primary']['files']['aggregate']);
        $this->assertSame('ssr-metrics-fallback.jsonl', $config['storage']['fallback']['files']['incoming']);
        $this->assertSame('ssr-metrics-recovery.jsonl', $config['storage']['fallback']['files']['recovery']);
        $this->assertSame(0.35, $config['score']['weights']['speed_index']);
        $this->assertSame(0.25, $config['score']['weights']['first_contentful_paint']);
        $this->assertSame(80, $config['score']['thresholds']['passing']);
        $this->assertSame(65, $config['score']['thresholds']['warning']);
        $this->assertSame(14, $config['retention']['primary_days']);
        $this->assertSame(3, $config['retention']['fallback_days']);
        $this->assertSame(90, $config['retention']['aggregate_days']);
    }

    public function test_it_honours_environment_overrides(): void
    {
        $this->setEnv('SSR_METRICS_PATHS', '/foo,/bar , /baz/qux');
        $this->setEnv('SSR_METRICS_STORAGE_PRIMARY_DISK', 's3-primary');
        $this->setEnv('SSR_METRICS_STORAGE_FALLBACK_DISK', 's3-fallback');
        $this->setEnv('SSR_METRICS_STORAGE_PRIMARY_FILE', 'primary.jsonl');
        $this->setEnv('SSR_METRICS_STORAGE_PRIMARY_AGGREGATE_FILE', 'primary-summary.json');
        $this->setEnv('SSR_METRICS_STORAGE_FALLBACK_FILE', 'fallback.jsonl');
        $this->setEnv('SSR_METRICS_STORAGE_FALLBACK_RECOVERY_FILE', 'recovery.jsonl');
        $this->setEnv('SSR_METRICS_WEIGHT_SPEED_INDEX', '0.5');
        $this->setEnv('SSR_METRICS_WEIGHT_FCP', '0.2');
        $this->setEnv('SSR_METRICS_WEIGHT_LCP', '0.2');
        $this->setEnv('SSR_METRICS_WEIGHT_TTI', '0.1');
        $this->setEnv('SSR_METRICS_THRESHOLD_PASSING', '90');
        $this->setEnv('SSR_METRICS_THRESHOLD_WARNING', '70');
        $this->setEnv('SSR_METRICS_RETENTION_PRIMARY_DAYS', '21');
        $this->setEnv('SSR_METRICS_RETENTION_FALLBACK_DAYS', '5');
        $this->setEnv('SSR_METRICS_RETENTION_AGGREGATE_DAYS', '120');

        $config = $this->reloadConfig();

        $this->assertSame(['/foo', '/bar', '/baz/qux'], $config['paths']);
        $this->assertSame('s3-primary', $config['storage']['primary']['disk']);
        $this->assertSame('s3-fallback', $config['storage']['fallback']['disk']);
        $this->assertSame('primary.jsonl', $config['storage']['primary']['files']['incoming']);
        $this->assertSame('primary-summary.json', $config['storage']['primary']['files']['aggregate']);
        $this->assertSame('fallback.jsonl', $config['storage']['fallback']['files']['incoming']);
        $this->assertSame('recovery.jsonl', $config['storage']['fallback']['files']['recovery']);
        $this->assertSame(0.5, $config['score']['weights']['speed_index']);
        $this->assertSame(0.2, $config['score']['weights']['first_contentful_paint']);
        $this->assertSame(0.2, $config['score']['weights']['largest_contentful_paint']);
        $this->assertSame(0.1, $config['score']['weights']['time_to_interactive']);
        $this->assertSame(90, $config['score']['thresholds']['passing']);
        $this->assertSame(70, $config['score']['thresholds']['warning']);
        $this->assertSame(21, $config['retention']['primary_days']);
        $this->assertSame(5, $config['retention']['fallback_days']);
        $this->assertSame(120, $config['retention']['aggregate_days']);
    }

    /**
     * @return array<string, mixed>
     */
    private function reloadConfig(): array
    {
        $config = require config_path('ssrmetrics.php');
        config()->set('ssrmetrics', $config);

        return $config;
    }

    private function setEnv(string $key, string $value): void
    {
        $this->modifiedEnv[] = $key;

        putenv(sprintf('%s=%s', $key, $value));
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    private function clearEnv(string $key): void
    {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
}
