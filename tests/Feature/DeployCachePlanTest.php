<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\DeployCachePlan;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class DeployCachePlanTest extends TestCase
{
    public function test_deploy_cache_plan_runs_commands_in_expected_order(): void
    {
        $envPath = base_path('.env');
        $cleanupEnv = false;

        if (! file_exists($envPath)) {
            file_put_contents($envPath, '');
            $cleanupEnv = true;
        }

        $stub = new class
        {
            /** @var list<string> */
            public array $recorded = [];

            public function call(string $command, array $parameters = [], $outputBuffer = null): int
            {
                $this->recorded[] = $command;

                return 0;
            }
        };

        $originalArtisan = Artisan::getFacadeRoot();

        Artisan::swap($stub);

        $command = app(DeployCachePlan::class);
        $command->setLaravel(app());

        $buffer = new BufferedOutput;

        try {
            $command->run(new ArrayInput([]), $buffer);
        } finally {
            Artisan::swap($originalArtisan);

            if ($cleanupEnv) {
                @unlink($envPath);
            }
        }

        $this->assertSame([
            'config:clear',
            'config:cache',
            'route:clear',
            'route:cache',
            'view:clear',
            'view:cache',
            'event:clear',
            'event:cache',
        ], $stub->recorded);

        $output = $buffer->fetch();

        $this->assertStringContainsString('Executing deployment cache plan...', $output);
        $this->assertStringContainsString('Summary:', $output);
        $this->assertStringContainsString('1. php artisan config:clear', $output);
        $this->assertStringContainsString('2. php artisan config:cache', $output);
        $this->assertStringContainsString('7. php artisan event:clear', $output);
        $this->assertStringContainsString('8. php artisan event:cache', $output);
    }
}
