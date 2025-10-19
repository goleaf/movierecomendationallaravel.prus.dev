<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\ImageProxyStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshProxiedImage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public string $url) {}

    public function handle(): void
    {
        ImageProxyStorage::refresh($this->url);
    }
}
