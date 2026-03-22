# ARES Lesson Repository — Troubleshooting Log

This document records problems encountered during the first production deployment to DreamHost (sheql.com) in March 2026, what they turned out to be, and how they were fixed. Future deployments should be much smoother now that these are understood.

---

## 1. `npm` not found on Mac during asset build

**Symptom:** `zsh: command not found: npm` when running `npm run build` locally before uploading assets.

**Cause:** Node.js was not installed on the Mac Mini.

**Fix:**
```bash
brew install node
npm install
npm run build
```

**Note:** This is a one-time local setup step. DreamHost cannot run npm, so assets must be compiled locally first and uploaded via rsync.

---

## 2. Composer not found on DreamHost

**Symptom:** `bash: composer: command not found` when trying to run `composer install` on DreamHost.

**Cause:** DreamHost shared hosting does not include Composer in PATH.

**Fix:** Install Composer manually as a phar and create a wrapper script:
```bash
curl -sS https://getcomposer.org/installer | /usr/local/php84/bin/php
cat > ~/composer.sh << 'EOF'
#!/bin/bash
/usr/local/php84/bin/php "$HOME/composer.phar" "$@"
EOF
chmod +x ~/composer.sh
```

Then pass `COMPOSER_BIN=~/composer.sh` when running the deploy script:
```bash
COMPOSER_BIN=~/composer.sh bash ~/Lesson2/UPDATE_SITE.sh
```

---

## 3. Wrong PHP version in shell (8.2 instead of 8.4)

**Symptom:** `php -v` on DreamHost showed PHP 8.2 even though the domain was set to PHP 8.4 in the panel.

**Cause:** The DreamHost shell's default `php` binary is always 8.2 regardless of the per-domain panel setting. The panel setting only affects what Apache uses for web requests.

**Fix:** Add PHP 8.4 to the shell PATH:
```bash
echo 'export PATH=/usr/local/php84/bin:$PATH' >> ~/.bashrc
source ~/.bashrc
```

The deploy script detects `/usr/local/php84/bin/php` automatically and does not require this.

---

## 4. DB_CONNECTION set to sqlite by default

**Symptom:** Migrations appeared to succeed but the site showed no data. Subsequent `php artisan tinker` queries returned nothing.

**Cause:** Laravel's default `.env.example` uses `DB_CONNECTION=sqlite`. The `.env` file was created from this template and never updated to `mysql`.

**Fix:** In `.env` on DreamHost:
```ini
DB_CONNECTION=mysql
DB_HOST=mysql.sheql.com
```

Then run `php artisan config:clear` and re-run migrations.

**Note:** The MySQL hostname on DreamHost is `mysql.sheql.com`, **not** `localhost`. DreamHost MySQL runs on a separate host; `localhost` causes connection refused.

---

## 5. APP_KEY missing — "MissingAppKeyException"

**Symptom:** Laravel showed a cryptic error about headers already sent, or silently redirected every login attempt back to the login page. Laravel logs contained `MissingAppKeyException`.

**Cause:** `APP_KEY` was blank in `.env`. Without an app key, Laravel cannot encrypt sessions or cookies, so login silently fails — the session can never be committed.

**Fix:**
```bash
/usr/local/php84/bin/php artisan key:generate
/usr/local/php84/bin/php artisan config:clear
```

**Important:** After generating the key, always clear the config cache. If a stale cache exists, the new key is not picked up even though it is in `.env`.

---

## 6. Config cache not reading updated .env values

**Symptom:** Changes to `.env` (ADMIN_PASSWORD, APP_KEY, DB_HOST) appeared to have no effect even after editing the file.

**Cause:** A previous `config:cache` run had frozen the config. Artisan reads the cache, not `.env` directly, when the cache exists.

**Fix:** Always run after any `.env` edit:
```bash
/usr/local/php84/bin/php artisan config:clear
```

Or to cache the new values:
```bash
/usr/local/php84/bin/php artisan config:cache
```

---

## 7. Login silently redirects back to the login page

**Symptom:** Entering valid credentials on `/login` redirected straight back to the login form with no error message.

**Cause (primary):** `APP_KEY` was missing (see issue 5). Without a key, sessions cannot be written, so the "you are now logged in" state is never persisted.

**Cause (secondary):** DreamHost routes traffic through a load balancer. Without trusted proxy configuration, Laravel could not determine the real HTTPS protocol, which broke session cookie `SameSite` / `Secure` attributes.

**Fix for primary:** Generate `APP_KEY` (see issue 5).

**Fix for secondary:** Add to `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: '*');
})
```

Commit and deploy this change. Until it was committed and deployed, the proxy fix had no effect.

---

## 8. Livewire JS returns 404

**Symptom:** Browser network tab showed `GET /vendor/livewire/livewire.js` returning 404. All Livewire components (forms, tables) were broken or missing on the page.

**Cause:** Livewire 4 requires its JS assets to be published into `public/vendor/livewire/`. This is a one-time step not covered by the standard `composer install` or `npm run build` workflow.

**Fix:**
```bash
/usr/local/php84/bin/php artisan livewire:publish --assets
```

Run this once after the initial clone. The deploy script does not re-run it on subsequent deploys (the files persist).

---

## 9. Laravel welcome page at `/` instead of Filament login

**Symptom:** Visiting `https://www.sheql.com` showed the Laravel "Laravel" splash/welcome page instead of the Filament login form.

**Cause:** `routes/web.php` contained the default Laravel welcome route:
```php
Route::get('/', function () {
    return view('welcome');
});
```
This route took priority over Filament's root path registration.

**Fix:** Remove the welcome route from `routes/web.php`. Filament registers `/` itself.

---

## 10. `php_value output_buffering` in .htaccess causes 500

**Symptom:** Adding `php_value output_buffering 4096` to `public/.htaccess` caused a 500 Internal Server Error across the entire site.

**Cause:** DreamHost shared hosting uses FastCGI (mod_fcgid), not mod_php. `php_value` directives in `.htaccess` are a mod_php feature and are not supported under FastCGI. Apache rejects the directive and returns 500.

**Fix:** Remove the `php_value` line from `.htaccess`. Do not attempt to set PHP ini values via `.htaccess` on DreamHost.

---

## 11. Deploy script reports "local changes" due to DreamHost auto-created files

**Symptom:** Running `UPDATE_SITE.sh` on DreamHost reported:
```
ERROR: /home/david_sheql/Lesson2 has local changes.
```

**Cause:** DreamHost automatically creates files in the `public/` directory: `public/.dh-diag` and `public/favicon.gif`. These are untracked files (`??` in `git status`), not modified tracked files, so they do not represent real local changes.

**Fix (in deploy script):** The dirty check was updated to ignore untracked files:
```bash
git status --porcelain | grep -v '^??'
```

If you still see this error, check whether a tracked file was actually modified:
```bash
git diff --name-only
git checkout -- public/.htaccess   # or whichever file was changed
```

---

## 12. Git divergence — "Not possible to fast-forward"

**Symptom:** Deploy script step [1/8] failed with:
```
fatal: Not possible to fast-forward, aborting.
```

**Cause:** The DreamHost repo's local `main` branch had commits that were not on `origin/main` (for example, because a force-push was used to rewrite history on GitHub). `pull --ff-only` refuses to proceed when the branches have diverged.

**Fix (immediate):**
```bash
cd ~/Lesson2 && git fetch origin && git reset --hard origin/main
```

**Fix (permanent, in deploy script):** The script now uses `git reset --hard origin/$BRANCH` instead of `pull --ff-only`. DreamHost should always be an exact mirror of GitHub.

---

## 13. Password double-hashing in seeders

**Symptom:** After seeding, no user could log in. Password check in Tinker failed for every seeded user.

**Cause:** The seeder called `bcrypt('password')` and stored the result in a User model with a `password` attribute cast as `hashed`. The `hashed` cast applies bcrypt automatically. The password was being hashed twice, producing a hash of a hash that never matched.

**Fix:** Pass plain password strings to the seeder; let the model cast handle hashing:
```php
// Wrong:
'password' => bcrypt('secret')
// Right:
'password' => 'secret'
```

---

## 14. Filament 5 — wrong Action namespaces

**Symptom:** Admin panel pages threw:
```
Class "Filament\Tables\Actions\EditAction" not found
Class "Filament\Tables\Actions\Action" not found
```

**Cause:** In Filament 5, table actions that were previously in `Filament\Tables\Actions\` have been reorganised. Some are now in `Filament\Actions\`.

**Fix:** Check the actual namespace in the installed vendor source:
```bash
find vendor/filament -name 'EditAction.php' 2>/dev/null
```

Update imports to match the installed package, not training-data assumptions. In this project the correct imports for `SubjectGradeResource` are in `Filament\Actions\`.

---

## 15. MethodNotAllowedHttpException on logout

**Symptom:** Visiting `/logout` directly in the browser threw `MethodNotAllowedHttpException`.

**Cause:** Laravel's logout route only accepts `POST` requests (CSRF protection). Visiting the URL directly sends a `GET`.

**Fix:** Use the Filament logout button in the UI, which submits the correct POST form. Do not navigate to `/logout` directly in the address bar.

---

## Summary

The root cause of most of the deployment time was three compounding issues:

1. **Missing APP_KEY** — made every login attempt silently fail, obscuring all other problems
2. **Config cache not refreshed** — changes to `.env` had no effect until the cache was cleared
3. **Livewire assets not published** — broken interactive UI until `livewire:publish --assets` was run

Once those three were resolved, the app worked. The remaining issues (PHP path, Composer path, trusted proxies, welcome route) were straightforward DreamHost quirks now documented in `deployment.md`.

---

*First deployment completed: 2026-03-22.*
