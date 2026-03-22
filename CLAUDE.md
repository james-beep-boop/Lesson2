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

`class` is a PHP reserved keyword — never use it as a variable, method, or route name in this codebase.

---

## Authorization model

- **Global roles** via Spatie: `site_administrator`
- **subject_grade-scoped roles** via custom `subject_grade_user` pivot — `role` enum: `editor`
- Subject Admin is stored as `subject_grades.subject_admin_user_id` (nullable FK) — **not** in the pivot
- Policies are the single source of truth for authorization — no ad hoc checks in components or controllers

Role scoping is **per subject_grade**, not per subject. A Math Grade 4 Subject Admin has zero authority over Math Grade 5.

---

## MariaDB constraints — no partial indexes

DreamHost runs MariaDB. Partial/filtered unique indexes are a PostgreSQL feature and must not be used.

Uniqueness constraints use:
- **Nullable FK on parent record** — `lesson_plan_families.official_version_id`, `subject_grades.subject_admin_user_id`
- **Standard composite unique index** — `favorites (user_id, family_id)`
- **Service-layer transaction** — inverse constraints with no clean DB expression

Never suggest a `WHERE` clause on a `CREATE UNIQUE INDEX` statement for this project.

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

AI suggestions are gated by the `ai-suggestions` Pennant feature flag, **disabled by default**.
The "Ask AI" button must not render when the flag is off — no dead UI, no error states.
Enable for demo via `.env`: `AI_SUGGESTIONS_ENABLED=true`.

---

## Seeder split

- `DatabaseSeeder` — production-safe. Seeds System user and Site Admin only. Site Admin password from `ADMIN_PASSWORD` env var.
- `DemoSeeder` — dev/demo only. Run with `php artisan db:seed --class=DemoSeeder`. Never on production.

---

## Testing

All tests use **Pest**. Test files live in `tests/Feature` and `tests/Unit`.
Use `AgentFake` for AI SDK tests — never make real API calls in tests.
The full test checklist is in **Section 17** of `Lesson2.md`.

---

## When in doubt

1. Check `Lesson2.md` — it is the canonical spec
2. Use Boost `search_docs` or WebFetch to verify the current API; if docs and installed source disagree, trust the installed source
3. Choose the simplest maintainable option and document any deviation in `Lesson2.md`
4. Do not invent features not listed in the spec
