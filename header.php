<?php
// header.php - shared header with logo and top navigation
// Expect bootstrap.php to be included before this file
?>
<!---- shared header start ---->
<header class="site-header">
    <div class="container">
        <div class="brand">
            <a href="<?php echo site_href('index.php'); ?>" style="display:flex;align-items:center;text-decoration:none;color:inherit;">
                <img src="<?php echo asset_url('assets/brand/logo-text.svg'); ?>" alt="ShareHub logo" class="site-logo" />
            </a>
        </div>
    <button class="nav-toggle" aria-expanded="false" aria-controls="siteNav" aria-label="Open menu" title="Menu">☰</button>
        <nav class="site-nav" id="siteNav">
            <?php $homeHref = !empty($_SESSION['loggedin']) ? site_href('dashboard.php') : site_href('index.php'); ?>
            <a href="<?php echo $homeHref; ?>">Home</a>
            <?php if (!empty($_SESSION['loggedin'])): ?>
                <?php if(isset($_SESSION['role']) && $_SESSION['role']==='admin'): ?>
                    <a href="<?php echo site_href('admin/panel.php'); ?>">Admin Panel</a>
                    <a href="<?php echo site_href('admin/requests.php'); ?>">Requests</a>
                <?php endif; ?>
                <?php
                // Fetch a small batch of unread notifications for the bell dropdown
                $unreadCount = 0; $unreadList = [];
                if (function_exists('notifications_fetch_unread') && isset($_SESSION['user_id'])) {
                    $unreadList = notifications_fetch_unread((int)$_SESSION['user_id'], 7);
                    $unreadCount = count($unreadList);
                }
                ?>
                <div class="notif-bell" style="position:relative;display:inline-block;">
                    <a href="#" class="notif-toggle" aria-haspopup="true" aria-expanded="false" title="Notifications" style="position:relative;display:inline-flex;align-items:center;gap:4px;padding:0 4px;">
                        <span style="font-size:18px;line-height:1;">🔔</span>
                        <?php if ($unreadCount): ?>
                            <span class="notif-badge" style="position:absolute;top:-4px;right:-4px;background:#d9534f;color:#fff;font-size:11px;line-height:1;padding:2px 5px;border-radius:10px;min-width:18px;text-align:center;">
                                <?php echo $unreadCount; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <div class="notif-menu" style="display:none;position:absolute;right:0;top:100%;margin-top:6px;width:340px;max-height:420px;overflow-y:auto;background:var(--panel-bg,#fff);border:1px solid #ccc;box-shadow:0 4px 14px rgba(0,0,0,.15);border-radius:6px;z-index:1000;">
                        <div style="padding:8px 10px;border-bottom:1px solid #e5e5e5;font-weight:600;font-size:14px;">Notifications</div>
                        <?php if (!$unreadCount): ?>
                            <div style="padding:12px 10px;font-size:13px;color:#666;">No new notifications</div>
                        <?php else: ?>
                            <?php foreach ($unreadList as $n): ?>
                                <div style="padding:8px 10px;border-bottom:1px solid #f2f2f2;font-size:13px;">
                                    <div style="font-weight:600;"><?php echo e($n['subject']); ?></div>
                                    <?php if (!empty($n['body'])): ?>
                                        <div style="margin-top:2px;color:#555;line-height:1.3;">
                                            <?php echo $n['body']; // body may contain trusted HTML generated server-side ?>
                                        </div>
                                    <?php endif; ?>
                                    <div style="margin-top:4px;font-size:11px;color:#888;">
                                        <?php echo date('M j, H:i', strtotime($n['created_at'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <div style="padding:8px 10px;display:flex;justify-content:space-between;align-items:center;gap:6px;">
                            <form method="post" action="<?php echo site_href('notifications_mark_read.php'); ?>" style="margin:0;">
                                <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                                <input type="hidden" name="all" value="1" />
                                <button type="submit" class="btn btn-default" style="font-size:12px;padding:4px 8px;">Mark all read</button>
                            </form>
                            <a href="<?php echo site_href('notifications.php'); ?>" style="font-size:12px;">View all</a>
                        </div>
                    </div>
                </div>
                <a href="<?php echo site_href('profile.php'); ?>">Profile</a>
                <a href="<?php echo site_href('logout.php'); ?>">Logout</a>
            <?php else: ?>
                <a href="<?php echo site_href('login.php'); ?>">Login</a>
            <?php endif; ?>
            <button id="themeToggle" class="btn btn-outline" style="margin-left:12px;">🌙</button>
        </nav>
    </div>
</header>
<main class="main-content">
<!-- shared header end -->
<?php if (function_exists('db_connected') && !db_connected()): ?>
<div class="wrapper" style="border:1px solid #fecaca;background:#fff1f2;color:#991b1b;">
    <div class="alert alert-danger" style="margin:0;">
        Database connection is offline. Pages may be limited until the DB is available.
    </div>
</div>
<?php endif; ?>
<script>
// Theme toggle: persists in localStorage and sets html[data-theme]
(function(){
    const key = 'theme-preference';
    const root = document.documentElement; // html element
    function applyTheme(value){
        const theme = (value === 'dark') ? 'dark' : 'light';
        root.setAttribute('data-theme', theme);
        const btn = document.getElementById('themeToggle');
        if(btn){ btn.textContent = (theme === 'dark') ? '☀️' : '🌙'; btn.setAttribute('aria-label', theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode'); }
    }
    const saved = localStorage.getItem(key);
    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    applyTheme(saved || (prefersDark ? 'dark' : 'light'));
    window.addEventListener('DOMContentLoaded', function(){
        const btn = document.getElementById('themeToggle');
        if(!btn) return;
        btn.addEventListener('click', function(){
            const current = root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            localStorage.setItem(key, next);
            applyTheme(next);
        });
    });
})();
// Expose header height as CSS var for perfect auth centering
(function(){
    function setHeaderHeightVar(){
        var header = document.querySelector('.site-header');
        if(!header) return;
        var h = header.offsetHeight || 64;
        document.documentElement.style.setProperty('--header-h', h + 'px');
    }
    window.addEventListener('load', setHeaderHeightVar);
    window.addEventListener('resize', setHeaderHeightVar);
    // Also update after fonts/images change layout
    var ro = window.ResizeObserver ? new ResizeObserver(setHeaderHeightVar) : null;
    if(ro){
        var header = document.querySelector('.site-header');
        if(header) ro.observe(header);
    }
})();
// Mobile nav toggle
(function(){
    function qs(sel){ return document.querySelector(sel); }
    var btn = qs('.nav-toggle');
    var nav = qs('#siteNav');
    if(!btn || !nav) return;
    function setExpanded(exp){ btn.setAttribute('aria-expanded', exp ? 'true' : 'false'); }
    btn.addEventListener('click', function(){
        var open = nav.classList.toggle('open');
        setExpanded(open);
    });
    // Close menu on link click (better UX on mobile)
    nav.addEventListener('click', function(ev){
        var t = ev.target;
        if(t && t.tagName === 'A' && nav.classList.contains('open')){
            nav.classList.remove('open');
            setExpanded(false);
        }
    });
})();
// Notifications bell toggle (simple client-side show/hide)
(function(){
    var bell = document.querySelector('.notif-bell .notif-toggle');
    var menu = document.querySelector('.notif-bell .notif-menu');
    if(!bell || !menu) return;
    bell.addEventListener('click', function(ev){
        ev.preventDefault();
        var open = menu.style.display === 'block';
        menu.style.display = open ? 'none' : 'block';
        bell.setAttribute('aria-expanded', open ? 'false' : 'true');
    });
    document.addEventListener('click', function(ev){
        if(menu.style.display === 'block' && !menu.contains(ev.target) && !bell.contains(ev.target)){
            menu.style.display = 'none';
            bell.setAttribute('aria-expanded','false');
        }
    });
    document.addEventListener('keydown', function(ev){
        if(ev.key === 'Escape' && menu.style.display === 'block'){
            menu.style.display='none';
            bell.setAttribute('aria-expanded','false');
        }
    });
})();
// Ensure viewport meta exists for responsive scaling on pages missing <meta name="viewport">
(function(){
    var m = document.querySelector('meta[name="viewport"]');
    if(!m){
        m = document.createElement('meta');
        m.name = 'viewport';
        m.content = 'width=device-width, initial-scale=1';
        document.head && document.head.appendChild(m);
    }
})();
</script>
