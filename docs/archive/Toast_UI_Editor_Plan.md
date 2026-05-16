# Toast UI Editor Integration Plan — Final

## 1. Architecture: Alpine Component in the Existing Page

**Do not** create a custom Filament field class or a standalone Livewire sub-component. The correct pattern is an Alpine-managed editor island inside the existing `editMode` block of `view-lesson-plan-family.blade.php`.

The page is already a single Livewire component (`ViewLessonPlanFamily`) with `$editContent` as the backing property and `saveNewVersion()` as the save handler. Toast UI replaces only the textarea. Everything else — save button, version bump radios, revision note field, discard button — stays unchanged.

```
ViewLessonPlanFamily (Livewire)
  └── view-lesson-plan-family.blade.php
        └── @if($editMode)
              └── x-data Alpine scope
                    └── wire:ignore div  ← editor lives here
                          └── Toast UI Editor instance (JS, Alpine-managed)
```

The existing tab structure ("View Lesson" / "Edit Lesson") is removed. Toast UI provides its own built-in WYSIWYG/Markdown mode toggle in the toolbar — teachers get the same choice without a custom tab UI.

The revised edit mode layout:
```
[Save Edits]  [version bump radios]  [Discard Edits]   ← unchanged
[Revision note input]                                    ← unchanged
[Toast UI Editor — full width, 600px]
  [toolbar includes built-in mode switch]
```

---

## 2. GFM Feature Set — Toolbar

Start minimal. Enable only what lesson plans demonstrably need:

```
heading, bold, italic, strike
ul, ol, task
table
link
[WYSIWYG / Markdown mode toggle — built in to Toast UI]
```

Explicitly excluded from v1:
- `image` — no upload service
- `hr` — unnecessary noise in lesson plans
- `code`, `codeBlock` — no demonstrated need; add only if real users request it
- No plugins: no chart, no UML, no colour syntax, no merged-cell tables (merged cells are not standard GFM and won't render in the viewer)

Set `language: 'en'` explicitly. Height: `600px`, `minHeight: 300px`.

Task lists (`- [ ]`) are included because they are genuinely useful for activity checklists in lesson plans.

**Implementation note:** The toolbar item name strings (e.g. `'heading'`, `'ul'`, `'task'`) must be verified against the exact installed version of `@toast-ui/editor` before coding the blade template. These names have changed between major versions. Check the package's own documentation or source after `npm install`.

---

## 3. Livewire/Alpine Bridge — Sync on Save Only

### The `wire:ignore` Rule

Wrap the editor mount node in `wire:ignore`. Without it, any Livewire DOM morph would destroy the editor instance:

```html
<div wire:ignore id="toast-editor-mount-{{ $record->id }}">
    {{-- Toast UI mounts here --}}
</div>
```

The unique ID using `$record->id` matters — Livewire uses element IDs as morph anchors.

### Sync Strategy: Alpine Holds the State, Syncs on Save Only

**Do not call `$wire.set('editContent', ...)` on every editor change** — this generates a Livewire HTTP request per keystroke.

**Do not sync on mode switch.** Toast UI manages both WYSIWYG and Markdown modes internally. The content is consistent within the editor regardless of which mode is displayed. Switching modes does not change anything that needs persisting server-side.

Alpine holds the content locally. Livewire is updated only at one moment: **immediately before calling `saveNewVersion()`**.

```javascript
// In Alpine x-data
{
    editor: null,
    saving: false,
    initialMarkdown: '',

    async init() {
        // ... editor initialisation (see section 6)
        this.initialMarkdown = this.editor.getMarkdown();

        this._beforeUnload = (e) => {
            if (this.editor && this.editor.getMarkdown() !== this.initialMarkdown) {
                e.preventDefault();
                e.returnValue = '';
            }
        };
        window.addEventListener('beforeunload', this._beforeUnload);
    },

    async save() {
        if (this.saving) return;
        this.saving = true;
        try {
            const md = this.editor.getMarkdown();
            await $wire.set('editContent', md);
            await $wire.call('saveNewVersion');
            // Update snapshot so the beforeunload warning does not fire
            // after a successful save if the component stays mounted.
            // Note: a successful save usually triggers a Livewire re-render
            // that tears down Alpine entirely — this line is a safety net
            // for cases where the component stays mounted (e.g. no-op save).
            this.initialMarkdown = md;
        } finally {
            // finally runs even if Alpine is being torn down after save —
            // this is harmless. Do not build logic that depends on
            // this.saving being false after a successful save.
            this.saving = false;
        }
    },

    cancel() {
        $wire.call('cancelEditMode');
    },

    destroy() {
        window.removeEventListener('beforeunload', this._beforeUnload);
        if (this.editor) {
            this.editor.destroy();
            this.editor = null;
        }
    }
}
```

The `saving` flag disables the Save button during the request, preventing double-submissions. The Save button in the blade template should reflect this:

```html
<x-filament::button
    x-on:click="save()"
    x-bind:disabled="saving"
    x-bind:class="saving ? 'opacity-50 cursor-not-allowed' : ''"
>
    <span x-show="!saving">Save Edits</span>
    <span x-show="saving">Saving…</span>
</x-filament::button>
```

### Dedicated Livewire Methods for Edit Mode Lifecycle

**Do not use `$wire.call('$set', ...)` to exit edit mode.** That is an internal Livewire mechanism, not a stable public API.

Add explicit methods to `ViewLessonPlanFamily`:

```php
public function enterEditMode(): void
{
    $this->authorize('create', [LessonPlanVersion::class, $this->record]);
    $this->editContent = $this->selectedVersion?->content ?? '';
    $this->baseLatestVersionId = $this->record->latestVersion?->id;
    $this->editMode = true;
}

public function cancelEditMode(): void
{
    $this->resetEditState();
}

public function saveNewVersion(): void
{
    $this->authorize('create', [LessonPlanVersion::class, $this->record]);
    // ... validation, stale check, normalization, no-op check, addVersion()
    $this->resetEditState();
}

private function resetEditState(): void
{
    $this->editMode = false;
    $this->editContent = $this->selectedVersion?->content ?? '';
    $this->revisionNote = null;
    $this->versionBump = 'patch'; // reset to safe default; do not carry over previous session's choice
    $this->baseLatestVersionId = null;
}
```

All three exit paths — save, cancel, and no-op — call `resetEditState()`, ensuring state is always cleaned up consistently.

### Stale Version Guard — Enforced in PHP Against Fresh Database State

**Do not compare `$this->baseLatestVersionId` to `$this->selectedVersion?->id`.** Both values are held in the same Livewire component's in-memory state. If another user creates a new version while this editor is open, the component's own `selectedVersion` is likely still the old one — so the comparison passes incorrectly and the guard provides no protection.

Instead, store the family's latest version ID when edit mode opens (`$this->baseLatestVersionId`), then on save re-fetch that value directly from the database:

```php
// Inside saveNewVersion(), after authorization, before normalization
$freshLatestVersionId = $this->record->fresh(['latestVersion'])->latestVersion?->id;

if ($freshLatestVersionId !== $this->baseLatestVersionId) {
    Notification::make('stale-version')
        ->title('The lesson plan was updated while you were editing.')
        ->body('A newer version exists. Copy any unsaved changes before refreshing.')
        ->warning()
        ->send();
    // Do NOT reset edit state here — the user may have unsaved work.
    // Leave edit mode open so they can copy their content if needed.
    return;
}
```

**Do not call `resetEditState()` on a stale version detection.** Doing so exits edit mode and discards the user's unsaved work without any opportunity to recover it. The correct behaviour is to warn and stay in edit mode, leaving the user in control of what to do next.

Pass `baseLatestVersionId` to Alpine via `Js::from()` for an optional early client-side warning only — this is a UX convenience, not the authoritative check:

```javascript
baseLatestVersionId: {{ Js::from($baseLatestVersionId) }},
```

The PHP check on fresh database state is the authoritative guard.

---

## 4. Normalization Layer

### Service: `app/Services/MarkdownNormalizer.php`

A pure PHP class, no dependencies. Applies only non-semantic, non-controversial transformations.

**V1 normalizations — in order:**

1. **Line ending unification.** Convert `\r\n` (Windows) and lone `\r` (old Mac) to `\n`. Toast UI on Windows browsers may produce CRLF. Strings containing a mix of both must be handled correctly in a single pass.
2. **Final newline.** If the string is non-empty and does not end with `\n`, append one.

**Explicitly excluded from v1:**
- Trailing space/tab stripping — trailing double-space is a GFM hard line break; stripping it is a semantic change
- Multiple blank line collapsing — while GFM renders them identically, collapsing them changes the source document in ways that are not purely cosmetic to the author; defer until a specific problem is observed

**Edge case rule:**
- Empty string in → empty string out (do not append `\n` to an empty string)
- Non-empty string → ends with exactly one `\n`

**Idempotency requirement (mandatory):** Normalising an already-normalised string must produce identical output. This is a required unit test.

### Order of Operations in `saveNewVersion()`

1. Authorization check — first, unchanged
2. Validate `editContent` — on the raw value, before normalization
3. Stale version check — re-fetch `$this->record->fresh(['latestVersion'])->latestVersion?->id` and compare against `$this->baseLatestVersionId`; if stale, warn and return **without** resetting edit state
4. Normalize via `MarkdownNormalizer`
5. No-op check — compare normalized new content against normalized current version content; if identical, send info notification and call `resetEditState()`
6. Call `addVersion()` with normalized content
7. Call `resetEditState()`

```php
$normalized = app(MarkdownNormalizer::class)->normalize($this->editContent);

if ($normalized === app(MarkdownNormalizer::class)->normalize($this->selectedVersion?->content ?? '')) {
    Notification::make('no-change')
        ->title('No changes detected — content is identical to the current version.')
        ->info()
        ->send();
    $this->resetEditState();
    return;
}

$this->editContent = $normalized;
// ... call addVersion(), then resetEditState()
```

---

## 5. Paste Policy

Toast UI WYSIWYG mode accepts HTML clipboard payloads and converts them to markdown. For basic content from Google Docs (bold, italic, lists) this works acceptably. For Word documents with nested spans and inline styles, the result can be messy.

**Approach: inform, don't restrict.**

Add a dismissable info banner inside the edit panel:

> _Pasting from Word or Google Docs? Use **Paste as Plain Text** (Ctrl+Shift+V / Cmd+Shift+V) to avoid formatting problems._

Do not implement a custom paste interceptor — it would break normal in-editor copy/paste. Do not add a server-side HTML sanitizer — by the time `saveNewVersion()` runs, the editor has already serialized to markdown; there is no HTML in the stored string.

If a teacher accidentally saves a version with messy markdown, they can view the diff against the previous version and restore it. The versioning model is the safety net.

---

## 6. Asset Pipeline

### Install

```bash
npm install @toast-ui/editor
```

### CSS — Include in Normal Vite Build

Load the editor CSS in `resources/css/app.css`:

```css
@import '@toast-ui/editor/dist/toastui-editor.css';
@import '@toast-ui/editor/dist/theme/toastui-editor-dark.css';
```

Do not dynamic-import the CSS. Dynamic CSS loading causes flash of unstyled content and is unreliable to debug on shared hosting. The CSS cost is small relative to Filament's own styles and is worth the simplicity.

The dark theme applies when the editor is initialised with `theme: 'dark'`. Read Filament's dark mode class at Alpine `init()` time:

```javascript
theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light'
```

### JS — Lazy Load Only

Only editors and admins ever enter edit mode. Non-editing users should never download the editor JS bundle (~400–600 KB gzipped).

In `resources/js/app.js`:

```javascript
window.loadToastUIEditor = () =>
    import('@toast-ui/editor').then(m => {
        window.ToastUIEditor = m.default;
    });
```

In the Alpine `init()` in the blade template:

```javascript
async init() {
    if (!window.ToastUIEditor) {
        await window.loadToastUIEditor();
    }
    const editor = new window.ToastUIEditor({
        el: document.querySelector('#toast-editor-mount-{{ $record->id }}'),
        initialValue: {{ Js::from($editContent) }},
        previewStyle: 'tab',
        initialEditType: 'wysiwyg',
        language: 'en',
        height: '600px',
        minHeight: '300px',
        theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
        toolbarItems: [
            // Verify these names against the installed package version before use
            ['heading', 'bold', 'italic', 'strike'],
            ['ul', 'ol', 'task'],
            ['table', 'link'],
        ],
    });
    this.editor = editor;
    this.initialMarkdown = editor.getMarkdown();

    this._beforeUnload = (e) => {
        if (this.editor && this.editor.getMarkdown() !== this.initialMarkdown) {
            e.preventDefault();
            e.returnValue = '';
        }
    };
    window.addEventListener('beforeunload', this._beforeUnload);
},
```

### DreamHost Deployment

Build locally: `npm run build`. Deploy `public/build/` to DreamHost as before. No server-side Node.js needed. No changes to deployment scripts.

After deploying, run `php artisan view:clear` on the server since the `@markdown` directive compiles into cached views.

---

## 7. Authorization

No policy changes needed. The existing `LessonPlanVersionPolicy` `create` check already gates:
- Editors for the relevant `SubjectGrade` (pivot role)
- Subject Admins for the relevant `SubjectGrade` (`subject_grades.subject_admin_user_id`)
- Site Administrators (global Spatie role)

Both `enterEditMode()` and `saveNewVersion()` call `$this->authorize('create', ...)` independently — authorization is checked at both the UI entry point and the write operation. The "Edit This Plan" button uses `$canEdit` from the policy. Nothing changes.

---

## 8. Testing Plan

### Unit: `tests/Unit/MarkdownNormalizerTest.php`

```
it('normalizes CRLF to LF')
it('normalizes lone CR to LF')
it('normalizes a string containing mixed CRLF and lone CR in one pass')
it('leaves LF-only content unchanged')
it('appends trailing newline to non-empty content that lacks one')
it('does not double-append trailing newline if already present')
it('returns empty string unchanged — does not append newline to empty string')
it('does not alter headings, bullets, bold, italic, or table syntax')
it('is idempotent — normalizing twice produces same result as normalizing once')
```

### Feature: `tests/Feature/LessonPlans/EditVersionTest.php`

```
it('editor can enter edit mode — editMode becomes true, editContent equals current version content, baseLatestVersionId is set')
it('teacher with no role cannot enter edit mode')
it('unauthorized user cannot call saveNewVersion() directly even without entering edit mode')
it('saving normalizes line endings before storing')
it('saving content identical to current version after normalization sends info notification and does not create a new version')
it('saving changed content creates a new version')
it('the new version stores the normalized content, not the raw editor output')
it('cancelEditMode resets editMode, revisionNote, versionBump, editContent, and baseLatestVersionId')
it('saveNewVersion resets all edit state after a successful save')
it('saveNewVersion sends warning notification and keeps edit mode open when a newer version exists in the database')
it('version bump selection major is respected on save')
it('version bump selection minor is respected on save')
it('version bump selection patch is respected on save')
it('revision note is stored with the new version')
it('saving empty content fails validation')
```

### Manual Smoke Checklist (Formal Deliverable)

Because the JS/Alpine/Toast UI integration cannot be covered by Pest, a written manual checklist must be completed and signed off before the feature is considered shipped. It is not optional.

```
□ Enter edit mode — editor loads, existing content is visible and correctly formatted
□ Edit text in WYSIWYG mode — bold, italic, heading, list all work
□ Switch to Markdown source mode via toolbar — raw GFM is visible and correct
□ Switch back to WYSIWYG — content is unchanged
□ Edit a table cell in WYSIWYG mode — validate usability; note if Markdown mode
    is more practical for table-heavy documents and record finding for UX review
□ Click Save Edits — button disables and shows "Saving…" while request is in flight
□ New version appears in version selector after save completes
□ Open the new version — content is correct in the read-only view
□ Open the new version as PDF — content is rendered (not raw markdown)
□ Save identical content — info notification appears, no new version created
□ Click Discard Edits — returns to read-only view, version count unchanged,
    no state left over from edit session; versionBump resets to patch
□ Navigate away mid-edit without saving — browser warns about unsaved changes
□ Navigate away after saving — browser does NOT warn (snapshot was updated)
□ Paste plain text from clipboard — content appears correctly
□ Paste rich content from Google Docs — paste-warning banner is visible
□ Dark mode: enter edit mode — editor uses dark theme
□ Verify on tablet viewport (~768px) — toolbar is usable
□ Concurrent edit test: open the same lesson plan in two browser windows;
    save in window A; attempt to save in window B — verify warning notification
    appears and edit mode remains open with content intact in window B
```

### Browser Automation — Phase 1.5

A single Laravel Dusk smoke test covering the core path (enter edit mode → type → save → confirm new version) is scheduled as an immediate follow-up to v1 shipping, not deferred indefinitely. The manual checklist covers the gap in the interim.

---

## 9. Known Limitations

- **Mobile:** Toast UI's toolbar is wide; it wraps or overflows below ~700px. Phone editing is a known limitation. Tablet and laptop are the intended targets.
- **Table editing in WYSIWYG mode:** Complex operations (adding columns mid-table) require keyboard shortcuts that are not discoverable. Document Tab to navigate cells and the right-click context menu in a help tooltip on the edit panel. If smoke testing reveals that table-heavy lesson plans are significantly easier to edit in Markdown source mode, consider defaulting `initialEditType` to `'markdown'` for a follow-up release.
- **Concurrent editing:** Two users editing the same family simultaneously will each produce a new version — no merge, no overwrite. The stale version guard in `saveNewVersion()` detects this via a fresh database query, warns the user, and keeps edit mode open so unsaved work is not lost.

---

## 10. Implementation Sequence

1. `npm install @toast-ui/editor` — verify `npm run build` completes cleanly; check bundle output size; confirm toolbar item names against the installed version's documentation or source
2. Add `@import` lines to `resources/css/app.css` and the lazy-load function to `resources/js/app.js`
3. Create `app/Services/MarkdownNormalizer.php` — pure PHP, no dependencies
4. Write `tests/Unit/MarkdownNormalizerTest.php` and confirm all pass
5. Add `$baseLatestVersionId` public property to `ViewLessonPlanFamily`; add `enterEditMode()`, `cancelEditMode()`, and private `resetEditState()` methods; modify `saveNewVersion()` to add normalization, no-op guard, and fresh-DB stale version check (warn and return without resetting state on stale detection)
6. Write `tests/Feature/LessonPlans/EditVersionTest.php` and confirm all pass
7. Rewrite the `@if($editMode)` section of `view-lesson-plan-family.blade.php` — add `wire:ignore` mount div; rewrite Alpine `x-data` scope with lazy-load init, editor init, `initialMarkdown` snapshot, `saving` flag, `beforeunload` handler, sync-on-save, and cleanup
8. Remove the existing tab structure, textarea, and selection-capture Alpine code; disconnect the floating "Edit Selected Text" button from the UI
9. Run the manual smoke checklist locally
10. `npm run build`, deploy `public/build/` to DreamHost, run `php artisan view:clear` on the server
11. Run the manual smoke checklist on production
12. Schedule the Dusk smoke test as phase 1.5
13. Once Toast UI is confirmed stable in production (suggested: after two weeks of normal use with no editing regressions), delete `MarkdownSelectionMatcher.php`, its associated Livewire method, and any remaining disconnected selection-mapping code

---

## Critical Files for Implementation

| File | Change |
|---|---|
| `app/Services/MarkdownNormalizer.php` | New file |
| `app/Filament/App/Resources/LessonPlanFamilyResource/Pages/ViewLessonPlanFamily.php` | Add `$baseLatestVersionId` property; add `enterEditMode()`, `cancelEditMode()`, `resetEditState()`; modify `saveNewVersion()` |
| `resources/views/filament/app/pages/view-lesson-plan-family.blade.php` | Replace edit mode section with `wire:ignore` div, Alpine editor init, saving state on button |
| `resources/js/app.js` | Add lazy-load function for Toast UI JS |
| `resources/css/app.css` | Add `@import` for Toast UI CSS and dark theme |
| `tests/Unit/MarkdownNormalizerTest.php` | New file |
| `tests/Feature/LessonPlans/EditVersionTest.php` | New file |
