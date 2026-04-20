# ARES Lesson Library — Deployment Guide

This is the active deployment runbook for the current production workflow.

The important architectural rule is simple:

- local machine or CI builds and uploads the release
- DreamHost is a runtime host only
- DreamHost does not run `npm`, `composer install`, `git pull`, or build steps during deploy

Historical deployment incidents remain in [`troubleshooting.md`](/Users/jamesmcclelland/Documents/GitHub/Lesson2/troubleshooting.md).

---

## 1. Current Deployment Model

Production deploy is two-stage:

1. [`DEPLOY_SITE.sh`](/Users/jamesmcclelland/Documents/GitHub/Lesson2/DEPLOY_SITE.sh) runs locally
2. [`UPDATE_SITE.sh`](/Users/jamesmcclelland/Documents/GitHub/Lesson2/UPDATE_SITE.sh) runs on DreamHost

Local stage responsibilities:

- verify the working tree
- verify Laravel boots locally
- build a production `vendor/` bundle with `--no-dev`
- upload app code, production dependencies, and built frontend assets via `rsync`
- invoke the DreamHost finalizer

DreamHost finalizer responsibilities:

- enable maintenance mode
- run migrations
- create `public/storage` symlink if needed
- publish Filament assets
- rebuild Laravel caches
- write the deployed release SHA to `storage/app/version.txt`
- clear web OPcache
- lift maintenance mode

---

## 2. Prerequisites

### Local machine

Required locally:

- PHP 8.4+
- Composer
- Node.js / npm
- `ssh`
- `rsync`

You must have already run:

```bash
composer install
npm install
npm run build
```

### DreamHost

DreamHost must already have:

- the app directory created, usually `~/Lesson2`
- the domain document root pointing at `~/Lesson2/public`
- a valid `.env` file present on the server
- PHP 8.4 selected in the DreamHost panel
- database credentials configured

Important: DreamHost shared hosting has **no Node.js**. Do not rely on server-side asset builds.

---

## 3. First-Time Setup

### Local

Prepare the app:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

### DreamHost

Create the app directory and `.env`, then deploy from the local machine.

Required DreamHost `.env` settings include:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://www.sheql.com

DB_CONNECTION=mysql
DB_HOST=mysql.sheql.com
DB_PORT=3306
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

AI_SUGGESTIONS_ENABLED=false
ADMIN_PASSWORD=your_admin_password
```

Notes:

- `DB_HOST` must not be `localhost` on DreamHost
- `.env` is preserved on the server and is not overwritten by deploys

Run the first deploy locally:

```bash
bash ./DEPLOY_SITE.sh
```

If this is the first production install and seed data is needed:

```bash
ssh david_sheql@sheql.com "cd ~/Lesson2 && PHP_BIN=/usr/local/php84/bin/php php artisan db:seed --force"
```

Use `DemoSeeder` only outside production.

---

## 4. Normal Ongoing Deploy

From the local machine:

```bash
bash ./DEPLOY_SITE.sh
```

Useful overrides:

```bash
REMOTE_HOST=david_sheql@sheql.com
REMOTE_APP_DIR=~/Lesson2
REMOTE_SCRIPT=UPDATE_SITE.sh
PHP_BIN=/path/to/php
ALLOW_DIRTY=1
```

What the local script requires before it will proceed:

- clean working tree unless `ALLOW_DIRTY=1`
- local `vendor/autoload.php`
- dev packages still present locally
- `public/build/manifest.json`
- Laravel bootable via `artisan about`

---

## 5. DreamHost Runtime Quirks

These are the DreamHost-specific rules that matter for this app.

### PHP binary

DreamHost web requests use the panel-selected PHP version, but shell sessions may not.
Use the explicit binary path in server-side commands when needed:

```bash
/usr/local/php84/bin/php
```

### No Node.js

DreamHost shared hosting cannot run:

- `npm install`
- `npm run build`
- `npx`
- Vite build steps

All frontend assets must be built locally or in CI and uploaded.

### Composer on the server

The current deploy flow does **not** run Composer on DreamHost.
If you are ever doing manual recovery, prefer re-running the local deploy rather than improvising a server-side `composer install`.

### Database host

DreamHost MySQL is on a separate host. `DB_HOST` is not `localhost`.

### Sessions / cache / queues

Use file-based drivers:

```ini
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

There are no long-running queue workers on shared hosting.

### Trusted proxies

DreamHost sits behind a load balancer. Trusted proxy configuration is required or HTTPS/session behavior becomes unreliable.

### `.htaccess`

DreamHost uses Apache + FastCGI, not mod_php.

- `RewriteRule` works
- HTTPS redirect rules work
- `php_value` and `php_flag` in `.htaccess` do not

Do not use `php_value` or `php_flag` in `.htaccess`.

### Storage permissions

DreamHost runs PHP as the domain SSH user via suEXEC, not as `www-data`.

Expected fix if writes fail:

```bash
chmod -R 775 storage bootstrap/cache
```

If that does not solve it, check ownership first.

### Persistent MySQL connections

Do not enable persistent MySQL connections on DreamHost shared hosting. They can cause intermittent failures under FastCGI worker recycling.

### OPcache

CLI cache clears do not invalidate the web worker OPcache. `UPDATE_SITE.sh` already handles a web-triggered OPcache reset at the end of deploy.

---

## 6. Verification After Deploy

Basic checks:

1. open `https://www.sheql.com`
2. confirm the app loads and routes do not 500
3. log in as an authorized user
4. verify a Filament page loads
5. verify lesson-plan list and a version page load
6. verify one Livewire interaction works
7. verify one PDF route works if PDF features were touched

Optional server checks:

```bash
ssh david_sheql@sheql.com "cd ~/Lesson2 && /usr/local/php84/bin/php artisan about"
ssh david_sheql@sheql.com "cd ~/Lesson2 && cat storage/app/version.txt"
```

---

## 7. Recovery / Manual Finalization

If upload succeeded but finalization did not, rerun only the DreamHost finalizer:

```bash
ssh david_sheql@sheql.com "cd ~/Lesson2 && RELEASE_COMMIT=<git-sha> bash ./UPDATE_SITE.sh"
```

If assets are missing, rebuild locally and redeploy. Do not build assets on DreamHost.

If `vendor/` is missing or wrong, rerun the full local deploy. Do not manually compose a DreamHost-side recovery unless absolutely necessary.

If `.env` values changed, rerun the finalizer or at minimum clear and rebuild config cache:

```bash
ssh david_sheql@sheql.com "cd ~/Lesson2 && /usr/local/php84/bin/php artisan optimize:clear && /usr/local/php84/bin/php artisan config:cache"
```

---

## 8. Cron

Use a normal DreamHost cron for scheduled tasks:

```text
* * * * * /usr/local/php84/bin/php /home/david_sheql/Lesson2/artisan schedule:run >> /dev/null 2>&1
```

Do not try to run `queue:work` persistently on DreamHost shared hosting.
