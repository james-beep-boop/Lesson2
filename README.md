# ARES Lesson Repository

Laravel 13 / Filament 5 application for storing, versioning, comparing, translating, exporting, and managing lesson plans for ARES Kenya.

Live site: https://www.sheql.com

## What This Repo Is For

- Teachers can browse lesson plans, compare versions, favorite versions, and use the inbox.
- Editors can create new versions within assigned `subject_grade` scopes.
- Subject Administrators and Site Administrators can create new lesson-plan families, mark official versions, and manage scoped roles.
- AI-assisted features include Ask AI guidance and Swahili translation preview, both gated by `AI_SUGGESTIONS_ENABLED`.

## Core Stack

- PHP 8.4+
- Laravel 13
- Filament 5
- Livewire 4
- Tailwind CSS 4
- Pest
- MariaDB
- Laravel AI SDK (`laravel/ai`)

## Local Development

Prerequisites:

- PHP 8.4+
- Composer
- Node.js / npm
- MariaDB or another compatible MySQL database

Typical setup:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan db:seed
```

For demo data:

```bash
php artisan db:seed --class=DemoSeeder
```

Run the app in development with your preferred local workflow. The Composer `dev` script is available:

```bash
composer run dev
```

## Deployment

The current production workflow is:

1. Build and verify locally.
2. Run [`DEPLOY_SITE.sh`](/Users/jamesmcclelland/Documents/GitHub/Lesson2/DEPLOY_SITE.sh) from the local machine.
3. Let [`UPDATE_SITE.sh`](/Users/jamesmcclelland/Documents/GitHub/Lesson2/UPDATE_SITE.sh) finalize on DreamHost.

See [`deployment.md`](/Users/jamesmcclelland/Documents/GitHub/Lesson2/deployment.md) for the full runbook.

## Important Docs

- [`USER_GUIDE.md`](/Users/jamesmcclelland/Documents/GitHub/Lesson2/USER_GUIDE.md): first-time user guide with roles and demo logins
- [`Lesson2.md`](/Users/jamesmcclelland/Documents/GitHub/Lesson2/Lesson2.md): canonical product/spec document
- [`PROGRESS.md`](/Users/jamesmcclelland/Documents/GitHub/Lesson2/PROGRESS.md): current build tracker
- [`Toast_UI_Editor_Plan.md`](/Users/jamesmcclelland/Documents/GitHub/Lesson2/Toast_UI_Editor_Plan.md): next implementation plan
- [`deployment.md`](/Users/jamesmcclelland/Documents/GitHub/Lesson2/deployment.md): active deployment guide
- [`troubleshooting.md`](/Users/jamesmcclelland/Documents/GitHub/Lesson2/troubleshooting.md): historical deployment/debugging log
