<?php
session_start();
include "app_init.php";

// Only admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Handle approve/decline property
if (isset($_GET['approve_property'])) {
    $id = intval($_GET['approve_property']);
    rc_mig_set_property_status($conn, $id, 'approved');
    header("Location: admin_dashboard.php");
    exit;
}

if (isset($_GET['decline_property'])) {
    $id = intval($_GET['decline_property']);
    rc_mig_set_property_status($conn, $id, 'declined');
    header("Location: admin_dashboard.php");
    exit;
}

$dashStats = rc_mig_get_admin_dashboard_stats($conn);
$total_users = (int) ($dashStats['total_users'] ?? 0);
$total_properties = (int) ($dashStats['total_properties'] ?? 0);
$total_requests = (int) ($dashStats['total_requests'] ?? 0);
$pending_requests = (int) ($dashStats['pending_requests'] ?? 0);

$pending_properties = rc_mig_get_pending_properties_for_admin($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Admin dashboard for managing properties and users.">
<meta name="theme-color" content="#1f8f67">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="manifest" href="/manifest.json">
<title>Admin Dashboard - RentConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --ink: #1f2430;
    --muted: #5d6579;
    --brand: #1f8f67;
    --brand-deep: #15543e;
    --accent: #ff7a2f;
    --line: rgba(31, 36, 48, 0.12);
    --shadow: 0 18px 36px rgba(19, 36, 33, 0.14);
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'Manrope', sans-serif;
    color: var(--ink);
    background:
        radial-gradient(circle at 10% 4%, rgba(255, 122, 47, 0.22), transparent 34%),
        radial-gradient(circle at 92% 8%, rgba(31, 143, 103, 0.2), transparent 30%),
        linear-gradient(165deg, #f9f6ef 0%, #f2f7f8 58%, #fffdfa 100%);
    min-height: 100vh;
}

.container {
    width: min(1180px, 95vw);
    margin: 0 auto;
}

.hero {
    margin-top: 22px;
    border-radius: 20px;
    color: #fff;
    padding: clamp(18px, 4vw, 28px);
    background: linear-gradient(140deg, rgba(16, 62, 79, 0.93), rgba(31, 143, 103, 0.86));
    box-shadow: var(--shadow);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
}

.hero h1 {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: clamp(1.45rem, 2.8vw, 2.05rem);
    letter-spacing: -0.02em;
    margin-bottom: 6px;
}

.hero p {
    color: rgba(255, 255, 255, 0.9);
}

.logout {
    text-decoration: none;
    color: #fff;
    font-weight: 700;
    border-radius: 9px;
    padding: 10px 13px;
    background: rgba(255, 255, 255, 0.2);
}

.nav-panel {
    margin-top: 12px;
    background: rgba(255, 255, 255, 0.93);
    border: 1px solid rgba(255, 255, 255, 0.9);
    border-radius: 14px;
    box-shadow: 0 10px 24px rgba(15, 31, 40, 0.09);
    padding: 10px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.nav-panel a {
    text-decoration: none;
    color: #33445f;
    font-weight: 700;
    padding: 8px 12px;
    border-radius: 9px;
}

.nav-panel a:hover {
    background: rgba(31, 143, 103, 0.08);
    color: var(--brand-deep);
}

.stats {
    margin-top: 12px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 10px;
}

.stat {
    background: rgba(255, 255, 255, 0.93);
    border: 1px solid rgba(255, 255, 255, 0.9);
    border-radius: 14px;
    box-shadow: 0 10px 24px rgba(15, 31, 40, 0.09);
    padding: 14px;
}

.stat h2 {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1.9rem;
    color: #12384b;
    margin-bottom: 2px;
}

.stat p {
    color: var(--muted);
    font-weight: 600;
    font-size: 0.92rem;
}

.section {
    margin-top: 14px;
    background: rgba(255, 255, 255, 0.93);
    border: 1px solid rgba(255, 255, 255, 0.9);
    border-radius: 14px;
    box-shadow: 0 10px 24px rgba(15, 31, 40, 0.09);
    padding: 14px;
}

.section h3 {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1.15rem;
    margin-bottom: 10px;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 760px;
}

th,
td {
    border-bottom: 1px solid var(--line);
    padding: 10px;
    text-align: left;
    font-size: 0.9rem;
}

th {
    background: #f6f9fc;
    color: #415067;
}

.actions a {
    display: inline-block;
    text-decoration: none;
    color: #fff;
    font-size: 0.82rem;
    font-weight: 700;
    border-radius: 8px;
    padding: 7px 10px;
    margin-right: 4px;
}

.approve { background: linear-gradient(140deg, #1f8f67, #15543e); }
.decline { background: linear-gradient(140deg, #e5554f, #d43c35); }

.empty {
    color: var(--muted);
}
</style>
</head>
<body>
<main class="container">
    <section class="hero">
        <div>
            <h1>Admin Dashboard</h1>
            <p>Monitor platform activity and review pending property submissions.</p>
        </div>
        <a href="logout.php" class="logout">Logout</a>
    </section>

    <nav class="nav-panel">
        <a href="admin_dashboard.php">Dashboard</a>
        <a href="manage_users.php">Users</a>
        <a href="manage_properties.php">Properties</a>
        <a href="manage_requests.php">Requests</a>
        <a href="settings.php">Settings</a>
    </nav>

    <section class="stats">
        <article class="stat"><h2><?php echo (int) $total_users; ?></h2><p>Total Users</p></article>
        <article class="stat"><h2><?php echo (int) $total_properties; ?></h2><p>Total Properties</p></article>
        <article class="stat"><h2><?php echo (int) $total_requests; ?></h2><p>Total Requests</p></article>
        <article class="stat"><h2><?php echo (int) $pending_requests; ?></h2><p>Pending Requests</p></article>
    </section>

    <section class="section">
        <h3>Pending Properties</h3>
        <?php if (count($pending_properties) > 0): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Location</th>
                            <th>Price</th>
                            <th>Landlord</th>
                            <th>Uploaded At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_properties as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                <td><?php echo htmlspecialchars($row['location']); ?></td>
                                <td>$<?php echo number_format($row['price']); ?></td>
                                <td><?php echo htmlspecialchars($row['owner_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                <td class="actions">
                                    <a href="?approve_property=<?php echo (int) $row['id']; ?>" class="approve">Approve</a>
                                    <a href="?decline_property=<?php echo (int) $row['id']; ?>" class="decline">Decline</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="empty">No pending properties.</p>
        <?php endif; ?>
    </section>
</main>

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
