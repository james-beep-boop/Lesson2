# ARES Lesson Library — Build Progress

Lightweight tracker extracted from `Lesson2.md`. For requirements and behavioral rules, defer to `Lesson2.md`.

**Live site:** https://www.sheql.com
**Last updated:** 2026-04-06

---

## Legend

- `Done`: implemented and in the codebase
- `In progress`: partially implemented or planned next
- `Not started`: still pending
- `Deferred`: intentionally out of scope for MVP

---

## Current State

### Infrastructure / Setup

- Done: Laravel 13 + Filament 5 + Livewire 4 scaffold
- Done: Spatie Permission + Filament Shield installed
- Done: production-safe `DatabaseSeeder` and separate `DemoSeeder`
- Done: current deploy pipeline is local rsync upload via `DEPLOY_SITE.sh` plus DreamHost finalization via `UPDATE_SITE.sh`
- Done: DreamHost runtime configuration for trusted proxies, file-based session/cache, maintenance mode, cache rebuild, and OPcache reset

### Authentication / Access

- Done: login, logout, registration, email verification gate, forgot/reset password
- Done: app-panel vs admin-panel access rules
- Done: tab-guard logout route and tests

### Lesson Plans

- Done: family/version model, semantic version bumps, immutable versions
- Done: compare view, official version marking, favorites, deletion request flow
- Done: file import for `.md`, `.txt`, and `.docx`
- Done: PDF download and email PDF
- Done: Ask AI streaming panel
- Done: Swahili translation preview and translation email PDF
- In progress: richer editing experience will move from the current markdown editor to Toast UI
- Not started: transactional save of translated Swahili family/version to the database

### Messaging / Admin

- Done: inbox list, message detail, compose, reply, unread counts
- Done: admin user management, subject management, subject-grade management
- Done: deletion request admin resource
- Done: admin dashboard summary counts
- Not started: system-generated duplicate-family message flow
- Not started: admin user filtering by `subject_grade` assignment

### Testing

- Done: auth coverage exists
- Done: role and permission coverage exists
- Done: favorites, messaging, deletion, PDF, translation preview, and tab-guard tests exist
- In progress: broader cleanup / expansion of the Pest suite to fully match Section 17 of `Lesson2.md`

---

## Next Sprint

Definitive next step: [`Toast_UI_Editor_Plan.md`](../../Toast_UI_Editor_Plan.md)

Priority order:

1. Toast UI editor integration inside the existing `ViewLessonPlanFamily` edit mode
2. Save-only Alpine/Livewire sync
3. `MarkdownNormalizer` service and tests
4. stale-version guard during save
5. paste guidance / editor UX cleanup

After that:

1. system-generated duplicate-family messaging
2. admin user filtering by `subject_grade`
3. broader spec-alignment testing
4. persisted Swahili translation save flow

---

## Deferred

- Deferred: DOC / DOCX export
- Deferred: advanced PDF export / print formatting
- Deferred: network or device transfer workflows
- Deferred: rich analytics dashboards
- Deferred: threaded messaging
- Deferred: email-change workflow
- Deferred: full-text content search
