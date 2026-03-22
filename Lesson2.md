# ARES Lesson Repository — MVP Specification

> This document is the canonical reference for the build. All implementation decisions, deviations, and clarifications agreed upon during pre-build discussion are recorded here. Code and architecture must conform to this spec.

---

## 1. Project Overview

Build the MVP for the **ARES Kenya Lesson Plan Repository** — a web application for teachers and administrators to store, version, browse, compare, and manage lesson plans.

**Working directory:** `/Users/jamesmcclelland/Documents/GitHub/Lesson2`

---

## 2. Stack

| Layer | Choice | Notes |
|---|---|---|
| Language | PHP 8.4+ | Laravel 13 minimum is PHP 8.3; 8.4 is above minimum and preferred |
| Framework | Laravel 13 | Released March 17, 2026. Minor update, no breaking changes from 12. |
| Admin / UI framework | Filament 5 | Used for **all** UI — both admin and user-facing panels. Eliminates style split. |
| Reactive components | Livewire 4 | Used within Filament as needed; no standalone Livewire pages |
| CSS | Tailwind CSS 4+ | Single pipeline via Filament |
| JS (lightweight) | Alpine.js | Bundled with Filament/Livewire |
| Roles / permissions | Spatie Laravel-Permission + custom pivot | See Section 7 |
| Admin permissions | Filament Shield | Covers global (Site Admin) permissions only |
| Testing | Pest | Feature and unit tests |
| AI dev tooling | Laravel Boost | Dev dependency (`--dev`). MCP server + AI guidelines + agent skills. Includes Filament 2–5 documentation API. |
| Scaffolding | Laravel Shift Blueprint | Dev dependency (`--dev`). Generates models, migrations, factories, form requests, and tests from a YAML draft file. Does not generate Filament resources — those are built on top. |
| AI features (runtime) | Laravel AI SDK (`laravel/ai`) | First-party. Powers the in-editor AI suggestions and translation features. Provider-abstracted: Anthropic for demo, Ollama for future local LLM. Gated by a config flag (`AI_SUGGESTIONS_ENABLED`). |
| Hosting target | DreamHost shared hosting | Optimize for low server load, simple dependencies, no heavy runtime requirements |
| Markdown | Stored in database | Canonical lesson-plan format; no filesystem identity reliance |

### Filament panel strategy

- **`app` panel** — lesson browsing, viewing, editing, compare, favorites, inbox. Accessible to all authenticated users.
- **`admin` panel** — user management, subject assignment, role management, deletion approvals, summary counts. Accessible to Site Administrators only.
- Both panels share the same Tailwind/Filament CSS pipeline. No separate public frontend.

### Tailwind CSS v4 notes

Tailwind v4 is a ground-up rewrite. Key changes that affect this project:
- **CSS-first config** — no `tailwind.config.js`. Theme customisation lives in an `@theme {}` block in CSS. Automated migration tool available.
- **Filament compatibility** — handled via `@source` directives (e.g. `@source "../vendor/filament/forms/dist"`). Filament manages this; no manual content-path config needed.
- **Performance** — incremental builds up to 182× faster than v3. Negligible impact on DreamHost but pleasant during development.
- Utility renames: `bg-gradient-to-r` → `bg-linear-to-r`. These are within Filament's internals; unlikely to surface in custom code.

### Laravel Boost

Install as a dev dependency:

```bash
composer require laravel/boost --dev
php artisan boost:install
```

Boost is a **dev-time tool only** — an MCP server that bridges AI coding assistants (including Claude Code) with your running Laravel application. It provides:
- Version-specific guidelines for Laravel, Livewire, Filament, Tailwind, Pest, and more
- Searchable documentation API (17,000+ entries, covering Filament 2–5)
- Live app introspection: routes, DB schema, config, logs, Tinker execution

Note: Boost guidelines currently cover Laravel 10–12. Laravel 13 is a minor update with no breaking changes, so coverage remains valid. Filament 5 docs are indexed.

### Laravel Shift Blueprint

Install as a dev dependency:

```bash
composer require laravel-shift/blueprint --dev
php artisan blueprint:new
```

Blueprint generates traditional Laravel boilerplate (models, migrations, factories, form requests, controllers, routes, tests) from a single `draft.yaml` file. It does **not** generate Filament resources or Livewire components — those are built on top of the generated models. Use Blueprint in the initial scaffolding sprint to eliminate migration/model/factory boilerplate.

### Laravel AI SDK

Install as a production dependency:

```bash
composer require laravel/ai
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
php artisan migrate
```

Used to power the in-editor AI suggestions feature. Provider is abstracted via environment config:

```ini
# Demo / current
AI_DEFAULT_PROVIDER=anthropic
ANTHROPIC_API_KEY=sk-ant-...

# Future — local LLM on NVIDIA DGX Spark
AI_DEFAULT_PROVIDER=ollama
OLLAMA_BASE_URL=http://dgx-spark-host:11434
```

No application code changes required when switching providers. The `LessonPlanAdvisor` agent class targets whichever provider is configured.

---

## 3. AI Tooling and Knowledge Currency

### ⚠️ Knowledge currency warning

The AI assistants working on this project (Claude Code and others) have a training cutoff of **August 2025**. The following packages were released or significantly updated **after** that date:

| Package | Version used |
|---|---|
| Laravel | 13 |
| Filament | 5 |
| Livewire | 4 |
| Laravel AI SDK (`laravel/ai`) | first-party, Laravel 13 era |

Release dates are context, not the rule. **The rule is: verify before using.** Built-in training knowledge of these packages must be treated as unreliable. Always verify against live documentation or installed package source before implementing any feature that touches them. If documentation and installed source disagree, trust the installed source. This is not optional — silent assumption errors on post-cutoff APIs are the primary risk on this project.

### How to verify during the build

In order of preference:

1. **Boost `search_docs` MCP tool** — when Boost is installed and available, query Filament 5, Laravel 13, Livewire 4 docs directly from within the coding session.
2. **WebFetch on official doc pages** — when Boost is unavailable or results are insufficient.
3. **Read installed package source** — when a specific method signature or config key is uncertain, read the installed vendor file directly. **If installed source and docs disagree, trust the installed source.**

**Never** proceed with a package-specific implementation based solely on training-data recall when any of the above verification options are available.

### Key documentation URLs

Verify these URLs are current at build time — doc URL structures occasionally change between major versions:

| Resource | URL |
|---|---|
| Laravel 13 | https://laravel.com/docs/13.x |
| Laravel AI SDK | https://laravel.com/docs/13.x/ai-sdk |
| Laravel Boost | https://laravel.com/docs/13.x/boost |
| Filament 5 | https://filamentphp.com/docs/5.x *(verify URL at build time)* |
| Livewire 4 | https://livewire.laravel.com/docs |
| Tailwind CSS v4 | https://tailwindcss.com/docs |
| Spatie Permission | https://spatie.be/docs/laravel-permission |
| Laravel Shift Blueprint | https://blueprint.laravelshift.com |

### Pre-build verification checklist

Run through this before writing code in each phase. Use Boost `search_docs` or WebFetch for each item.

**Before any Filament work:**
- [ ] Confirm Panel Provider registration syntax for Filament 5
- [ ] Confirm multi-panel setup (app panel + admin panel) in Filament 5
- [ ] Confirm Filament Shield installation and config for Filament 5
- [ ] Confirm Tailwind v4 + Filament 5 CSS pipeline setup (`@source` directives)

**Before any auth work:**
- [ ] Confirm Filament 5 built-in auth scaffold (login, register, password reset, email verify)

**Before any Livewire work:**
- [ ] Confirm Livewire 4 component syntax and lifecycle hooks
- [ ] Confirm how Livewire 4 integrates within Filament 5 custom pages

**Before AI SDK work:**
- [ ] Confirm `laravel/ai` install and `AiServiceProvider` publish steps
- [ ] Confirm Agent class interface and `Promptable` trait in current version
- [ ] Confirm streaming response API

**Before Blueprint scaffolding:**
- [ ] Confirm `draft.yaml` syntax for current Blueprint version (2.13+)
- [ ] Confirm which generators are available and which require flags

### CLAUDE.md

`CLAUDE.md` in the project root is the live instruction file for AI coding sessions. It is committed to the repository and loaded automatically by Claude Code at the start of every session.

`php artisan boost:install` generates an initial `CLAUDE.md`. After running it, the generated file must be reviewed and merged with the hand-authored `CLAUDE.md` already in the repository — do not overwrite it blindly.

### Version pinning

After the first `composer install`, record the resolved versions of key packages here. **Only useful if actively maintained — if it falls out of date, delete it rather than leave it wrong.**

```
# Resolved package versions — 2026-03-22
laravel/framework: 13.1.1
filament/filament: 5.4.1
livewire/livewire: 4.2.1
laravel/ai: 0.3.2
laravel/pennant: (not installed — not used; direct config check used instead)
laravel/boost: (install separately: composer require laravel/boost --dev)
laravel-shift/blueprint: 2.13.0
spatie/laravel-permission: 6.25.0
bezhansalleh/filament-shield: 4.2.0
```

---

## 4. Branding and UI

### Header
- **Top-left:** ARES Education / Kenya Lesson Plan Repository
- **Top-right:** Lessons | Admin *(only when authorized)* | unread message badge | account menu | sign out

### Footer
```
Kenya Lesson Plan Repository © 2026 ARES Education — Lesson Plans are licensed under CC BY-SA 4.0
```
Simple licensing link/icon treatment is acceptable for MVP.

### Design principles
- White background, black/gray text, restrained color use
- Clean, professional, simple
- Responsive — desktop is primary use case, mobile must be usable
- Navigation must be obvious at all times

---

## 5. Authentication

Standard Laravel auth flows via Filament's built-in auth scaffold:

- Register
- Login
- Logout
- Email verification (required before access)
- Forgot password / reset password
- Account page with password change

New signups become **Teachers** by default.

---

## 6. Roles

There are four role levels. Three are subject_grade-scoped; one is global.

| Role | Scope |
|---|---|
| Teacher | Global (any signed-in user) |
| Editor | subject_grade-scoped |
| Subject Administrator | subject_grade-scoped (at most one per subject_grade) |
| Site Administrator | Global |

**"Contributor"** is not a role. It is the user who created a specific lesson-plan version, recorded as `contributor_id` on the version record.

### Role rules

- All signed-in users are Teachers by default.
- A Site Administrator assigns users to subject_grades and elevates roles within those subject_grades.
- Editors and Subject Administrators are scoped to specific subject_grade records — "Math Grade 4" and "Math Grade 5" are separate assignments with independent role holders.
- **At most one Subject Administrator per subject_grade** (zero is valid).
- **A user may be Subject Administrator for at most one subject_grade** — never more than one simultaneously.
- If a Site Administrator designates a user as Subject Administrator for a subject_grade, and a Subject Administrator already exists for that subject_grade, the existing Subject Administrator is **automatically demoted to Editor** for that subject_grade.
- If the same user already holds a Subject Administrator role for a *different* subject_grade, that prior Subject Administrator role is **automatically demoted to Editor** before the new one is granted.

---

## 7. Authorization Architecture — Custom Pivot

subject_grade-scoped roles use a **custom pivot table**, not Spatie Teams mode. Spatie Laravel-Permission handles global roles (Site Administrator) only. Filament Shield covers admin panel access.

### Why custom pivot over Spatie Teams

- Spatie Teams requires a global config switch affecting all role lookups — fragile and poorly tested with Filament Shield
- Custom pivot (`subject_grade_user` with a `role` enum) is explicit, readable, and testable
- Uniqueness constraints ("one Subject Admin per subject_grade", "one subject_grade per Subject Admin") are handled via nullable FK columns, standard unique indexes, and service-layer transactions — no partial indexes required or used
- Policy helpers (`$user->roleInSubjectGrade($subjectGrade)`) are straightforward and easy to audit

### Authorization model summary

- **Global:** Spatie role `site_administrator` — checked via Filament Shield for admin panel access
- **subject_grade-scoped:** `subject_grade_user` pivot with `role` enum — checked in Policies for lesson-plan and subject_grade operations
- Policies are the single source of truth for authorization. No ad hoc permission checks in controllers or components.

---

## 8. Data Model

### Entities

| Table | Purpose |
|---|---|
| `users` | All users including the reserved System user |
| `subjects` | Academic subjects only — e.g. Mathematics, English, Science |
| `subject_grades` | The assignable unit: unique combination of `subject_id` + `grade` (integer). One row per subject/grade pair that actually exists in the school. |
| `subject_grade_user` | Role pivot: user ↔ subject_grade with `role` enum (`editor`). Teacher is the implicit default; not stored here. Subject Admin is stored on `subject_grades` directly (see below). |
| `lesson_plan_families` | Grouping: `subject_grade_id` + day + language. Also holds `official_version_id` (nullable FK to `lesson_plan_versions`). Grade is implicit via subject_grade. |
| `lesson_plan_versions` | Individual versions within a family; immutable once saved. No `is_official` boolean — official status lives on the family record. |
| `favorites` | User ↔ specific lesson plan version, constrained to one per family per user (`user_id`, `family_id`, `version_id`). Favoriting a new version of the same family replaces the previous favorite (upsert). |
| `messages` | Inbox messages between users (including System user) |
| `deletion_requests` | Tracks deletion requests; triggers a message on creation |

### Key modeling rules

- Markdown content stored in the database as the canonical source.
- Do not use filesystem filenames as primary identity.
- Use stable IDs and slugs where appropriate.
- Every version records: `contributor_id`, `created_at`, `revision_note`, `version` (semver string). No `is_official` column on versions.
- `subject_grades.grade` is an integer. Display as "Grade N" in all UI.
- `subject_grades.subject_admin_user_id` is a **nullable FK to users**. Enforces "at most one Subject Admin per subject_grade" structurally — no partial index needed. NULL means no Subject Admin assigned.
- `lesson_plan_families.official_version_id` is a **nullable FK to lesson_plan_versions**. Enforces "at most one official version per family" structurally — no partial index needed. NULL means no official version designated.
- "One subject_grade per Subject Admin" (inverse constraint) is enforced in the **service layer**: before promoting a user, check all `subject_grades.subject_admin_user_id` records and demote within the same transaction.
- `lesson_plan_families` has a **composite unique index on `(subject_grade_id, day, language)`** — enforces the family key at DB level, fully MariaDB-safe. On uniqueness conflict at save time (e.g. concurrent creation race), catch the exception and redirect the user to the existing family with a prompt to add a new version instead. Never surface a raw DB error to the user.
- `favorites` uses a standard unique index on `(user_id, family_id)` — fully MariaDB-safe. `version_id` must belong to the given `family_id` (enforced at service layer).
- Use database transactions for all critical business rules (official version toggling, Subject Admin promotion/demotion, favorite upsert, new family + first version creation).

### MariaDB / DreamHost compatibility note

The spec deliberately avoids partial/filtered unique indexes (a PostgreSQL feature). All uniqueness constraints use one of:
- **Nullable FK column on the parent record** — official version, Subject Admin assignment
- **Standard composite unique index** — favorites `(user_id, family_id)`
- **Service-layer transaction** — inverse constraints that have no clean DB-level expression

### User fields

Every user record has:

| Field | Notes |
|---|---|
| `username` | Unique. Chosen by the user at registration. Displayed in the UI wherever a user is identified. |
| `name` | Full name. Displayed alongside username where space allows. |
| `email` | Unique. Used for login and email verification. **Visible only to Site Administrators** in admin tables — never shown to Teachers, Editors, or Subject Administrators in any user-facing table or view. |
| `password` | Hashed. Verified email required before account activation. |
| `is_system` | Boolean. `true` only for the reserved System user. |

### Email privacy rule

Users who are not Site Administrators must not see the email addresses of other users anywhere in the application — not in tables, not in message headers, not in search results. Contributor attribution on lesson plans shows `username` (and optionally `name`) only.

This is enforced at the **view layer** (Filament resources and custom pages), not only in policies. When building any table or view that shows user information, default to showing `username`. Only the admin panel surfaces `email`.

### System user

A reserved **"System"** user is seeded at install time:

- `username`: system
- `name`: System
- `email`: system@ares.internal
- `password`: null (no login possible)
- `is_system`: true

The System user never appears in user search, compose-message UI, or user management lists. It is used as `from_user_id` for any application-generated messages (errors, duplicate alerts, future automated notifications).

---

## 9. Subjects and Subject Grades

### Subjects

A **subject** is the academic discipline only — Mathematics, English, Science, etc. Subjects have no grade attached.

Subject management (create, rename, archive) is a Site Administrator function in the admin panel.

### Subject Grades

A **subject_grade** is the assignable unit: a unique pairing of one subject and one integer grade level.

Examples: Mathematics + 4, English + 7, Science + 10.

These are displayed throughout the UI as **"Mathematics — Grade 4"**, **"English — Grade 7"**, etc.

Subject_grade records are created by a Site Administrator when a subject/grade combination needs to exist in the system. Roles (Editor, Subject Administrator) are assigned to users at the subject_grade level — a Math Grade 4 Subject Administrator is entirely independent from a Math Grade 5 Subject Administrator.

The current Subject Administrator for a subject_grade is stored as `subject_grades.subject_admin_user_id` (nullable FK). This enforces the one-admin-per-subject_grade constraint at the database level without requiring a partial index.

---

## 10. Lesson-Plan Rules

### Family

A **lesson-plan family** is defined by: **subject_grade + day + language**.

Grade is implicit — it is carried by the subject_grade record, not stored separately on the family. All versions sharing these three attributes belong to the same family.

### Versioning

- Version numbers use semantic format `x.y.z` only.
- The **first version** in a new family is always `1.0.0`. **Exception:** a family created by AI translation inherits the version number of the source version (see Section 12 — Translation feature).
- The system computes the next valid version number automatically. Users never type arbitrary version strings.
- Default bump when saving edits is **Patch**.
- User may choose Patch, Minor, or Major before saving.
- Version numbers must be **unique within a family**.
- Versions are **immutable once saved**. Editing always creates a new version; existing versions are never overwritten.

### Official version

- Only one version per family may be marked **official** at a time.
- Official status is stored as `lesson_plan_families.official_version_id` (nullable FK to `lesson_plan_versions`), not as a boolean on the version itself.
- Setting a new official version is a single `UPDATE lesson_plan_families SET official_version_id = $id` inside a transaction — atomic by nature, no need to unset a previous flag.
- Setting official to none: set `official_version_id = NULL`.
- "Is this the official version?" = `$family->official_version_id === $version->id`.

### Creating a new lesson plan

Creating a **new lesson-plan family** (a brand-new lesson plan, not a new version of an existing one) is restricted to Subject Administrators and Site Administrators only. Editors and Teachers cannot create new families.

**Who can create:**
- **Subject Administrator** — may create new families for their own subject_grade only
- **Site Administrator** — may create new families for any subject_grade

**Creation flow:**
1. Authorised user clicks "Add Lesson Plan" (visible only to Subject Admins and Site Admins)
2. A form collects the family key: subject_grade (scoped to the user's own subject_grade for Subject Admins; full list for Site Admins), day, language
3. If a family with those attributes already exists → the user is redirected to the existing family's version list with a prompt to add a new version instead
4. If no family exists → system opens the editor. **The family record is not yet created.**
5. User enters content (see input methods below) and saves → the family record and version `1.0.0` are created together in a single transaction. If save fails or user abandons the editor, no record is left in the database.

**Input methods:**

| Method | Details |
|---|---|
| Type / paste | User writes or pastes Markdown directly into the editor. Filament `MarkdownEditor` field with live preview. |
| Upload `.md` or `.txt` | File contents are read server-side and loaded into the editor for review before saving. |
| Upload `.docx` | File is converted to Markdown via a two-step pipeline (see below) and loaded into the editor. A prominent warning is shown before conversion begins. |

**Word document warning** (shown on upload of `.docx`, before conversion begins):
> *"This system stores all lesson plans as Markdown. Word documents are converted automatically, but complex formatting — tables, images, footnotes, and columns — may not convert correctly. Please review the result carefully before saving."*

**File upload rules:**
- Uploaded files never persist on disk — content is extracted to a string and loaded into the editor
- The user always reviews content in the editor before saving — no auto-commit on upload
- PDF upload is not supported (deferred)
- Maximum upload size: follow DreamHost's `upload_max_filesize` (typically 32MB on shared hosting)

**Adding a new version to an existing family** follows the same editor flow but is available to Editors and above. The version number is bumped from the current highest; user chooses Patch / Minor / Major.

**DOCX conversion pipeline:**
PHPWord has no Markdown writer. The conversion uses two steps:
1. `phpoffice/phpword` reads the `.docx` and outputs HTML via its `HTML` writer
2. `league/html-to-markdown` converts that HTML to Markdown

Both packages are required in `composer.json`. Fidelity limits apply at each step — tables and basic formatting survive; images, footnotes, and complex layouts do not. This is why the warning exists and why the user must review before saving.

---

## 11. Permissions Matrix

| Action | Teacher | Editor | Subject Admin | Site Admin |
|---|---|---|---|---|
| View all lesson plans | ✓ | ✓ | ✓ | ✓ |
| Compare versions (same family, read-only) | ✓ | ✓ | ✓ | ✓ |
| Favorite a specific version / change favorite | ✓ | ✓ | ✓ | ✓ |
| Use inbox / send messages | ✓ | ✓ | ✓ | ✓ |
| Use "Ask AI" in editor | — | ✓ | ✓ | ✓ |
| Create new lesson plan (new family) | — | — | own subject_grade | ✓ |
| Translate lesson plan to Swahili | — | — | own subject_grade | ✓ |
| Add new version to existing family (edit) | — | own subject_grades | own subject_grades | ✓ |
| Mark version official | — | — | own subject_grades | ✓ |
| Promote/demote Teacher ↔ Editor | — | — | own subject_grades | ✓ |
| Request deletion of version | — | — | own subject_grades | ✓ |
| Hard-delete version directly | — | — | — | ✓ |
| Manage users | — | — | — | ✓ |
| Assign subject_grades to users | — | — | — | ✓ |
| Assign / change Subject Administrators | — | — | — | ✓ |
| Admin summary counts | — | — | — | ✓ |

---

## 12. AI Editing Suggestions

### Overview

Editors, Subject Administrators, and Site Administrators see an **"Ask AI"** button on the lesson-plan edit panel. Teachers (read-only users) do not see this button.

### UX flow

1. User is editing a lesson plan in the Filament editor.
2. User clicks **"Ask AI"**.
3. A slide-over or modal opens with:
   - A short prompt field ("What would you like help with?")
   - Pre-set quick options: *"Suggest improvements"*, *"Check for clarity"*, *"Simplify language"*, *"Ask a question about this lesson plan"*
4. User submits. The current document content is sent to the `LessonPlanAdvisor` agent along with the user's prompt.
5. The AI response streams into the panel.
6. The user reads the suggestion and manually incorporates anything useful into the editor. The AI does **not** auto-apply changes.
7. The panel can be dismissed or re-prompted.

### Feature flag

The AI suggestions and translation features are both gated by a **direct config check**. Pennant is not used for this toggle — Pennant's default database store caches resolved values per scope, which means a Pennant-based feature would not respond predictably to a simple env change without purging cached state. A direct config read is simpler and correct for a global on/off switch.

```php
// config/features.php
return [
    'ai_suggestions' => env('AI_SUGGESTIONS_ENABLED', false),
];
```

Check in components and views:

```php
config('features.ai_suggestions')
```

To enable for a demo or dev environment:

```ini
# .env
AI_SUGGESTIONS_ENABLED=true
```

If per-user feature control is needed in the future, Pennant can be introduced then with proper scope and store configuration. For MVP, the config check is the single pattern used everywhere.

The "Ask AI" and "Translate to Swahili" buttons do not render at all when the flag is off — no dead UI, no error states to handle.

### Constraints for MVP

- Single prompt → single response only. No multi-turn conversation in the panel for MVP.
- AI never writes directly to the document. User copy-pastes what they want.
- No rate limiting for MVP (demo mode). Rate limiting is a post-demo concern.
- Provider: **Anthropic** for demo (configured via `ANTHROPIC_API_KEY`). Switching to Ollama requires a config change only — no application code changes.
- **Verify at build time:** The exact env var names (`AI_DEFAULT_PROVIDER`, `OLLAMA_BASE_URL`) are project conventions. Confirm the actual `config/ai.php` shape from the installed `laravel/ai` package before treating these as canonical.

### Provider / hosting note — DGX Spark on Tailscale

The DGX Spark runs on a Tailscale private network. This affects where Ollama can be used:

- **Development:** Any dev machine on the Tailscale network can reach the Spark directly via its Tailscale IP. Set `OLLAMA_BASE_URL=http://100.x.x.x:11434`. No complications.
- **Production on DreamHost shared hosting:** Shared hosting cannot install Tailscale and therefore cannot reach the Spark. Ollama is not usable from DreamHost production.

**Options when moving to production with a local LLM:**
1. **Keep Anthropic for production, Ollama for dev/demo** — simplest. Different `.env` per environment. Set `AI_SUGGESTIONS_ENABLED=false` on DreamHost until a suitable provider is reachable.
2. **Move to a VPS** (DigitalOcean, Hetzner, etc.) — a VPS can install Tailscale and join the network. Reaches the Spark directly. Worth considering if the app outgrows shared hosting.
3. **Tailscale Funnel** — exposes the Spark's Ollama port to the public internet over HTTPS. Allows DreamHost to reach it without joining Tailscale. Unconventional but technically workable.

### Agent classes

```php
// app/Ai/Agents/LessonPlanAdvisor.php
class LessonPlanAdvisor implements Agent {
    use Promptable;

    public function instructions(): string {
        return 'You are an expert educational content advisor helping teachers
                in Kenya write clear, effective lesson plans. Provide concise,
                practical suggestions. Do not rewrite the entire document unless
                asked — focus on specific, actionable feedback.';
    }
}
```

```php
// app/Ai/Agents/LessonPlanTranslator.php
class LessonPlanTranslator implements Agent {
    use Promptable;

    public function instructions(): string {
        return 'You are an expert translator specialising in Kenyan educational
                content. Translate the provided lesson plan from English to Swahili.
                Preserve all markdown formatting exactly. Translate all body text
                and headings. Do not translate proper nouns, subject names, or
                version metadata. Return only the translated markdown content,
                nothing else.';
    }
}
```

---

### Translation feature (English → Swahili)

Subject Administrators (own subject_grade) and Site Administrators can trigger an AI-powered translation of any English lesson plan version into Swahili.

**Why not Editors?** Translation that targets a non-existent Swahili family creates a new family, which is an admin-only action. Restricting translation to Subject Admins and Site Admins keeps the permission model consistent — the same user who may create a new English family may create the Swahili equivalent.

**UX flow:**

1. User views a lesson-plan version (language = English).
2. A **"Translate to Swahili"** button is visible to Subject Admins (own subject_grade) and Site Admins.
3. User clicks the button. The current version's Markdown content is sent to `LessonPlanTranslator`.
4. The translated content appears in a **review panel** — the user can read and optionally edit it before saving.
5. User confirms. The system creates in a single transaction:
   - A new `lesson_plan_family` record (if none exists): same `subject_grade_id` + `day`, `language = 'sw'`
   - A new `lesson_plan_version` record: `version` = **same version number as the English source**, `contributor_id` = the user who triggered it
   - If the user abandons the review panel without confirming, nothing is written to the database.
6. User is navigated to the new Swahili version.

**Rules:**

- If a Swahili family already exists for that `subject_grade + day` combination, the new version inherits the English source version number. If that version number already exists in the Swahili family (conflict), the system falls back to the standard bump flow from the highest existing Swahili version — user chooses Patch / Minor / Major.
- The English source version is never modified.
- The "Translate to Swahili" button requires **both** `config('features.ai_suggestions') === true` **and** Subject Admin or Site Admin role. The config flag alone is not sufficient — role is checked independently.
- Teacher-only users do not see the button. Editor-role users see "Ask AI" but not "Translate to Swahili".

**Testing:**

- Translation creates a new Swahili family when none exists
- Translation adds a new version to an existing Swahili family when one exists
- Source English version is unchanged after translation
- Button is hidden from Teachers
- Button is hidden when `config('features.ai_suggestions')` is false
- Use `LessonPlanTranslator::fake()` in tests

---

## 13. Deletion Workflow

1. A **Subject Administrator** may request deletion of a lesson-plan version within their subject_grade.
2. The deletion request creates an **in-app message** from the requesting Subject Administrator to:
   - The version's contributor
   - All Site Administrators
3. A **Site Administrator** reviews and may **hard-delete** directly from the admin panel.
4. Editors do not request or perform deletion.
5. A `deletion_requests` record is created and linked to the relevant version.

---

## 14. Messaging

- Simple **direct inbox-style** messaging only.
- Every message has a `from_user_id` and `to_user_id`.
- Admin-initiated messages (e.g., deletion requests) appear to come **from the admin who took the action** — not from System.
- System-generated messages (errors, duplicate alerts, etc.) use `from_user_id` = the seeded System user.
- **Unread message count** appears in the top navigation as a badge/indicator, updated in real time or on page load.
- No threading for MVP.
- The System user never appears as a selectable recipient in the compose UI.

---

## 15. UI — Documents (Lessons) Page

- Summary count cards at top (e.g., total families, total versions, favorites)
- Responsive:
  - Desktop: full column set
  - Mobile: reduced columns; remaining detail via expandable panel or secondary view

### Search / filter bar

Appears directly above the table. All criteria are optional and combinable. Active filters are shown as dismissible chips so the user can see and clear them individually.

| Filter | Type | Notes |
|---|---|---|
| Subject | Dropdown | List of academic subjects |
| Grade | Dropdown | Integer grades, displayed as "Grade N" |
| Day | Dropdown | |
| Official status | Toggle | Yes / No / Either (default: Either) |
| Contributor | User search | Searches by `username` and `name` (both fields). Results display `username` only — email is never shown to non-admins. |
| My favorites only | Toggle | When on, shows only families the current user has favorited |

### Tab bar

Sits below the search bar. Tabs apply on top of any active filters — they are quick sub-views, not replacements for the filter bar. Tab counts reflect the filtered result set, not the full table.

| Tab | What it shows |
|---|---|
| **All** | Every family matching the current filters (default) |
| **Official** | Families where `official_version_id` is set; one row per family showing the official version |
| **Latest revision** | One row per family showing only the most recently created version (by `created_at`), regardless of official status |
| **My favorites** | Families where the current user has a favorite recorded; one row per family showing the favorited version |

**Note on "My favorites" tab vs. filter bar toggle:** Both produce the same base result set. The tab is a one-click shortcut; the filter bar toggle allows combining favorite status with other criteria (e.g., "my favorites in Science Grade 4"). Keep both.

### Table behaviour

- Sortable columns: subject, grade, day, version, contributor, updated date
- Selecting a row opens the **family view**, defaulting to the official version (or most recent if no official is set)
- Within the family view, the user can navigate to any version
- **★ Favorite** button appears in the **version viewer** only — the user consciously stars a specific version
- Favoriting a version of a family the user has already favorited silently replaces the old favorite (upsert — no confirmation needed)
- The listing indicates when the user's favorited version differs from the official version (e.g., a subtle badge or secondary version label on the row)
- **Compare mode:** read-only, limited to versions within the same family
- **Edit** button visible only to authorized users

---

## 16. Admin Panel

Built in Filament's `admin` panel. Functional and simple.

### Search / filter / tab pattern

The same search bar + tab bar pattern used on the lessons page applies to all admin tables. Each table has criteria appropriate to its content:

**Users table**
- Filter bar: name/email search, role (Teacher / Editor / Subject Admin / Site Admin), subject_grade assignment
- Tabs: All | Site Admins | Subject Admins | Editors | Teachers (unassigned)

**Subject_grades table**
- Filter bar: subject dropdown, grade dropdown, has Subject Admin (Yes / No / Either)
- Tabs: All | Has Subject Admin | No Subject Admin

**Lesson plan families table** (admin view)
- Same filter bar as the lessons page (subject, grade, day, official status, contributor)
- Tabs: All | Official | Latest revision | Pending deletion request

**Deletion requests table**
- Filter bar: subject_grade, contributor, requesting admin, status (pending / resolved)
- Tabs: All | Pending | Resolved

### Key workflows
- User management (list, view, edit roles)
- Subject management (create, rename, archive academic subjects)
- Subject_grade management (create subject+grade pairings, assign users)
- Subject Administrator assignment per subject_grade (with auto-demotion logic)
- Role promotion/demotion within subject_grades
- Deletion request review and hard-delete
- Summary counts (users, subjects, subject_grades, lesson-plan families, versions)

---

## 17. Testing (Pest)

Required test coverage:

**Auth**
- Registration requires email verification
- Password reset flow works
- Logout invalidates session

**Roles and permissions**
- Teachers cannot edit lesson plans
- Editors can edit only their assigned subject_grades
- Subject Administrators can manage only their assigned subject_grades
- A Math Grade 4 Editor can **view** Math Grade 5 lesson plans but cannot edit, version, or manage them (view is universal; write/manage is scoped)
- Site Administrators can manage all subject_grades and users
- Only one Subject Administrator can exist per subject_grade
- A user cannot be Subject Administrator for more than one subject_grade
- Elevating a new Subject Admin auto-demotes the existing one (same subject_grade)
- Elevating a user who already admins another subject_grade demotes them from the old one

**Versioning**
- Editing creates a new immutable version
- Patch / Minor / Major bumping works correctly
- First version in a new family created normally is 1.0.0
- First version in a family created by translation inherits the English source version number
- Duplicate version numbers in same family are rejected
- Only one official version per family (enforced via `official_version_id` FK on family)
- Setting a new official version updates `official_version_id` atomically; no boolean flag to unset

**Compare**
- Compare is limited to versions in the same family
- Compare mode is read-only

**Messaging**
- Unread message count updates correctly
- Deletion request creates a message to contributor and Site Admins

**Favorites**
- Favoriting a version records `(user_id, family_id, version_id)`
- Favoriting a second version of the same family replaces the first (upsert)
- A user cannot hold two favorites for the same family simultaneously
- Favorites are user-specific — another user's favorite for the same family is independent

**AI suggestions and translation**
- "Ask AI" button and "Translate to Swahili" button do not render when `config('features.ai_suggestions')` is false
- When flag is on: "Ask AI" button visible to Editors, Subject Admins, and Site Admins; "Translate to Swahili" button visible to Subject Admins and Site Admins only; both hidden from Teachers
- An Editor sees "Ask AI" but does NOT see "Translate to Swahili" even when the flag is on
- A Subject Admin sees both buttons for their own subject_grade
- A Teacher sees neither button regardless of flag state
- Submitting a prompt returns a suggestion response (use `LessonPlanAdvisor::fake()` in tests; assert with `LessonPlanAdvisor::assertPrompted(...)` — never make real API calls in tests)
- AI response does not auto-modify the document content

---

## 18. Deferred Features (Do Not Build)

- DOC / DOCX export
- Advanced PDF export
- Advanced print formatting
- Network / device transfer workflows
- Rich analytics dashboards or complex charting
- Threaded messaging
- Advanced mobile polish beyond responsive usability
- Email-change workflow unless it comes almost free from existing auth scaffolding
- "View Original" for DOCX uploads — store and allow download of the original `.docx` if the Markdown conversion is unreadable. Deferred: MVP handles this with the pre-conversion warning and mandatory editor review. Worth adding if user frustration with conversion quality is reported.
- Full-text content search (searching within lesson plan Markdown text). If added, implement a `FULLTEXT` index on `lesson_plan_versions.content`. Not needed for MVP — the filter bar searches metadata only.

---

## 19. Seeding Strategy

### DatabaseSeeder (always runs)

Runs on every `php artisan db:seed`, including production installs. Contains only what the application requires to function:

- **System user** — `system@ares.internal`, no password, `is_system = true`
- **Site Administrator** — `admin@sheql.com`, password set via `ADMIN_PASSWORD` env variable (no hardcoded default)

### DemoSeeder (opt-in)

Run separately: `php artisan db:seed --class=DemoSeeder`

Intended for development, review, and demo environments only. Never run on production. All demo passwords are `password`.

**Demo users:**

| Name | Username | Email | Role |
|---|---|---|---|
| Alice Kamau | `alice` | `alice@demo.test` | Subject Admin — Mathematics Grade 10 |
| Bob Ochieng | `bob` | `bob@demo.test` | Editor — Mathematics Grade 10 |
| Carol Mwangi | `carol` | `carol@demo.test` | Editor — Science Grade 10 |
| David Njoroge | `david` | `david@demo.test` | Teacher (no subject assignment) |
| Eve Wanjiku | `eve` | `eve@demo.test` | Subject Admin — Science Grade 10 |

Eve is seeded as the existing Subject Admin of Science Grade 10. The **auto-demotion** test scenario is: the Site Admin promotes Carol (currently Editor, Science Grade 10) to Subject Admin — Eve should be automatically demoted to Editor.

**Demo subjects:** Mathematics, English, Science, Kiswahili

**Demo subject_grades:** Mathematics Grade 10, Mathematics Grade 11, English Grade 10, Science Grade 10

**Demo lesson plan content:**
- At least one family with 3+ versions; one version marked official, a different version favourited by Alice — exercises the "your favourite differs from official" UI state
- At least one family with no official version set — exercises the no-official fallback
- Placeholder markdown content is sufficient (no real lesson plans required)

**Demo message:**
- One message from the System user to David Njoroge — so the inbox and unread badge are testable immediately on first login as David

**Note:** A clear warning comment at the top of `DemoSeeder.php`:
```php
// DEMO ONLY — never run this seeder in production.
// All passwords are 'password'. This seeder is for development and review use only.
```

---

## 20. Implementation Order

Build in this sequence unless a clearly better order presents itself:

> **First implementation artifact:** Before generating any code, write and validate a **Blueprint draft YAML** (`draft.yaml`) covering all models, their columns, relationships, and controllers. Run `php artisan blueprint:build` against it and review the output. Fix the YAML until the generated scaffolding is correct before proceeding. The draft YAML is the implementation spec for the data layer — it replaces guessing about migration column types and relationship shapes.

1. Project scaffolding — Boost install, Blueprint install, AI SDK install, package version verification
2. Auth and user model (including System user seed via `DatabaseSeeder`)
3. Subject, SubjectGrade, `subject_grade_user` pivot, role logic
4. Lesson-plan family / version schema and migrations (use Blueprint draft YAML for models/migrations/factories)
5. Versioning service (semver bump, `official_version_id` toggle, immutability)
6. Filament panels setup (app panel + admin panel)
7. Lesson browsing and viewing
8. Editing and save-new-version flow
9. AI suggestions panel ("Ask AI" button) — `LessonPlanAdvisor` agent wired into edit panel
10. Compare flow
11. Favorites (version-level, upsert)
12. Messaging and unread alerts
13. Admin panel workflows
14. DemoSeeder
15. Test coverage and cleanup

---

## 21. Definition of Done

The MVP is complete when:

- The app installs and runs on a DreamHost-compatible shared hosting environment
- Auth works (register, verify, login, logout, password reset)
- Roles and subject assignments work with correct scoping
- Lesson plans can be created, viewed, edited into new versions, compared, favorited, and marked official correctly
- Subject Administrator promotion / demotion logic (including auto-demotion) works correctly
- "Ask AI" button functions in the edit panel for authorized users
- Messaging and unread alerts work
- Admin panel covers: subject assignment, role management, official status, deletion request review
- `DemoSeeder` runs cleanly and produces a fully navigable demo environment
- Tests cover all key business rules listed in Section 17
- All deferred features remain deferred

---

## 22. Conventions and Constraints

- Keep scope under control. Do not invent features not listed here.
- Prefer maintainable, ordinary Laravel patterns over cleverness.
- If a requested package version is incompatible with the actual environment, choose the nearest stable alternative and document the deviation here.
- If something is ambiguous, choose the simplest maintainable option and document it in this file.
- "Class" is a reserved PHP keyword and is not used anywhere in this codebase.
- **`Subject`** (`Subject` model, `subjects` table) refers to the academic discipline only — Mathematics, English, etc.
- **`SubjectGrade`** (`SubjectGrade` model, `subject_grades` table, `subject_grade_user` pivot) is the assignable unit combining a subject and an integer grade level. This is the entity to which roles, lesson-plan families, and user assignments are attached.
- Grade is always stored and handled as an integer. Always displayed as "Grade N" in the UI.
- All critical business rules enforced at the **service layer and database level**, not only in UI components.
- Policies are the single source of truth for authorization logic.

---

*Last updated: 2026-03-21. Added Section 3 — AI Tooling and Knowledge Currency: knowledge currency warning, pre-build verification checklist, doc URLs, CLAUDE.md guidance, version pinning template. Section count: 22.*
