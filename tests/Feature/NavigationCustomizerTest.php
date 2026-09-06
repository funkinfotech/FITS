<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\NavigationController;
use App\Models\User;
use App\Support\PanelNavigation;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class NavigationCustomizerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function actingAsAdmin(array $attributes = []): User
    {
        $user = User::factory()->create(['is_admin' => true, ...$attributes]);

        $this->actingAs($user);

        return $user;
    }

    protected function toggle(User $user, string $key): void
    {
        $request = Request::create('/admin/navigation/toggle', 'POST', ['key' => $key]);
        $request->setUserResolver(fn () => $user);

        (new NavigationController())($request);

        $user->refresh();
        $this->actingAs($user);
    }

    public function test_all_modules_are_visible_by_default(): void
    {
        $this->actingAsAdmin();

        $labels = collect(PanelNavigation::visibleItems())->map->getLabel();

        $this->assertEqualsCanonicalizing(
            ['Dashboard', 'Tickets', 'Invoices', 'Companies', 'Contacts', 'Portal Users', 'Company Profile'],
            $labels->all(),
        );
    }

    public function test_modules_follow_the_preferred_order(): void
    {
        $this->actingAsAdmin();

        $labels = collect(PanelNavigation::visibleItems())->map->getLabel()->values()->all();

        $this->assertSame(
            ['Dashboard', 'Tickets', 'Invoices', 'Companies', 'Contacts', 'Portal Users', 'Company Profile'],
            $labels,
        );
    }

    public function test_hidden_modules_are_removed_from_the_bar_but_kept_in_the_menu(): void
    {
        $user = $this->actingAsAdmin(['navigation_preferences' => ['hidden' => ['Invoices']]]);

        $visible = collect(PanelNavigation::visibleItems())->map->getLabel();
        $this->assertNotContains('Invoices', $visible->all());

        $data = PanelNavigation::customizerViewData();
        $this->assertSame(['Invoices'], $data['hiddenItems']->pluck('label')->all());
        $this->assertTrue($data['items']->firstWhere('label', 'Invoices')['isHidden']);
        $this->assertFalse($data['items']->firstWhere('label', 'Tickets')['isHidden']);

        unset($user);
    }

    public function test_toggle_hides_then_shows_a_module(): void
    {
        $user = $this->actingAsAdmin();

        $this->toggle($user, 'Contacts');
        $this->assertSame(['hidden' => ['Contacts']], $user->navigation_preferences);
        $this->assertNotContains('Contacts', collect(PanelNavigation::visibleItems())->map->getLabel()->all());

        $this->toggle($user, 'Invoices');
        $this->assertEqualsCanonicalizing(['Contacts', 'Invoices'], $user->navigation_preferences['hidden']);

        $this->toggle($user, 'Contacts');
        $this->assertSame(['Invoices'], $user->navigation_preferences['hidden']);
        $this->assertContains('Contacts', collect(PanelNavigation::visibleItems())->map->getLabel()->all());
    }

    public function test_preferences_are_isolated_per_user(): void
    {
        $alice = $this->actingAsAdmin(['navigation_preferences' => ['hidden' => ['Invoices']]]);
        $bob = User::factory()->create(['is_admin' => true]);

        $this->actingAs($bob);

        $this->assertContains('Invoices', collect(PanelNavigation::visibleItems())->map->getLabel()->all());

        unset($alice);
    }

    public function test_toggle_route_requires_a_key(): void
    {
        $user = $this->actingAsAdmin();

        $request = Request::create('/admin/navigation/toggle', 'POST', []);
        $request->setUserResolver(fn () => $user);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        (new NavigationController())($request);
    }
}
