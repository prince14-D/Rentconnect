<?php
session_start();
include "db.php";

// Ensure landlord access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// Fetch property counts
$counts = ['pending' => 0, 'approved' => 0, 'taken' => 0];
$stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM properties WHERE landlord_id=? GROUP BY status");
$stmt->bind_param("i", $landlord_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $counts[$row['status']] = $row['count'];
}
$stmt->close();

// Fetch pending rental requests count
$stmt2 = $conn->prepare("SELECT COUNT(*) FROM rental_requests WHERE property_id IN (SELECT id FROM properties WHERE landlord_id=?) AND status='pending'");
$stmt2->bind_param("i", $landlord_id);
$stmt2->execute();
$stmt2->bind_result($pending_requests);
$stmt2->fetch();
$stmt2->close();

// Fetch booking stats
$stmt3 = $conn->prepare("SELECT COUNT(*) as booked_count FROM bookings WHERE landlord_id=? AND status='active'");
$stmt3->bind_param("i", $landlord_id);
$stmt3->execute();
$stmt3->bind_result($booked_count);
$stmt3->fetch();
$stmt3->close();

// Fetch pending payment approvals
$stmt4 = $conn->prepare("SELECT COUNT(*) as pending_payments FROM payments WHERE landlord_id=? AND status='pending'");
$stmt4->bind_param("i", $landlord_id);
$stmt4->execute();
$stmt4->bind_result($pending_payments);
$stmt4->fetch();
$stmt4->close();

// Fetch pending income
$stmt5 = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as pending_income FROM payments WHERE landlord_id=? AND status='pending'");
$stmt5->bind_param("i", $landlord_id);
$stmt5->execute();
$stmt5->bind_result($pending_income);
$stmt5->fetch();
$stmt5->close();
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
    --card: rgba(255, 255, 255, 0.92);
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
    width: min(1120px, 94vw);
    margin: 0 auto;
}

.top {
    padding: 30px 0 14px;
}

.head {
    background: linear-gradient(140deg, rgba(17, 59, 72, 0.94), rgba(31, 143, 103, 0.88));
    color: #fff;
    border-radius: 22px;
    padding: clamp(18px, 4vw, 28px);
    box-shadow: var(--shadow);
    display: flex;
    justify-content: space-between;
    gap: 18px;
    align-items: center;
    flex-wrap: wrap;
}

.head h1 {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: clamp(1.35rem, 3vw, 2rem);
    letter-spacing: -0.02em;
    margin-bottom: 4px;
}

.head p {
    color: rgba(255, 255, 255, 0.9);
}

.quick-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.quick-stats span {
    display: inline-block;
    padding: 8px 11px;
    border-radius: 999px;
    font-size: 0.87rem;
    background: rgba(255, 255, 255, 0.14);
}

.message {
    margin-top: 12px;
    padding: 11px 13px;
    border-radius: 12px;
    background: rgba(39, 165, 106, 0.12);
    border: 1px solid rgba(39, 165, 106, 0.35);
    color: #165a39;
    font-weight: 700;
}

.grid {
    margin-top: 18px;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 14px;
}

.card-btn {
    position: relative;
    text-decoration: none;
    color: #273149;
    background: var(--card);
    border: 1px solid rgba(255, 255, 255, 0.92);
    border-radius: 16px;
    box-shadow: 0 10px 24px rgba(15, 31, 40, 0.09);
    padding: 16px;
    min-height: 125px;
    display: grid;
    align-content: center;
    justify-items: center;
    gap: 9px;
    text-align: center;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 16px 26px rgba(15, 31, 40, 0.14);
}

.card-btn i {
    font-size: 1.8rem;
    color: #15543e;
}

.card-btn .label {
    font-weight: 700;
}

.badge {
    position: absolute;
    top: 10px;
    right: 10px;
    min-width: 24px;
    height: 24px;
    border-radius: 999px;
    display: grid;
    place-items: center;
    font-size: 0.75rem;
    font-weight: 800;
    color: #fff;
    background: linear-gradient(135deg, var(--accent), #f15f13);
}

.card-btn.logout {
    background: linear-gradient(140deg, #ef554d, #d53d36);
    color: #fff;
}

.card-btn.logout i {
    color: #fff;
}

@media (max-width: 600px) {
    .grid { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 430px) {
    .grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<section class="top">
    <div class="container">
        <div class="head">
            <div>
                <h1>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
                <p>Manage your listings, rental requests, and tenant communication in one place.</p>
            </div>
            <div class="quick-stats">
                <span>Total Properties: <?php echo array_sum($counts); ?></span>
                <span>Booked Units: <?php echo (int) $booked_count; ?></span>
                <span>Pending Payments: $<?php echo number_format($pending_income, 2); ?></span>
                <span>Requests: <?php echo (int) $pending_requests; ?></span>
            </div>
        </div>

        <?php if ($message): ?>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <div class="grid">
            <a href="my_properties.php" class="card-btn">
                <i class="fas fa-house"></i>
                <span class="label">My Properties</span>
                <span class="badge"><?php echo array_sum($counts); ?></span>
            </a>

            <a href="rental_requests.php" class="card-btn">
                <i class="fas fa-envelope-open-text"></i>
                <span class="label">Rental Requests</span>
                <?php if ($pending_requests > 0): ?>
                    <span class="badge"><?php echo (int) $pending_requests; ?></span>
                <?php endif; ?>
            </a>

            <a href="add_property.php" class="card-btn">
                <i class="fas fa-plus-circle"></i>
                <span class="label">Upload Property</span>
            </a>

            <a href="approved_properties.php" class="card-btn">
                <i class="fas fa-circle-check"></i>
                <span class="label">Approved</span>
                <?php if ($counts['approved'] > 0): ?>
                    <span class="badge"><?php echo (int) $counts['approved']; ?></span>
                <?php endif; ?>
            </a>

            <a href="pending_properties.php" class="card-btn">
                <i class="fas fa-hourglass-half"></i>
                <span class="label">Pending</span>
                <?php if ($counts['pending'] > 0): ?>
                    <span class="badge"><?php echo (int) $counts['pending']; ?></span>
                <?php endif; ?>
            </a>

            <a href="taken_properties.php" class="card-btn">
                <i class="fas fa-circle-xmark"></i>
                <span class="label">Taken</span>
                <?php if ($counts['taken'] > 0): ?>
                    <span class="badge"><?php echo (int) $counts['taken']; ?></span>
                <?php endif; ?>
            </a>

            <a href="chat.php" class="card-btn">
                <i class="fas fa-comments"></i>
                <span class="label">Chat</span>
            </a>

            <a href="manage_bookings.php" class="card-btn">
                <i class="fas fa-calendar-check"></i>
                <span class="label">Bookings</span>
                <?php if ($booked_count > 0): ?>
                    <span class="badge"><?php echo (int) $booked_count; ?></span>
                <?php endif; ?>
            </a>

            <a href="payment_approval.php" class="card-btn">
                <i class="fas fa-money-bill-check"></i>
                <span class="label">Payments</span>
                <?php if ($pending_payments > 0): ?>
                    <span class="badge"><?php echo (int) $pending_payments; ?></span>
                <?php endif; ?>
            </a>

            <a href="landlord_income.php" class="card-btn">
                <i class="fas fa-chart-line"></i>
                <span class="label">Income</span>
            </a>

            <a href="settings.php" class="card-btn">
                <i class="fas fa-gear"></i>
                <span class="label">Settings</span>
            </a>

            <a href="logout.php" class="card-btn logout">
                <i class="fas fa-right-from-bracket"></i>
                <span class="label">Logout</span>
            </a>
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
