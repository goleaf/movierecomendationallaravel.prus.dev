<div class="space-y-6">
  <form wire:submit.prevent="refreshData" class="grid gap-4 md:grid-cols-4">
    <div class="flex flex-col gap-1">
      <label class="text-xs font-semibold text-gray-400">{{ __('admin.trends.filters.days') }}</label>
      <select wire:model.live="days" class="fi-input block w-full rounded-lg border border-gray-700/60 bg-gray-900/40 px-3 py-2 text-sm text-white">
        @foreach($dayOptions as $option)
          <option value="{{ $option }}">{{ __('admin.trends.days_option', ['days' => $option]) }}</option>
        @endforeach
      </select>
    </div>
    <div class="flex flex-col gap-1">
      <label class="text-xs font-semibold text-gray-400">{{ __('admin.trends.filters.type') }}</label>
      <select wire:model.live="type" class="fi-input block w-full rounded-lg border border-gray-700/60 bg-gray-900/40 px-3 py-2 text-sm text-white">
        @foreach($typeOptions as $value => $label)
          <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
      </select>
    </div>
    @if($showAdvancedFilters)
      <div class="flex flex-col gap-1">
        <label class="text-xs font-semibold text-gray-400">{{ __('admin.trends.filters.genre') }}</label>
        <input type="text" wire:model.live="genre" placeholder="{{ __('admin.trends.genre_placeholder') }}" class="fi-input block w-full rounded-lg border border-gray-700/60 bg-gray-900/40 px-3 py-2 text-sm text-white" />
      </div>
      <div class="flex flex-col gap-1">
        <label class="text-xs font-semibold text-gray-400">{{ __('admin.trends.filters.year_from') }}</label>
        <input type="number" wire:model.live="yearFrom" placeholder="{{ __('admin.trends.year_from_placeholder') }}" class="fi-input block w-full rounded-lg border border-gray-700/60 bg-gray-900/40 px-3 py-2 text-sm text-white" />
      </div>
      <div class="flex flex-col gap-1">
        <label class="text-xs font-semibold text-gray-400">{{ __('admin.trends.filters.year_to') }}</label>
        <input type="number" wire:model.live="yearTo" placeholder="{{ __('admin.trends.year_to_placeholder') }}" class="fi-input block w-full rounded-lg border border-gray-700/60 bg-gray-900/40 px-3 py-2 text-sm text-white" />
      </div>
    @endif
    <div class="md:col-span-2 lg:col-span-1 flex items-end">
      <button type="submit" class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-400">
        {{ __('admin.trends.apply') }}
      </button>
    </div>
  </form>

  <x-filament::card>
    <div class="text-sm text-gray-400">{{ __('messages.trends.period', ['from' => $fromDate, 'to' => $toDate, 'days' => $days, 'days_short' => __('messages.trends.days_short')]) }}</div>
  </x-filament::card>

  @if(empty($items))
    <x-filament::card>
      <div class="text-sm text-gray-400">{{ __('messages.trends.empty') }}</div>
    </x-filament::card>
  @else
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      @foreach($items as $item)
        <div class="rounded-xl border border-gray-800 bg-gray-900/60 p-4">
          @if($item['poster_url'])
            <img src="{{ $item['poster_url'] }}" alt="{{ $item['title'] }}" class="mb-3 w-full rounded-lg" loading="lazy" />
          @endif
          <div class="text-base font-semibold text-white">{{ $item['title'] }} <span class="text-sm text-gray-400">{{ $item['year'] ?? __('messages.common.dash') }}</span></div>
          <div class="mt-1 text-xs text-gray-400">
            {{ __('messages.common.clicks', ['count' => $item['clicks'] ?? __('messages.common.dash')]) }}
            @if($item['imdb_rating'])
              • {{ __('messages.common.imdb_only', ['rating' => $item['imdb_rating']]) }}
            @endif
            @if($item['imdb_votes'])
              • {{ __('messages.trends.votes', ['count' => number_format($item['imdb_votes'], 0, '.', ' ')]) }}
            @endif
          </div>
        </div>
      @endforeach
    </div>
  @endif
</div>
