<?php
// header.php - shared header with logo and top navigation (moved to /partials)
// Expect bootstrap.php to be included before this file
?>
<!---- shared header start ---->
<header class="site-header">
	<div class="container">
		<div class="brand">
			<?php
			$brandHref = site_href('pages/index.php');
			if (!empty($_SESSION['loggedin'])) {
				$brandHref = (($_SESSION['role'] ?? '') === 'seeker') ? site_href('pages/seeker_feed.php') : site_href('pages/dashboard.php');
			}
			?>
			<a href="<?php echo $brandHref; ?>" style="display:flex;align-items:center;text-decoration:none;color:inherit;">
				<img src="<?php echo asset_url('assets/brand/logo-text.svg'); ?>" alt="ShareHub logo" class="site-logo" />
			</a>
		</div>
		<button class="nav-toggle" aria-expanded="false" aria-controls="siteNav" aria-label="Open menu" title="Menu">☰</button>
		<nav class="site-nav" id="siteNav">
			<?php
			$homeHref = site_href('pages/index.php');
			if (!empty($_SESSION['loggedin'])) {
				$homeHref = (($_SESSION['role'] ?? '') === 'seeker') ? site_href('pages/seeker_feed.php') : site_href('pages/dashboard.php');
			}
			?>
			<a href="<?php echo $homeHref; ?>">Home</a>
			<?php if (!empty($_SESSION['loggedin'])): ?>
				<?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
					<a href="<?php echo site_href('admin/panel.php'); ?>">Admin Panel</a>
					<a href="<?php echo site_href('admin/requests.php'); ?>">Requests</a>
				<?php endif; ?>
				<?php
				$unreadCount = 0;
				$unreadList = [];
				if (function_exists('notifications_fetch_unread') && isset($_SESSION['user_id'])) {
					$unreadList = notifications_fetch_unread((int)$_SESSION['user_id'], 7);
					$unreadCount = count($unreadList);
				}
				?>
				<div class="notif-bell" id="notifBell" style="position:relative;display:inline-block;">
					<a href="#" class="notif-toggle" aria-haspopup="true" aria-expanded="false" title="Notifications">
						<span style="font-size:18px;line-height:1;">🔔</span>
						<span class="notif-badge" id="notifBadge" style="<?php echo $unreadCount ? '' : 'display:none;'; ?>">
							<?php echo $unreadCount; ?>
						</span>
					</a>
					<div class="notif-menu" id="notifMenu" style="display:none;position:absolute;right:0;top:100%;">
						<div class="menu-head"><span>Notifications</span><button type="button" id="notifMarkAll" class="btn btn-default">Mark all</button></div>
						<div id="notifList" class="menu-list">
							<div class="notif-item is-read">
								<div class="n-body">Loading...</div>
							</div>
						</div>
						<div class="menu-foot"><a href="<?php echo site_href('pages/notifications.php'); ?>">View all →</a></div>
					</div>
				</div>
				<a href="<?php echo site_href('pages/profile.php'); ?>">Profile</a>
				<a href="<?php echo site_href('pages/logout.php'); ?>">Logout</a>
			<?php else: ?>
				<a href="<?php echo site_href('pages/login.php'); ?>">Login</a>
			<?php endif; ?>
			<button id="themeToggle" class="btn btn-outline" style="margin-left:12px;" type="button" aria-pressed="false" title="Toggle theme">🌙</button>
		</nav>
	</div>
</header>
<script>
	// Ensure favicon/logo shows in the browser tab even on pages that don't include head_meta
	(function() {
		try {
			var head = document.head || document.getElementsByTagName('head')[0];
			if (!head) return;
			var hasIcon = head.querySelector('link[rel*="icon"]');
			if (!hasIcon) {
				var l = document.createElement('link');
				l.setAttribute('rel', 'icon');
				l.setAttribute('type', 'image/svg+xml');
				l.setAttribute('href', '<?php echo asset_url('assets/brand/logo-badge.svg'); ?>');
				head.appendChild(l);
			}
		} catch (e) {}
	})();
</script>
<?php if (!empty($_SESSION['loggedin']) && (($_SESSION['role'] ?? '') === 'seeker')): ?>
	<?php
	$currentPath = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
	$pendingReqCount = 0;
	if (isset($conn) && isset($_SESSION['user_id'])) {
		try {
			if ($stc = $conn->prepare("SELECT COUNT(*) c FROM requests WHERE requester_id=? AND status='pending'")) {
				$uidc = (int)$_SESSION['user_id'];
				$stc->bind_param('i', $uidc);
				if ($stc->execute()) {
					$rc = $stc->get_result();
					if ($rowc = $rc->fetch_assoc()) {
						$pendingReqCount = (int)$rowc['c'];
					}
					if ($rc) $rc->free();
				}
				$stc->close();
			}
		} catch (Throwable $e) {
		}
	}
	?>
	<aside id="seekerSidebarGlobal" class="sidebar-global">
		<div class="card" style="margin:12px;">
			<div class="card-body">
				<div style="font-weight:800; margin-bottom:8px;">Navigation</div>
				<nav class="nav-vertical" style="display:flex;flex-direction:column;gap:8px;">
					<a class="btn btn-primary <?php echo $currentPath === 'seeker_feed.php' ? 'active' : ''; ?>" <?php echo $currentPath === 'seeker_feed.php' ? 'aria-current="page"' : ''; ?> href="<?php echo site_href('pages/seeker_feed.php'); ?>">🏠 Feed</a>
					<a class="btn btn-primary <?php echo $currentPath === 'my_requests.php' ? 'active' : ''; ?>" <?php echo $currentPath === 'my_requests.php' ? 'aria-current="page"' : ''; ?> href="<?php echo site_href('pages/my_requests.php'); ?>">📝 My Requests<?php if ($pendingReqCount > 0): ?><span class="count-badge" title="Pending requests"><?php echo $pendingReqCount; ?></span><?php endif; ?></a>
					<a class="btn btn-primary <?php echo $currentPath === 'conversations.php' ? 'active' : ''; ?>" <?php echo $currentPath === 'conversations.php' ? 'aria-current="page"' : ''; ?> href="<?php echo site_href('pages/conversations.php'); ?>">💬 Messages</a>
					<a class="btn btn-primary <?php echo $currentPath === 'notifications.php' ? 'active' : ''; ?>" <?php echo $currentPath === 'notifications.php' ? 'aria-current="page"' : ''; ?> href="<?php echo site_href('pages/notifications.php'); ?>">🔔 Notifications<?php if (!empty($unreadCount)): ?><span class="count-badge warn" title="Unread notifications"><?php echo (int)$unreadCount; ?></span><?php endif; ?></a>
					<a class="btn btn-primary <?php echo $currentPath === 'reviews.php' ? 'active' : ''; ?>" <?php echo $currentPath === 'reviews.php' ? 'aria-current="page"' : ''; ?> href="<?php echo site_href('pages/reviews.php'); ?>">⭐ Reviews</a>
					<a class="btn btn-primary <?php echo $currentPath === 'profile.php' ? 'active' : ''; ?>" <?php echo $currentPath === 'profile.php' ? 'aria-current="page"' : ''; ?> href="<?php echo site_href('pages/profile.php'); ?>">👤 Profile</a>
					<a class="btn btn-primary" href="<?php echo site_href('pages/logout.php'); ?>">🚪 Logout</a>
				</nav>
			</div>
		</div>
	</aside>
	<div id="sidebarOverlay" aria-hidden="true"></div>
	<button id="sidebarFab" class="btn btn-outline" aria-controls="seekerSidebarGlobal" aria-expanded="false" title="Open menu">☰ Menu</button>
<?php endif; ?>
<main class="main-content">
	<?php if (function_exists('db_connected') && !db_connected()): ?>
		<div class="wrapper" style="border:1px solid #fecaca;background:#fff1f2;color:#991b1b;">
			<div class="alert alert-danger" style="margin:0;">
				Database connection is offline. Pages may be limited until the DB is available.
			</div>
		</div>
	<?php endif; ?>
	<script>
		// Theme toggle + persistence
		(function() {
			var root = document.documentElement;
			var btn = document.getElementById('themeToggle');
			if (!btn) return;
			var stored = localStorage.getItem('theme');
			if (stored) {
				root.setAttribute('data-theme', stored);
				btn.textContent = stored === 'dark' ? '☀️' : '🌙';
				btn.setAttribute('aria-pressed', stored === 'dark');
			}
			btn.addEventListener('click', function() {
				var current = root.getAttribute('data-theme');
				var next = current === 'dark' ? 'light' : 'dark';
				root.setAttribute('data-theme', next);
				localStorage.setItem('theme', next);
				btn.textContent = next === 'dark' ? '☀️' : '🌙';
				btn.setAttribute('aria-pressed', next === 'dark');
			});
		})();
		// Auto body class for seeker sidebar alignment fallback
		(function() {
			try {
				if (document.getElementById('seekerSidebarGlobal')) {
					document.body.classList.add('has-seeker-sidebar');
				}
			} catch (e) {}
		})();
		// Notifications dropdown (API-driven)
		(function() {
			const bell = document.getElementById('notifBell');
			if (!bell) return;
			const toggle = bell.querySelector('.notif-toggle');
			const menu = document.getElementById('notifMenu');
			const listEl = document.getElementById('notifList');
			const badge = document.getElementById('notifBadge');
			const markAllBtn = document.getElementById('notifMarkAll');
			let loaded = false;
			let loading = false;

			function csrfToken() {
				const meta = document.querySelector('meta[name="csrf-token"]');
				if (meta) return meta.getAttribute('content');
				// Fallback hidden input (if forms output them globally)
				const hidden = document.querySelector('input[name="csrf_token"], input[name="csrf"]');
				if (hidden) return hidden.value;
				// Server-side embedded fallback
				return '<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>';
			}

			async function apiList() {
				loading = true;
				try {
					const r = await fetch('<?php echo site_href('pages/api/notifications.php'); ?>?action=list', {
						credentials: 'same-origin'
					});
					const j = await r.json();
					if (!j.success) {
						listEl.innerHTML = '<div style="padding:12px 10px;font-size:13px;color:#b91c1c;">Failed: ' + (j.message || 'error') + '</div>';
						return;
					}
					renderList(j.data || []);
				} catch (e) {
					listEl.innerHTML = '<div style="padding:12px 10px;font-size:13px;color:#b91c1c;">Network error</div>';
				} finally {
					loading = false;
					loaded = true;
				}
			}

			function renderList(items) {
				if (!items.length) {
					listEl.innerHTML = '<div style="padding:12px 10px;font-size:13px;color:#666;">No notifications</div>';
					badge && (badge.style.display = 'none');
					return;
				}
				let unreadCount = 0;
				const html = items.map(it => {
					const read = !!it.read_at || !!it.is_read;
					if (!read) unreadCount++;
					return '<div class="notif-item' + (read ? ' is-read' : ' is-unread') + '" data-id="' + it.id + '">' +
						'<div class="n-title">' + escapeHtml(it.subject || 'Notification') + '</div>' +
						(it.body ? '<div class="n-body">' + escapeHtml(it.body) + '</div>' : '') +
						'<div class="n-meta">' + formatDate(it.created_at) + '</div>' +
						'</div>';
				}).join('');
				listEl.innerHTML = html;
				if (badge) {
					if (unreadCount > 0) {
						badge.textContent = unreadCount;
						badge.style.display = 'inline-block';
					} else {
						badge.style.display = 'none';
					}
				}
			}

			function escapeHtml(s) {
				return (s || '').replace(/[&<>"]/g, c => ({
					'&': '&amp;',
					'<': '&lt;',
					'>': '&gt;',
					'"': '&quot;'
				} [c]));
			}

			function formatDate(str) {
				if (!str) return '';
				try {
					const d = new Date(str.replace(' ', 'T'));
					return d.toLocaleString(undefined, {
						month: 'short',
						day: 'numeric',
						hour: '2-digit',
						minute: '2-digit'
					});
				} catch (e) {
					return str;
				}
			}

			async function markAll() {
				if (loading) return;
				try {
					const r = await fetch('<?php echo site_href('pages/api/notifications.php'); ?>', {
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json'
						},
						body: JSON.stringify({
							action: 'mark_all',
							csrf_token: csrfToken()
						})
					});
					const j = await r.json();
					if (j.success) {
						// Re-render list as read
						bell.querySelectorAll('.notif-item').forEach(el => {
							el.style.opacity = '.7';
						});
						if (badge) {
							badge.textContent = '0';
							badge.style.display = 'none';
						}
						// Update empty state if needed
						const children = listEl.querySelectorAll('.notif-item');
						if (children.length === 0) {
							listEl.innerHTML = '<div style="padding:12px 10px;font-size:13px;color:#666;">No notifications</div>';
						}
					}
				} catch (e) {}
			}

			function toggleMenu(e) {
				e.preventDefault();
				const visible = menu.style.display !== 'none';
				if (visible) {
					menu.style.display = 'none';
					toggle.setAttribute('aria-expanded', 'false');
					return;
				}
				menu.style.display = 'block';
				toggle.setAttribute('aria-expanded', 'true');
				if (!loaded) apiList();
			}

			bell.addEventListener('click', function(ev) {
				if (ev.target === toggle || toggle.contains(ev.target)) {
					toggleMenu(ev);
				}
			});
			document.addEventListener('click', function(ev) {
				if (!bell.contains(ev.target)) {
					menu.style.display = 'none';
					toggle.setAttribute('aria-expanded', 'false');
				}
			});
			markAllBtn && markAllBtn.addEventListener('click', function(ev) {
				ev.preventDefault();
				markAll();
			});

		})();
		// Mobile top navigation toggle
		(function() {
			var toggle = document.querySelector('.nav-toggle');
			var nav = document.getElementById('siteNav');
			if (!toggle || !nav) return;

			function setOpen(open) {
				nav.classList.toggle('open', !!open);
				toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			}
			toggle.addEventListener('click', function(e) {
				e.preventDefault();
				setOpen(!nav.classList.contains('open'));
			});
			// Close when clicking outside on small screens
			document.addEventListener('click', function(ev) {
				if (!window.matchMedia('(max-width: 900px)').matches) return;
				if (nav.contains(ev.target)) return;
				if (ev.target === toggle || toggle.contains(ev.target)) return;
				setOpen(false);
			});
			// Close on ESC
			document.addEventListener('keydown', function(ev) {
				if (ev.key === 'Escape') setOpen(false);
			});
			// Close when resizing up to desktop
			window.addEventListener('resize', function() {
				if (!window.matchMedia('(max-width: 900px)').matches) setOpen(false);
			});
		})();
		// Seeker off-canvas sidebar toggle (mobile)
		(function() {
			var sidebar = document.getElementById('seekerSidebarGlobal');
			var overlay = document.getElementById('sidebarOverlay');
			var fab = document.getElementById('sidebarFab');
			if (!sidebar || !overlay || !fab) return;

			function setOpen(open) {
				var isOpen = !!open;
				sidebar.classList.toggle('open', isOpen);
				overlay.classList.toggle('open', isOpen);
				fab.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
				// prevent body from scrolling when drawer open
				document.body.style.overflow = isOpen ? 'hidden' : '';
			}
			fab.addEventListener('click', function(e) {
				e.preventDefault();
				setOpen(!sidebar.classList.contains('open'));
			});
			overlay.addEventListener('click', function() {
				setOpen(false);
			});
			document.addEventListener('keydown', function(ev) {
				if (ev.key === 'Escape') setOpen(false);
			});
			window.addEventListener('resize', function() {
				if (!window.matchMedia('(max-width: 900px)').matches) setOpen(false);
			});
		})();
	</script>