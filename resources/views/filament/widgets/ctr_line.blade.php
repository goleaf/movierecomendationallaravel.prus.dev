<x-filament-widgets::widget>
  @if($svg)
    <div wire:ignore>{!! $svg !!}</div>
  @else
    <div class="text-sm text-gray-400">{{ __('admin.ctr.no_data') }}</div>
  @endif
</x-filament-widgets::widget>
