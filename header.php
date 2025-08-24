<?php
// header.php - shared header with logo and top navigation
// Do not unconditionally call session_start() here because header.php may be included
// after output; start session only if headers are not yet sent and no session exists.
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
?>
<!---- shared header start ---->
<header class="site-header">
    <div class="container">
        <div class="brand">
            <a href="about.php" style="display:flex;align-items:center;text-decoration:none;color:inherit;">
                <img src="logo.svg" alt="ShareHub logo" class="site-logo" />
                <div class="brand-text">
                    <div class="brand-title">ShareHub</div>
                    <div class="brand-sub">A Community Resources Sharing Platform</div>
                </div>
            </a>
        </div>
        <nav class="site-nav">
            <a href="index.php">Home</a>
            <?php if(isset($_SESSION['loggedin']) && $_SESSION['loggedin']===true): ?>
                <a href="dashboard.php">Dashboard</a>
                <a href="view_items.php">Items</a>
                <a href="view_services.php">Services</a>
                <a href="profile.php">Profile</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="main-content">
<!-- shared header end -->
