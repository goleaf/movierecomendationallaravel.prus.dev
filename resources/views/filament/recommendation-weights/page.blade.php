<x-filament-panels::page>
  <form wire:submit.prevent="save" class="space-y-6">
    <x-filament::card>
      <h2 class="text-lg font-semibold text-white">{{ __('admin.recommendation_weights.title') }}</h2>
      <p class="mt-2 text-sm text-gray-300">{{ __('admin.recommendation_weights.description') }}</p>
    </x-filament::card>

    {{ $this->form }}

    <x-filament::card>
      <h3 class="text-lg font-semibold text-white">{{ __('admin.recommendation_weights.summary.heading') }}</h3>
      <p class="mt-2 text-sm text-gray-300">{{ __('admin.recommendation_weights.summary.description') }}</p>
      <div class="mt-4 grid gap-4 md:grid-cols-2">
        @foreach($normalised as $variant => $weights)
          <div class="rounded-lg border border-gray-700/60 bg-gray-900/70 p-4">
            <div class="text-sm font-semibold text-sky-200">
              {{ __('admin.recommendation_weights.summary.variant', ['variant' => $variant]) }}
            </div>
            <dl class="mt-3 space-y-2 text-sm text-gray-100">
              <div class="flex justify-between">
                <dt>{{ __('admin.recommendation_weights.fields.pop') }}</dt>
                <dd>{{ number_format(($weights['pop'] ?? 0) * 100, 1) }}%</dd>
              </div>
              <div class="flex justify-between">
                <dt>{{ __('admin.recommendation_weights.fields.recent') }}</dt>
                <dd>{{ number_format(($weights['recent'] ?? 0) * 100, 1) }}%</dd>
              </div>
              <div class="flex justify-between">
                <dt>{{ __('admin.recommendation_weights.fields.pref') }}</dt>
                <dd>{{ number_format(($weights['pref'] ?? 0) * 100, 1) }}%</dd>
              </div>
            </dl>
          </div>
        @endforeach
      </div>
    </x-filament::card>

    <div class="flex justify-end">
      <x-filament::button type="submit">
        {{ __('admin.recommendation_weights.actions.save') }}
      </x-filament::button>
    </div>
  </form>
</x-filament-panels::page>
