# Refactor Notes (Procedural Migration)

This project was migrated from a PSR-4 class-based structure under `src/` to a simplified procedural include structure under `includes/`.

## Key Changes
- Domain logic moved: items, services, requests => `includes/items.php`, `includes/services.php`, `includes/requests.php`.
- Security (CSRF, verification, password reset stubs) => `includes/security.php`.
- Auth helpers => `includes/auth.php`.
- Messaging, notifications, audit, uploads, helpers consolidated under `includes/`.
- Unified mail helper => `includes/mail.php` delegating to existing `App\Mail\Mailer` if still present.
 - Password reset logic fully proceduralized: new `includes/password_reset.php` replaces former `src/Security/PasswordReset.php`.

## Cleanup
`composer.json` autoload removed because runtime now loads only procedural includes via `bootstrap.php`.

If you later re-introduce classes, restore an `autoload` section and run `composer dump-autoload`.

## Safe Removal
The `src/Domain` and `src/Security` class files are now redundant if no remaining code references them. (Search for `App\\Domain` or `App\\Security` to confirm before deleting.) The legacy `PasswordReset` class has already been removed after migration. Mailer retained to leverage PHPMailer pipeline; delete `src/Mail/Mailer.php` only if fully replaced.

## Next Steps
- Delete unused `src/Domain` directory (security classes already removed) when confident.
- Consider adding simple unit/feature tests around procedural functions before further changes.
- Add a `.gitignore` entry for `/uploads/*` except placeholder, and `/storage/mail.log`.
 - Add throttling/rate limiting for password reset initiation (see potential enhancement section below).

## Password Reset Migration
Replaced class `App\\Security\\PasswordReset` with procedural helpers in `includes/password_reset.php`:

Functions:
- `password_reset_create($email)`: Generates 6-digit OTP (1h expiry), invalidates previous unused codes, silently succeeds for unknown/unverified emails (privacy), logs events to `storage/mail.log`.
- `password_reset_validate($userId,$code)`: Returns `['ok'=>bool,'status'=>one of ok|expired|mismatch|used|notfound|missing|db,'code'=>string|null]`.
- `password_reset_consume($userId,$code,$newPassword)`: Atomically marks code used and updates password hash.

Behavior parity maintained; email content & logging preserved. Future enhancement: add per-IP / per-email rate limiting and optional code hashing (currently stored in plain numeric form; can migrate to hash if desired).

## Potential Enhancements
- Add `tools/self_check.php` script to verify DB connectivity, required tables, and writable directories.
- Implement reset request throttle (e.g., store last request timestamp in session and block <60s). 
- Introduce pruning job (cron) to delete expired/used rows in `verification_tokens` and `password_resets`.
- Add integration tests for password reset flow (create → validate → consume, expired, mismatch).

## Migration Matrix (Root -> pages/)

| Original Root File | New Location | Stub Behavior |
| ------------------ | ------------ | ------------- |
| `index.php` | `pages/index.php` | Root includes/redirects (now minimal); marketing served inline for guests. |
| `about_alt.php` | `pages/about_alt.php` | Root one-line include stub for backward compatibility. |
| `login.php` | `pages/login.php` | (If present) logic migrated earlier; direct access maintained. |
| `logout.php` | `pages/logout.php` | Root includes to preserve POST/session semantics. |
| `dashboard.php` | `pages/dashboard.php` | Root (if existed) can include—unified location now. |
| `seeker_feed.php` | `pages/seeker_feed.php` | Direct mapping. |
| `add_item.php` | `pages/add_item.php` | Direct mapping. |
| `add_service.php` | `pages/add_service.php` | Direct mapping. |
| `manage_items.php` | `pages/manage_items.php` | Direct mapping. |
| `manage_services.php` | `pages/manage_services.php` | Direct mapping. |
| `manage_requests.php` | `pages/manage_requests.php` | Direct mapping. |
| `my_requests.php` | `pages/my_requests.php` | Direct mapping. |
| `conversations.php` | `pages/conversations.php` | Root include preserves POST (AJAX) body. |
| `notifications.php` | `pages/notifications.php` | Root include. |
| `notifications_mark_read.php` | `pages/notifications_mark_read.php` | Root include (POST action). |
| `reviews.php` | `pages/reviews.php` | Root: GET redirect, POST include. |
| `profile.php` | `pages/profile.php` | Direct mapping. |
| `register.php` | `pages/register.php` | Direct mapping. |
| `forgot_password.php` | `pages/forgot_password.php` | Direct mapping. |
| `reset_password_code.php` | `pages/reset_password_code.php` | Direct mapping. |
| `verify.php` | `pages/verify.php` | Direct mapping. |
| `verify_notice.php` | `pages/verify_notice.php` | Root include. |
| `verify_resend.php` | `pages/verify_resend.php` | Root include. |
| `privacy.php` | `pages/privacy.php` | Root include. |
| `contact.php` | `pages/contact.php` | Root include (form POST). |
| `get_item.php` | `pages/api/get_item.php` | Root include stub (JSON). |
| `get_service.php` | `pages/api/get_service.php` | Root include stub (JSON). |

## Rationale for Stubs vs Redirects

- Use `include` (no redirect) when preserving original HTTP method (especially POST/ AJAX) is important (e.g., conversations, JSON endpoints, form handlers like contact, notifications_mark_read).
- Use `header('Location: ...')` for pure GET content where method preservation is not required (e.g., reviews GET view) to canonicalize the new URL and reduce duplicate content.
- One-line include stubs keep legacy deep links working and allow gradual link updates without breaking bookmarks or external references.

## API Consolidation Plan

Current dedicated API endpoints: `pages/api/get_item.php`, `pages/api/get_service.php`.

Planned grouping (future):
- `pages/api/conversations.php` (actions: send, fetch, list, mark_read, user_search) to extract POST switch from `pages/conversations.php`.
- `pages/api/notifications.php` (list/filter, mark_read) could unify listing & mark-all.
- `pages/api/reviews.php` (create, list) if front-end moves to fetch-based dynamic loading.

Guidelines:
1. Each API script returns JSON only and sets `Content-Type: application/json` early.
2. Enforce auth + verification at top (e.g., `require_login(); verification_require();`).
3. Reject non-POST for mutating actions; allow GET for safe reads where useful.
4. CSRF verification for state changes (`csrf_verify()`).

## Removed Legacy Classes

- `src/Domain/Items.php`, `src/Domain/Services.php`, `src/Domain/Requests.php` were empty stubs and are removed after procedural migration (`includes/items.php`, etc.).

## Link Audit Summary

All internal navigation now uses `site_href()` except static inline marketing anchors inside `pages/about_alt.php` which are simple relative links (acceptable). No remaining direct references to removed root logic beyond intentional stubs.

## Next Documentation Targets

- Add section documenting CSRF usage patterns and helper locations.
- Add short HOWTO for adding a new page (place in `pages/`, create optional root stub if externally linked).
- Describe environment requirements (PHP version, extensions, writable `storage/`, `uploads/`).

-- End of migration documentation update.
