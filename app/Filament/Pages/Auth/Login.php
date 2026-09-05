<?php

namespace App\Filament\Pages\Auth;

use App\Support\Turnstile;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Validation\ValidationException;

/**
 * Adds Cloudflare Turnstile verification to Filament's stock login page,
 * matching the same anti-bot / brute-force protection used on the public
 * login and password-reset forms.
 */
class Login extends BaseLogin
{
    public string $turnstileToken = '';

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        if (! Turnstile::verify($this->turnstileToken, request()->ip())) {
            throw ValidationException::withMessages([
                'turnstileToken' => __('Please complete the verification challenge.'),
            ]);
        }

        $data = $this->form->getState();

        if (! Filament::auth()->attempt($this->getCredentialsFromFormData($data), $data['remember'] ?? false)) {
            $this->throwFailureValidationException();
        }

        $user = Filament::auth()->user();

        if (
            ($user instanceof FilamentUser) &&
            (! $user->canAccessPanel(Filament::getCurrentPanel()))
        ) {
            Filament::auth()->logout();

            $this->throwFailureValidationException();
        }

        session()->regenerate();

        return app(LoginResponse::class);
    }
}
