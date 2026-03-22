# ARES Lesson Repository — Deployment Guide

This document covers deployment for all intended environments. All hardware listed (Mac Mini, Radxa Rock 5B, NVIDIA DGX Spark) is assumed to have internet access unless the air-gapped scenario is explicitly noted.

---

## 1. Target Summary

| Target | Use case | Method |
|---|---|---|
| DreamHost shared hosting | Primary production | Direct file + SSH |
| Docker (internet) | Staging, VPS, local servers | Docker Compose |
| Docker (air-gapped) | Schools without reliable internet | Pre-built image on USB/LAN |
| Mac Mini | Local development or local school server | Laravel Herd (dev) or Docker (server) |
| Radxa Rock 5B | Local school server / staging | Docker on Ubuntu ARM64 |
| NVIDIA DGX Spark | Development, local LLM host, staging | Docker on Linux ARM64 |

---

## 2. Prerequisites — All Targets

These steps apply everywhere. Do them once before consulting the target-specific section.

### Required environment variables

Create a `.env` file (copy from `.env.example`) and set at minimum:

```ini
APP_NAME="ARES Lesson Repository"
APP_ENV=production         # or local for dev
APP_KEY=                   # php artisan key:generate
APP_DEBUG=false            # true in dev only
APP_URL=https://www.sheql.com

DB_CONNECTION=mysql
DB_HOST=mysql.sheql.com     # IMPORTANT: localhost will not work on DreamHost
DB_PORT=3306
DB_DATABASE=lesson2_db
DB_USERNAME=david_sheql
DB_PASSWORD=your_db_password    # set to your actual password

MAIL_MAILER=smtp           # or log for dev
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=noreply@sheql.com
MAIL_FROM_NAME="ARES Lesson Repository"

ADMIN_PASSWORD=            # Site Admin password — required for DatabaseSeeder

# AI suggestions — disabled by default; enable for demo
AI_SUGGESTIONS_ENABLED=false
AI_DEFAULT_PROVIDER=anthropic   # or ollama
ANTHROPIC_API_KEY=              # required if provider is anthropic
OLLAMA_BASE_URL=                # required if provider is ollama, e.g. http://100.x.x.x:11434
```

### Post-deployment commands

Run after every deployment (code push or fresh install):

```bash
composer install --no-dev --optimize-autoloader
npm ci                           # install frontend dependencies
npm run build                    # compile Vite/Tailwind/Filament assets
php artisan key:generate         # first install only
php artisan migrate --force
php artisan db:seed --force      # DatabaseSeeder only — System user + Site Admin
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link   # DreamHost and local dev only — do NOT run in Docker
```

> **Note for DreamHost:** DreamHost shared hosting does not provide Node.js. The preferred approach is a CI/CD pipeline that runs `npm run build` and uploads the compiled `public/build/` directory to the server as part of deployment. Do not commit compiled build artifacts to the repository.

For demo environments, additionally run:
```bash
php artisan db:seed --class=DemoSeeder
```

---

## 3. DreamHost Shared Hosting (Primary Production)

DreamHost shared hosting runs Apache, MariaDB, and shared PHP. No Docker, no root access, no persistent background processes.

### PHP version

DreamHost allows selecting a PHP version per domain. In the panel, set PHP to **8.4** (or the highest 8.x available).

**Important:** The default `php` binary in the DreamHost shell points to PHP 8.2, not 8.4. Add PHP 8.4 to your shell PATH permanently:

```bash
echo 'export PATH=/usr/local/php84/bin:$PATH' >> ~/.bashrc
source ~/.bashrc
php -v   # should now show 8.4.x
```

The `UPDATE_SITE.sh` deploy script detects this automatically and does not require it in PATH.

### Composer

Composer is not installed on DreamHost by default. Install it once as a `phar` and create a wrapper script:

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

Then set the `COMPOSER_BIN` variable when running the deploy script if Composer is not in PATH:

```bash
COMPOSER_BIN=~/composer.sh bash ~/Lesson2/UPDATE_SITE.sh
```

(The deploy script also checks for `~/composer.phar` automatically.)

### Database

Create a MySQL/MariaDB database in the DreamHost panel:
- Database name, username, and password are assigned in the panel
- Record these for your `.env` file

**Critical:** The MySQL hostname on DreamHost is **not** `localhost`. It is your domain's dedicated MySQL host, typically `mysql.sheql.com`. Using `localhost` causes a connection refused error because MySQL runs on a separate host.

### First-time setup

1. SSH into your DreamHost account
2. Clone the repo:
   ```bash
   cd ~
   git clone https://github.com/james-beep-boop/Lesson2.git Lesson2
   ```
3. In the DreamHost panel, set the domain's document root to `~/Lesson2/public`
4. Create `~/Lesson2/.env` with production credentials (see Section 2)
5. Build frontend assets locally and upload them (DreamHost has no Node.js):
   ```bash
   # On your Mac:
   cd /path/to/Lesson2
   npm install
   npm run build
   rsync -avz --delete public/build/ david_sheql@sheql.com:~/Lesson2/public/build/
   ```
6. On DreamHost, run the first-time commands:
   ```bash
   cd ~/Lesson2
   COMPOSER_BIN=~/composer.sh /usr/local/php84/bin/php artisan key:generate
   COMPOSER_BIN=~/composer.sh bash ~/Lesson2/UPDATE_SITE.sh
   /usr/local/php84/bin/php artisan db:seed --force
   /usr/local/php84/bin/php artisan livewire:publish --assets
   ```

**Why `livewire:publish --assets`?** Livewire 4 requires its JS assets to be published to `public/vendor/livewire/`. Without this step, `livewire.js` returns a 404 and all interactive components are broken. Run this once after the initial clone.

### Ongoing deployments

After every push to GitHub, run on DreamHost:

```bash
bash ~/Lesson2/UPDATE_SITE.sh
```

If the deploy script reports local changes (DreamHost creates `public/.dh-diag` and `public/favicon.gif` as untracked files, which the script ignores; but it will block on modified tracked files):

```bash
cd ~/Lesson2 && git checkout -- . && bash ~/Lesson2/UPDATE_SITE.sh
```

If the local repo has diverged from `origin/main` (can happen after a force-push):

```bash
cd ~/Lesson2 && git fetch origin && git reset --hard origin/main
bash ~/Lesson2/UPDATE_SITE.sh
```

If you need to rebuild frontend assets (Tailwind/Vite changes):

```bash
# On your Mac:
npm run build
rsync -avz --delete public/build/ david_sheql@sheql.com:~/Lesson2/public/build/
```

### Trusted proxies

DreamHost shared hosting routes traffic through a load balancer. Without trusted proxy configuration, Laravel cannot determine the real client IP or protocol, causing HTTPS redirect loops and session issues.

`bootstrap/app.php` already includes:
```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: '*');
})
```

This is required on DreamHost. Do not remove it.

### Apache `.htaccess`

`public/.htaccess` handles routing and HTTPS redirection. The deployed version includes:

```apache
# Force HTTPS
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

**Do not add `php_value output_buffering`** to `.htaccess` — it causes a 500 Internal Server Error on DreamHost's FastCGI/mod_fcgid setup.

### Cron job (queued jobs / scheduled tasks)

In the DreamHost panel, add a cron job:

```
* * * * * /usr/local/php84/bin/php /home/david_sheql/Lesson2/artisan schedule:run >> /dev/null 2>&1
```

### Storage permissions

On DreamHost, PHP runs as the SSH/domain user via suEXEC — not `www-data`. Set permissions accordingly:

```bash
chmod -R 775 storage bootstrap/cache
```

If writes fail, check ownership first (not just permissions):

```bash
/usr/local/php84/bin/php artisan tinker --execute="file_put_contents(storage_path('test-write.txt'), 'ok'); echo file_get_contents(storage_path('test-write.txt')); unlink(storage_path('test-write.txt'));"
```

### Required `.env` settings for DreamHost

```ini
APP_ENV=production
APP_DEBUG=false          # IMPORTANT: set to false before going live
APP_KEY=                 # generate with: php artisan key:generate
APP_URL=https://www.sheql.com

DB_CONNECTION=mysql
DB_HOST=mysql.sheql.com  # NOT localhost — DreamHost MySQL is on a separate host
DB_PORT=3306
DB_DATABASE=your_db_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

CACHE_STORE=file         # no Redis on shared hosting
SESSION_DRIVER=file      # no Redis on shared hosting
QUEUE_CONNECTION=sync    # no queue workers on shared hosting
```

After creating or editing `.env`, always clear the config cache:

```bash
/usr/local/php84/bin/php artisan config:clear
```

### Known DreamHost constraints

- No Supervisor or queue workers — use `QUEUE_CONNECTION=sync` (jobs run inline)
- No Redis — use `CACHE_STORE=file` and `SESSION_DRIVER=file`
- No Node.js — all Vite/Tailwind/Filament assets must be compiled locally or in CI and uploaded
- No partial unique indexes (MariaDB constraint) — accounted for in the data model
- Ollama is not reachable from DreamHost — use Anthropic as AI provider for production
- `php_value` directives in `.htaccess` for FastCGI settings can cause 500 errors — test carefully before committing
- DreamHost auto-creates files in `public/` (`public/.dh-diag`, `public/favicon.gif`) — these are untracked and harmless; the deploy script already ignores them

---

## 4. Docker (Internet-Connected)

Use Docker Compose for any self-hosted Linux environment (VPS, local school server, Radxa, DGX Spark). This is the recommended approach for any deployment you control.

### File structure

```
docker/
  nginx/
    default.conf
  php/
    Dockerfile
docker-compose.yml
```

### `docker/php/Dockerfile`

```dockerfile
FROM php:8.4-fpm

# System dependencies
RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libonig-dev \
    libxml2-dev libzip-dev libpq-dev mariadb-client \
    && docker-php-ext-install pdo pdo_mysql mbstring zip exif pcntl bcmath gd

# Node.js (for asset compilation)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .
RUN composer install --no-dev --optimize-autoloader
RUN npm ci && npm run build && rm -rf node_modules

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

COPY docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
```

### `docker/php/entrypoint.sh`

This script copies the compiled public assets from the image into the shared `public_assets` volume on every container start. This ensures the Nginx container always serves the assets from the current image, not a stale volume from a previous deployment.

```bash
#!/bin/sh
set -e

# Sync public assets from the image into the shared volume.
# Nginx reads from /var/www/public-shared; this keeps it current with each deploy.
cp -rp /var/www/public/. /var/www/public-shared/

exec "$@"
```

Update the production `docker-compose.yml` to use `/var/www/public-shared` as the shared path:

```yaml
# app service volumes:
volumes:
  - ./storage:/var/www/storage
  - public_assets:/var/www/public-shared   # entrypoint populates this on start

# web service volumes:
volumes:
  - public_assets:/var/www/public-shared:ro
  - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
```

Update `docker/nginx/default.conf` root to `/var/www/public-shared`.

### `docker/nginx/default.conf`

```nginx
server {
    listen 80;
    index index.php;
    root /var/www/public-shared;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

Two compose files are provided: one for development (bind mounts, live code reload) and one for production/air-gapped (image-based, no source bind mounts). **Do not use the dev compose in production.**

### `docker-compose.yml` (production / air-gapped)

The app image contains all source code. The only host mounts are `storage/` (persistent uploads/logs) and the database volume.

```yaml
services:
  app:
    image: ares-lessons:latest    # pre-built image; never builds from source here
    volumes:
      - ./storage:/var/www/storage                      # persistent uploads and logs
      - public_assets:/var/www/public-shared            # entrypoint populates this on start
    env_file: .env
    depends_on:
      - db

  web:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - public_assets:/var/www/public-shared:ro         # compiled assets from app image
      - ./storage/app/public:/var/www/public-shared/storage:ro  # user-uploaded files
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app

  db:
    image: mariadb:11
    environment:
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
    volumes:
      - db_data:/var/lib/mysql
    # no ports exposed externally in production

volumes:
  db_data:
  public_assets:
```

> **Note on `storage:link`:** Do **not** run `php artisan storage:link` in Docker production. The web container has no access to the app container's filesystem to follow a symlink. Instead, `./storage/app/public` is mounted directly into the web container at `/var/www/public-shared/storage`, making uploaded files accessible to Nginx without a symlink. The `storage:link` command remains valid for DreamHost and local dev (Herd) only.

### `docker-compose.dev.yml` (development only)

Bind-mounts the source tree for live editing. Run with:
```bash
docker compose -f docker-compose.dev.yml up -d
```

```yaml
services:
  app:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    volumes:
      - .:/var/www                    # live source bind mount
    env_file: .env
    depends_on:
      - db

  web:
    image: nginx:alpine
    ports:
      - "80:80"
    volumes:
      - .:/var/www                    # matches app for static file serving
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app

  db:
    image: mariadb:11
    environment:
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
    volumes:
      - db_data:/var/lib/mysql
    ports:
      - "3306:3306"

volumes:
  db_data:
```

### Build and start (internet-connected)

```bash
# Build the production image (assets compiled inside the image)
docker compose -f docker-compose.dev.yml build
docker tag ares-lessons-app:latest ares-lessons:latest

# Start the production stack
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
# Note: do NOT run storage:link in Docker — see note above
```

---

## 5. Docker — Air-Gapped / No Internet Access

For schools or sites with no reliable internet, build the Docker image where you have internet, package it, and carry it to the target machine.

### On a machine with internet access

The production `docker-compose.yml` has no `build:` directive — it expects a pre-built image. Use the dev compose file to build, then tag the result:

```bash
# Build using the dev compose file (which has build: directives)
docker compose -f docker-compose.dev.yml build
docker tag ares-lessons-app:latest ares-lessons:latest

# Save the tagged production image
docker save ares-lessons:latest | gzip > ares-lessons.tar.gz

# Also save MariaDB and Nginx dependency images
docker save mariadb:11 nginx:alpine | gzip > ares-lessons-deps.tar.gz
```

Copy `ares-lessons.tar.gz`, `ares-lessons-deps.tar.gz`, `docker-compose.yml`, and a prepared `.env` file to a USB drive or transfer via LAN.

### On the air-gapped target machine

```bash
# Load images
docker load < ares-lessons.tar.gz
docker load < ares-lessons-deps.tar.gz

# Copy your .env and docker-compose.yml into place, then:
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
# Note: do NOT run storage:link in Docker — uploaded files are served via direct volume mount
```

### Air-gapped update workflow

When pushing an update to an air-gapped site:

**On the internet-connected build machine:**
```bash
# Build new image and tag it
docker compose -f docker-compose.dev.yml build
docker tag ares-lessons-app:latest ares-lessons:latest

# Save only the updated app image
docker save ares-lessons:latest | gzip > ares-lessons-update.tar.gz
```
Ship `ares-lessons-update.tar.gz` to the target machine via USB or LAN.

**On the air-gapped target machine:**
```bash
# Load the new image (no --build — image is pre-built)
docker load < ares-lessons-update.tar.gz

# Restart only the app container with the new image
docker compose up -d --no-deps app

# Run any pending migrations
docker compose exec app php artisan migrate --force
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
```

---

## 6. Mac Mini

The Mac Mini is assumed to be Apple Silicon (M-series, ARM64) running macOS.

### For development

**Laravel Herd** is the simplest option — it bundles PHP 8.4, Nginx, and MySQL with a native macOS app, no terminal setup required.

```bash
# Install Herd from https://herd.laravel.com
# Then in the project directory:
herd link          # creates ares-lessons.test
herd open          # opens in browser
```

Herd manages PHP version, virtual hosts, and SSL certs automatically. Use the DemoSeeder to populate data.

To point Herd at PHP 8.4:
- Herd → PHP → select 8.4 for this site

### For running as a local school server

Use Docker (Section 4). Docker Desktop for Mac supports Apple Silicon natively.

```bash
# Install Docker Desktop from docker.com
docker compose up -d
```

The Mac Mini can serve the app on your local network at its IP address. If it is on Tailscale, it is reachable from anywhere on the network via its Tailscale IP.

To make the stack start automatically on login: Docker Desktop → Settings → General → "Start Docker Desktop when you log in."

---

## 7. Radxa Rock 5B

The Radxa Rock 5B runs Ubuntu 22.04 or 24.04 on ARM64 (Rockchip RK3588). It is a capable small server — up to 16 GB RAM, runs Docker well.

### Install Docker on Ubuntu ARM64

```bash
sudo apt update
sudo apt install -y ca-certificates curl gnupg
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
  | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
echo \
  "deb [arch=arm64 signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" \
  | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
sudo usermod -aG docker $USER
```

### Deploy the app

Clone the repo, add your `.env`, then follow the Docker Compose steps in Section 4. All images (PHP 8.4-fpm, MariaDB 11, Nginx Alpine) have ARM64 variants and will pull correctly.

### Auto-start on boot

```bash
sudo systemctl enable docker
```

Add a systemd service to bring the Compose stack up on boot:

```ini
# /etc/systemd/system/ares-lessons.service
[Unit]
Description=ARES Lessons Docker Compose
After=docker.service
Requires=docker.service

[Service]
WorkingDirectory=/home/youruser/ares-lessons
ExecStart=/usr/bin/docker compose up
ExecStop=/usr/bin/docker compose down
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable ares-lessons
sudo systemctl start ares-lessons
```

### Tailscale access

Install Tailscale on the Rock 5B:

```bash
curl -fsSL https://tailscale.com/install.sh | sh
sudo tailscale up
```

The Rock 5B will appear on your Tailscale network. Access the app at its Tailscale IP from any other device on the network.

---

## 8. NVIDIA DGX Spark

The DGX Spark runs Linux on ARM64 (Grace CPU). It is on Tailscale. The app and Ollama can run side by side.

### Deploy the app

Follow Section 4 (Docker Compose) — ARM64 images work without modification. The production `docker-compose.yml` expects a pre-built `ares-lessons:latest` image; build it first:

```bash
git clone https://github.com/your-org/ares-lessons.git
cd ares-lessons
cp .env.example .env
# Edit .env — set DB credentials, APP_KEY, and Ollama as AI provider (see Ollama section below)

# Build the production image (must be done before docker compose up)
docker compose -f docker-compose.dev.yml build
docker tag ares-lessons-app:latest ares-lessons:latest

# Start the production stack
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
# Note: do NOT run storage:link in Docker — uploaded files are served via direct volume mount
```

### Running Ollama on the DGX Spark

```bash
# Install Ollama
curl -fsSL https://ollama.com/install.sh | sh

# Pull a model suited to lesson plan editing suggestions
ollama pull llama3.1:70b      # recommended — high quality, fits comfortably in 128 GB
# or for faster responses at slightly lower quality:
ollama pull mistral:7b

# Start Ollama (persists via systemd after install)
ollama serve
```

Confirm it is running:
```bash
curl http://localhost:11434/api/tags
```

**If the app runs directly on the DGX Spark (no Docker):**
```ini
AI_SUGGESTIONS_ENABLED=true
AI_DEFAULT_PROVIDER=ollama
OLLAMA_BASE_URL=http://localhost:11434
```

**If the app runs in Docker on the DGX Spark:**
`localhost` inside a container refers to the container itself, not the host. Use `host.docker.internal` instead. Add `extra_hosts` to the **`app` service** in `docker-compose.yml` — this is a mandatory addition when Ollama runs on the host:

```yaml
services:
  app:
    image: ares-lessons:latest
    extra_hosts:
      - "host.docker.internal:host-gateway"   # allows container to reach host Ollama
    volumes:
      - ./storage:/var/www/storage
      - public_assets:/var/www/public-shared
    env_file: .env
    depends_on:
      - db
```

```ini
OLLAMA_BASE_URL=http://host.docker.internal:11434
```

**If the app runs on a different machine on the Tailscale network:**
Use the DGX Spark's Tailscale IP directly:
```ini
OLLAMA_BASE_URL=http://100.x.x.x:11434
```

### Ollama from other Tailscale devices

By default Ollama listens on `127.0.0.1:11434` (loopback only). To allow other Tailscale devices to query it:

```bash
# Edit Ollama's systemd service to listen on the Tailscale interface
sudo systemctl edit ollama
```

Add:
```ini
[Service]
Environment="OLLAMA_HOST=0.0.0.0:11434"
```

```bash
sudo systemctl restart ollama
```

Then from another machine on Tailscale, `OLLAMA_BASE_URL=http://<dgx-tailscale-ip>:11434`.

> **Security note:** Ollama has no built-in authentication. Binding to `0.0.0.0` on the DGX Spark is acceptable within a Tailscale network (Tailscale traffic is encrypted and authenticated), but do not expose port 11434 to the public internet.

---

## 9. HTTPS

- **DreamHost:** Handles Let's Encrypt SSL automatically in the panel — enable it per domain.
- **Docker (VPS):** Use [Caddy](https://caddyserver.com) as the reverse proxy instead of Nginx — it handles Let's Encrypt automatically with almost no config. Alternatively, add Certbot to the Nginx setup.
- **Local Tailscale deployments (Mac Mini, Rock 5B, DGX Spark):** Tailscale provides HTTPS for services via [Tailscale HTTPS certificates](https://tailscale.com/kb/1153/enabling-https) — enable it in the Tailscale admin console. Each device gets a `machine-name.ts.net` hostname with a valid TLS cert.
- **Laravel Herd (Mac Mini dev):** Herd generates local self-signed certs automatically. No action required.

---

## 10. Post-Deployment Checklist

Run through this after any fresh install or major update:

- [ ] `APP_DEBUG=false` in production `.env` — disable with: `sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env`
- [ ] `APP_KEY` is set — generate with `php artisan key:generate`
- [ ] `php artisan config:cache` run (and `config:clear` first if `.env` was recently edited)
- [ ] `php artisan route:cache` run
- [ ] `storage/` and `bootstrap/cache/` are writable (`chmod -R 775 storage bootstrap/cache`)
- [ ] `php artisan storage:link` run — **DreamHost and local dev only; skip for Docker deployments**
- [ ] **DreamHost only:** `php artisan livewire:publish --assets` run at least once after initial clone
- [ ] **DreamHost only:** `DB_HOST` is `mysql.sheql.com` (not `localhost`)
- [ ] **DreamHost only:** `CACHE_STORE=file`, `SESSION_DRIVER=file`, `QUEUE_CONNECTION=sync`
- [ ] **DreamHost only:** Trusted proxies configured in `bootstrap/app.php` (`trustProxies(at: '*')`)
- [ ] Frontend assets present: `public/build/manifest.json` exists
- [ ] Database migrated: `php artisan migrate --status` shows no pending
- [ ] Site Admin can log in (`admin@sheql.com`)
- [ ] System user exists: `php artisan tinker` → `App\Models\User::where('is_system', true)->first()`
- [ ] Email delivery works (send a test password-reset email)
- [ ] `AI_SUGGESTIONS_ENABLED=false` in production unless intentionally enabled
- [ ] Cron job set up (DreamHost) or systemd timer / scheduler running (Docker)

---

## 11. Environment Variable Reference

| Variable | Required | Default | Notes |
|---|---|---|---|
| `APP_KEY` | Yes | — | Generate with `php artisan key:generate` |
| `APP_ENV` | Yes | `production` | `local` for dev |
| `APP_DEBUG` | Yes | `false` | Never `true` in production |
| `APP_URL` | Yes | — | Full URL including `https://` |
| `DB_*` | Yes | — | Database connection details |
| `ADMIN_PASSWORD` | Yes | — | Site Admin user password (seeder) |
| `MAIL_*` | Yes | — | Email config; use `log` driver for dev |
| `AI_SUGGESTIONS_ENABLED` | No | `false` | Set `true` to enable Ask AI button |
| `AI_DEFAULT_PROVIDER` | No | `anthropic` | `anthropic` or `ollama` |
| `ANTHROPIC_API_KEY` | If using Anthropic | — | `sk-ant-...` |
| `OLLAMA_BASE_URL` | If using Ollama | — | e.g. `http://100.x.x.x:11434` |
| `QUEUE_CONNECTION` | No | `sync` | `sync` for DreamHost; `database` or `redis` elsewhere |
| `CACHE_STORE` | No | `file` | `file` for DreamHost; `redis` elsewhere if available |
| `SESSION_DRIVER` | No | `file` | `file` for DreamHost |

---

*Last updated: 2026-03-22. Expanded DreamHost section based on live deployment experience.*
