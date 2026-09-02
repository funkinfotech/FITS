<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Server-side verification for Cloudflare Turnstile tokens.
 *
 * https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
 */
class Turnstile
{
    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Whether Turnstile is configured. When it is not, verification is skipped
     * so local/dev/CI environments keep working without keys.
     */
    public static function enabled(): bool
    {
        return filled(config('services.turnstile.site_key'))
            && filled(config('services.turnstile.secret_key'));
    }

    /**
     * Validate a widget response token against Cloudflare.
     */
    public static function verify(?string $token, ?string $ip = null): bool
    {
        if (! static::enabled()) {
            return true;
        }

        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post(static::VERIFY_URL, [
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]);
        } catch (\Throwable $e) {
            report($e);

            return false;
        }

        return $response->successful() && $response->json('success') === true;
    }
}
