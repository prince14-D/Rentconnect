<?php
session_start();
include "app_init.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'renter') {
    header("Location: login.php");
    exit;
}

$renter_id = (int) $_SESSION['user_id'];
$message = '';
$message_type = 'success';

if (isset($_GET['cancel_request'])) {
    $request_id = (int) $_GET['cancel_request'];
    if (rc_mig_cancel_pending_request($conn, $request_id, $renter_id)) {
        $message = 'Request canceled successfully.';
        $message_type = 'success';
    } else {
        $message = 'Could not cancel this request. It may already be processed.';
        $message_type = 'error';
    }
}

$requests = rc_mig_get_renter_requests($conn, $renter_id);

$counts = [
    'all' => count($requests),
    'pending' => 0,
    'approved' => 0,
    'declined' => 0,
];

foreach ($requests as $row) {
    $status = strtolower((string) ($row['status'] ?? 'pending'));
    if ($status === 'pending') {
        $counts['pending']++;
    } elseif ($status === 'approved') {
        $counts['approved']++;
    } else {
        $counts['declined']++;
    }
}

$filter = strtolower(trim((string) ($_GET['status'] ?? 'all')));
if (!in_array($filter, ['all', 'pending', 'approved', 'declined'], true)) {
    $filter = 'all';
}

$filtered_requests = [];
foreach ($requests as $row) {
    $status = strtolower((string) ($row['status'] ?? 'pending'));
    $normalized = in_array($status, ['pending', 'approved'], true) ? $status : 'declined';
    if ($filter !== 'all' && $normalized !== $filter) {
        continue;
    }
    $row['_normalized_status'] = $normalized;
    $filtered_requests[] = $row;
}

$current_page = basename((string) ($_SERVER['PHP_SELF'] ?? 'my_requests.php'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Track and manage your rental requests.">
<title>My Requests - RentConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --bg: #edf3f8;
    --panel: #ffffff;
    --ink: #1f2836;
    --muted: #627089;
    --brand: #1f8f67;
    --accent: #ff7a2f;
    --error: #d9534f;
    --border: #d6e2ea;
    --nav-bg: linear-gradient(180deg, rgba(19, 64, 78, 0.96), rgba(16, 50, 63, 0.96));
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    background: radial-gradient(circle at top right, #f7fbff 0%, #edf3f8 42%, #e6eef5 100%);
    color: var(--ink);
    font-family: 'Manrope', sans-serif;
}

.wrap {
    max-width: 1220px;
    margin: 0 auto;
    padding: 20px 14px 34px;
}

.hero {
    background: linear-gradient(120deg, #123f50 0%, #176480 64%, #1f8f67 100%);
    color: #fff;
    border-radius: 14px;
    padding: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    box-shadow: 0 10px 24px rgba(13, 31, 43, 0.2);
}

.hero h1 {
    margin: 0;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1.35rem;
}

.hero p {
    margin: 5px 0 0;
    opacity: 0.92;
    font-size: 0.92rem;
}

.back-home {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    text-decoration: none;
    color: #133744;
    background: #fff;
    border-radius: 10px;
    padding: 9px 12px;
    font-size: 0.86rem;
    font-weight: 800;
}

.layout {
    margin-top: 14px;
    display: flex;
    gap: 14px;
    align-items: flex-start;
}

.side {
    width: 250px;
    flex-shrink: 0;
    background: var(--nav-bg);
    border-radius: 14px;
    padding: 12px;
    box-shadow: 0 10px 24px rgba(15, 31, 40, 0.14);
    position: sticky;
    top: 14px;
}

.side h3 {
    margin: 4px 4px 10px;
    color: #fff;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1rem;
}

.menu {
    display: grid;
    gap: 7px;
}

.menu a {
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

.menu a:hover,
.menu a.active {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(255, 255, 255, 0.34);
    transform: translateX(2px);
}

.main {
    flex: 1;
    min-width: 0;
}

.notice {
    margin: 0 0 12px;
    border-radius: 11px;
    border: 1px solid rgba(31, 143, 103, 0.22);
    background: rgba(31, 143, 103, 0.09);
    color: #1f8f67;
    padding: 9px 11px;
    font-size: 0.88rem;
    font-weight: 700;
}

.notice.error {
    border-color: rgba(217, 83, 79, 0.26);
    background: rgba(217, 83, 79, 0.1);
    color: #a83430;
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
}

.stat {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 11px;
}

.stat .label {
    color: var(--muted);
    font-size: 0.77rem;
    font-weight: 700;
}

.stat .value {
    margin-top: 4px;
    font-size: 1.2rem;
    font-weight: 800;
    color: var(--ink);
}

.panel {
    margin-top: 12px;
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 13px;
    padding: 13px;
    box-shadow: 0 6px 20px rgba(28, 50, 76, 0.05);
}

.panel-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.panel-head h2 {
    margin: 0;
    font-size: 1.06rem;
    font-family: 'Plus Jakarta Sans', sans-serif;
}

.filters {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.filters a {
    text-decoration: none;
    color: #234b66;
    border: 1px solid #cbdbe6;
    border-radius: 999px;
    padding: 6px 10px;
    font-size: 0.8rem;
    font-weight: 800;
}

.filters a.active {
    background: #1f8f67;
    color: #fff;
    border-color: #1f8f67;
}

.request-list {
    margin-top: 12px;
    display: grid;
    gap: 10px;
}

.request-card {
    border: 1px solid #d8e4ed;
    border-radius: 11px;
    padding: 11px;
    background: #fdfefe;
}

.request-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.request-card h3 {
    margin: 0;
    font-size: 0.99rem;
    color: #1f2a3b;
}

.request-meta {
    margin-top: 7px;
    color: #5f6f87;
    font-size: 0.84rem;
    display: grid;
    gap: 4px;
}

.status {
    display: inline-block;
    border-radius: 999px;
    padding: 5px 10px;
    font-size: 0.73rem;
    font-weight: 800;
    color: #fff;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.status.pending {
    background: #f0a13f;
}

.status.approved {
    background: #1f8f67;
}

.status.declined {
    background: #7d8ba0;
}

.actions {
    margin-top: 9px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    border: none;
    border-radius: 8px;
    padding: 8px 10px;
    font-size: 0.79rem;
    font-weight: 800;
    cursor: pointer;
}

.btn.chat {
    background: #1764d2;
    color: #fff;
}

.btn.cancel {
    background: #fff2f1;
    color: #a93f3d;
    border: 1px solid #f2c2be;
}

.btn.view {
    background: #1f8f67;
    color: #fff;
}

.empty {
    color: #5f6f87;
    font-size: 0.9rem;
    margin-top: 12px;
}

@media (max-width: 980px) {
    .layout {
        flex-direction: column;
    }

    .side {
        width: 100%;
        position: static;
    }

    .menu {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 560px) {
    .hero {
        flex-direction: column;
        align-items: flex-start;
    }

    .menu,
    .stats {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>
<div class="wrap">
    <header class="hero">
        <div>
            <h1>My Rental Requests</h1>
            <p>Track request progress and quickly contact landlords for approved requests.</p>
        </div>
        <a class="back-home" href="renter_dashboard.php"><i class="fa-solid fa-arrow-left"></i>Dashboard</a>
    </header>

    <div class="layout">
        <aside class="side">
            <h3>Renter Services</h3>
            <nav class="menu">
                <a href="renter_profile.php" class="<?php echo $current_page === 'renter_profile.php' ? 'active' : ''; ?>"><i class="fa-solid fa-user"></i>Profile</a>
                <a href="browse_properties.php" class="<?php echo $current_page === 'browse_properties.php' ? 'active' : ''; ?>"><i class="fa-solid fa-house"></i>Browse Properties</a>
                <a href="my_requests.php" class="<?php echo $current_page === 'my_requests.php' ? 'active' : ''; ?>"><i class="fa-solid fa-envelope-open-text"></i>My Requests</a>
                <a href="active_bookings.php" class="<?php echo $current_page === 'active_bookings.php' ? 'active' : ''; ?>"><i class="fa-solid fa-calendar-check"></i>Active Bookings</a>
                <a href="rent_reminders.php" class="<?php echo $current_page === 'rent_reminders.php' ? 'active' : ''; ?>"><i class="fa-solid fa-bell"></i>Reminders</a>
                <a href="rent_payments.php" class="<?php echo $current_page === 'rent_payments.php' ? 'active' : ''; ?>"><i class="fa-solid fa-credit-card"></i>Payments</a>
                <a href="chat_list.php" class="<?php echo in_array($current_page, ['chat.php', 'chat_list.php'], true) ? 'active' : ''; ?>"><i class="fa-solid fa-comments"></i>Chat</a>
                <a href="index.php"><i class="fa-solid fa-house-circle-check"></i>Home</a>
            </nav>
        </aside>

        <main class="main">
            <?php if ($message !== ''): ?>
                <p class="notice <?php echo $message_type === 'error' ? 'error' : ''; ?>"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>

            <section class="stats">
                <article class="stat">
                    <div class="label">All Requests</div>
                    <div class="value"><?php echo (int) $counts['all']; ?></div>
                </article>
                <article class="stat">
                    <div class="label">Pending</div>
                    <div class="value"><?php echo (int) $counts['pending']; ?></div>
                </article>
                <article class="stat">
                    <div class="label">Approved</div>
                    <div class="value"><?php echo (int) $counts['approved']; ?></div>
                </article>
                <article class="stat">
                    <div class="label">Declined</div>
                    <div class="value"><?php echo (int) $counts['declined']; ?></div>
                </article>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <h2>Request History</h2>
                    <div class="filters">
                        <a href="?status=all" class="<?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
                        <a href="?status=pending" class="<?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                        <a href="?status=approved" class="<?php echo $filter === 'approved' ? 'active' : ''; ?>">Approved</a>
                        <a href="?status=declined" class="<?php echo $filter === 'declined' ? 'active' : ''; ?>">Declined</a>
                    </div>
                </div>

                <?php if (!empty($filtered_requests)): ?>
                    <div class="request-list">
                        <?php foreach ($filtered_requests as $row): ?>
                            <?php
                            $status = (string) ($row['_normalized_status'] ?? 'pending');
                            $createdAt = (string) ($row['created_at'] ?? '');
                            ?>
                            <article class="request-card">
                                <div class="request-top">
                                    <h3><?php echo htmlspecialchars((string) ($row['title'] ?? 'Property')); ?> - $<?php echo number_format((float) ($row['price'] ?? 0)); ?></h3>
                                    <span class="status <?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span>
                                </div>

                                <div class="request-meta">
                                    <div><strong>Location:</strong> <?php echo htmlspecialchars((string) ($row['location'] ?? 'Not set')); ?></div>
                                    <div><strong>Landlord:</strong> <?php echo htmlspecialchars((string) ($row['landlord_name'] ?? 'Unknown')); ?> (<?php echo htmlspecialchars((string) ($row['landlord_email'] ?? 'No email')); ?>)</div>
                                    <div><strong>Requested:</strong> <?php echo htmlspecialchars($createdAt); ?></div>
                                </div>

                                <div class="actions">
                                    <?php if ($status === 'approved'): ?>
                                        <a class="btn chat" href="chat.php?property_id=<?php echo (int) ($row['property_id'] ?? 0); ?>&with=<?php echo (int) ($row['landlord_id'] ?? 0); ?>"><i class="fa-solid fa-comments"></i>Chat Landlord</a>
                                    <?php endif; ?>

                                    <?php if ($status === 'pending'): ?>
                                        <a class="btn cancel" href="?cancel_request=<?php echo (int) ($row['request_id'] ?? 0); ?>" onclick="return confirm('Cancel this pending request?');"><i class="fa-solid fa-xmark"></i>Cancel Request</a>
                                    <?php endif; ?>

                                    <a class="btn view" href="property.php?id=<?php echo (int) ($row['property_id'] ?? 0); ?>"><i class="fa-solid fa-up-right-from-square"></i>View Property</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="empty">No requests found for this filter.</p>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>
</body>
</html>
