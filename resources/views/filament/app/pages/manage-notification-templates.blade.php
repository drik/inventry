<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->taskAssignedForm }}

        {{ $this->taskCompletedForm }}

        {{ $this->userInvitationForm }}

        <div class="mt-6 flex items-center gap-x-3">
            <x-filament::button type="submit">
                Save Templates
            </x-filament::button>

            <x-filament::button color="gray" wire:click="resetToDefaults" type="button">
                Reset to Defaults
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
