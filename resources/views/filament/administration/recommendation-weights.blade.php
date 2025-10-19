<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                {{ __('Recommendation Variants') }}
            </x-slot>

            <p class="text-sm text-gray-300">
                Tune the weighting of popularity, recency, and preference signals for each experiment variant.
            </p>
        </x-filament::section>

        {{ $this->form }}
    </div>
</x-filament-panels::page>
