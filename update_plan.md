# Upgrade Plan

## Current Baseline

- PHP: `8.5.x`
- Laravel: `13.5.0`
- Filament: `5.4.3`
- Livewire: `4.2.2`
- Spatie Permission: `7.2.4`
- Tailwind: `v4`

## Rules

- Upgrade one track at a time.
- Keep backend package upgrades separate from Tailwind.
- Run the full test suite before and after each upgrade.
- Do not update transitive dependencies directly unless Composer requires it.

## Sequence

### 1. Laravel

- Already completed.
- Keep an eye on production for a short period after deploy.

### 2. Filament

- Update Filament with Composer.
- Let Composer move Livewire only if required; Filament updates may pull Livewire forward with `--with-all-dependencies`.
- Re-run tests and smoke test all Filament pages.

Suggested commands:

```bash
composer update filament/filament --with-all-dependencies
php artisan optimize:clear
php artisan test --compact
```

### 3. Spatie Permission

- Update after Filament is stable.
- Smoke test all role and authorization flows.

Suggested commands:

```bash
composer update spatie/laravel-permission --with-all-dependencies
php artisan optimize:clear
php artisan test --compact
```

### 4. Tailwind

- Update last.
- Do a visual pass on login, Filament pages, tables, buttons, and modals.
- Rebuild assets and verify the browser output.

Suggested commands:

```bash
npm install
npm run build
php artisan test --compact
```

## Transitive Packages

These are usually pulled in by Laravel or other first-party packages and should not be upgraded independently unless Composer forces it:

- `nesbot/carbon`
- `symfony/*`
- `voku/portable-ascii`
- `webmozart/assert`

If one of these changes during an upgrade, treat it as part of the parent package update and verify the app normally.

## Smoke Test Checklist

- Login works for teacher, subject admin, and site admin accounts.
- Manage Team add/remove editor works.
- Lesson plan view, compare, and edit work.
- Lesson plan create/upload works.
- Admin user list works.
- Deletion requests page works.
