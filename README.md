# ARES Lesson Library

Laravel 13 / Filament 5 application for storing, versioning, comparing, translating, exporting, and managing lesson plans for ARES Kenya.

> **Note:** This project has been superseded by a clean-slate Node/Payload rewrite in the **Lesson3** repository (`james-beep-boop/Lesson3`). This repo is preserved for reference.

Live site: https://www.sheql.com

## Docs

- `Lesson2.md` — canonical product/spec document
- `USER_GUIDE.md` — first-time user guide and role summary
- `PROGRESS.md` — current build tracker
- `Toast_UI_Editor_Plan.md` — editor implementation plan

## Local development

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

Run the app with your preferred local workflow or:

```bash
composer run dev
```
