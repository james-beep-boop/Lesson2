# DreamHost Shared Hosting — Laravel/Filament Deployment Reference

This document captures every DreamHost-specific quirk discovered during real deployments. Copy the relevant sections into a new project's `CLAUDE.md` or `deployment.md` to avoid repeating the same debugging.

Verified against: PHP 8.4, Laravel 13, Filament 5, Livewire 4, MariaDB on DreamHost shared hosting (2026-03-22).

---

## PHP

**The shell `php` binary is always 8.2, regardless of the panel setting.**

The per-domain PHP version selected in the DreamHost panel only affects web requests served by Apache. SSH sessions always default to system PHP (8.2). Add the right version to PATH in `~/.bashrc`:

```bash
echo 'export PATH=/usr/local/php84/bin:$PATH' >> ~/.bashrc
source ~/.bashrc
```

Available paths follow this pattern:
- PHP 8.4: `/usr/local/php84/bin/php`
- PHP 8.3: `/usr/local/php83/bin/php`

Use full paths in cron jobs:
```
* * * * * /usr/local/php84/bin/php /home/username/myapp/artisan schedule:run >> /dev/null 2>&1
```

---

## Composer

**Composer is not installed. Install it manually once per account.**

```bash
cd ~
curl -sS https://getcomposer.org/installer | /usr/local/php84/bin/php
# Creates ~/composer.phar

cat > ~/composer.sh << 'EOF'
#!/bin/bash
/usr/local/php84/bin/php "$HOME/composer.phar" "$@"
EOF
chmod +x ~/composer.sh
```

Use `~/composer.sh install ...` in place of `composer install`. The same phar works for all apps on the account.

---

## Database

**`DB_HOST` is never `localhost`.**

DreamHost MySQL runs on a separate host. The correct hostname is your database's dedicated host, visible in the DreamHost panel under Databases. It is typically `mysql.yourdomain.com`. Using `localhost` causes a connection refused error.

```ini
DB_CONNECTION=mysql
DB_HOST=mysql.yourdomain.com   # NOT localhost
DB_PORT=3306
```

**MariaDB version may differ from Docker.**

DreamHost runs MariaDB 10.x (exact version varies). Avoid migration features that require MariaDB 11+ or PostgreSQL-only features (partial/filtered unique indexes). Write migrations to be compatible with MariaDB 10.6+.

---

## Node.js / Frontend Assets

**There is no Node.js on DreamHost shared hosting.**

You cannot run `npm install`, `npm run build`, `npx`, or Vite on the server. All frontend assets must be compiled locally or in CI before deployment.

Workflow:
```bash
# On your Mac:
npm install
npm run build
rsync -avz --delete public/build/ username@yourdomain.com:~/myapp/public/build/
```

Alternatively, use a CI pipeline (GitHub Actions) that runs the build and uploads `public/build/` to DreamHost via rsync.

**Never commit compiled assets to the repository.** Add `public/build/` to `.gitignore` and transfer them out-of-band.

---

## Livewire

**Livewire JS assets must be published after the initial clone.**

`composer install` does not publish Livewire's JS files. Without this step, `livewire.js` returns 404 and all Livewire components are broken.

```bash
/usr/local/php84/bin/php artisan livewire:publish --assets
```

Run once after the initial clone. The files persist across subsequent deploys; do not re-run on every update.

The files land in `public/vendor/livewire/`. They are served directly by Apache and do not need the Laravel router.

---

## APP_KEY and Config Cache

**Always generate APP_KEY on the server. Always clear the config cache after editing `.env`.**

```bash
/usr/local/php84/bin/php artisan key:generate
/usr/local/php84/bin/php artisan config:clear
```

If `APP_KEY` is blank, Laravel cannot encrypt sessions or cookies. The symptom is a login form that silently redirects back to itself with no error message — one of the most confusing symptoms possible, because no error is displayed.

Whenever you edit `.env`, the config cache will serve stale values until cleared. If a setting appears to have no effect, run `config:clear` before investigating further.

---

## Trusted Proxies

**DreamHost routes web traffic through a load balancer.**

Without trusted proxy configuration, Laravel cannot determine the real client IP or HTTPS protocol. The practical symptom is login redirect loops: Laravel thinks the request is HTTP, issues a redirect to HTTPS, which arrives again as HTTP from the proxy, and the loop continues.

Add to `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: '*');
})
```

This is required for all Laravel apps on DreamHost shared hosting.

---

## Sessions and Cache

**No Redis. No Memcached. File drivers only.**

```ini
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

`sync` queue means jobs run inline in the HTTP request. This is fine for low-traffic apps but means long-running jobs (e.g. AI API calls) block the request. Design accordingly — use streaming responses for anything that could take more than a few seconds.

---

## Storage Permissions

**PHP runs as your SSH user, not `www-data`.**

DreamHost uses suEXEC — the web process runs as the domain owner, the same user you SSH in as. Standard `755`/`644` Laravel advice applies, but `www-data` is irrelevant.

```bash
chmod -R 775 storage bootstrap/cache
```

If writes fail, check ownership first:
```bash
ls -la storage/
```

If something else owns `storage/`, fix the owner — not the mode bits.

---

## `.htaccess` — What Works and What Doesn't

**Apache/FastCGI, not Apache/mod_php.**

DreamHost shared hosting uses FastCGI (mod_fcgid) to run PHP. This matters for `.htaccess`:

| Directive | Works? | Notes |
|---|---|---|
| `RewriteRule` / `mod_rewrite` | Yes | Enabled by default; Laravel routing works |
| HTTPS redirect via `RewriteCond %{HTTPS} off` | Yes | Standard approach works |
| `php_value` | **No** | mod_php only — causes 500 on FastCGI |
| `php_flag` | **No** | Same — causes 500 |
| `AddHandler application/x-httpd-php` | Avoid | Can conflict with FastCGI setup |

**Do not use `php_value` or `php_flag` in `.htaccess`.** If you need to change a PHP ini setting (e.g. `max_execution_time`), use `ini_set()` in PHP code or a `php.ini`/`.user.ini` file in the app root.

Example — increasing max execution time for AI calls:
```php
// In the relevant controller or service method:
set_time_limit(60);
```

---

## Git on DreamHost

**DreamHost creates untracked files in `public/`.**

DreamHost automatically creates `public/.dh-diag` and `public/favicon.gif`. These are untracked and harmless. Any deploy script dirty-check should ignore untracked files:

```bash
# Check for modified tracked files only (ignore untracked):
git status --porcelain | grep -v '^??'
```

**Force-pushed branches cause divergence.**

If you rewrite history on GitHub (force push), the DreamHost clone will have diverged local commits and `git pull` will fail. Fix with:

```bash
git fetch origin && git reset --hard origin/main
```

Use `git reset --hard origin/$BRANCH` in deploy scripts rather than `git pull --ff-only` to handle this automatically.

---

## Cron Jobs

Set up scheduled tasks via the DreamHost panel (Goodies → Cron Jobs) or via SSH. Always use the full PHP path:

```
* * * * * /usr/local/php84/bin/php /home/username/myapp/artisan schedule:run >> /dev/null 2>&1
```

There are no queue workers — do not attempt to set up `php artisan queue:work` as a cron job on shared hosting. It will be killed by DreamHost's process limits. Use `QUEUE_CONNECTION=sync` instead, or upgrade to a VPS.

---

## HTTPS

DreamHost provides free Let's Encrypt certificates configured in the panel (Websites → Manage Websites → HTTPS). Enable it per domain — it is not on by default.

Force HTTPS in `public/.htaccess`:
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

This must come before Laravel's routing rules in `.htaccess`.

---

## Default Laravel/Filament Gotchas

These are not DreamHost-specific but are commonly hit on first deployments:

**Laravel's default welcome route overrides Filament's root path.**

`routes/web.php` ships with:
```php
Route::get('/', function () { return view('welcome'); });
```

This prevents Filament from serving `/`. Remove it. Filament registers its own root path.

**Password hashing in seeders with `hashed` model cast.**

If your `User` model has `'password' => 'hashed'` in `$casts`, do not call `bcrypt()` or `Hash::make()` in seeders — the cast does it automatically. Passing an already-hashed value results in a double-hashed password that will never match.

```php
// Wrong:
User::create(['password' => bcrypt('secret')]);
// Right:
User::create(['password' => 'secret']);
```

---

## Quick Reference — First-Time DreamHost Setup

Checklist for a brand-new Laravel app on DreamHost:

```
[ ] Install Composer phar + wrapper script (once per account)
[ ] Add PHP 8.4 to ~/.bashrc PATH (once per account)
[ ] Create database in DreamHost panel, note host/name/user/password
[ ] Clone app to ~/myapp
[ ] Set domain document root to ~/myapp/public in DreamHost panel
[ ] Create ~/myapp/.env from .env.example
    [ ] APP_ENV=production, APP_DEBUG=false
    [ ] DB_CONNECTION=mysql, DB_HOST=mysql.yourdomain.com
    [ ] CACHE_STORE=file, SESSION_DRIVER=file, QUEUE_CONNECTION=sync
[ ] php artisan key:generate
[ ] php artisan config:cache
[ ] Build assets locally (npm run build) and rsync to ~/myapp/public/build/
[ ] ~/composer.sh install --no-dev --optimize-autoloader
[ ] php artisan migrate --force
[ ] php artisan db:seed --force
[ ] php artisan storage:link
[ ] php artisan livewire:publish --assets  (if using Livewire)
[ ] chmod -R 775 storage bootstrap/cache
[ ] Enable HTTPS in DreamHost panel
[ ] Set up cron job for schedule:run
[ ] Test login in incognito window
```

---

*Last updated: 2026-03-22.*
