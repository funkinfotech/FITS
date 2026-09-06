<?php

namespace App\Support;

use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Collection;

/**
 * Flattens the admin panel navigation into a single ordered list of "modules"
 * and applies each user's personal show/hide choices. Used by the panel's
 * navigation builder and by the "…" customize menu in the top bar
 * (see resources/views/filament/navigation-customizer.blade.php).
 */
class PanelNavigation
{
    /**
     * Preferred left-to-right order of modules in the bar. Anything not listed
     * (a newly built module) falls in after these, ordered by its own label.
     *
     * @var array<int, string>
     */
    protected static array $order = [
        'Dashboard',
        'Tickets',
        'Invoices',
        'Companies',
        'Contacts',
        'Portal Users',
        'Company Profile',
    ];

    /**
     * Every navigation item the current user may see, before their hide list is
     * applied, in bar order.
     *
     * @return Collection<int, NavigationItem>
     */
    public static function allItems(): Collection
    {
        $panel = Filament::getCurrentPanel();

        $items = collect();

        foreach ($panel->getPages() as $page) {
            if ($page::canAccess() && $page::shouldRegisterNavigation()) {
                $items->push(...$page::getNavigationItems());
            }
        }

        foreach ($panel->getResources() as $resource) {
            if ($resource::canAccess() && $resource::shouldRegisterNavigation()) {
                $items->push(...$resource::getNavigationItems());
            }
        }

        $items->push(...$panel->getNavigationItems());

        return $items
            ->filter(fn (NavigationItem $item): bool => $item->isVisible() && filled($item->getUrl()))
            ->unique(fn (NavigationItem $item): string => static::keyFor($item))
            ->sortBy(fn (NavigationItem $item): string => sprintf('%03d %s', static::orderIndex($item), $item->getLabel()))
            ->values();
    }

    /**
     * The navigation items to render in the bar for the current user.
     *
     * @return array<int, NavigationItem>
     */
    public static function visibleItems(): array
    {
        $hidden = static::hiddenKeys();

        return static::allItems()
            ->reject(fn (NavigationItem $item): bool => in_array(static::keyFor($item), $hidden, true))
            ->values()
            ->all();
    }

    /**
     * Data for the "…" customize menu.
     *
     * @return array{items: Collection<int, array<string, mixed>>, hiddenItems: Collection<int, array<string, mixed>>}
     */
    public static function customizerViewData(): array
    {
        $hidden = static::hiddenKeys();

        $items = static::allItems()->map(fn (NavigationItem $item): array => [
            'key' => static::keyFor($item),
            'label' => $item->getLabel(),
            'icon' => $item->getIcon(),
            'url' => $item->getUrl(),
            'isHidden' => in_array(static::keyFor($item), $hidden, true),
        ])->values();

        return [
            'items' => $items,
            'hiddenItems' => $items->where('isHidden', true)->values(),
        ];
    }

    /**
     * Stable identifier for an item in a user's stored preferences. The label
     * is unique across our modules and reads well in the JSON.
     */
    public static function keyFor(NavigationItem $item): string
    {
        return $item->getLabel();
    }

    /**
     * @return array<int, string>
     */
    public static function hiddenKeys(): array
    {
        $user = Filament::auth()->user();

        return array_values(array_filter(
            (array) data_get($user?->navigation_preferences, 'hidden', []),
            'is_string',
        ));
    }

    protected static function orderIndex(NavigationItem $item): int
    {
        $index = array_search($item->getLabel(), static::$order, true);

        return $index === false ? count(static::$order) : $index;
    }
}
