# Update Recommendations

This file captures deferred dependency-update guidance so it does not get mixed into active feature work.

Current recommendation date: 2026-04-06

---

## Summary

The project is in a healthy state and does **not** require immediate dependency updates before the Toast UI editor work.

Safest current strategy:

- do not update dependencies immediately
- complete the Toast UI editor implementation first
- perform dependency updates afterward as a separate maintenance task

Reasoning:

- the app is about to undergo a meaningful editor workflow change
- diff behavior is important to the edit/review UX and should remain stable during that work
- separating feature work from dependency churn makes regressions easier to isolate

---

## Do Not Update Right Now

### Hold completely for now

- `jfcherng/php-diff`
- `phpunit/phpunit`

### Why hold `jfcherng/php-diff`

The app uses diff rendering directly in [`app/Services/DiffService.php`](../../app/Services/DiffService.php), and the Toast UI plan makes diff behavior more important as part of the editing safety net.

Even though the v7 release notes suggest a modernization-focused release, it is still a major version bump and may require API migration around differ/renderer options.

Recommendation:

- keep the currently working diff stack during Toast UI implementation
- evaluate `jfcherng/php-diff` v7 only in a dedicated follow-up task

### Why hold `phpunit/phpunit`

`phpunit/phpunit` is a major-version upgrade and Pest sits on top of it. That makes it a broader ecosystem change than a normal patch or minor update.

Recommendation:

- do not move to PHPUnit 13 until there is a specific test-stack upgrade pass

---

## Low-Risk Updates To Consider Later

These updates looked reasonable to consider in a later maintenance pass:

- `laravel/framework`
- `filament/filament`
- `laravel/ai`
- `pestphp/pest`

At the time of review, available updates were:

- `laravel/framework` `13.2.0 -> 13.3.0`
- `filament/filament` `5.4.3 -> 5.4.4`
- `laravel/ai` `0.4.2 -> 0.4.4`
- `pestphp/pest` `4.4.3 -> 4.4.5`

These are still best handled in a dedicated update pass rather than alongside feature work.

---

## Frontend Tooling

Frontend tooling is currently in a good state.

Installed versions at review time:

- `vite` `8.0.5`
- `laravel-vite-plugin` `3.0.1`
- `tailwindcss` `4.2.2`

No urgent frontend dependency action is currently recommended.

---

## Suggested Later Update Workflow

When you are ready for a conservative update pass, use a separate branch and update only the low-risk packages first.

Suggested commands:

```bash
composer require laravel/framework:^13.3 filament/filament:^5.4.4 laravel/ai:^0.4.4
composer require --dev pestphp/pest:^4.4.5
composer update
php artisan test
npm run build
```

Review results before merging.

Do **not** include `jfcherng/php-diff` or `phpunit/phpunit` in that pass.

---

## Dedicated Future Task For `jfcherng/php-diff`

When revisiting `jfcherng/php-diff`, do it as its own small migration task:

1. Read the v7 migration guide.
2. Check whether `DiffHelper::calculate()` still accepts array options or now expects value objects.
3. Update [`app/Services/DiffService.php`](../../app/Services/DiffService.php) only if required.
4. Manually verify both side-by-side and unified diff output in the app.
5. Run relevant tests.

This should stay isolated from editor-feature work.

---

## Environment Note

Local development on this Mac currently uses PHP `8.5.4`, while DreamHost production uses PHP `8.4`.

That is workable, but it means:

- avoid introducing PHP 8.5-only language usage
- treat local validation as close to production, but not identical

If desired later, local PHP parity can be improved separately.
