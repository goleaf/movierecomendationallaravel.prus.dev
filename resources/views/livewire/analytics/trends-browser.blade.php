<div class="space-y-6">
    <div class="bg-white shadow-sm rounded-xl p-6 space-y-6">
        <div class="flex flex-col gap-4">
            <form wire:submit.prevent="apply" @class([
                'grid gap-4',
                'md:grid-cols-6' => $showAdvanced,
                'md:grid-cols-3' => ! $showAdvanced,
            ])>
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium text-gray-600" for="trends-days">{{ __('admin.trends.filters.days') }}</label>
                    <select id="trends-days" wire:model.defer="filters.days" class="fi-input block w-full rounded-lg border-gray-300">
                        @foreach([3, 7, 14, 30] as $days)
                            <option value="{{ $days }}">{{ __('admin.trends.days_option', ['days' => $days]) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-sm font-medium text-gray-600" for="trends-type">{{ __('admin.trends.filters.type') }}</label>
                    <select id="trends-type" wire:model.defer="filters.type" class="fi-input block w-full rounded-lg border-gray-300">
                        <option value="">{{ __('admin.trends.type_placeholder') }}</option>
                        <option value="movie">{{ __('admin.trends.types.movie') }}</option>
                        <option value="series">{{ __('admin.trends.types.series') }}</option>
                        <option value="animation">{{ __('admin.trends.types.animation') }}</option>
                    </select>
                </div>
                @if($showAdvanced)
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium text-gray-600" for="trends-genre">{{ __('admin.trends.filters.genre') }}</label>
                        <input id="trends-genre" type="text" wire:model.defer="filters.genre" class="fi-input block w-full rounded-lg border-gray-300" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium text-gray-600" for="trends-year-from">{{ __('admin.trends.filters.year_from') }}</label>
                        <input id="trends-year-from" type="number" wire:model.defer="filters.year_from" class="fi-input block w-full rounded-lg border-gray-300" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-sm font-medium text-gray-600" for="trends-year-to">{{ __('admin.trends.filters.year_to') }}</label>
                        <input id="trends-year-to" type="number" wire:model.defer="filters.year_to" class="fi-input block w-full rounded-lg border-gray-300" />
                    </div>
                @endif
                <div class="flex items-end">
                    <button type="submit" class="fi-btn fi-btn-primary w-full">
                        {{ __('admin.trends.apply') }}
                    </button>
                </div>
            </form>
            <div class="text-sm text-gray-500">
                {{ __('admin.trends.period', ['from' => $period['from'], 'to' => $period['to'], 'days' => $period['days']]) }}
            </div>
        </div>
        @if(empty($items))
            <p class="text-sm text-gray-500">{{ __('admin.trends.empty') }}</p>
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($items as $item)
                    <a href="{{ route('movies.show', ['movie' => $item['id']]) }}" class="flex flex-col overflow-hidden rounded-xl border border-gray-200 transition hover:border-primary-500">
                        @if($poster = proxy_image_url($item['poster_url']))
                            <img src="{{ $poster }}" alt="{{ $item['title'] }}" loading="lazy" class="aspect-[2/3] w-full object-cover" />
                        @endif
                        <div class="space-y-2 p-4">
                            <div class="text-base font-semibold text-gray-900">{{ $item['title'] }} <span class="text-sm font-normal text-gray-500">({{ $item['year'] ?? '—' }})</span></div>
                            <div class="text-sm text-gray-600 space-y-1">
                                <div>{{ __('admin.trends.clicks', ['count' => $item['clicks'] ?? '—']) }}</div>
                                @if($item['imdb_rating'])
                                    <div>{{ __('admin.trends.imdb', ['rating' => $item['imdb_rating']]) }}</div>
                                @endif
                                @if($item['imdb_votes'])
                                    <div>{{ __('admin.trends.votes', ['count' => number_format($item['imdb_votes'], 0, '.', ' ')]) }}</div>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
