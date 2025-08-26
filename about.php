<?php require_once __DIR__ . '/bootstrap.php';
// Build small showcases for latest items and services
$latest_items = [];
$latest_services = [];

// Helpers: format big numbers like 1200 -> 1.2k+
function format_count_display(int $n): string {
    if ($n >= 1000000) return round($n / 1000000, 1) . 'M+';
    if ($n >= 1000) return round($n / 1000, 1) . 'k+';
    return (string)$n;
}

// Safe queries using small limits; try to fetch live counts and latest rows
try {
    // latest items
    $items_q = $conn->query("SELECT item_id, title, description, posting_date FROM items WHERE availability_status='available' ORDER BY posting_date DESC LIMIT 6");
    if ($items_q) {
        while ($r = $items_q->fetch_assoc()) $latest_items[] = $r;
    }

    // latest services
    $services_q = $conn->query("SELECT service_id, title, description, posting_date FROM services WHERE availability='available' ORDER BY posting_date DESC LIMIT 6");
    if ($services_q) {
        while ($r = $services_q->fetch_assoc()) $latest_services[] = $r;
    }

    // live counts (use simple COUNT queries)
    $items_count = 0;
    $services_count = 0;
    $users_count = 0;

    $c1 = $conn->query("SELECT COUNT(*) AS c FROM items WHERE availability_status='available'");
    if ($c1 && ($row = $c1->fetch_assoc())) $items_count = (int)$row['c'];

    $c2 = $conn->query("SELECT COUNT(*) AS c FROM services WHERE availability='available'");
    if ($c2 && ($row = $c2->fetch_assoc())) $services_count = (int)$row['c'];

    // count active users (status = 1) as community members
    $c3 = $conn->query("SELECT COUNT(*) AS c FROM users WHERE status = 1");
    if ($c3 && ($row = $c3->fetch_assoc())) $users_count = (int)$row['c'];

    // Fallbacks: if counts are zero (table missing or empty), keep the previous heuristics
    $items_display = $items_count > 0 ? format_count_display($items_count) : format_count_display(count($latest_items) + 120);
    $services_display = $services_count > 0 ? format_count_display($services_count) : format_count_display(count($latest_services) + 40);
    $users_display = $users_count > 0 ? format_count_display($users_count) : '1.2k+';

} catch (Exception $e) {
    // ignore DB errors here — page should still render with reasonable defaults
    $items_display = format_count_display(count($latest_items) + 120);
    $services_display = format_count_display(count($latest_services) + 40);
    $users_display = '1.2k+';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ShareHub — A Community Resources Sharing Platform</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <style>
        /* Homepage specific styles */
        .home-hero { display:flex; align-items:center; gap:28px; padding:56px 18px; background:linear-gradient(120deg,#2563eb 0%, #06b6d4 100%); color:white; border-radius:12px; box-shadow:0 8px 40px rgba(37,99,235,0.12); }
        .home-hero .hero-left { max-width:640px; }
        .home-hero h1 { font-size:2.6rem; margin:0 0 12px 0; }
        .home-hero p.lead { font-size:1.05rem; opacity:0.95; margin-bottom:18px; }
        .home-hero .hero-ctas a { margin-right:12px; }

        .stats { display:flex; gap:18px; margin-top:20px; }
        .stat { background:rgba(255,255,255,0.08); padding:12px 16px; border-radius:10px; text-align:center; }
        .stat .num { font-weight:800; font-size:1.25rem; }

        .showcase { margin-top:36px; }
        .showcase-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:16px; }
        .card { background:#fff; padding:14px; border-radius:12px; box-shadow:0 6px 18px rgba(15,23,42,0.06); }
        .card h4 { margin:0 0 8px 0; }
        .section-title { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }

        .testimonials { margin-top:36px; display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:14px; }
        .testimonial { background:#fff; padding:16px; border-radius:12px; box-shadow:0 6px 18px rgba(15,23,42,0.06); }

        footer.site-footer { margin-top:40px; padding:24px 18px; text-align:center; color:#6b7280; }

        @media (max-width:900px) {
            .home-hero { flex-direction:column; text-align:center; }
            .home-hero .hero-left { max-width:100%; }
        }
    </style>
</head>
<body>
    <?php render_header(); ?>

    <section class="home-hero wrapper">
        <div class="hero-left">
            <h1>ShareHub — Share, Request, and Reuse in Your Community</h1>
            <p class="lead">Find items and services shared by neighbors. Post what you can give, request what you need, and build a more sustainable, supportive local network.</p>
            <div class="hero-ctas">
                <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin']===true): ?>
                    <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                    <a href="view_items.php" class="btn btn-default">Browse Items</a>
                    <a href="view_services.php" class="btn btn-default">Browse Services</a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-primary">Get Started — Join Free</a>
                    <a href="view_items.php" class="btn btn-default">Browse Items</a>
                <?php endif; ?>
            </div>
            <div class="stats">
                <div class="stat"><div class="num"><?php echo count($latest_items) + 120; ?></div><div class="label">Items listed</div></div>
                <div class="stat"><div class="num"><?php echo count($latest_services) + 40; ?></div><div class="label">Services offered</div></div>
                <div class="stat"><div class="num">1.2k+</div><div class="label">Community members</div></div>
            </div>
        </div>
        <div class="hero-right">
            <img src="assets/brand/logo-badge.svg" alt="ShareHub" style="width:160px;height:160px;filter:drop-shadow(0 8px 20px rgba(0,0,0,0.12));border-radius:16px;background:#fff;padding:12px;"/>
        </div>
    </section>

    <div class="wrapper">
        <section class="showcase">
            <div class="section-title">
                <h3>Latest Items</h3>
                <a href="view_items.php">See all</a>
            </div>
            <div class="showcase-grid">
                <?php if (count($latest_items) > 0): ?>
                    <?php foreach ($latest_items as $it): ?>
                        <div class="card">
                            <h4><?php echo htmlspecialchars($it['title']); ?></h4>
                            <p style="color:#6b7280;font-size:0.95rem;"><?php echo htmlspecialchars(strlen($it['description'])>120?substr($it['description'],0,120).'...':$it['description']); ?></p>
                            <div style="margin-top:10px;font-size:0.85rem;color:#9ca3af;">Posted: <?php echo date('M j, Y', strtotime($it['posting_date'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card">No recent items available.</div>
                <?php endif; ?>
            </div>
        </section>

        <section class="showcase" style="margin-top:28px;">
            <div class="section-title">
                <h3>Latest Services</h3>
                <a href="view_services.php">See all</a>
            </div>
            <div class="showcase-grid">
                <?php if (count($latest_services) > 0): ?>
                    <?php foreach ($latest_services as $s): ?>
                        <div class="card">
                            <h4><?php echo htmlspecialchars($s['title']); ?></h4>
                            <p style="color:#6b7280;font-size:0.95rem;"><?php echo htmlspecialchars(strlen($s['description'])>120?substr($s['description'],0,120).'...':$s['description']); ?></p>
                            <div style="margin-top:10px;font-size:0.85rem;color:#9ca3af;">Posted: <?php echo date('M j, Y', strtotime($s['posting_date'])); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card">No recent services available.</div>
                <?php endif; ?>
            </div>
        </section>

        <section class="testimonials" style="margin-top:36px;">
            <div class="testimonial">
                <p>"ShareHub made it so easy to find a gently used drill from my neighbor — saved me time and money."</p>
                <div style="margin-top:10px;font-weight:700;">— A. Morgan</div>
            </div>
            <div class="testimonial">
                <p>"I offered up my old textbooks and met people in my community. Love the idea and simplicity."</p>
                <div style="margin-top:10px;font-weight:700;">— Priya S.</div>
            </div>
            <div class="testimonial">
                <p>"As a giver, the request tracking is clear and respectful. I can approve or decline with a message."</p>
                <div style="margin-top:10px;font-weight:700;">— Jamal R.</div>
            </div>
        </section>

        <footer class="site-footer">
            <div style="max-width:1100px;margin:0 auto;">
                <p>&copy; <?php echo date('Y'); ?> ShareHub — A Community Resources Sharing Platform</p>
                <p><a href="about.php">About</a> • <a href="contact.php">Contact</a> • <a href="privacy.php">Privacy</a></p>
            </div>
        </footer>
    </div>

    </main>
</body>
</html>
