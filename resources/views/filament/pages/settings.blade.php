<x-filament-panels::page>
    <form wire:submit="save">
        <div class="space-y-6">
            {{ $this->form }}
        </div>

        <div class="flex justify-end" style="margin-top: 2rem;">
            <x-filament::button type="submit" color="primary">
                Save Settings
            </x-filament::button>
        </div>

        <x-filament-actions::modals />
    </form>
</x-filament-panels::page>
