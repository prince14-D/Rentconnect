<?php
session_start();
include "app_init.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

$dashboardStats = rc_mig_get_landlord_dashboard_stats($conn, (int) $landlord_id);
$counts = $dashboardStats['counts'];
$pending_requests = (int) $dashboardStats['pending_requests'];
$booked_count = (int) $dashboardStats['booked_count'];
$pending_payments = (int) $dashboardStats['pending_payments'];
$pending_income = (float) $dashboardStats['pending_income'];
$current_page = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Manage your rental properties and requests.">
<meta name="theme-color" content="#1f8f67">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="manifest" href="/manifest.json">
<title>Landlord Dashboard - RentConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --ink: #212733;
    --muted: #59627a;
    --brand: #1f8f67;
    --brand-deep: #15543e;
    --accent: #ff7a2f;
    --line: rgba(33, 39, 51, 0.12);
    --card: rgba(255, 255, 255, 0.94);
    --shadow: 0 18px 38px rgba(20, 31, 44, 0.15);
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    min-height: 100vh;
    font-family: 'Manrope', sans-serif;
    color: var(--ink);
    background:
        radial-gradient(circle at 8% 8%, rgba(255, 122, 47, 0.2), transparent 34%),
        radial-gradient(circle at 92% 8%, rgba(31, 143, 103, 0.18), transparent 30%),
        linear-gradient(165deg, #f9f6ef 0%, #f2f7f8 56%, #fffdfa 100%);
}

.container {
    width: min(1220px, 95vw);
    margin: 0 auto;
}

.top {
    padding: 24px 0;
}

.shell {
    display: flex;
    gap: 18px;
    align-items: stretch;
}

.sidebar {
    width: 290px;
    flex-shrink: 0;
    background: linear-gradient(180deg, rgba(18, 64, 77, 0.96), rgba(14, 50, 63, 0.96));
    border-radius: 20px;
    box-shadow: var(--shadow);
    color: #fff;
    padding: 18px 14px;
    display: flex;
    flex-direction: column;
    min-height: calc(100vh - 48px);
}

.brand {
    border-bottom: 1px solid rgba(255, 255, 255, 0.18);
    padding: 4px 6px 14px;
    margin-bottom: 12px;
}

.brand h1 {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1.15rem;
    letter-spacing: -0.02em;
    margin-bottom: 4px;
}

.brand p {
    color: rgba(255, 255, 255, 0.78);
    font-size: 0.9rem;
}

.side-section-title {
    padding: 8px 10px;
    color: rgba(255, 255, 255, 0.65);
    font-size: 0.74rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-weight: 700;
}

.side-nav {
    display: grid;
    gap: 6px;
    margin-bottom: 12px;
}

.nav-link {
    text-decoration: none;
    color: #eaf4f1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    border-radius: 11px;
    padding: 10px 11px;
    border: 1px solid transparent;
    transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
}

.nav-link:hover {
    background: rgba(255, 255, 255, 0.11);
    border-color: rgba(255, 255, 255, 0.2);
    transform: translateX(2px);
}

.nav-link.active {
    background: rgba(255, 255, 255, 0.16);
    border-color: rgba(143, 224, 196, 0.45);
}

.nav-link.active .nav-left i {
    color: #fff;
}

.nav-left {
    display: flex;
    align-items: center;
    gap: 10px;
}

.nav-left i {
    width: 18px;
    text-align: center;
    color: #8fe0c4;
}

.nav-count {
    min-width: 22px;
    height: 22px;
    border-radius: 999px;
    display: grid;
    place-items: center;
    padding: 0 6px;
    font-size: 0.72rem;
    font-weight: 700;
    background: linear-gradient(135deg, var(--accent), #f15f13);
    color: #fff;
}

.nav-link.logout {
    margin-top: auto;
    background: linear-gradient(140deg, #ef554d, #d53d36);
    border-color: rgba(255, 255, 255, 0.22);
}

.nav-link.logout .nav-left i {
    color: #fff;
}

.content {
    flex: 1;
    min-width: 0;
}

.head {
    background: linear-gradient(140deg, rgba(17, 59, 72, 0.94), rgba(31, 143, 103, 0.88));
    color: #fff;
    border-radius: 20px;
    padding: clamp(18px, 3.6vw, 28px);
    box-shadow: var(--shadow);
}

.head h2 {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: clamp(1.3rem, 2.8vw, 1.9rem);
    letter-spacing: -0.02em;
    margin-bottom: 6px;
}

.head p { color: rgba(255, 255, 255, 0.88); }

.message {
    margin-top: 12px;
    padding: 11px 13px;
    border-radius: 12px;
    background: rgba(39, 165, 106, 0.12);
    border: 1px solid rgba(39, 165, 106, 0.35);
    color: #165a39;
    font-weight: 700;
}

.stat-grid {
    margin-top: 14px;
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
}

.stat-card {
    background: var(--card);
    border: 1px solid rgba(255, 255, 255, 0.92);
    border-radius: 16px;
    box-shadow: 0 10px 24px rgba(15, 31, 40, 0.09);
    padding: 14px;
}

.stat-card .kicker {
    color: var(--muted);
    font-size: 0.82rem;
    margin-bottom: 4px;
}

.stat-card .value {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1.5rem;
    letter-spacing: -0.02em;
    color: #1b2738;
}

.panel-grid {
    margin-top: 14px;
    display: grid;
    grid-template-columns: 1.2fr 1fr;
    gap: 12px;
}

.panel {
    background: var(--card);
    border: 1px solid rgba(255, 255, 255, 0.92);
    border-radius: 16px;
    box-shadow: 0 10px 24px rgba(15, 31, 40, 0.09);
    padding: 16px;
}

.panel h3 {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1.05rem;
    margin-bottom: 10px;
}

.panel p {
    color: var(--muted);
    font-size: 0.92rem;
    line-height: 1.5;
}

.quick-links {
    margin-top: 10px;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.quick-links a {
    text-decoration: none;
    border-radius: 10px;
    border: 1px solid var(--line);
    background: #fff;
    color: #1d2a3a;
    padding: 10px;
    font-size: 0.88rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.quick-links a:hover {
    border-color: rgba(31, 143, 103, 0.45);
    background: #f5fffb;
}

.health-list {
    list-style: none;
    display: grid;
    gap: 8px;
}

.health-list li {
    border: 1px solid var(--line);
    border-radius: 11px;
    padding: 10px;
    background: #fff;
    display: flex;
    justify-content: space-between;
    gap: 10px;
    align-items: center;
}

.health-list strong {
    font-size: 0.9rem;
}

.health-list span {
    color: var(--muted);
    font-size: 0.84rem;
}

.watermark {
    position: fixed;
    right: 14px;
    bottom: 10px;
    z-index: 20;
    color: rgba(35, 49, 70, 0.62);
    background: rgba(255, 255, 255, 0.72);
    border: 1px solid rgba(31, 143, 103, 0.2);
    border-radius: 999px;
    padding: 4px 10px;
    font-size: 0.74rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    backdrop-filter: blur(3px);
}

@media (max-width: 1100px) {
    .stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .panel-grid { grid-template-columns: 1fr; }
}

@media (max-width: 900px) {
    .shell { flex-direction: column; }
    .sidebar {
        width: 100%;
        min-height: auto;
    }
    .nav-link.logout { margin-top: 8px; }
}

@media (max-width: 620px) {
    .stat-grid { grid-template-columns: 1fr; }
    .quick-links { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<section class="top">
    <div class="container">
        <div class="shell">
            <aside class="sidebar">
                <div class="brand">
                    <h1>Landlord Panel</h1>
                    <p><?php echo htmlspecialchars($_SESSION['name']); ?></p>
                </div>

                <p class="side-section-title">Property Management</p>
                <nav class="side-nav">
                    <a href="my_properties.php" class="nav-link <?php echo $current_page === 'my_properties.php' ? 'active' : ''; ?>">
                        <span class="nav-left"><i class="fas fa-house"></i>My Properties</span>
                        <span class="nav-count"><?php echo array_sum($counts); ?></span>
                    </a>
                    <a href="add_property.php" class="nav-link <?php echo $current_page === 'add_property.php' ? 'active' : ''; ?>">
                        <span class="nav-left"><i class="fas fa-plus-circle"></i>Upload Property</span>
                    </a>
                    <a href="approved_properties.php" class="nav-link <?php echo $current_page === 'approved_properties.php' ? 'active' : ''; ?>">
                        <span class="nav-left"><i class="fas fa-circle-check"></i>Approved</span>
                        <?php if ($counts['approved'] > 0): ?><span class="nav-count"><?php echo (int) $counts['approved']; ?></span><?php endif; ?>
                    </a>
                    <a href="pending_properties.php" class="nav-link <?php echo $current_page === 'pending_properties.php' ? 'active' : ''; ?>">
                        <span class="nav-left"><i class="fas fa-hourglass-half"></i>Pending</span>
                        <?php if ($counts['pending'] > 0): ?><span class="nav-count"><?php echo (int) $counts['pending']; ?></span><?php endif; ?>
                    </a>
                    <a href="taken_properties.php" class="nav-link <?php echo $current_page === 'taken_properties.php' ? 'active' : ''; ?>">
                        <span class="nav-left"><i class="fas fa-circle-xmark"></i>Taken</span>
                        <?php if ($counts['taken'] > 0): ?><span class="nav-count"><?php echo (int) $counts['taken']; ?></span><?php endif; ?>
                    </a>
                </nav>

                <p class="side-section-title">Operations</p>
                <nav class="side-nav">
                    <a href="rental_requests.php" class="nav-link <?php echo $current_page === 'rental_requests.php' ? 'active' : ''; ?>">
                        <span class="nav-left"><i class="fas fa-envelope-open-text"></i>Rental Requests</span>
                        <?php if ($pending_requests > 0): ?><span class="nav-count"><?php echo (int) $pending_requests; ?></span><?php endif; ?>
                    </a>
                    <a href="manage_bookings.php" class="nav-link <?php echo $current_page === 'manage_bookings.php' ? 'active' : ''; ?>">
                        <span class="nav-left"><i class="fas fa-calendar-check"></i>Bookings</span>
                        <?php if ($booked_count > 0): ?><span class="nav-count"><?php echo (int) $booked_count; ?></span><?php endif; ?>
                    </a>
                    <a href="payment_approval.php" class="nav-link <?php echo $current_page === 'payment_approval.php' ? 'active' : ''; ?>">
                        <span class="nav-left"><i class="fas fa-money-bill-check"></i>Payments</span>
                        <?php if ($pending_payments > 0): ?><span class="nav-count"><?php echo (int) $pending_payments; ?></span><?php endif; ?>
                    </a>
                    <a href="landlord_income.php" class="nav-link <?php echo $current_page === 'landlord_income.php' ? 'active' : ''; ?>">
                        <span class="nav-left"><i class="fas fa-chart-line"></i>Income</span>
                    </a>
                    <a href="chat.php" class="nav-link <?php echo $current_page === 'chat.php' ? 'active' : ''; ?>">
                        <span class="nav-left"><i class="fas fa-comments"></i>Chat</span>
                    </a>
                    <a href="settings.php" class="nav-link <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                        <span class="nav-left"><i class="fas fa-gear"></i>Settings</span>
                    </a>
                </nav>

                <a href="logout.php" class="nav-link logout">
                    <span class="nav-left"><i class="fas fa-right-from-bracket"></i>Logout</span>
                </a>
            </aside>

            <main class="content">
                <div class="head">
                    <h2>Dashboard Overview</h2>
                    <p>Manage your listings, rental requests, tenant communication, and payment operations from one professional workspace.</p>
                </div>

                <?php if ($message): ?>
                    <p class="message"><?php echo htmlspecialchars($message); ?></p>
                <?php endif; ?>

                <section class="stat-grid">
                    <article class="stat-card">
                        <p class="kicker">Total Properties</p>
                        <p class="value"><?php echo (int) array_sum($counts); ?></p>
                    </article>
                    <article class="stat-card">
                        <p class="kicker">Booked Units</p>
                        <p class="value"><?php echo (int) $booked_count; ?></p>
                    </article>
                    <article class="stat-card">
                        <p class="kicker">Pending Requests</p>
                        <p class="value"><?php echo (int) $pending_requests; ?></p>
                    </article>
                    <article class="stat-card">
                        <p class="kicker">Pending Payment Value</p>
                        <p class="value">$<?php echo number_format($pending_income, 2); ?></p>
                    </article>
                </section>

                <section class="panel-grid">
                    <article class="panel">
                        <h3>Quick Actions</h3>
                        <p>Use these shortcuts to keep listings current and respond quickly to tenants.</p>
                        <div class="quick-links">
                            <a href="add_property.php"><i class="fas fa-plus-circle"></i>Add New Property</a>
                            <a href="rental_requests.php"><i class="fas fa-envelope-open-text"></i>Review Requests</a>
                            <a href="manage_bookings.php"><i class="fas fa-calendar-check"></i>Manage Bookings</a>
                            <a href="payment_approval.php"><i class="fas fa-money-bill-check"></i>Verify Payments</a>
                        </div>
                    </article>
                    <article class="panel">
                        <h3>Portfolio Health</h3>
                        <ul class="health-list">
                            <li><strong>Approved Listings</strong><span><?php echo (int) $counts['approved']; ?> active</span></li>
                            <li><strong>Pending Listings</strong><span><?php echo (int) $counts['pending']; ?> awaiting review</span></li>
                            <li><strong>Taken Listings</strong><span><?php echo (int) $counts['taken']; ?> currently occupied</span></li>
                            <li><strong>Pending Payments</strong><span><?php echo (int) $pending_payments; ?> to verify</span></li>
                        </ul>
                    </article>
                </section>

                <p class="watermark">developed by Tec Liberia CEO</p>
            </main>
        </div>
    </div>
</section>

<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js')
            .then((registration) => console.log('[PWA] Service Worker registered'))
            .catch((error) => console.warn('[PWA] Service Worker registration failed:', error));
    });
}
</script>
</body>
</html>
