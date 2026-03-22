# ARES Lesson Repository — AI Assistant Instructions

This file is loaded automatically by Claude Code at the start of every session.
The canonical project specification is **`Lesson2.md`** in this directory. Read it before making any architectural decisions.

---

## Project summary

**ARES Kenya Lesson Plan Repository** — a Laravel 13 / Filament 5 / Livewire 4 web application for teachers and administrators to store, version, browse, compare, and manage lesson plans.

**Working directory:** `/Users/jamesmcclelland/Documents/GitHub/Lesson2`

---

## ⚠️ Knowledge currency — read this first

Your training cutoff is **August 2025**. These packages in this project post-date that cutoff:

| Package | Version used |
|---|---|
| Laravel | 13 |
| Filament | 5 |
| Livewire | 4 |
| Laravel AI SDK (`laravel/ai`) | first-party, Laravel 13 era |

The exact release dates are less important than the rule: **treat your built-in knowledge of these packages as potentially wrong and verify before using.**

Before implementing any feature that touches Filament, Livewire, the Laravel AI SDK, or any Laravel 13-specific API:

1. Use the **Boost `search_docs` MCP tool** if Boost is installed and available
2. Or **WebFetch** the official docs page for that feature
3. Or **read the installed vendor source** directly

**If official documentation and the installed package source disagree, trust the installed package source.** It is what will actually run.

Do not proceed on training-data recall alone when verification is available. Silent assumption errors on post-cutoff APIs are the primary risk on this project.

---

## Key documentation URLs

| Resource | URL |
|---|---|
| Laravel 13 | https://laravel.com/docs/13.x |
| Laravel AI SDK | https://laravel.com/docs/13.x/ai-sdk |
| Laravel Pennant | https://laravel.com/docs/13.x/pennant |
| Laravel Boost | https://laravel.com/docs/13.x/boost |
| Filament 5 | https://filamentphp.com/docs/5.x *(verify at build time)* |
| Livewire 4 | https://livewire.laravel.com/docs |
| Tailwind CSS v4 | https://tailwindcss.com/docs |
| Spatie Permission | https://spatie.be/docs/laravel-permission |
| Blueprint | https://blueprint.laravelshift.com |

---

## Stack

```
PHP 8.4+
Laravel 13
Filament 5          — all UI (app panel + admin panel), no separate frontend
Livewire 4          — within Filament only, no standalone Livewire pages
Tailwind CSS 4      — single pipeline via Filament
Alpine.js           — bundled with Filament/Livewire
Spatie Permission   — global roles only (Site Administrator)
Custom pivot        — subject_grade-scoped roles (see below)
Filament Shield     — admin panel access control
Laravel Pennant     — feature flags (first-party package, verify install separately)
Laravel AI SDK      — in-editor AI suggestions (gated by feature flag)
Laravel Boost       — dev-time MCP server (use when available)
Laravel Shift Blueprint — scaffolding from draft.yaml
Pest                — all tests
MariaDB             — DreamHost shared hosting target
```

---

## Critical naming conventions

| Term | Meaning | DB / Model |
|---|---|---|
| `Subject` | Academic discipline only — Mathematics, English, Science | `subjects` table, `Subject` model |
| `SubjectGrade` | Assignable unit: one subject + one integer grade | `subject_grades` table, `SubjectGrade` model |
| `SubjectGrade` | This is what roles, families, and user assignments attach to — not bare Subject | `subject_grade_user` pivot |
| Grade | Always an integer. Always displayed as "Grade N" | `subject_grades.grade` integer column |

`class` is a PHP reserved keyword — never use it as a variable name (`$class`), method name, route segment (`/class`), or model name. This does not mean avoiding the `class` keyword for defining PHP classes — that is unavoidable and correct. The restriction is on using "class" as an *identifier* for the educational concept. The entity is always called `SubjectGrade`.

---

## Authorization model

- **Global roles** via Spatie: `site_administrator`
- **subject_grade-scoped roles** via custom `subject_grade_user` pivot — `role` enum: `editor`
- Subject Admin is stored as `subject_grades.subject_admin_user_id` (nullable FK) — **not** in the pivot
- Policies are the single source of truth for authorization — no ad hoc checks in components or controllers

Role scoping is **per subject_grade**, not per subject. A Math Grade 4 Subject Admin has zero authority over Math Grade 5.

When promoting a user to Subject Admin, always use a **service layer transaction** that:
1. Finds any `subject_grades` row where `subject_admin_user_id = $userId` and sets it to `NULL` (removes their existing Subject Admin role if any)
2. Sets `subject_grades.subject_admin_user_id = $userId` on the target subject_grade
3. Demotes the previous Subject Admin of the target subject_grade (if any) to Editor in the pivot

Never update `subject_admin_user_id` directly without this full transaction. A partial update leaves ghost admins assigned to other subject_grades.

---

## DreamHost shared hosting constraints

DreamHost is the **primary production target**. It has significant limitations — keep all of these in mind whenever writing code or suggesting commands that will run in production.

### No Node.js on the server
DreamHost shared hosting does not have Node.js. All frontend assets (Vite/Tailwind/Filament) **must be compiled locally or in CI** before deployment. Never suggest `npm install`, `npm run build`, or any Node command as a server-side production step. The preferred deployment path is a CI pipeline that builds assets and uploads the compiled `public/build/` directory to DreamHost. Do not commit compiled assets to the repository.

### Tailwind CSS v4 — no tailwind.config.js
This project uses Tailwind CSS v4, which is CSS-first. **Do not create or modify `tailwind.config.js`** — in Tailwind v4 that file is ignored or causes build warnings. All theme customisations go in `@theme {}` blocks inside the CSS entry file. Filament manages its own Tailwind pipeline via `@source` directives, not a JS config file.

### AI calls are synchronous — use streaming
`QUEUE_CONNECTION=sync` on DreamHost means AI requests run inline in the HTTP request. Anthropic API calls can take 10–30 seconds. To prevent browser and PHP timeouts:
- **Always use streaming** when calling the Laravel AI SDK. Streaming sends response chunks progressively, keeping the connection alive rather than hanging until completion.
- In Livewire components, stream the AI response into the UI as chunks arrive.
- As a safety net, add `php_value max_execution_time 60` to the DreamHost `.htaccess` — the default is often 30 seconds.
- Also call `set_time_limit(60)` at the start of the AI agent execution path as an in-process fallback, since `.htaccess` directives are not always honoured on all shared hosting configurations.
- Never design an AI interaction that waits for a full synchronous response before rendering anything.

### MariaDB — no partial indexes
DreamHost runs MariaDB. Partial/filtered unique indexes are a PostgreSQL feature and must not be used.

Uniqueness constraints use:
- **Nullable FK on parent record** — `lesson_plan_families.official_version_id`, `subject_grades.subject_admin_user_id`
- **Standard composite unique index** — `favorites (user_id, family_id)`
- **Service-layer transaction** — inverse constraints with no clean DB expression

Never suggest a `WHERE` clause on a `CREATE UNIQUE INDEX` statement for this project.

The MariaDB version on DreamHost shared hosting may differ from the `mariadb:11` used in Docker. Write all migrations to be compatible with MariaDB 10.6+. Avoid column types or index features not present in that version. Test migrations against the actual DreamHost version before deploying.

### Storage permissions
On DreamHost, PHP runs as the domain/SSH user via suEXEC — not as `www-data`. The web server user and the SSH user are the same. Set `storage/` and `bootstrap/cache/` to `775` and ensure they are owned by your SSH user. If writes fail, check ownership first, not just permissions. Verify with Tinker:
```bash
php artisan tinker --execute="file_put_contents(storage_path('test-write.txt'), 'ok'); echo file_get_contents(storage_path('test-write.txt')); unlink(storage_path('test-write.txt'));"
```
If that fails, ownership is the likely cause — not the permission bits.

---

## Key data model facts

- `lesson_plan_families.official_version_id` — nullable FK to versions. Official status lives on the family, not the version. No `is_official` boolean column on versions.
- `favorites (user_id, family_id, version_id)` — unique on `(user_id, family_id)`. One favorite per family per user. Favoriting a different version of the same family is an upsert.
- `subject_grade_user` pivot stores `editor` role only. Subject Admin is stored on `subject_grades` directly.
- All versions are immutable once saved. Editing always creates a new version.
- First version in any new family is always `1.0.0`.
- System user (`system@ares.internal`, `is_system = true`) is seeded by `DatabaseSeeder`. Never expose in user-facing UI.

---

## Feature flags

AI suggestions and translation are both gated by a **direct config check**: `config('features.ai_suggestions')`. Pennant is not used — it caches per scope and would not respond reliably to a simple env change. The config reads from `env('AI_SUGGESTIONS_ENABLED', false)`.
Neither button renders when the flag is off — no dead UI, no error states.
Enable for demo via `.env`: `AI_SUGGESTIONS_ENABLED=true`.

---

## Seeder split

- `DatabaseSeeder` — production-safe. Seeds System user and Site Admin only. Site Admin password from `ADMIN_PASSWORD` env var.
- `DemoSeeder` — dev/demo only. Run with `php artisan db:seed --class=DemoSeeder`. Never on production.

---

## Testing

All tests use **Pest**. Test files live in `tests/Feature` and `tests/Unit`.
Use per-agent fakes for AI SDK tests — e.g. `LessonPlanAdvisor::fake()`, assert with `LessonPlanAdvisor::assertPrompted(...)`. Never make real API calls in tests.
The full test checklist is in **Section 17** of `Lesson2.md`.

---

## When in doubt

1. Check `Lesson2.md` — it is the canonical spec
2. Use Boost `search_docs` or WebFetch to verify the current API; if docs and installed source disagree, trust the installed source
3. Choose the simplest maintainable option and document any deviation in `Lesson2.md`
4. Do not invent features not listed in the spec
