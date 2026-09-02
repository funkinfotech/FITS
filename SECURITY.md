# Security hardening

This documents the application-level protections in this repo and the
server-level steps that must be done on the host (`/var/www/support.funkinfotech.com`).

## What's enforced in the app

| Area | Protection |
|------|-----------|
| Registration | Public self-registration is **disabled**. Create client accounts in the Filament admin (`/admin` → Users). |
| Login brute force | `App\Livewire\Forms\LoginForm` throttles to 5 failed attempts per email+IP, plus a `throttle:20,1` route limit. Filament's panel login has its own 5/min throttle. |
| Password reset abuse | `forgot-password` / `reset-password` routes limited to `throttle:6,1`. |
| Bot signups / credential stuffing | Cloudflare Turnstile on the login and forgot-password forms (`App\Support\Turnstile`). Disabled automatically when keys are absent. |
| Ticket/comment endpoints | All behind `auth`, rate-limited, with per-record ownership checks (owner or admin only). |
| Response headers | `App\Http\Middleware\SecurityHeaders` — HSTS (https only), CSP, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`. |
| Real client IP | `bootstrap/app.php` trusts Cloudflare proxy ranges so rate limiting keys off the true client IP. |

### Turnstile setup

1. Cloudflare dashboard → Turnstile → add a widget for `support.funkinfotech.com`.
2. Put the keys in the server `.env`:
   ```
   TURNSTILE_SITE_KEY=0x4AAAAAAA...
   TURNSTILE_SECRET_KEY=0x4AAAAAAA...
   ```
3. `php artisan config:cache`.

Leaving the keys blank disables the check (local dev / CI).

## Server-level checklist (do on the host)

### 1. Production environment — verify the server `.env`
```
APP_ENV=production
APP_DEBUG=false          # critical — debug=true leaks stack traces + env vars
APP_URL=https://support.funkinfotech.com
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
```
Then: `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
The repo `.env` currently shows `APP_ENV=local` / `APP_DEBUG=true` — make sure the
deployed one does **not**.

### 2. Put Cloudflare in front (proxied DNS, orange cloud)
- Enable **Always Use HTTPS** and **HSTS**.
- SSL/TLS mode: **Full (strict)**.
- Firewall / WAF:
  - Managed Ruleset: on.
  - Rate-limiting rule on `/login`, `/admin/login`, `/forgot-password` (e.g. 10 req/min/IP → block 1h).
  - Optional: "Under Attack" / Bot Fight Mode, or a WAF rule challenging known bad ASNs.
- **Lock the origin to Cloudflare**: only accept :80/:443 from Cloudflare IP ranges
  (https://www.cloudflare.com/ips/) at the firewall (ufw) or in Apache. Otherwise
  attackers bypass Cloudflare by hitting the origin IP directly, defeating Turnstile
  and the WAF.

### 3. Host firewall (ufw)
```
ufw default deny incoming
ufw allow from <your-ip> to any port 22 proto tcp   # SSH: restrict to your IP if possible
ufw allow 443/tcp
ufw allow 80/tcp
ufw enable
```
Restrict 80/443 to Cloudflare ranges (see above) once the proxy is confirmed working.

### 4. fail2ban
```
apt install fail2ban
```
`/etc/fail2ban/jail.local`:
```
[sshd]
enabled = true
maxretry = 4
bantime = 1h

[apache-auth]
enabled = true

[apache-badbots]
enabled = true

[apache-noscript]
enabled = true
```
Add a custom filter for Laravel login failures if you log them (watch
`storage/logs/laravel.log` for the `Illuminate\Auth\Events\Lockout` / failed-login lines).

### 5. SSH hardening (`/etc/ssh/sshd_config`)
```
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
```
The deploy uses a dedicated key (`SERVER_SSH_KEY` GitHub secret) — make sure that
key's user has only the access it needs and rotate it if it was ever shared.

### 6. Apache
- Confirm `AllowOverride` lets `public/.htaccess` run, docroot is `public/`, and
  nothing above `public/` is web-reachable (no `.env`, `.git`, `storage/`).
- `ServerTokens Prod` and `ServerSignature Off`.
- Block dotfiles:
  ```apache
  <FilesMatch "^\.">
      Require all denied
  </FilesMatch>
  ```
- Confirm `.git/` is not served: `curl -I https://support.funkinfotech.com/.git/config` → 403/404.

### 7. Filament admin
- Consider a Cloudflare Access policy (email OTP / Google) in front of `/admin` for
  a second auth factor with zero app code.
- Keep `filament/filament` and `laravel/framework` patched (`composer outdated`).

### 8. Ongoing
- `composer audit` in CI.
- Log review: watch for spikes of 401/403/429 in Cloudflare analytics.
- Back up the DB off-box.
