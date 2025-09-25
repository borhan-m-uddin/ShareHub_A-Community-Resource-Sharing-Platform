<?php
require_once __DIR__ . '/../bootstrap.php';

// Rebuild full landing page content (restored from original) so it lives inside pages/about_alt.php
// This becomes the primary public marketing / home-style page for visitors.

// Gather dynamic stats only if DB connected.
$items_display = $services_display = $users_display = '0';
$requests_today = $requests_week = 0; $fulfilled_rate = null;
$latest_item_t = $latest_serv_t = 'N/A';
$items_new_week = $services_new_week = 0;
$latest_items = $latest_services = [];

if (function_exists('db_connected') && db_connected()) {
    global $conn;

    // Items count + latest title + new this week
    try {
        $nowWeek = date('Y-m-d H:i:s', time() - 7*86400);
        if ($st = $conn->query("SELECT COUNT(*) c FROM items")) { $items_display = (string)($st->fetch_row()[0] ?? '0'); }
        if ($st = $conn->query("SELECT title FROM items ORDER BY posting_date DESC LIMIT 1")) { $row=$st->fetch_row(); if($row){ $latest_item_t = $row[0]; } }
        if ($st = $conn->prepare("SELECT COUNT(*) FROM items WHERE posting_date >= ?")) { $st->bind_param('s',$nowWeek); $st->execute(); $r=$st->get_result()->fetch_row(); $items_new_week = (int)($r[0] ?? 0); $st->close(); }
    } catch (Throwable $e) {}

    // Services count + latest + new week
    try {
        $nowWeek = date('Y-m-d H:i:s', time() - 7*86400);
        if ($st = $conn->query("SELECT COUNT(*) c FROM services")) { $services_display = (string)($st->fetch_row()[0] ?? '0'); }
        if ($st = $conn->query("SELECT title FROM services ORDER BY posting_date DESC LIMIT 1")) { $row=$st->fetch_row(); if($row){ $latest_serv_t = $row[0]; } }
        if ($st = $conn->prepare("SELECT COUNT(*) FROM services WHERE posting_date >= ?")) { $st->bind_param('s',$nowWeek); $st->execute(); $r=$st->get_result()->fetch_row(); $services_new_week = (int)($r[0] ?? 0); $st->close(); }
    } catch (Throwable $e) {}

    // Users count
    try { if ($st = $conn->query("SELECT COUNT(*) FROM users")) { $users_display = (string)($st->fetch_row()[0] ?? '0'); } } catch (Throwable $e) {}

    // Requests stats
    try {
        $today = date('Y-m-d 00:00:00');
        if ($st = $conn->prepare("SELECT COUNT(*) FROM requests WHERE request_date >= ?")) { $st->bind_param('s',$today); $st->execute(); $r=$st->get_result()->fetch_row(); $requests_today = (int)($r[0] ?? 0); $st->close(); }
        $weekAgo = date('Y-m-d H:i:s', time() - 7*86400);
        if ($st = $conn->prepare("SELECT COUNT(*) FROM requests WHERE request_date >= ?")) { $st->bind_param('s',$weekAgo); $st->execute(); $r=$st->get_result()->fetch_row(); $requests_week = (int)($r[0] ?? 0); $st->close(); }
        if ($st = $conn->query("SELECT COUNT(*) f FROM requests WHERE status='completed'")) { $completed = (int)($st->fetch_row()[0] ?? 0); }
        if (!empty($requests_week) || !empty($requests_today)) {
            // approximate total vs completed ratio
            if ($st2 = $conn->query("SELECT COUNT(*) t FROM requests")) { $totalAll = (int)($st2->fetch_row()[0] ?? 0); if ($totalAll>0 && isset($completed)) { $fulfilled_rate = round(($completed/$totalAll)*100); } }
        }
    } catch (Throwable $e) {}

    // Latest items/services lists for ticker
    try {
        if ($st = $conn->query("SELECT title, posting_date FROM items ORDER BY posting_date DESC LIMIT 5")) { while($r=$st->fetch_assoc()){ $latest_items[]=$r; } }
    } catch (Throwable $e) {}
    try {
        if ($st = $conn->query("SELECT title, posting_date FROM services ORDER BY posting_date DESC LIMIT 5")) { while($r=$st->fetch_assoc()){ $latest_services[]=$r; } }
    } catch (Throwable $e) {}
}

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>ShareHub – Community Sharing Platform</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>" />
<link rel="stylesheet" href="<?php echo asset_url('assets/css/marketing.css'); ?>" />
<script>
 document.addEventListener('DOMContentLoaded', function(){
   var ul = document.querySelector('.ticker ul'); if(!ul) return; ul.innerHTML = ul.innerHTML + ul.innerHTML; // duplicate for smooth scroll
 });
</script>
</head>
<body>
<?php render_header(); ?>
<section class="alt-wrapper">
  <div class="alt-hero wrapper">
    <div class="inner">
      <div>
        <?php /* Login errors are rendered on the login page; do not show them here */ ?>
        <h1>ShareHub — A fresh way to Share, Request, and Connect</h1>
        <p class="lead">Discover community-shared items and services. Post what you can give, request what you need, and build a more connected neighborhood.</p>
        <div class="ctas">
          <?php if (!empty($_SESSION['loggedin'])): ?>
            <a href="<?php echo site_href('pages/dashboard.php'); ?>" class="btn btn-primary">Go to Dashboard</a>
            <a href="<?php echo site_href('pages/seeker_feed.php?tab=items'); ?>" class="btn btn-default">Browse Items</a>
            <a href="<?php echo site_href('pages/seeker_feed.php?tab=services'); ?>" class="btn btn-default">Browse Services</a>
          <?php else: ?>
            <a href="<?php echo site_href('pages/register.php'); ?>" class="btn btn-primary">Join ShareHub</a>
            <a href="<?php echo site_href('pages/seeker_feed.php?tab=items'); ?>" class="btn btn-default">Explore Items</a>
          <?php endif; ?>
        </div>
        <div class="stats-row">
          <span class="pill">Items available: <strong><?php echo htmlspecialchars($items_display); ?></strong></span>
          <span class="pill">Services offered: <strong><?php echo htmlspecialchars($services_display); ?></strong></span>
          <span class="pill">Members: <strong><?php echo htmlspecialchars($users_display); ?></strong></span>
          <span class="pill">Requests today: <strong><?php echo (int)$requests_today; ?></strong></span>
          <span class="pill">This week: <strong><?php echo (int)$requests_week; ?></strong></span>
          <?php if ($fulfilled_rate !== null): ?>
            <span class="pill">Fulfilled: <strong><?php echo $fulfilled_rate; ?>%</strong></span>
          <?php endif; ?>
        </div>
        <div class="mini-widgets">
          <div class="mini"><h5>Latest item</h5><p><strong><?php echo e($latest_item_t); ?></strong> • total <?php echo e($items_display); ?> • +<?php echo (int)$items_new_week; ?> this week</p></div>
          <div class="mini"><h5>Latest service</h5><p><strong><?php echo e($latest_serv_t); ?></strong> • total <?php echo e($services_display); ?> • +<?php echo (int)$services_new_week; ?> this week</p></div>
          <div class="mini"><h5>Community</h5><p><strong><?php echo e($users_display); ?></strong> members<?php if ($fulfilled_rate !== null) { echo ' • ' . e($fulfilled_rate) . '% fulfilled'; } ?></p></div>
        </div>
      </div>
    </div>
  </div>
  <section class="features">
    <div class="tile">
      <div class="icon" aria-hidden="true">🔄</div>
      <h4>Share what you can</h4>
      <p>List items or services you’re happy to lend or give away—help neighbors and reduce waste.</p>
      <ul><li>Quick listing with photos</li><li>Availability controls</li><li>Safe messaging</li></ul>
  <a class="more" href="<?php echo site_href('pages/add_item.php'); ?>">Post an item →</a>
    </div>
    <div class="tile">
      <div class="icon" aria-hidden="true">🆘</div>
      <h4>Request what you need</h4>
      <p>Need a tool or a hand? Post a request and connect with local givers quickly and safely.</p>
      <ul><li>Clear status updates</li><li>Real-time notifications</li><li>Giver approvals</li></ul>
  <a class="more" href="<?php echo site_href('pages/seeker_feed.php?tab=items'); ?>">Create a request →</a>
    </div>
    <div class="tile">
      <div class="icon" aria-hidden="true">🤝</div>
      <h4>Connect and build trust</h4>
      <p>Message, review, and grow a trusted sharing community around you.</p>
      <ul><li>Profile and reviews</li><li>Role-based permissions</li><li>Report & support tools</li></ul>
  <a class="more" href="<?php echo site_href('pages/reviews.php'); ?>">See community reviews →</a>
    </div>
  </section>
  <div class="categories" aria-label="Popular categories">
    <span class="tag">Household</span><span class="tag">Tools</span><span class="tag">Electronics</span><span class="tag">Books</span><span class="tag">Clothing</span><span class="tag">Services</span><span class="tag">Sports</span><span class="tag">Garden</span>
  </div>
  <div class="ticker" aria-label="Recent activity"><ul>
    <?php if (function_exists('db_connected') && db_connected() && ($latest_items || $latest_services)): ?>
      <?php foreach ($latest_items as $it): ?>
        <li>New item: <?php echo e($it['title']); ?> • <?php echo e(date('M j', strtotime($it['posting_date']))); ?></li>
      <?php endforeach; ?>
      <?php foreach ($latest_services as $sv): ?>
        <li>New service: <?php echo e($sv['title']); ?> • <?php echo e(date('M j', strtotime($sv['posting_date']))); ?></li>
      <?php endforeach; ?>
    <?php else: ?>
      <li>Welcome to ShareHub — explore items and services in your community.</li>
    <?php endif; ?>
  </ul></div>
  <section class="how">
    <div class="how-steps">
      <div class="step"><span class="num">1</span><h5>Create your profile</h5><p>Join as a seeker or giver and set your preferences.</p></div>
      <div class="step"><span class="num">2</span><h5>List or request</h5><p>Post items/services to share, or request what you need.</p></div>
      <div class="step"><span class="num">3</span><h5>Connect and complete</h5><p>Message, arrange handoff, and leave a review.</p></div>
    </div>
  </section>
  <section class="testimonials" aria-label="Testimonials">
    <div class="tcard"><p>“Found a ladder in minutes and met a friendly neighbor—amazing.”</p><div class="who">— Lina M.</div></div>
    <div class="tcard"><p>“I list my spare tools on weekends. Easy to manage and feels great to help.”</p><div class="who">— Kevin R.</div></div>
    <div class="tcard"><p>“The request/approval flow keeps everything clear and respectful.”</p><div class="who">— Sahana P.</div></div>
  </section>
  <section class="quick-links">
  <div class="q"><a href="<?php echo site_href('pages/seeker_feed.php?tab=items'); ?>">Browse all items →</a></div>
  <div class="q"><a href="<?php echo site_href('pages/seeker_feed.php?tab=services'); ?>">Browse all services →</a></div>
  <div class="q"><a href="<?php echo site_href('pages/seeker_feed.php?tab=items'); ?>">Create a request →</a></div>
  <div class="q"><a href="<?php echo site_href('pages/reviews.php'); ?>">Read reviews →</a></div>
  </section>
  <div class="newsletter">
    <div><strong>Stay in the loop</strong><div style="opacity:.95">Get tips and highlights from your community.</div></div>
    <form action="#" method="post" onsubmit="event.preventDefault(); alert('Thanks for subscribing!');"><input type="email" placeholder="you@email.com" required><button class="btn btn-primary" type="submit">Subscribe</button></form>
  </div>
  <div class="cta-band">
    <div><strong>Ready to start sharing?</strong><div style="opacity:.9; margin-top:4px;">Join free and post your first item or request in minutes.</div></div>
    <div class="actions">
      <?php if (!empty($_SESSION['loggedin'])): ?>
  <a href="<?php echo site_href('pages/add_item.php'); ?>" class="btn btn-success">Post an Item</a>
  <a href="<?php echo site_href('pages/add_service.php'); ?>" class="btn btn-info btn-outline">Offer a Service</a>
      <?php else: ?>
  <a href="<?php echo site_href('pages/register.php'); ?>" class="btn btn-primary">Create an Account</a>
    <a href="<?php echo site_href('pages/login.php'); ?>" class="btn btn-outline">Sign In</a>
      <?php endif; ?>
    </div>
  </div>
  <footer class="alt-footer">
    <p>&copy; <?php echo date('Y'); ?> ShareHub — Home</p>
  <p><a href="<?php echo site_href('pages/contact.php'); ?>">Contact</a> • <a href="<?php echo site_href('pages/privacy.php'); ?>">Privacy</a></p>
  </footer>
</section>
<?php render_footer(); ?>
</body>
</html>
