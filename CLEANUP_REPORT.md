# Cleanup Report

Date: 2025-09-25

Scope: Inventory the project for unused or unsuitable code and propose safe removals and low-risk fixes.

Summary
- Normalized navigation to `pages/*` and hardened `public/index.php` router with fallback.
- Notifications and conversations use JSON APIs under `pages/api/*`.
- Added themed styles; removed inline styles where practical.

Suggested removals (safe)
- pages/notifications_mark_read.php — Deprecated. Replaced by `pages/api/notifications.php` actions `mark_read` and `mark_all`. File removed.
- src/Security/PasswordReset.php — Superseded by procedural `includes/password_reset.php`. Deleted.
- src/Security/Verification.php — Superseded by procedural `includes/security.php` verification helpers. Deleted.
- src/Security/Auth.php — Unused; application uses `includes/auth.php`. Deleted.
- src/Support/CSRF.php — Unused; CSRF handled by `includes/security.php`. Deleted.
- src/Support/Flash.php — Unused. Deleted.
- src/Support/Helpers.php — Unused; `includes/helpers.php` provides helpers. Deleted.
- src/Support/Session.php — Unused; sessions handled directly in includes. Deleted.
- src/Support/Upload.php — Unused; `includes/upload.php` is used across pages. Deleted.

Keep (legacy-compatible)
- public/index.php — central router with fallbacks; required.
- tools/self_check.php — useful for future checks.
- docs/* — documentation/resource; keep.

Duplicates/overlaps to watch
- Root `conversations.php` vs `pages/conversations.php`: ensure root file is not used; prefer `pages/*`. If root becomes unused, delete in a later pass.
	(Resolved) src/Security/* and src/Support/* shims removed; includes-based implementations are canonical.

Low-risk code hygiene done
- Themed notification dropdown with classes; removed most inline styles.
- Preserved tiny inline positioning fallback to prevent regressions if CSS cached.
- Replaced several direct SQL UPDATEs with prepared statements (verification tokens, password resets, conversations read markers).
- Fixed verification links to point to `pages/verify.php` consistently.

Developer-only utilities and docs
- tools/self_check.php — Lightweight diagnostic to verify environment health: PHP version/extensions, DB connectivity and critical tables, writable dirs, mail log, session state. Safe to keep in repo for deployments and debugging. Not exposed when the web root is `public/` (recommended). If your server accidentally serves the repo root, restrict it (CLI-only or admin-gated) or delete before production.
- docs/* and database/migrations/* — Documentation and schema bootstrap; keep for maintenance.

Next steps (optional, larger changes)
- Audit includes for prepared statements and CSRF across all POSTs (most covered via helpers).
- Add small integration tests for router mappings and API endpoints.

Verification checklist
- [x] Grep shows no runtime references to `App\\Security\\*` or `App\\Support\\*` namespaces.
- [x] Composer autoloader still valid (no classmap entries needed for removed files).
- [x] Key pages pass syntax check: `partials/header.php`, `pages/conversations.php`, `pages/api/*`, `includes/*`.
