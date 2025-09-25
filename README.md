
## Features

- User accounts with roles: Admin, Giver, Seeker
- Email verification and OTP-based password reset
- Items: create, edit, delete; categories, condition, availability; image upload
- Services: create, edit, delete; categories, location, availability
- Requests lifecycle: pending → approved/rejected → completed
- Messaging: conversation-based chat between users
- Notifications: in-app with read/unread states
- Admin panel: manage users, items, services, requests, reviews, and view stats
- Security: prepared statements, CSRF protection, password hashing
- Email via PHPMailer with graceful fallbacks and logging

## How to Use

Seeker
1) Register and verify your email
2) Browse items/services and submit a request with a short message
3) Watch notifications for status updates; chat with the provider
4) After fulfillment, mark complete (admin-assisted) and optionally leave a review

Giver
1) Register and verify your email
2) Post items/services with clear descriptions and availability
3) Respond to requests and coordinate via messaging
4) Update availability or remove listings when no longer available

Admin
1) Log in and open `admin/panel.php`
2) Manage users (roles/status), items, services, requests, and reviews
3) Approve/reject/complete requests; monitor platform stats and audit logs

### Seeker Feed
- After login, seekers land on a unified feed at `seeker_feed.php` showing all available items and services.
- Left sidebar provides quick navigation to Feed, My Requests, Messages, Notifications, and Profile.

## Sample Data

An example database schema with a small data set is available at `docs/community_sharing.sql`.

To import locally (optional):
- Create a database named `community_sharing` in your MySQL/MariaDB instance.
- Import the SQL file via your preferred client (phpMyAdmin, Adminer, or CLI).
- Update your local `config.php` credentials if needed.

## Run locally (dev server) and clean URLs

This repo uses a front-controller router at `public/index.php` for clean URLs like `/login` instead of `login.php`.

- PHP built-in server (Windows, PowerShell or cmd):
	- Start the server with `public` as the docroot and the router file:
		- PowerShell:
			- `php -S 127.0.0.1:8000 -t "public" "public/index.php"`
		- cmd.exe:
			- `php -S 127.0.0.1:8000 -t public public/index.php`
	- Visit http://127.0.0.1:8000

- Apache (or hosting with .htaccess support):
	- `public/.htaccess` rewrites all non-existing paths to `public/index.php` while serving existing files directly.
	- Make sure your VirtualHost/DocumentRoot points to the `public/` directory.

Direct links to legacy pages like `/login.php` still work for backward compatibility.
