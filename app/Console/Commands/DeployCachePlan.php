<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class DeployCachePlan extends Command
{
    protected $signature = 'deploy:cache-plan';

    protected $description = 'Clear and rebuild deployment caches in a predictable order.';

    public function handle(): int
    {
        $cachePlan = [
            ['label' => 'Config', 'clear' => 'config:clear', 'build' => 'config:cache'],
            ['label' => 'Routes', 'clear' => 'route:clear', 'build' => 'route:cache'],
            ['label' => 'Views', 'clear' => 'view:clear', 'build' => 'view:cache'],
            ['label' => 'Events', 'clear' => 'event:clear', 'build' => 'event:cache'],
        ];

        $this->info('Executing deployment cache plan...');

        $summary = [];

        foreach ($cachePlan as $step) {
            $this->line(sprintf('• Clearing %s cache', strtolower($step['label'])));
            Artisan::call($step['clear']);
            $summary[] = $step['clear'];

            $this->line(sprintf('• Rebuilding %s cache', strtolower($step['label'])));
            Artisan::call($step['build']);
            $summary[] = $step['build'];
        }

        $this->newLine();
        $this->info('Summary:');

        foreach ($summary as $index => $command) {
            $this->line(sprintf('%d. php artisan %s', $index + 1, $command));
        }

        return static::SUCCESS;
    }
}
