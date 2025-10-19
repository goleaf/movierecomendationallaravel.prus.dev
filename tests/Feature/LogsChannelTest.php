<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Tests\TestCase;

class LogsChannelTest extends TestCase
{
    protected function tearDown(): void
    {
        app()->forgetInstance('request');

        parent::tearDown();
    }

    public function test_ingestion_channel_writes_structured_log(): void
    {
        Carbon::setTestNow(Carbon::create(2024, 1, 15, 12));

        $path = storage_path('logs/ingestion-2024-01-15.log');
        File::delete($path);

        try {
            $request = Request::create('/ingestion', 'POST');
            $request->attributes->set('request_id', 'req-123');
            $request->attributes->set('film_id', 'film-456');
            app()->instance('request', $request);

            Log::channel('ingestion')->info('Ingestion test entry');

            $this->assertFileExists($path);
            $contents = File::get($path);
            $this->assertStringContainsString('Ingestion test entry', $contents);
            $this->assertStringContainsString('request-id:req-123', $contents);
            $this->assertStringContainsString('film-id:film-456', $contents);
        } finally {
            File::delete($path);
            Carbon::setTestNow();
        }
    }

    public function test_logging_defaults_to_daily_rotation(): void
    {
        $this->assertSame('daily', config('logging.default'));
        $this->assertSame('daily', config('logging.channels.ingestion.driver'));
    }

    public function test_logs_tail_command_outputs_latest_lines(): void
    {
        Carbon::setTestNow(Carbon::create(2024, 1, 15, 12));

        $path = storage_path('logs/ingestion-2024-01-15.log');

        try {
            File::put($path, "first\nsecond\n");

            $this->artisan('logs:tail', [
                '--channel' => 'ingestion',
                '--lines' => 1,
            ])->expectsOutput('second')
                ->assertExitCode(SymfonyCommand::SUCCESS);
        } finally {
            File::delete($path);
            Carbon::setTestNow();
        }
    }
}
