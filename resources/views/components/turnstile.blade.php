@props(['model' => 'turnstileToken'])

@if (\App\Support\Turnstile::enabled())
    @once
        @assets
            <script>
                window.onloadTurnstileCallback = function () {
                    window.dispatchEvent(new Event('turnstile:loaded'));
                };
            </script>
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onloadTurnstileCallback&render=explicit"
                    async defer></script>
        @endassets
    @endonce

    <div wire:ignore class="mt-4"
         x-data="{
            widgetId: null,
            render() {
                if (!window.turnstile || this.widgetId !== null) return;
                this.widgetId = window.turnstile.render($refs.widget, {
                    sitekey: @js(config('services.turnstile.site_key')),
                    callback: (token) => $wire.set(@js($model), token, false),
                    'expired-callback': () => $wire.set(@js($model), '', false),
                    'error-callback': () => $wire.set(@js($model), '', false),
                });
            },
            reset() {
                if (this.widgetId !== null && window.turnstile) {
                    window.turnstile.reset(this.widgetId);
                }
                $wire.set(@js($model), '', false);
            }
         }"
         x-init="
            window.turnstile ? render() : window.addEventListener('turnstile:loaded', () => render(), { once: true });
            Livewire.hook('commit', ({ component, succeed }) => {
                if (component.id !== $wire.id) return;
                succeed(() => reset());
            });
         ">
        <div x-ref="widget"></div>
    </div>

    <x-input-error :messages="$errors->get($model)" class="mt-2" />
@endif
