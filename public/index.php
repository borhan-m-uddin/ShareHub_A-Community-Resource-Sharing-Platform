<?php
// Front Controller / Router
// Phase 1: Attempt new controller-based routes (src/Http/Routing/routes.php) then fallback to legacy flat PHP scripts.
// Compatible with Apache via .htaccess rewrite and PHP built-in server (php -S 127.0.0.1:8000 -t public).

// Normalize path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';

$root = dirname(__DIR__) . DIRECTORY_SEPARATOR; // project root (contains legacy pages)
$appSrc = $root . 'src' . DIRECTORY_SEPARATOR;

// Autoload (Composer)
if (is_file($root . 'vendor/autoload.php')) {
	require_once $root . 'vendor/autoload.php';
}

// Load bootstrap for legacy globals / shims (session, db, helpers, etc.)
require_once $root . 'bootstrap.php';

// Static assets passthrough: serve uploads and assets from project root when using public/ as docroot
// This allows URLs like /uploads/items/xxx.jpg and /assets/... to work in both dev and prod.
// Security: Only allow whitelisted prefixes and specific files; never serve .php.
function __serve_static(string $absFile): void
{
	$type = 'application/octet-stream';
	if (function_exists('mime_content_type')) {
		$det = @mime_content_type($absFile);
		if ($det) {
			$type = $det;
		}
	} else {
		$ext = strtolower(pathinfo($absFile, PATHINFO_EXTENSION));
		$map = [
			'css' => 'text/css',
			'js' => 'application/javascript',
			'svg' => 'image/svg+xml',
			'png' => 'image/png',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'gif' => 'image/gif',
			'webp' => 'image/webp',
			'ico' => 'image/x-icon'
		];
		if (isset($map[$ext])) {
			$type = $map[$ext];
		}
	}
	header('Content-Type: ' . $type);
	header('Cache-Control: public, max-age=86400');
	readfile($absFile);
	exit;
}

// Allow-list static roots
if (strpos($path, '/uploads/') === 0 || strpos($path, '/assets/') === 0 || $path === '/style.css' || $path === '/favicon.ico' || $path === '/robots.txt') {
	$candidate = $root . ltrim($path, '/');
	// Never serve php files
	if (is_file($candidate) && stripos($candidate, '.php') === false) {
		__serve_static($candidate);
	}
}

// Helper: safely include a legacy script by relative path (whitelist root and admin subdir)
$safeInclude = function (string $rel) use ($root) {
	$rel = ltrim($rel, '/\\');
	if ($rel === '') {
		$rel = 'index.php';
	}
	// Allow only files directly under root, admin/, pages/, or pages/api/
	if (
		preg_match('#^(admin/)?[A-Za-z0-9_-]+\.php$#', $rel) !== 1
		&& preg_match('#^pages/[A-Za-z0-9_-]+\.php$#', $rel) !== 1
		&& preg_match('#^pages/api/[A-Za-z0-9_-]+\.php$#', $rel) !== 1
	) {
		// If a simple name like "login.php" was provided and not found in root, try pages/ fallback below
		if (preg_match('#^[A-Za-z0-9_-]+\.php$#', $rel) !== 1 && preg_match('#^admin/[A-Za-z0-9_-]+\.php$#', $rel) !== 1) {
			return false;
		}
	}
	$file = $root . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
	if (is_file($file)) {
		require $file;
		return true;
	}
	// Fallback: if looking for a root file that doesn't exist, try pages/<name>.php
	if (preg_match('#^[A-Za-z0-9_-]+\.php$#', $rel) === 1) {
		$pfile = $root . 'pages' . DIRECTORY_SEPARATOR . $rel;
		if (is_file($pfile)) {
			require $pfile;
			return true;
		}
	}
	return false;
};

// Explicit legacy route map (clean URL -> legacy script) - kept for fallback & pages not yet migrated
$legacyMap = [
	'/'                       => 'index.php', // will fall back to pages/index.php if root missing
	'/home'                   => 'index.php',
	'/about'                  => 'about_alt.php',
	'/login'                  => 'login.php',
	'/logout'                 => 'logout.php',
	'/register'               => 'register.php',
	'/forgot-password'        => 'forgot_password.php',
	'/reset-password'         => 'reset_password_code.php', // expects uid via query string
	'/verify'                 => 'verify.php',
	'/verify/notice'          => 'verify_notice.php',
	'/verify/resend'          => 'verify_resend.php',
	'/dashboard'              => 'dashboard.php',
	'/seeker'                 => 'seeker_feed.php',
	'/seeker-feed'            => 'seeker_feed.php',
	'/my-requests'            => 'my_requests.php',
	'/conversations'          => 'conversations.php',
	'/notifications'          => 'notifications.php',
	// '/notifications/mark-read' legacy endpoint removed in favor of API
	'/items/add'              => 'add_item.php',
	'/items/manage'           => 'manage_items.php',
	'/services/add'           => 'add_service.php',
	'/services/manage'        => 'manage_services.php',
	'/requests/manage'        => 'manage_requests.php',
	'/profile'                => 'profile.php',

	// Admin area
	'/admin'                  => 'admin/panel.php',
	'/admin/panel'            => 'admin/panel.php',
	'/admin/users'            => 'admin/users.php',
	'/admin/items'            => 'admin/items.php',
	'/admin/services'         => 'admin/services.php',
	'/admin/requests'         => 'admin/requests.php',
	'/admin/reviews'          => 'admin/reviews.php',
	'/admin/reports'          => 'admin/reports.php',
	'/admin/settings'         => 'admin/settings.php',
];

// 1) New controller routing phase
// Load route definitions (array of [METHOD, pattern, Controller@action])
$routeFile = $appSrc . 'Http/Routing/routes.php';
if (is_file($routeFile)) {
	$definition = require $routeFile; // returns array
	$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
	foreach ($definition as $def) {
		if (count($def) < 3) {
			continue;
		}
		[$verb, $pattern, $handler] = $def;
		if (strcasecmp($verb, $method) !== 0) {
			continue;
		}
		// Convert simple pattern with named groups already regex-ready or plain path
		$regex = '#^' . $pattern . '$#';
		if (preg_match($regex, $path, $m)) {
			// Resolve controller@method
			if (is_string($handler) && strpos($handler, '@') !== false) {
				[$controllerName, $action] = explode('@', $handler, 2);
				$fqcn = 'App\\Http\\Controllers\\' . $controllerName;
				if (class_exists($fqcn) && method_exists($fqcn, $action)) {
					$controller = new $fqcn();
					// Extract named params only
					$params = [];
					foreach ($m as $k => $v) {
						if (!is_int($k)) {
							$params[$k] = $v;
						}
					}
					if (method_exists($controller, 'setRouteParams')) {
						$controller->setRouteParams($params);
					}
					$controller->$action();
					return; // done
				}
			}
		}
	}
}

// 2) Legacy direct mapping if defined
if (isset($legacyMap[$path]) && $safeInclude($legacyMap[$path])) {
	exit; // served by legacy script
}

// 3) Backward-compat: if someone requests "/file.php" or "/admin/file.php" or "/pages[/api]/file.php", try to serve it
if (
	preg_match('#^/(admin/)?[A-Za-z0-9_-]+\.php$#', $path) === 1
	|| preg_match('#^/pages/[A-Za-z0-9_-]+\.php$#', $path) === 1
	|| preg_match('#^/pages/api/[A-Za-z0-9_-]+\.php$#', $path) === 1
) {
	if ($safeInclude(ltrim($path, '/'))) {
		exit;
	}
}

// 4) Implicit mapping: "/xxxx" -> "xxxx.php" ; "/admin/xxxx" -> "admin/xxxx.php"
if (preg_match('#^/([A-Za-z0-9_-]+)$#', $path, $m)) {
	if ($safeInclude($m[1] . '.php')) {
		exit;
	}
}
if (preg_match('#^/admin/([A-Za-z0-9_-]+)$#', $path, $m)) {
	if ($safeInclude('admin/' . $m[1] . '.php')) {
		exit;
	}
}

// 5) Static assets under /public should pass through via web server; if we got here, it's likely a 404 route
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>404 Not Found</title>
	<link rel="stylesheet" href="/style.css">
</head>

<body>
	<div class="wrapper" style="max-width:720px;margin:40px auto;">
		<h2>Page not found</h2>
		<p>The requested path <code><?php echo htmlspecialchars($path, ENT_QUOTES, 'UTF-8'); ?></code> was not found.</p>
		<div class="page-top-actions" style="margin-top:12px;">
			<a class="btn btn-primary" href="/">Go to Home</a>
			<a class="btn btn-outline" href="/login">Login</a>
		</div>
	</div>
</body>

</html>