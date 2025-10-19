<x-filament::section :heading="$heading">
    <div
        x-data="{ tab: '{{ $tabs[0]['key'] ?? '' }}' }"
        class="space-y-4"
    >
        <div class="flex flex-wrap gap-2">
            @foreach($tabs as $tab)
                <button
                    type="button"
                    x-on:click="tab='{{ $tab['key'] }}'"
                    :class="tab === '{{ $tab['key'] }}' ? 'bg-primary-600 text-white' : 'bg-gray-800/80 text-gray-300 hover:bg-gray-700/80'"
                    class="px-3 py-1.5 rounded text-sm flex items-center gap-2 transition"
                >
                    <x-filament::icon :icon="$tab['icon']" class="w-4 h-4" />
                    <span>{{ $tab['label'] }}</span>
                </button>
            @endforeach
        </div>

        @foreach($tabs as $tab)
            <div
                x-show="tab === '{{ $tab['key'] }}'"
                x-cloak
                class="space-y-4"
            >
                @foreach($tab['widgets'] as $index => $widgetClass)
                    @livewire($widgetClass, ['widgetId' => $widgetId.'-'.$tab['key'].'-'.$index], key($widgetId.'-'.$tab['key'].'-'.$index))
                @endforeach
            </div>
        @endforeach
    </div>
</x-filament::section>
