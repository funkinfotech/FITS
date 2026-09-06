{{--
    The "…" menu at the end of the top bar. Lists modules the user has hidden
    (as links, so they stay reachable) and a checklist to show/hide each one.
    State lives per user in users.navigation_preferences; each toggle posts to
    the panel route and reloads.

    @var \Illuminate\Support\Collection $items
    @var \Illuminate\Support\Collection $hiddenItems
--}}
@php($toggleUrl = route('filament.admin.navigation.toggle'))

<div class="fi-nav-customizer hidden lg:flex">
    <x-filament::dropdown placement="bottom-end" teleport width="xs">
        <x-slot name="trigger">
            <x-filament::icon-button
                icon="heroicon-m-ellipsis-horizontal"
                color="gray"
                label="Customize navigation"
            />
        </x-slot>

        @if ($hiddenItems->isNotEmpty())
            <x-filament::dropdown.header>Hidden</x-filament::dropdown.header>

            <x-filament::dropdown.list>
                @foreach ($hiddenItems as $item)
                    <x-filament::dropdown.list.item
                        tag="a"
                        :href="$item['url']"
                        :icon="$item['icon'] ?: 'heroicon-o-square-2-stack'"
                    >
                        {{ $item['label'] }}
                    </x-filament::dropdown.list.item>
                @endforeach
            </x-filament::dropdown.list>
        @endif

        <x-filament::dropdown.header>Show in navigation</x-filament::dropdown.header>

        <x-filament::dropdown.list>
            @foreach ($items as $item)
                <form method="POST" action="{{ $toggleUrl }}" style="display: contents">
                    @csrf
                    <input type="hidden" name="key" value="{{ $item['key'] }}">

                    <x-filament::dropdown.list.item
                        tag="button"
                        type="submit"
                        :icon="$item['isHidden'] ? 'heroicon-o-eye-slash' : 'heroicon-m-check'"
                        :color="$item['isHidden'] ? 'gray' : 'primary'"
                    >
                        {{ $item['label'] }}
                    </x-filament::dropdown.list.item>
                </form>
            @endforeach
        </x-filament::dropdown.list>
    </x-filament::dropdown>
</div>
