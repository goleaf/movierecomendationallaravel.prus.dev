<div class="space-y-6">
    <div class="bg-white shadow-sm rounded-xl p-6 space-y-6">
        <div class="flex flex-col gap-4">
            <form wire:submit.prevent="apply" class="grid gap-4 md:grid-cols-5">
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium text-gray-600" for="ctr-from">{{ __('admin.ctr.filters.from') }}</label>
                    <input id="ctr-from" type="date" wire:model.defer="filters.from" class="fi-input block w-full rounded-lg border-gray-300" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium text-gray-600" for="ctr-to">{{ __('admin.ctr.filters.to') }}</label>
                    <input id="ctr-to" type="date" wire:model.defer="filters.to" class="fi-input block w-full rounded-lg border-gray-300" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium text-gray-600" for="ctr-variant">{{ __('admin.ctr.filters.variant') }}</label>
                    <select id="ctr-variant" wire:model.defer="filters.variant" class="fi-input block w-full rounded-lg border-gray-300">
                        <option value="">{{ __('admin.ctr.filters.variant_all') }}</option>
                        @foreach($variants as $variant)
                            <option value="{{ $variant }}">{{ $variant }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium text-gray-600" for="ctr-placement">{{ __('admin.ctr.filters.placement') }}</label>
                    <select id="ctr-placement" wire:model.defer="filters.placement" class="fi-input block w-full rounded-lg border-gray-300">
                        <option value="">{{ __('admin.ctr.filters.placement_all') }}</option>
                        @foreach($placements as $placement)
                            <option value="{{ $placement }}">{{ $placement }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="fi-btn fi-btn-primary w-full">
                        {{ __('admin.ctr.filters.apply') }}
                    </button>
                </div>
            </form>
            <div class="text-sm text-gray-500">
                {{ __('admin.ctr.period', ['from' => $period['from'], 'to' => $period['to']]) }}
            </div>
        </div>
        <div class="grid gap-6 md:grid-cols-2">
            <img src="{{ route('admin.ctr.svg', ['from' => $filters['from'], 'to' => $filters['to'], 'v' => $filters['variant'] ?: null]) }}" alt="{{ __('admin.ctr.line_alt') }}" class="w-full rounded-lg border border-gray-200 bg-gray-50" />
            <img src="{{ route('admin.ctr.bars.svg', ['from' => $filters['from'], 'to' => $filters['to'], 'v' => $filters['variant'] ?: null]) }}" alt="{{ __('admin.ctr.bars_alt') }}" class="w-full rounded-lg border border-gray-200 bg-gray-50" />
        </div>
    </div>

    <div class="bg-white shadow-sm rounded-xl p-6 space-y-4">
        <h3 class="text-lg font-semibold text-gray-900">{{ __('admin.ctr.ab_summary_heading') }}</h3>
        <dl class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @forelse($summary as $item)
                <div class="rounded-lg border border-gray-200 p-4">
                    <dt class="text-sm font-medium text-gray-600">{{ $item['variant'] }}</dt>
                    <dd class="mt-2 text-sm text-gray-900">
                        {{ __('admin.ctr.ab_summary_item', [
                            'variant' => $item['variant'],
                            'impressions' => number_format($item['impressions']),
                            'clicks' => number_format($item['clicks']),
                            'ctr' => number_format($item['ctr'], 2),
                        ]) }}
                    </dd>
                </div>
            @empty
                <div class="text-sm text-gray-500">{{ __('admin.ctr.empty_summary') }}</div>
            @endforelse
        </dl>
    </div>

    <div class="bg-white shadow-sm rounded-xl p-6 space-y-6">
        <h3 class="text-lg font-semibold text-gray-900">{{ __('admin.funnel.period', ['from' => $period['from'], 'to' => $period['to']]) }}</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">{{ __('admin.funnel.headers.placement') }}</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">{{ __('admin.funnel.headers.imps') }}</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">{{ __('admin.funnel.headers.clicks') }}</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">{{ __('admin.funnel.headers.views') }}</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">{{ __('admin.funnel.headers.ctr') }}</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">{{ __('admin.funnel.headers.cuped_ctr') }}</th>
                        <th class="px-3 py-2 text-left font-medium text-gray-600">{{ __('admin.funnel.headers.view_rate') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($funnels as $placement => $data)
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-900">{{ $placement }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ number_format($data['imps']) }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ number_format($data['clks']) }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ number_format($data['views']) }}</td>
                            <td class="px-3 py-2 text-gray-700">{{ number_format($data['ctr'], 2) }}%</td>
                            <td class="px-3 py-2 text-gray-700">{{ number_format($data['cuped_ctr'], 2) }}%</td>
                            <td class="px-3 py-2 text-gray-700">{{ number_format($data['view_rate'], 2) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
