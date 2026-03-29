<?php
session_start();
include "app_init.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'renter') {
    header("Location: login.php");
    exit;
}

$renter_id = (int) $_SESSION['user_id'];

$active_bookings = rc_mig_get_renter_active_bookings($conn, $renter_id);
$requests = rc_mig_get_renter_requests($conn, $renter_id);
$unread_reminders = rc_mig_get_renter_unread_reminders_count($conn, $renter_id);
$pending_payments = rc_mig_get_renter_pending_payments_count($conn, $renter_id);
$available_properties = rc_mig_search_approved_properties($conn, '');

$available_count = 0;
foreach ($available_properties as $property) {
    $property_status = strtolower((string) ($property['status'] ?? 'approved'));
    $booking_status = strtolower((string) ($property['booking_status'] ?? 'available'));
    $is_unavailable = in_array($property_status, ['taken', 'booked', 'inactive', 'rejected'], true)
        || in_array($booking_status, ['taken', 'booked', 'maintenance'], true);
    if (!$is_unavailable) {
        $available_count++;
    }
}

$pending_requests = 0;
$approved_requests = 0;
foreach ($requests as $request) {
    $status = strtolower((string) ($request['status'] ?? 'pending'));
    if ($status === 'pending') {
        $pending_requests++;
    } elseif ($status === 'approved') {
        $approved_requests++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Renter service overview and quick actions.">
<meta name="theme-color" content="#1f8f67">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="manifest" href="/manifest.json">
<title>Renter Dashboard - RentConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --ink: #1f2836;
    --muted: #627089;
    --brand: #1f8f67;
    --line: #d8e4ed;
    --card: #ffffff;
}

* {
    box-sizing: border-box;
}

body {
    min-height: 100vh;
    margin: 0;
    font-family: 'Manrope', sans-serif;
    color: var(--ink);
    background: radial-gradient(circle at top right, #f7fbff 0%, #edf3f8 42%, #e6eef5 100%);
}

.container {
    width: min(1220px, 96vw);
    margin: 0 auto;
    padding: 20px 14px 34px;
}

.hero {
    background: linear-gradient(140deg, rgba(17, 59, 72, 0.94), rgba(31, 143, 103, 0.88));
    color: #fff;
    border-radius: 16px;
    padding: 16px;
    box-shadow: 0 10px 24px rgba(13, 31, 43, 0.2);
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.hero h1 {
    margin: 0 0 4px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: clamp(1.2rem, 2.8vw, 1.9rem);
}

.hero p {
    margin: 0;
    opacity: 0.93;
    font-size: 0.9rem;
}

.logout {
    text-decoration: none;
    color: #fff;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 9px;
    padding: 9px 12px;
    font-size: 0.85rem;
    font-weight: 700;
}

.dashboard-shell {
    margin-top: 14px;
    display: flex;
    gap: 14px;
    align-items: flex-start;
}

.side-services {
    width: 250px;
    flex-shrink: 0;
    background: linear-gradient(180deg, rgba(19, 64, 78, 0.96), rgba(16, 50, 63, 0.96));
    border-radius: 14px;
    padding: 12px;
    box-shadow: 0 10px 24px rgba(15, 31, 40, 0.14);
    position: sticky;
    top: 14px;
}

.side-services h3 {
    color: #fff;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1rem;
    margin: 4px 4px 10px;
}

.service-btns {
    display: grid;
    gap: 7px;
}

.service-btn {
    text-decoration: none;
    color: #eaf4f6;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 10px;
    padding: 9px 10px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.88rem;
    font-weight: 700;
    transition: all 0.2s ease;
}

.service-btn:hover,
.service-btn.active {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(255, 255, 255, 0.34);
    transform: translateX(2px);
}

.dashboard-main {
    flex: 1;
    min-width: 0;
}

.section {
    margin-top: 0;
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: 14px;
    box-shadow: 0 6px 20px rgba(28, 50, 76, 0.05);
    padding: 14px;
}

.section + .section {
    margin-top: 12px;
}

.section h2 {
    margin: 0 0 10px;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1.05rem;
}

.stat-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.stat {
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 11px;
    background: #fff;
}

.stat .label {
    color: var(--muted);
    font-size: 0.78rem;
    font-weight: 700;
}

.stat .value {
    margin-top: 4px;
    font-size: 1.25rem;
    font-weight: 800;
}

.quick-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 10px;
}

.quick-card {
    text-decoration: none;
    border-radius: 12px;
    border: 1px solid #dbe7ef;
    background: linear-gradient(150deg, #ffffff, #f6fbff);
    padding: 12px;
    color: var(--ink);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.quick-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 18px rgba(29, 58, 88, 0.12);
}

.quick-card .top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.88rem;
    font-weight: 800;
}

.quick-card .meta {
    margin-top: 6px;
    color: var(--muted);
    font-size: 0.82rem;
    font-weight: 600;
}

.activity-list {
    display: grid;
    gap: 8px;
}

.activity-item {
    border: 1px solid #dbe7ef;
    border-radius: 10px;
    padding: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.activity-item b {
    font-size: 0.9rem;
}

.activity-item span {
    color: var(--muted);
    font-size: 0.82rem;
}

@media (max-width: 980px) {
    .dashboard-shell {
        flex-direction: column;
    }

    .side-services {
        width: 100%;
        position: static;
    }

    .service-btns {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .stat-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 560px) {
    .service-btns {
        grid-template-columns: 1fr;
    }

    .hero {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
</head>
<body>
<div class="container">
    <div class="hero">
        <div>
            <h1>Welcome, <?php echo htmlspecialchars((string) ($_SESSION['name'] ?? 'Renter')); ?></h1>
            <p>Your renter overview with quick access to every service page.</p>
        </div>
        <a href="logout.php" class="logout">Logout</a>
    </div>

    <div class="dashboard-shell">
        <aside class="side-services">
            <h3>Renter Services</h3>
            <div class="service-btns">
                <a href="renter_dashboard.php" class="service-btn active">🏠 Dashboard Overview</a>
                <a href="renter_profile.php" class="service-btn">👤 Profile Page</a>
                <a href="browse_properties.php" class="service-btn">🏡 Browse Properties Page</a>
                <a href="my_requests.php" class="service-btn">📨 My Requests Page</a>
                <a href="active_bookings.php" class="service-btn">📋 Active Bookings Page</a>
                <a href="rent_reminders.php" class="service-btn">🔔 Reminders Page</a>
                <a href="rent_payments.php" class="service-btn">💳 Payments Page</a>
                <a href="chat_list.php" class="service-btn">💬 Chat Page</a>
                <a href="index.php" class="service-btn">↩ Back to Home</a>
            </div>
        </aside>

        <main class="dashboard-main">
            <section class="section">
                <h2>Overview Stats</h2>
                <div class="stat-grid">
                    <article class="stat">
                        <div class="label">Available Properties</div>
                        <div class="value"><?php echo (int) $available_count; ?></div>
                    </article>
                    <article class="stat">
                        <div class="label">Active Bookings</div>
                        <div class="value"><?php echo count($active_bookings); ?></div>
                    </article>
                    <article class="stat">
                        <div class="label">My Requests</div>
                        <div class="value"><?php echo count($requests); ?></div>
                    </article>
                    <article class="stat">
                        <div class="label">Pending Requests</div>
                        <div class="value"><?php echo (int) $pending_requests; ?></div>
                    </article>
                    <article class="stat">
                        <div class="label">Approved Requests</div>
                        <div class="value"><?php echo (int) $approved_requests; ?></div>
                    </article>
                    <article class="stat">
                        <div class="label">Pending Payments</div>
                        <div class="value"><?php echo (int) $pending_payments; ?></div>
                    </article>
                </div>
            </section>

            <section class="section">
                <h2>Quick Actions</h2>
                <div class="quick-grid">
                    <a class="quick-card" href="browse_properties.php">
                        <div class="top"><span>Browse Properties</span><i class="fa-solid fa-arrow-up-right-from-square"></i></div>
                        <div class="meta">Find available homes and submit requests.</div>
                    </a>
                    <a class="quick-card" href="my_requests.php">
                        <div class="top"><span>Track Requests</span><i class="fa-solid fa-arrow-up-right-from-square"></i></div>
                        <div class="meta">See pending and approved request updates.</div>
                    </a>
                    <a class="quick-card" href="active_bookings.php">
                        <div class="top"><span>Manage Bookings</span><i class="fa-solid fa-arrow-up-right-from-square"></i></div>
                        <div class="meta">Review active contracts and move dates.</div>
                    </a>
                    <a class="quick-card" href="rent_payments.php">
                        <div class="top"><span>Pay Rent</span><i class="fa-solid fa-arrow-up-right-from-square"></i></div>
                        <div class="meta">Submit payment and track approvals.</div>
                    </a>
                    <a class="quick-card" href="rent_reminders.php">
                        <div class="top"><span>View Reminders</span><i class="fa-solid fa-arrow-up-right-from-square"></i></div>
                        <div class="meta"><?php echo $unread_reminders > 0 ? (int) $unread_reminders . ' unread reminder(s).' : 'No unread reminders.'; ?></div>
                    </a>
                    <a class="quick-card" href="renter_profile.php">
                        <div class="top"><span>Edit Profile</span><i class="fa-solid fa-arrow-up-right-from-square"></i></div>
                        <div class="meta">Update your contact and profile photo.</div>
                    </a>
                </div>
            </section>

            <section class="section">
                <h2>Activity Highlights</h2>
                <div class="activity-list">
                    <div class="activity-item">
                        <b>Unread Reminders</b>
                        <span><?php echo $unread_reminders > 0 ? (int) $unread_reminders . ' action needed' : 'All clear'; ?></span>
                    </div>
                    <div class="activity-item">
                        <b>Request Progress</b>
                        <span><?php echo (int) $approved_requests; ?> approved, <?php echo (int) $pending_requests; ?> pending</span>
                    </div>
                    <div class="activity-item">
                        <b>Booking Status</b>
                        <span><?php echo count($active_bookings) > 0 ? count($active_bookings) . ' active booking(s)' : 'No active bookings'; ?></span>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js')
            .then(() => console.log('[PWA] Service Worker registered'))
            .catch((error) => console.warn('[PWA] Service Worker registration failed:', error));
    });
}
</script>
</body>
</html>
