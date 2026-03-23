# ARES Lesson Repository — Build Progress

Lightweight tracker extracted from `Lesson2.md`. Update this file as features are completed.
For full spec details (data model, auth rules, UX flows, test requirements) always refer to `Lesson2.md`.

**Live site:** https://www.sheql.com
**Last updated:** 2026-03-22 (session 5)

---

## Legend
- ✅ Done and deployed
- 🔧 In progress
- ⬜ Not started
- ❌ Deferred (Section 18 of spec — do not build)

---

## Infrastructure / Setup
- ✅ Laravel 13 + Filament 5 + Livewire 4 scaffold
- ✅ Spatie Permission + Filament Shield installed
- ✅ Models, migrations, factories (Blueprint-generated)
- ✅ DatabaseSeeder (System user + Site Admin)
- ✅ DemoSeeder (5 demo users, subjects, subject_grades, lesson plans)
- ✅ DreamHost deployment pipeline (`UPDATE_SITE.sh`, rsync for assets)
- ✅ Trusted proxies, APP_KEY, session/cache as `file`, APP_DEBUG off

---

## Authentication (Section 5)
- ✅ Login / Logout
- ✅ Email verification required before access
- ✅ Registration page (username + name + email + password; Teacher by default)
- ✅ Forgot password / reset password flow (`password_reset_tokens` migration added)
- ✅ Account page with password change (Profile page edit mode)

---

## Admin Panel (Section 16)

### Users
- ✅ List users (system user hidden)
- ✅ Edit user (assign `site_administrator` global role)
- 🔧 Filter bar: name/email search, role, subject_grade assignment ← next sprint
- 🔧 Tabs: All | Site Admins | Subject Admins | Editors | Teachers ← next sprint

### Subjects
- ✅ List, create, edit subjects

### Subject Grades
- ✅ List, create, edit subject_grades
- ✅ Assign Subject Admin (via `SubjectAdminService` transaction)
- ✅ Assign Editors (via pivot)
- ⬜ Filter bar: subject dropdown, grade dropdown, has Subject Admin
- ⬜ Tabs: All | Has Subject Admin | No Subject Admin

### Lesson Plan Families (admin view)
- ⬜ Admin resource for lesson plan families
- ⬜ Filter bar: subject, grade, day, official status, contributor
- ⬜ Tabs: All | Official | Latest revision | Pending deletion request

### Deletion Requests
- ✅ List deletion requests
- ⬜ Filter bar: subject_grade, contributor, requesting admin, status
- ✅ Tabs: All | Pending | Resolved (live counts)
- ✅ Hard-delete action (Site Admin only)

### Summary Counts
- ✅ Dashboard widget: users, subjects, subject_grades, families, versions, pending deletions (clickable cards + deletion banner)

---

## App Panel — Lesson Plan Browsing (Sections 10, 15)

### List page
- ✅ Table showing one row per version (not per family)
- ✅ Filter bar: Subject, Grade, Language dropdowns
- ✅ Tabs: All | Official | Latest | Favorites
- ✅ Official column: ✓ checkmark or blank
- ⬜ Filter bar: Official status toggle, Contributor, My favorites toggle
- ⬜ Summary count cards at top (total families, total versions, favorites)
- ⬜ "Favorite differs from official" indicator on list row
- ⬜ Sortable columns on nested relationship attributes

### View / Version detail page
- ✅ Version sidebar (all versions, official badge, ★ favorite badge)
- ✅ View mode: version metadata, content rendered as Markdown
- ✅ Mark Official button (Subject Admin + Site Admin)
- ✅ Favorite / Unfavorite button
- ✅ "Your favorited version differs from official" warning
- ✅ Compare mode: version selector
- ✅ Compare diff view: side-by-side and unified, pink/green highlights
- ✅ Edit This Plan button → save new version
- ✅ Version bump selector: Major / Minor / Patch with preview version numbers
- ✅ Discard Edits button
- ⬜ Ask AI panel wired to `LessonPlanAdvisor` (UI shell exists; not connected)
- ⬜ Translate to Swahili button wired to `LessonPlanTranslator` (UI shell exists; not connected)

### Create new lesson plan (Section 10)
- ✅ Permission gate: Subject Admin (own subject_grade) + Site Admin only
- ✅ Duplicate family detection → redirect to existing with warning notification
- ✅ "Family not created until save" transactional pattern (VersionService)
- ⬜ File upload: `.md` / `.txt` → load into editor
- ❌ File upload: `.docx` → DOCX conversion pipeline (deferred — Section 18)

---

## Messaging / Inbox (Section 14)
- ✅ Inbox page: list messages (from, subject/preview, unread indicator)
- ✅ Message detail view (ViewMessage page with formatted body)
- ✅ Compose message (to any user except System user)
- ✅ Mark as read (on first open)
- ✅ Unread message count badge in navigation (`getNavigationBadge`)
- ✅ Reply pre-fills To and Subject fields
- ⬜ System-generated messages (duplicate alert, etc.) from System user

---

## Deletion Workflow (Section 13)
- ✅ "Request deletion" button (Subject Admin, own subject_grade)
- ✅ Creates `deletion_requests` record + sends message to contributor + all Site Admins
- ✅ Admin panel deletion request list + hard-delete action

---

## Roles and Permissions UI (Section 6)
- ✅ Site Admin assignment (admin panel UserResource)
- ✅ Subject Admin assignment with auto-demotion (SubjectAdminService, SubjectGradeResource)
- ✅ Editor assignment (subject_grade_user pivot, SubjectGradeResource)
- ⬜ Role promotion/demotion from within lesson plan family view (promote Teacher → Editor, etc.)
- ⬜ Filter users by role and subject_grade assignment (admin panel)

---

## Branding and UI (Section 4)
- ⬜ Header: ARES Education / Kenya Lesson Plan Repository top-left
- ⬜ Header: Lessons | Admin (if authorized) | unread badge | account menu | sign out
- ⬜ Footer: "Kenya Lesson Plan Repository © 2026 ARES Education — CC BY-SA 4.0"

---

## AI Features (Sections 12, gated by `AI_SUGGESTIONS_ENABLED`)
- ⬜ `LessonPlanAdvisor` agent class (`app/Ai/Agents/LessonPlanAdvisor.php`)
- ⬜ Ask AI panel → sends prompt + document content → streams response
- ⬜ `LessonPlanTranslator` agent class (`app/Ai/Agents/LessonPlanTranslator.php`)
- ⬜ Translate to Swahili flow → review panel → transactional save of new Swahili family/version
- ⬜ Version number inheritance rule on translation (inherits source version; fallback to bump if conflict)

---

## Tests (Section 17)
- ⬜ Auth: registration requires email verification
- ⬜ Auth: password reset, logout
- ⬜ Roles: Teachers cannot edit; Editors scoped to subject_grade; Subject Admin scoped
- ⬜ Roles: one Subject Admin per subject_grade; auto-demotion on promotion
- ⬜ Versioning: immutability, bump types, 1.0.0 first version, duplicate rejection
- ⬜ Versioning: translation inherits source version number
- ⬜ Compare: same-family only, read-only
- ⬜ Messaging: unread count, deletion request creates messages
- ⬜ Favorites: upsert, uniqueness per family per user
- ⬜ AI: buttons hidden when flag off; correct role visibility; fake agent in tests

---

## Deferred (do not build) — Section 18
- ❌ DOC/DOCX export
- ❌ Advanced PDF export / print formatting
- ❌ Network/device transfer workflows
- ❌ Rich analytics dashboards
- ❌ Threaded messaging
- ❌ Email-change workflow
- ❌ "View Original" for DOCX uploads
- ❌ Full-text content search

---

## Current Sprint

### ✅ Completed (sessions 1–5)
- Version editor crash fix (`$user` undefined → `auth()->user()`)
- Button labels: "Edit This Plan", "Discard Edits"
- Version bump order (Major / Minor / Patch) with resulting version previews
- Full-width edit mode
- Compare: LCS diff with pink/green highlights, side-by-side + unified toggle
- Lesson list: one row per version, All/Official/Latest/Favorites tabs, Subject/Grade/Language filters, ✓ Official column
- Inbox / messaging: inbox list, message detail, compose, mark-as-read, unread badge in nav, reply pre-fill
- Admin: Create User button + page (username, name, email, password; auto-verified)
- Registration page (username + name + email + password + confirm password; auto email-verified)
- Forgot password / reset password flow
- Bug fixes (Nanoclaw round 1): compose 404, registration 403, filter leaks, version bump radios
- Bug fixes (Nanoclaw round 2): message detail 404, confirm-password clearing on blur
- Bug fix (Nanoclaw round 3): subject/grade filters completely non-functional — replaced dot-notation `whereHas` with explicit nested calls
- Role badge in user avatar dropdown (Teacher / Editor / Subject Admin / Administrator)
- Brand name 35% larger (1.5rem, bold)
- **Deletion workflow**: Request Deletion button (Subject Admin), admin `DeletionRequestResource` with hard-delete action
- **Admin dashboard**: stat cards (users, subjects, grades, families, versions, official), pending deletions banner (red when pending), clickable cards, quick links

### 🔧 Next (chosen)
**Admin user filter bar + tabs** — UserResource currently shows a flat list; the school needs to search by name/email and filter by role.

Scope:
- Text search filter on name and email columns
- Tabs: All | Site Admins | Subject Admins | Editors | Teachers (with live counts)
- Each tab scopes the query to users with that role/assignment

Following features (in order):
1. **File upload on Create page** — `.md`/`.txt` → populate content textarea (core Create flow already done)
2. **Footer** — "Kenya Lesson Plan Repository © 2026 ARES Education — CC BY-SA 4.0"
3. **AI features** — LessonPlanAdvisor streaming, LessonPlanTranslator (gated by `AI_SUGGESTIONS_ENABLED`)
