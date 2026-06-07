# Lesson2 Documentation Cleanup Plan

> **For Hermes:** Use this plan task-by-task. Keep `deployment.md` and `troubleshooting.md` separate and out of scope for this cleanup pass.

**Goal:** Clean up the core Lesson2 documentation set so the shared docs are shorter, easier to publish, and free of machine-specific absolute paths.

**Scope:**
- In scope: `README.md`, `Lesson2.md`, `USER_GUIDE.md`
- Out of scope for this pass: `deployment.md`, `deployment_checklist.md`, `troubleshooting.md`
  - These should remain separate for now.

**Primary outcome:**
- Remove local filesystem paths and replace them with repo-relative links or generic placeholders.
- Reduce repetition across the three in-scope documents.
- Keep each document focused on a distinct purpose:
  - `README.md` = entry point / index
  - `Lesson2.md` = canonical product/spec document
  - `USER_GUIDE.md` = end-user instructions and roles

---

## Current Issues to Fix

1. `README.md` contains absolute local paths in links, especially `/Users/.../Lesson2/...`.
2. `Lesson2.md` is very long and includes a lot of version-history, implementation rationale, and repeated detail that can be shortened.
3. `USER_GUIDE.md` repeats material that is already present in `Lesson2.md` and can be trimmed.
4. The three docs overlap in purpose, so the edits should remove redundancy rather than just rewording the same content.

---

## Proposed Structure After Cleanup

### `README.md`
Keep it as the shortest possible overview page.

Should contain:
- project name
- one-sentence description
- live site URL
- a short list of purpose bullets
- links to the canonical docs using repo-relative paths

Should not contain:
- absolute local paths
- full deploy instructions
- long role tables
- long architectural explanations

### `Lesson2.md`
Keep it as the authoritative spec.

Should contain:
- core product scope
- stack decisions
- roles and authorization model
- major architectural rules
- AI / runtime assumptions that affect implementation

Should be trimmed to:
- remove duplicated summary text found in README/USER_GUIDE
- remove long-winded explanatory paragraphs where a short rule is enough
- keep only the rationale that matters for implementation decisions

### `USER_GUIDE.md`
Keep it as the human-facing usage guide.

Should contain:
- what users can do
- role-based capabilities
- demo logins if still needed
- a small “getting started” section
- concise notes for behavior and limitations

Should be trimmed to:
- avoid repeating canonical spec text
- avoid long implementation detail already covered in `Lesson2.md`
- keep the guide focused on how to use the app, not how it is designed

---

## Cleanup Rules

### Link and path rules
- Replace every `/Users/.../Lesson2/...` link with repo-relative links such as `./Lesson2.md`, `./USER_GUIDE.md`, or `./README.md`.
- Prefer relative links for all internal docs.
- If a link must refer to a file outside the repo, replace it with a generic placeholder or explanatory text instead of a machine-specific path.

### Content-reduction rules
- Remove duplicated overview sentences that appear in more than one doc.
- Keep only one place for each major topic:
  - product overview
  - implementation spec
  - user instructions
- When a section exists in two docs, decide which doc owns it and cut the other copy.
- Prefer concise bullets over long paragraphs when the meaning does not change.

### Language rules
- Keep terminology consistent:
  - use one name for the product
  - use one name for roles
  - use one name for the live site
- Remove phrasing that sounds like a historical note if it does not help current readers.

---

## Task Plan

### Task 1: Inventory duplicated content
**Objective:** Identify which sections in `README.md`, `Lesson2.md`, and `USER_GUIDE.md` overlap and decide which doc owns each topic.

**Files to review:**
- `README.md`
- `Lesson2.md`
- `USER_GUIDE.md`

**Checklist:**
- Mark every section in each file as one of:
  - keep here
  - move elsewhere
  - delete as redundant
- Capture any absolute paths, long URLs, or machine-specific references that need replacement.
- Identify sections that are clearly repeated across multiple docs.

**Deliverable:**
- A short internal notes list or commented checklist before editing, so the edit pass is deliberate rather than ad hoc.

---

### Task 2: Rewrite `README.md` as the index
**Objective:** Turn `README.md` into a compact entry page with only the essential project summary and doc links.

**Files:**
- Modify: `README.md`

**Edits to make:**
- Replace any absolute local paths with repo-relative links.
- Reduce the intro to a brief project summary.
- Keep only the important links:
  - `./Lesson2.md`
  - `./USER_GUIDE.md`
  - `./deployment.md` if you want deployment docs referenced from the index
  - `./troubleshooting.md` only if you intentionally want it discoverable from the index
- Remove repeated detail that belongs in the spec or guide.

**Keep in mind:**
- This file should feel like a landing page, not a second spec.
- If a sentence can be removed without losing navigation value, remove it.

**Verification:**
- Read the rewritten file top-to-bottom and confirm it is short, linkable, and free of local paths.

---

### Task 3: Trim `Lesson2.md` to the canonical spec
**Objective:** Preserve the authoritative implementation rules while shortening the document by removing repeated explanation and historical chatter.

**Files:**
- Modify: `Lesson2.md`

**Edits to make:**
- Keep the spec sections that affect implementation decisions.
- Remove or compress repeated explanations that are already obvious from headings or tables.
- Move user-facing guidance out to `USER_GUIDE.md` if it currently appears here.
- Avoid duplicating README-level summary text.
- Keep the technical rationale only where it changes implementation choices.

**Target shape:**
- clearer section headings
- fewer repeated paragraphs
- more bullets, fewer long blocks of prose
- no machine-specific file paths unless they are truly part of the spec

**Potential cuts:**
- background sentences that repeat the same point in multiple places
- duplicated section introductions
- version-history style commentary that does not alter current requirements

**Verification:**
- The top-level spec should still answer: what is this app, what does it do, how is it structured, and what are the key rules?
- The file should still be the best source for implementation decisions.

---

### Task 4: Trim `USER_GUIDE.md` to the user-facing essentials
**Objective:** Make the user guide easy to scan and focused on actual user actions.

**Files:**
- Modify: `USER_GUIDE.md`

**Edits to make:**
- Keep the role descriptions and demo logins if they are still useful.
- Shorten repetitive capability lists.
- Remove implementation details that belong in `Lesson2.md`.
- Keep the “what can I do?” explanation crisp and user-oriented.

**Suggested emphasis:**
- login / access
- browse / compare / favorite
- edit / create when allowed
- AI and translation features when enabled
- export / email / print / inbox features
- role differences

**Verification:**
- A new reader should understand the app’s main actions in under a minute.
- The guide should not read like a spec document.

---

### Task 5: Cross-check for residual duplication and path leakage
**Objective:** Make sure the three docs are distinct and publishable.

**Files to inspect:**
- `README.md`
- `Lesson2.md`
- `USER_GUIDE.md`

**Checklist:**
- No `/Users/...` paths remain.
- Internal doc links are repo-relative.
- README is shortest and most navigational.
- Lesson2 is the canonical spec.
- USER_GUIDE is the user-facing guide.
- No section is obviously repeated verbatim across files.

**Pass criteria:**
- Each file has a clear job.
- The docs are shorter overall.
- There is less overlap without losing important information.

---

## Suggested Validation Steps

1. Re-read all three docs after editing.
2. Compare headings and section purpose across the files.
3. Confirm that links work as repo-relative references.
4. Search for absolute local paths and remove any remaining matches.
5. If needed, do one final pass to tighten wording in the longest sections.

Suggested checks:
- search for `/Users/`
- search for `Documents/GitHub/Lesson2`
- search for duplicate phrases like `lesson plan`, `DreamHost`, `AI suggestions`, or `official version` and decide whether each occurrence is necessary

---

## Out of Scope for This Pass

Do not edit these files in this cleanup pass:
- `deployment.md`
- `deployment_checklist.md`
- `troubleshooting.md`

Reason:
- the user explicitly wants deployment and troubleshooting kept separate for now
- they can be reviewed later as their own documentation set

---

## Risks / Tradeoffs

- Removing too much from `Lesson2.md` could weaken it as the canonical spec.
- Removing too much from `USER_GUIDE.md` could make it less helpful to new users.
- Over-compressing `README.md` could make the repo less discoverable, so keep the navigation links intact.
- The biggest win comes from eliminating repeated wording, not from making all three documents tiny.

---

## Done When

- `README.md` is a short, clean index with no local paths.
- `Lesson2.md` is shorter but still the authoritative spec.
- `USER_GUIDE.md` is shorter but still sufficient for first-time users.
- The three documents have clearly separated responsibilities.
- Deployment and troubleshooting remain separate and untouched.
