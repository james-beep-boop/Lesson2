# Deployment Checklist

## Before You Deploy

- Confirm the codebase is clean:
```bash
git status
```
- Run the full local test suite:
```bash
php artisan test --compact
```
- Check the current app version if you are about to ship a dependency update:
```bash
php artisan about
```

## Local Build And Verify

- If assets changed, build them locally:
```bash
npm run build
```
- Clear stale framework caches locally if needed:
```bash
php artisan optimize:clear
```
- Re-run tests after any package update:
```bash
php artisan test --compact
```

## Deploy To DreamHost

- Run your deploy script.
- After deploy, SSH into the server and verify the app boots:
```bash
cd ~/Lesson2
php artisan about
```
- Clear and rebuild production caches:
```bash
php artisan optimize:clear
php artisan optimize
```
- Check for fresh log errors only after reproducing a problem:
```bash
tail -n 20 storage/logs/laravel.log
```

## Smoke Test Order

1. Login with a teacher account.
2. Login with a subject admin account.
3. Login with a site admin account.
4. Manage Team add editor.
5. Manage Team remove editor.
6. Lesson plan view.
7. Lesson plan compare.
8. Lesson plan create/upload.
9. Admin users list.
10. Deletion requests page.
11. PDF export, DOCX export, and email sending.

## If Something Fails

- Do not keep deploying over the top of an unresolved failure.
- Capture the exact action that failed.
- Immediately check new log lines:
```bash
tail -n 50 storage/logs/laravel.log
```
- Reproduce the issue once more before changing code.

## Notes

- Keep backend package upgrades separate from Tailwind upgrades.
- Do not update transitive dependencies directly unless Composer requires it.
- Do not run `php artisan test` on DreamHost production; run tests locally.
