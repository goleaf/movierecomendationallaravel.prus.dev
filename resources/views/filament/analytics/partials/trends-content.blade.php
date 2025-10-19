<div class="space-y-6">
  {{ $this->form }}

  <x-filament::card>
    <div class="grid gap-4 text-sm text-gray-300 sm:grid-cols-3">
      <div>
        <div class="font-semibold text-white">{{ __('admin.trends.filters.genre') }}</div>
        <div>{{ filled($filters['genre']) ? $filters['genre'] : __('messages.common.dash') }}</div>
      </div>
      <div>
        <div class="font-semibold text-white">{{ __('admin.trends.filters.year_from') }}</div>
        <div>{{ filled($filters['year_from']) ? $filters['year_from'] : __('messages.common.dash') }}</div>
      </div>
      <div>
        <div class="font-semibold text-white">{{ __('admin.trends.filters.year_to') }}</div>
        <div>{{ filled($filters['year_to']) ? $filters['year_to'] : __('messages.common.dash') }}</div>
      </div>
    </div>
  </x-filament::card>

  <x-filament::card>
    <div class="text-sm text-gray-400">{{ __('messages.trends.period', ['from' => $filters['from'], 'to' => $filters['to'], 'days' => $filters['days'], 'days_short' => __('messages.trends.days_short')]) }}</div>
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
            <img src="{{ proxy_image_url($item['poster_url']) ?? $item['poster_url'] }}" alt="{{ !empty($item['title']) ? 'Постер фильма «' . $item['title'] . '»' : 'Постер фильма' }}" class="mb-3 w-full rounded-lg" loading="lazy" />
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
