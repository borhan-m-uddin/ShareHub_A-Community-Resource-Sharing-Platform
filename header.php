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
