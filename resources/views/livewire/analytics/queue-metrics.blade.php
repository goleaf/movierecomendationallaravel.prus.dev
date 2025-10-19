<div class="space-y-6">
    <div class="bg-white shadow-sm rounded-xl p-6 space-y-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ __('admin.metrics.heading') }}</h3>
                <p class="text-sm text-gray-500">{{ __('admin.metrics.title') }}</p>
            </div>
            <button wire:click="refreshMetrics" type="button" class="fi-btn fi-btn-primary self-start md:self-auto">
                {{ __('admin.metrics.refresh') }}
            </button>
        </div>
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-gray-200 p-4">
                <div class="text-sm font-medium text-gray-600">{{ __('admin.metrics.labels.jobs') }}</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($metrics['queue']) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 p-4">
                <div class="text-sm font-medium text-gray-600">{{ __('admin.metrics.labels.failed') }}</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($metrics['failed']) }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 p-4">
                <div class="text-sm font-medium text-gray-600">{{ __('admin.metrics.labels.batches') }}</div>
                <div class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($metrics['processed']) }}</div>
            </div>
        </div>
        @if($metrics['horizon']['workload'] || $metrics['horizon']['supervisors'])
            <div class="space-y-4">
                @if($metrics['horizon']['workload'])
                    <div>
                        <h4 class="text-sm font-medium text-gray-600">{{ __('admin.metrics.horizon.workload') }}</h4>
                        <pre class="mt-2 overflow-x-auto rounded-lg bg-gray-900 p-4 text-xs text-gray-100">{{ json_encode($metrics['horizon']['workload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </div>
                @endif
                @if($metrics['horizon']['supervisors'])
                    <div>
                        <h4 class="text-sm font-medium text-gray-600">{{ __('admin.metrics.horizon.supervisors') }}</h4>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-700">
                            @foreach($metrics['horizon']['supervisors'] as $supervisor)
                                <li>{{ $supervisor }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @else
            <p class="text-sm text-gray-500">{{ __('admin.metrics.horizon.empty') }}</p>
        @endif
    </div>
</div>
