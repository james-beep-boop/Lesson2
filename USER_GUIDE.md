# ARES Lesson Library User Guide

This guide is for first-time users exploring the ARES Lesson Library. The app lets authorized users browse, compare, favorite, edit, translate, print, export, and share lesson plans for ARES Kenya.

## What users can do

- Log in with a demo or production account.
- Browse lesson-plan families by subject, grade, day, and official status.
- Open a lesson plan to view versions and compare them.
- Favorite a version for quick return later.
- Edit lessons when your role allows it.
- Use **Ask AI** for writing help when AI is enabled.
- Preview Swahili translations when the translation feature is enabled.
- Print, save, or email lesson plans as PDF or `.docx` files.
- Use the inbox for alerts and messages.
- Request deletion when your role allows it.

## Roles

### Teacher

- View lesson plans
- Compare versions
- Favorite versions
- Use the inbox
- No editing or admin access

### Editor

- Everything a Teacher can do
- Edit assigned subject-grade lessons
- Create new versions in assigned lesson-plan families
- Use **Ask AI** and translation preview when enabled

### Subject Administrator

- Everything an Editor can do
- Create new lesson-plan families in assigned subject-grades
- Mark a version official for an assigned subject-grade
- Manage scoped users and deletion requests

### Site Administrator

- Full access to lesson plans and admin tools
- Manage users, roles, subject assignments, and official versions
- Review deletion requests
- Access the admin panel

## Demo logins

Use these accounts for review and testing. Demo passwords are configured in the seed data.

| Name | Username | Email | Role |
|---|---|---|---|
| David Njoroge | `david` | `david@demo.test` | Teacher |
| Test User | `user` | `user@demo.test` | Teacher |
| Bob Ochieng | `bob` | `bob@demo.test` | Editor - Mathematics Grade 10 |
| Carol Mwangi | `carol` | `carol@demo.test` | Editor - Science Grade 10 |
| Test Editor | `editor` | `editor@demo.test` | Editor - English Grade 10 |
| Test SubjectAdmin | `subject_admin` | `subject_admin@demo.test` | Subject Administrator - English Grade 10 |
| Alice Kamau | `alice` | `alice@demo.test` | Subject Administrator - Mathematics Grade 10 |
| Eve Wanjiku | `eve` | `eve@demo.test` | Subject Administrator - Science Grade 10 |
| Site Administrator | `admin` | `admin@sheql.com` | Site Administrator |

## Quick start

1. Sign in and open a lesson plan.
2. Compare two versions.
3. Favorite one version.
4. If you can edit, try creating a new version.
5. Try print, PDF, or `.docx` export.

## Notes

- Lesson plans are stored as Markdown in the database.
- Email addresses are only visible to Site Administrators.
- AI and translation features can be disabled by configuration.
- The interface changes by role, so different users see different actions.
