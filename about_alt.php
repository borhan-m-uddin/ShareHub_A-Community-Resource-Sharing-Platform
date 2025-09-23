<?php require_once __DIR__ . '/bootstrap.php';
/* Live data (prepared statements) */
$latest_items = [];
$latest_services = [];
$items_new_week = 0;
$services_new_week = 0;

function fmt_count(int $n): string
{
    if ($n >= 1000000) return round($n / 1000000, 1) . 'M+';
    if ($n >= 1000) return round($n / 1000, 1) . 'k+';
    return (string)$n;
}

if (db_connected()) {
    try {
        if ($st = $conn->prepare("SELECT item_id, title, posting_date FROM items WHERE availability_status='available' ORDER BY posting_date DESC LIMIT 5")) {
            $st->execute();
            $res = $st->get_result();
            while ($r = $res->fetch_assoc()) $latest_items[] = $r;
            $st->close();
        }
        if ($st = $conn->prepare("SELECT service_id, title, posting_date FROM services WHERE availability='available' ORDER BY posting_date DESC LIMIT 5")) {
            $st->execute();
            $res = $st->get_result();
            while ($r = $res->fetch_assoc()) $latest_services[] = $r;
            $st->close();
        }

        $items_count = 0;
        $services_count = 0;
        $users_count = 0;
        $requests_today = 0;
        $requests_week = 0;
        $fulfilled_rate = null;

        if ($st = $conn->prepare("SELECT COUNT(*) c FROM items WHERE availability_status='available'")) {
            $st->execute();
            $r = $st->get_result()->fetch_assoc();
            $items_count = (int)($r['c'] ?? 0);
            $st->close();
        }
        if ($st = $conn->prepare("SELECT COUNT(*) c FROM services WHERE availability='available'")) {
            $st->execute();
            $r = $st->get_result()->fetch_assoc();
            $services_count = (int)($r['c'] ?? 0);
            $st->close();
        }
        if ($st = $conn->prepare("SELECT COUNT(*) c FROM users WHERE status=1")) {
            $st->execute();
            $r = $st->get_result()->fetch_assoc();
            $users_count = (int)($r['c'] ?? 0);
            $st->close();
        }

        if ($st = $conn->prepare("SELECT COUNT(*) c FROM requests WHERE DATE(created_at)=CURDATE()")) {
            if ($st->execute()) {
                $r = $st->get_result()->fetch_assoc();
                $requests_today = (int)($r['c'] ?? 0);
            }
            $st->close();
        }
        if ($st = $conn->prepare("SELECT COUNT(*) c FROM requests WHERE YEARWEEK(created_at,1)=YEARWEEK(CURDATE(),1)")) {
            if ($st->execute()) {
                $r = $st->get_result()->fetch_assoc();
                $requests_week = (int)($r['c'] ?? 0);
            }
            $st->close();
        }
        if ($st = $conn->prepare("SELECT SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) done, COUNT(*) total FROM requests")) {
            if ($st->execute()) {
                $r = $st->get_result()->fetch_assoc();
                $done = (int)($r['done'] ?? 0);
                $total = (int)($r['total'] ?? 0);
                $fulfilled_rate = $total > 0 ? round(($done / $total) * 100) : null;
            }
            $st->close();
        }

        if ($st = $conn->prepare("SELECT COUNT(*) c FROM items WHERE availability_status='available' AND posting_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")) {
            if ($st->execute()) {
                $r = $st->get_result()->fetch_assoc();
                $items_new_week = (int)($r['c'] ?? 0);
            }
            $st->close();
        }
        if ($st = $conn->prepare("SELECT COUNT(*) c FROM services WHERE availability='available' AND posting_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")) {
            if ($st->execute()) {
                $r = $st->get_result()->fetch_assoc();
                $services_new_week = (int)($r['c'] ?? 0);
            }
            $st->close();
        }
    } catch (Exception $e) { /* render with defaults */
    }
}

$items_display    = $items_count > 0    ? fmt_count($items_count)       : fmt_count(count($latest_items) + 120);
$services_display = $services_count > 0 ? fmt_count($services_count)    : fmt_count(count($latest_services) + 40);
$users_display    = $users_count > 0    ? fmt_count($users_count)       : '1.2k+';
$latest_item_t    = $latest_items[0]['title']   ?? 'Garden Tools';
$latest_serv_t    = $latest_services[0]['title'] ?? 'Tutoring';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>ShareHub — Home</title>
    <link rel="stylesheet" href="<?php echo asset_url('style.css'); ?>">
    <style>
        /* Hero: gradient mesh */
        .alt-wrapper {
            max-width: 1100px;
            margin: 20px auto;
            padding: 0 22px;
        }

        .alt-hero {
            position: relative;
            overflow: hidden;
            border-radius: 16px;
            padding: 56px 22px;
            box-shadow: 0 14px 48px rgba(5, 41, 45, .16);
        }

        .alt-hero::before {
            content: "";
            position: absolute;
            inset: -10%;
            z-index: 0;
            pointer-events: none;
            background:
                radial-gradient(800px 400px at 10% 20%, rgba(35, 211, 179, .35), transparent 60%),
                radial-gradient(600px 320px at 90% 30%, rgba(76, 167, 201, .35), transparent 60%),
                radial-gradient(600px 360px at 50% 90%, rgba(15, 163, 168, .28), transparent 60%),
                linear-gradient(120deg, rgba(255, 255, 255, .08), rgba(255, 255, 255, .02));
            filter: saturate(120%);
        }

        .alt-hero .inner {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: 1fr;
            gap: 22px;
            max-width: 960px;
        }

        .alt-hero h1 {
            font-size: 2.6rem;
            margin: 0 0 12px;
            letter-spacing: .2px;
        }

        .alt-hero p.lead {
            font-size: 1.08rem;
            opacity: .95;
            margin: 0 0 18px;
        }

        .alt-hero .ctas a {
            margin-right: 10px;
        }

        /* Compact stat widgets (replacing old cluster) */
        .mini-widgets {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-top: 12px;
        }

        .mini {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: var(--shadow);
            padding: 14px;
            position: relative;
            overflow: hidden;
        }

        .mini::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(35, 211, 179, .12), transparent 60%);
            pointer-events: none;
        }

        .mini h5 {
            margin: 0 0 6px;
            font-size: 1rem;
        }

        .mini p {
            margin: 0;
            color: var(--muted);
            font-weight: 600;
        }

        @media (max-width: 900px) {
            .mini-widgets {
                grid-template-columns: 1fr;
            }
        }

        /* Stats pills */
        .stats-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .pill {
            background: linear-gradient(135deg, rgba(79, 209, 217, .20), rgba(15, 163, 168, .26));
            color: var(--text);
            border: 1px solid rgba(15, 163, 168, .25);
            border-radius: 999px;
            padding: 8px 14px;
            box-shadow: 0 4px 12px rgba(5, 41, 45, .10);
            font-weight: 600;
        }

        html[data-theme='dark'] .pill {
            background: linear-gradient(135deg, rgba(79, 209, 217, .20), rgba(15, 163, 168, .28));
            border-color: rgba(79, 209, 217, .40);
            color: #e8f2ff;
        }

        /* Feature tiles */
        .features {
            margin-top: 28px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .tile {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px;
            box-shadow: var(--shadow);
            transition: transform .18s ease, box-shadow .22s ease, border-color .22s ease;
            position: relative;
            overflow: hidden;
        }

        .tile:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 32px rgba(5, 41, 45, .12);
            border-color: rgba(37, 99, 235, .18);
        }

        .tile::before {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(400px 140px at -10% -10%, rgba(35, 211, 179, .15), transparent 70%);
            pointer-events: none;
        }

        .tile h4 {
            margin: 6px 0 8px;
        }

        .tile p {
            margin: 0;
            color: var(--muted);
        }

        .tile .icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: inline-grid;
            place-items: center;
            background: rgba(35, 211, 179, .12);
            color: var(--primary);
        }

        .tile ul {
            margin: 8px 0 0;
            padding-left: 18px;
            color: var(--muted);
        }

        .tile a.more {
            display: inline-block;
            margin-top: 10px;
            font-weight: 700;
        }

        /* Ticker */
        .ticker {
            margin-top: 28px;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--card);
        }

        .ticker ul {
            display: flex;
            gap: 24px;
            list-style: none;
            padding: 10px 16px;
            margin: 0;
            animation: tickerMove 28s linear infinite;
        }

        .ticker li {
            white-space: nowrap;
            color: var(--muted);
        }

        @keyframes tickerMove {
            0% {
                transform: translateX(0);
            }

            100% {
                transform: translateX(-50%);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .ticker ul {
                animation: none;
            }
        }

        /* CTA band, categories, how, testimonials, quick links, newsletter */
        .cta-band {
            margin-top: 32px;
            padding: 18px;
            border-radius: 14px;
            color: #fff;
            background: linear-gradient(120deg, var(--primary) 0%, var(--laser) 50%, var(--info) 100%);
            box-shadow: 0 12px 32px rgba(5, 41, 45, .18);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .cta-band .actions a {
            margin-left: 10px;
        }

        .categories {
            margin-top: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .tag {
            display: inline-block;
            padding: 10px 16px;
            border-radius: 999px;
            background: linear-gradient(135deg, rgba(15, 163, 168, .10), rgba(35, 211, 179, .16));
            border: 1px solid rgba(15, 163, 168, .30);
            color: var(--text);
            font-weight: 600;
            letter-spacing: .2px;
            box-shadow: 0 2px 6px rgba(5, 41, 45, .10);
            transition: transform .15s ease, box-shadow .2s ease, background .3s ease;
        }

        .tag:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(5, 41, 45, .18);
            background: linear-gradient(135deg, rgba(15, 163, 168, .18), rgba(35, 211, 179, .26));
        }

        html[data-theme='dark'] .tag {
            background: linear-gradient(135deg, rgba(79, 209, 217, .20), rgba(15, 163, 168, .26));
            border-color: rgba(79, 209, 217, .45);
            color: #e8f2ff;
        }

        .how {
            margin-top: 28px;
        }

        .how-steps {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .step {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px;
            box-shadow: var(--shadow);
        }

        .step .num {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: inline-grid;
            place-items: center;
            font-weight: 900;
            color: #fff;
            background: var(--primary);
            box-shadow: 0 6px 14px rgba(15, 163, 168, .28);
        }

        .step h5 {
            margin: 8px 0 6px;
            font-size: 1.05rem;
        }

        .step p {
            margin: 0;
            color: var(--muted);
        }

        .testimonials {
            margin-top: 28px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .tcard {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px;
            box-shadow: var(--shadow);
        }

        .tcard .who {
            margin-top: 10px;
            font-weight: 700;
            color: var(--text);
        }

        .quick-links {
            margin-top: 24px;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        .q {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .q a {
            font-weight: 800;
        }

        .newsletter {
            margin-top: 28px;
            padding: 18px;
            border-radius: 14px;
            background: linear-gradient(120deg, var(--primary) 0%, var(--laser) 50%, var(--info) 100%);
            color: #fff;
            box-shadow: 0 12px 32px rgba(5, 41, 45, .18);
            display: flex;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .newsletter input[type="email"] {
            width: 260px;
            max-width: 100%;
            padding: 10px 12px;
            border-radius: 10px;
            border: 0;
        }

        footer.alt-footer {
            margin: 36px 0 8px;
            text-align: center;
            color: var(--muted);
        }

        @media (max-width:900px) {
            .features {
                grid-template-columns: 1fr;
            }

            .how-steps {
                grid-template-columns: 1fr;
            }

            .testimonials {
                grid-template-columns: 1fr;
            }

            .quick-links {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var ul = document.querySelector('.ticker ul');
            if (!ul) return;
            ul.innerHTML = ul.innerHTML + ul.innerHTML; // duplicate once for smooth loop
        });
    </script>
</head>

<body>
    <?php render_header(); ?>

    <section class="alt-wrapper">
        <div class="alt-hero wrapper">
            <div class="inner">
                <div>
                    <h1>ShareHub — A fresh way to Share, Request, and Connect</h1>
                    <p class="lead">Discover community-shared items and services. Post what you can give, request what you need, and build a more connected neighborhood.</p>

                    <div class="ctas">
                        <?php if (!empty($_SESSION['loggedin'])): ?>
                            <a href="<?php echo e('dashboard.php'); ?>" class="btn btn-primary">Go to Dashboard</a>
                            <a href="<?php echo e('seeker_feed.php?tab=items'); ?>" class="btn btn-default">Browse Items</a>
                            <a href="<?php echo e('seeker_feed.php?tab=services'); ?>" class="btn btn-default">Browse Services</a>
                        <?php else: ?>
                            <a href="<?php echo e('register.php'); ?>" class="btn btn-primary">Join ShareHub</a>
                            <a href="<?php echo e('seeker_feed.php?tab=items'); ?>" class="btn btn-default">Explore Items</a>
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

                    <!-- Compact stat widgets -->
                    <div class="mini-widgets">
                        <div class="mini">
                            <h5>Latest item</h5>
                            <p><strong><?php echo e($latest_item_t); ?></strong> • total <?php echo e($items_display); ?> • +<?php echo (int)$items_new_week; ?> this week</p>
                        </div>
                        <div class="mini">
                            <h5>Latest service</h5>
                            <p><strong><?php echo e($latest_serv_t); ?></strong> • total <?php echo e($services_display); ?> • +<?php echo (int)$services_new_week; ?> this week</p>
                        </div>
                        <div class="mini">
                            <h5>Community</h5>
                            <p><strong><?php echo e($users_display); ?></strong> members<?php if ($fulfilled_rate !== null) {
                                                                                            echo " • " . e($fulfilled_rate) . "% fulfilled";
                                                                                        } ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <section class="features">
            <div class="tile">
                <div class="icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z" />
                    </svg>
                </div>
                <h4>Share what you can</h4>
                <p>List items or services you’re happy to lend or give away—help neighbors and reduce waste.</p>
                <ul>
                    <li>Quick listing with photos</li>
                    <li>Availability controls</li>
                    <li>Safe messaging</li>
                </ul>
                <a class="more" href="add_item.php">Post an item →</a>
            </div>
            <div class="tile">
                <div class="icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 7a5 5 0 00-5 5H5l3.5 3.5L12 12h-2a3 3 0 116 0 6 6 0 11-6-6z" />
                    </svg>
                </div>
                <h4>Request what you need</h4>
                <p>Need a tool or a hand? Post a request and connect with local givers quickly and safely.</p>
                <ul>
                    <li>Clear status updates</li>
                    <li>Real-time notifications</li>
                    <li>Giver approvals</li>
                </ul>
                <a class="more" href="seeker_feed.php?tab=items">Create a request →</a>
            </div>
            <div class="tile">
                <div class="icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 13c2.21 0 4-1.79 4-4S14.21 5 12 5 8 6.79 8 9s1.79 4 4 4zm0 2c-3.31 0-6 2.69-6 6h2a4 4 0 118 0h2c0-3.31-2.69-6-6-6z" />
                    </svg>
                </div>
                <h4>Connect and build trust</h4>
                <p>Message, review, and grow a trusted sharing community around you.</p>
                <ul>
                    <li>Profile and reviews</li>
                    <li>Role-based permissions</li>
                    <li>Report & support tools</li>
                </ul>
                <a class="more" href="reviews.php">See community reviews →</a>
            </div>
        </section>

        <div class="categories" aria-label="Popular categories">
            <span class="tag">Household</span>
            <span class="tag">Tools</span>
            <span class="tag">Electronics</span>
            <span class="tag">Books</span>
            <span class="tag">Clothing</span>
            <span class="tag">Services</span>
            <span class="tag">Sports</span>
            <span class="tag">Garden</span>
        </div>

        <div class="ticker" aria-label="Recent activity">
            <ul>
                <?php if (db_connected()): ?>
                    <?php foreach ($latest_items as $it): ?>
                        <li>New item: <?php echo e($it['title']); ?> • <?php echo e(date('M j', strtotime($it['posting_date']))); ?></li>
                    <?php endforeach; ?>
                    <?php foreach ($latest_services as $sv): ?>
                        <li>New service: <?php echo e($sv['title']); ?> • <?php echo e(date('M j', strtotime($sv['posting_date']))); ?></li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>Welcome to ShareHub — explore items and services in your community.</li>
                <?php endif; ?>
            </ul>
        </div>

        <section class="how">
            <div class="how-steps">
                <div class="step">
                    <span class="num">1</span>
                    <h5>Create your profile</h5>
                    <p>Join as a seeker or giver and set your preferences.</p>
                </div>
                <div class="step">
                    <span class="num">2</span>
                    <h5>List or request</h5>
                    <p>Post items/services to share, or request what you need.</p>
                </div>
                <div class="step">
                    <span class="num">3</span>
                    <h5>Connect and complete</h5>
                    <p>Message, arrange handoff, and leave a review.</p>
                </div>
            </div>
        </section>

        <section class="testimonials" aria-label="Testimonials">
            <div class="tcard">
                <p>“Found a ladder in minutes and met a friendly neighbor—amazing.”</p>
                <div class="who">— Lina M.</div>
            </div>
            <div class="tcard">
                <p>“I list my spare tools on weekends. Easy to manage and feels great to help.”</p>
                <div class="who">— Kevin R.</div>
            </div>
            <div class="tcard">
                <p>“The request/approval flow keeps everything clear and respectful.”</p>
                <div class="who">— Sahana P.</div>
            </div>
        </section>

        <section class="quick-links">
            <div class="q"><a href="seeker_feed.php?tab=items">Browse all items →</a></div>
            <div class="q"><a href="seeker_feed.php?tab=services">Browse all services →</a></div>
            <div class="q"><a href="seeker_feed.php?tab=items">Create a request →</a></div>
            <div class="q"><a href="reviews.php">Read reviews →</a></div>
        </section>

        <div class="newsletter">
            <div>
                <strong>Stay in the loop</strong>
                <div style="opacity:.95">Get tips and highlights from your community.</div>
            </div>
            <form action="#" method="post" onsubmit="event.preventDefault(); alert('Thanks for subscribing!');">
                <input type="email" placeholder="your@email.com" required>
                <button class="btn btn-primary" type="submit">Subscribe</button>
            </form>
        </div>

        <div class="cta-band">
            <div>
                <strong>Ready to start sharing?</strong>
                <div style="opacity:.9; margin-top:4px;">Join free and post your first item or request in minutes.</div>
            </div>
            <div class="actions">
                <?php if (!empty($_SESSION['loggedin'])): ?>
                    <a href="add_item.php" class="btn btn-success">Post an Item</a>
                    <a href="add_service.php" class="btn btn-info btn-outline">Offer a Service</a>
                <?php else: ?>
                    <a href="register.php" class="btn btn-primary">Create an Account</a>
                    <a href="login.php" class="btn btn-outline">Sign In</a>
                <?php endif; ?>
            </div>
        </div>

        <footer class="alt-footer">
            <p>&copy; <?php echo date('Y'); ?> ShareHub — Home</p>
            <p><a href="contact.php">Contact</a> • <a href="privacy.php">Privacy</a></p>
        </footer>
    </section>

    </main>
</body>

</html>