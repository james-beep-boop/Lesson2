# ARES Lesson Repository User Guide

This guide is for first-time users exploring the ARES Lesson Repository. The app is used to browse, view, edit, compare, translate, print, export, and share lesson plans for ARES Kenya.

## Main Functions

- Log in with your assigned demo or production account.
- Browse lesson-plan families by subject, grade, day, and official status.
- Open a lesson plan to view its current version and navigate between versions.
- Compare two versions in the same lesson family in read-only mode.
- Favorite a specific version so you can return to it quickly later.
- Edit lesson plans to create a new version when your role allows it.
- Use **Ask AI** for writing help and clarity suggestions when AI is enabled.
- Translate lesson plans to Swahili for review when translation preview is available.
- Send and receive inbox messages, including system notices and deletion-related alerts.
- Print lesson plans from the browser for a clean paper copy.
- Save or download lesson plans as PDF files.
- Save or download lesson plans as `.docx` Word files.
- Email lesson plans as PDF attachments.
- Email lesson plans as `.docx` attachments.
- Request deletion of a lesson-plan version when your role allows it.
- Manage users, subject assignments, official versions, and deletion requests in the admin area.

## User Types And Privileges

### Teacher

- Can view all lesson plans.
- Can compare versions.
- Can favorite versions.
- Can use the inbox and send messages.
- Cannot edit lesson plans.
- Cannot create new lesson-plan families.
- Cannot manage users or admin settings.

### Editor

- Has all Teacher privileges.
- Can edit lesson plans for assigned subject-grade areas.
- Can create a new version within an assigned lesson-plan family.
- Can use **Ask AI** on lessons they are allowed to edit.
- Can use Swahili translation preview on lessons they are allowed to edit when the feature is enabled.
- Cannot create brand-new lesson-plan families.
- Cannot manage users or global admin settings.

### Subject Administrator

- Has all Editor privileges for assigned subject-grade areas.
- Can create brand-new lesson-plan families for their assigned subject-grade.
- Can mark a version as official for their assigned subject-grade.
- Can request deletion of a version in their assigned subject-grade.
- Can manage editors and other scoped users for their assigned subject-grade.
- Cannot manage users outside their assigned subject-grade.

### Site Administrator

- Has all Teacher, Editor, and Subject Administrator privileges.
- Can manage all lesson-plan families and all subject-grades.
- Can manage users, roles, and subject assignments.
- Can mark official versions across the repository.
- Can review and complete deletion requests.
- Can access the admin panel.
- Can handle all export, email, and messaging workflows across the app.

## Demo Logins

Use these accounts for review and testing. Unless noted otherwise, the demo password is `password`.

| Name | Username | Email | Role | Password |
|---|---|---|---|---|
| David Njoroge | `david` | `david@demo.test` | Teacher | `password` |
| Test User | `user` | `user@demo.test` | Teacher | `password` |
| Bob Ochieng | `bob` | `bob@demo.test` | Editor - Mathematics Grade 10 | `password` |
| Carol Mwangi | `carol` | `carol@demo.test` | Editor - Science Grade 10 | `password` |
| Test Editor | `editor` | `editor@demo.test` | Editor - English Grade 10 | `password` |
| Test SubjectAdmin | `subject_admin` | `subject_admin@demo.test` | Subject Admin - English Grade 10 | `password` |
| Alice Kamau | `alice` | `alice@demo.test` | Subject Admin - Mathematics Grade 10 | `password` |
| Eve Wanjiku | `eve` | `eve@demo.test` | Subject Admin - Science Grade 10 | `password` |
| Site Administrator | `admin` | `admin@sheql.com` | Site Admin | Set separately |

## Suggested First Steps

1. Log in as a Teacher and open a lesson plan.
2. Try switching between versions and comparing them.
3. Mark a version as a favorite.
4. If your account has edit access, try creating a new version and using the AI suggestion panel.
5. Open the print, PDF, and `.docx` actions to see how lesson plans can be shared outside the app.

## Notes

- Lesson plans are stored as Markdown in the database.
- Email addresses are only visible to Site Administrators.
- AI and translation features may be disabled by configuration in some environments.
- The app’s interface is role-aware, so different users will see different buttons and actions.
