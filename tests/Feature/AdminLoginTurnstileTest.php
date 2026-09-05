<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

class AdminLoginTurnstileTest extends TestCase
{
    use RefreshDatabase;

    protected function enableTurnstile(): void
    {
        Config::set('services.turnstile.site_key', 'test-site-key');
        Config::set('services.turnstile.secret_key', 'test-secret-key');
    }

    public function test_admin_login_is_rejected_without_a_turnstile_token(): void
    {
        $this->enableTurnstile();

        $admin = User::factory()->create([
            'is_admin' => true,
            'password' => bcrypt('password'),
        ]);

        Livewire::test(Login::class)
            ->set('data.email', $admin->email)
            ->set('data.password', 'password')
            ->set('turnstileToken', '')
            ->call('authenticate')
            ->assertHasErrors(['turnstileToken']);

        $this->assertGuest();
    }

    public function test_admin_login_succeeds_when_turnstile_verification_passes(): void
    {
        $this->enableTurnstile();

        \Illuminate\Support\Facades\Http::fake([
            \App\Support\Turnstile::VERIFY_URL => \Illuminate\Support\Facades\Http::response(['success' => true]),
        ]);

        $admin = User::factory()->create([
            'is_admin' => true,
            'password' => bcrypt('password'),
        ]);

        Livewire::test(Login::class)
            ->set('data.email', $admin->email)
            ->set('data.password', 'password')
            ->set('turnstileToken', 'valid-token')
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_login_page_renders_the_turnstile_widget_when_enabled(): void
    {
        $this->enableTurnstile();

        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('challenges.cloudflare.com/turnstile', false);
    }
}
