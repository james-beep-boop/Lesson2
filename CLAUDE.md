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
Laravel AI SDK      — in-editor AI suggestions and translation (gated by config flag `AI_SUGGESTIONS_ENABLED`)
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
- First version in any new family is always `1.0.0`. **Exception:** a family created by AI translation inherits the version number of the English source version (e.g. translating v2.1.0 produces a Swahili family starting at v2.1.0).
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

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.5
- filament/filament (FILAMENT) - v5
- laravel/ai (AI) - v0
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- livewire/livewire (LIVEWIRE) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `laravel-best-practices` — Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns.
- `pest-testing` — Use this skill for Pest PHP testing in Laravel projects only. Trigger whenever any test is being written, edited, fixed, or refactored — including fixing tests that broke after a code change, adding assertions, converting PHPUnit to Pest, adding datasets, and TDD workflows. Always activate when the user asks how to write something in Pest, mentions test files or directories (tests/Feature, tests/Unit, tests/Browser), or needs browser testing, smoke testing multiple pages for JS errors, or architecture tests. Covers: it()/expect() syntax, datasets, mocking, browser testing (visit/click/fill), smoke testing, arch(), Livewire component tests, RefreshDatabase, and all Pest 4 features. Do not use for factories, seeders, migrations, controllers, models, or non-test PHP code.
- `tailwindcss-development` — Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audits, build tool configuration, and vanilla CSS.
- `ai-sdk-development` — Builds AI agents, generates text and chat responses, produces images, synthesizes audio, transcribes speech, generates vector embeddings, reranks documents, and manages files and vector stores using the Laravel AI SDK (laravel/ai). Supports structured output, streaming, tools, conversation memory, middleware, queueing, broadcasting, and provider failover. Use when building, editing, updating, debugging, or testing any AI functionality, including agents, LLMs, chatbots, text generation, image generation, audio, transcription, embeddings, RAG, similarity search, vector stores, prompting, structured output, or any AI provider (OpenAI, Anthropic, Gemini, Cohere, Groq, xAI, ElevenLabs, Jina, OpenRouter).

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== filament/filament rules ===

## Filament

- Filament is used by this application. Follow the existing conventions for how and where it is implemented.
- Filament is a Server-Driven UI (SDUI) framework for Laravel that lets you define user interfaces in PHP using structured configuration objects. Built on Livewire, Alpine.js, and Tailwind CSS.
- Use the `search-docs` tool for official documentation on Artisan commands, code examples, testing, relationships, and idiomatic practices. If `search-docs` is unavailable, refer to https://filamentphp.com/docs.

### Artisan

- Always use Filament-specific Artisan commands to create files. Find available commands with the `list-artisan-commands` tool, or run `php artisan --help`.
- Always inspect required options before running a command, and always pass `--no-interaction`.

### Patterns

Always use static `make()` methods to initialize components. Most configuration methods accept a `Closure` for dynamic values.

Use `Get $get` to read other form field values for conditional logic:

<code-snippet name="Conditional form field visibility" lang="php">
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

Select::make('type')
    ->options(CompanyType::class)
    ->required()
    ->live(),

TextInput::make('company_name')
    ->required()
    ->visible(fn (Get $get): bool => $get('type') === 'business'),

</code-snippet>

Use `state()` with a `Closure` to compute derived column values:

<code-snippet name="Computed table column value" lang="php">
use Filament\Tables\Columns\TextColumn;

TextColumn::make('full_name')
    ->state(fn (User $record): string => "{$record->first_name} {$record->last_name}"),

</code-snippet>

Actions encapsulate a button with an optional modal form and logic:

<code-snippet name="Action with modal form" lang="php">
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;

Action::make('updateEmail')
    ->schema([
        TextInput::make('email')
            ->email()
            ->required(),
    ])
    ->action(fn (array $data, User $record) => $record->update($data))

</code-snippet>

### Testing

Always authenticate before testing panel functionality. Filament uses Livewire, so use `Livewire::test()` or `livewire()` (available when `pestphp/pest-plugin-livewire` is in `composer.json`):

<code-snippet name="Table test" lang="php">
use function Pest\Livewire\livewire;

livewire(ListUsers::class)
    ->assertCanSeeTableRecords($users)
    ->searchTable($users->first()->name)
    ->assertCanSeeTableRecords($users->take(1))
    ->assertCanNotSeeTableRecords($users->skip(1));

</code-snippet>

<code-snippet name="Create resource test" lang="php">
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

livewire(CreateUser::class)
    ->fillForm([
        'name' => 'Test',
        'email' => 'test@example.com',
    ])
    ->call('create')
    ->assertNotified()
    ->assertRedirect();

assertDatabaseHas(User::class, [
    'name' => 'Test',
    'email' => 'test@example.com',
]);

</code-snippet>

<code-snippet name="Testing validation" lang="php">
use function Pest\Livewire\livewire;

livewire(CreateUser::class)
    ->fillForm([
        'name' => null,
        'email' => 'invalid-email',
    ])
    ->call('create')
    ->assertHasFormErrors([
        'name' => 'required',
        'email' => 'email',
    ])
    ->assertNotNotified();

</code-snippet>

<code-snippet name="Calling actions in pages" lang="php">
use Filament\Actions\DeleteAction;
use function Pest\Livewire\livewire;

livewire(EditUser::class, ['record' => $user->id])
    ->callAction(DeleteAction::class)
    ->assertNotified()
    ->assertRedirect();

</code-snippet>

<code-snippet name="Calling actions in tables" lang="php">
use Filament\Actions\Testing\TestAction;
use function Pest\Livewire\livewire;

livewire(ListUsers::class)
    ->callAction(TestAction::make('promote')->table($user), [
        'role' => 'admin',
    ])
    ->assertNotified();

</code-snippet>

### Correct Namespaces

- Form fields (`TextInput`, `Select`, etc.): `Filament\Forms\Components\`
- Infolist entries (`TextEntry`, `IconEntry`, etc.): `Filament\Infolists\Components\`
- Layout components (`Grid`, `Section`, `Fieldset`, `Tabs`, `Wizard`, etc.): `Filament\Schemas\Components\`
- Schema utilities (`Get`, `Set`, etc.): `Filament\Schemas\Components\Utilities\`
- Actions (`DeleteAction`, `CreateAction`, etc.): `Filament\Actions\`. Never use `Filament\Tables\Actions\`, `Filament\Forms\Actions\`, or any other sub-namespace for actions.
- Icons: `Filament\Support\Icons\Heroicon` enum (e.g., `Heroicon::PencilSquare`)

### Common Mistakes

- **Never assume public file visibility.** File visibility is `private` by default. Always use `->visibility('public')` when public access is needed.
- **Never assume full-width layout.** `Grid`, `Section`, and `Fieldset` do not span all columns by default. Explicitly set column spans when needed.

=== laravel/ai rules ===

## Laravel AI SDK

- This application uses the Laravel AI SDK (`laravel/ai`) for all AI functionality.
- Activate the `developing-with-ai-sdk` skill when building, editing, updating, debugging, or testing AI agents, text generation, chat, streaming, structured output, tools, image generation, audio, transcription, embeddings, reranking, vector stores, files, conversation memory, or any AI provider integration (OpenAI, Anthropic, Gemini, Cohere, Groq, xAI, ElevenLabs, Jina, OpenRouter).

</laravel-boost-guidelines>
