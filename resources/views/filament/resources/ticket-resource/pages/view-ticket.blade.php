<x-filament-panels::page>
    {{ $this->infolist }}

    <div class="mt-8">
        <x-filament::section heading="Conversation">
            @include('filament.tickets.conversation-timeline', ['record' => $record])
        </x-filament::section>
    </div>
</x-filament-panels::page>