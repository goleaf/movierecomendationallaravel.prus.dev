<x-filament-panels::page>
  <form wire:submit.prevent="submit" class="space-y-6">
    {{ $this->form }}

    <div class="flex justify-end">
      <x-filament::button type="submit" color="primary">
        Save weights
      </x-filament::button>
    </div>
  </form>
</x-filament-panels::page>
