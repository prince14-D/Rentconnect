<?php
session_start();
include "app_init.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'renter') {
    header("Location: login.php");
    exit;
}

$renter_id = (int) $_SESSION['user_id'];
$current_page = basename((string) ($_SERVER['PHP_SELF'] ?? 'active_bookings.php'));
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'cancel_booking') {
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $reason = trim((string) ($_POST['cancellation_reason'] ?? ''));

    if ($bookingId <= 0) {
        $error = 'Invalid booking selected.';
    } elseif ($reason === '') {
        $error = 'Please provide a cancellation reason.';
    } else {
        $check = $conn->prepare(
            "SELECT b.property_id
             FROM bookings b
             WHERE b.id = ? AND b.renter_id = ? AND b.status = 'active'
             LIMIT 1"
        );
        if ($check) {
            $check->bind_param('ii', $bookingId, $renter_id);
            $check->execute();
            $row = $check->get_result()->fetch_assoc();
            $check->close();

            if ($row) {
                $propertyId = (int) ($row['property_id'] ?? 0);
                $conn->begin_transaction();
                try {
                    $upd = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
                    if (!$upd) {
                        throw new Exception('Unable to prepare booking update.');
                    }
                    $upd->bind_param('i', $bookingId);
                    if (!$upd->execute()) {
                        throw new Exception('Failed to cancel booking.');
                    }

                    $meta = $conn->prepare("UPDATE bookings SET cancelled_by = 'renter', cancellation_reason = ?, cancelled_at = NOW() WHERE id = ?");
                    if ($meta) {
                        $meta->bind_param('si', $reason, $bookingId);
                        $meta->execute();
                    }

                    $activeCheck = $conn->prepare("SELECT COUNT(*) AS c FROM bookings WHERE property_id = ? AND status = 'active' AND id <> ?");
                    $hasOtherActive = false;
                    if ($activeCheck) {
                        $activeCheck->bind_param('ii', $propertyId, $bookingId);
                        $activeCheck->execute();
                        $cRow = $activeCheck->get_result()->fetch_assoc();
                        $hasOtherActive = ((int) ($cRow['c'] ?? 0)) > 0;
                    }

                    if (!$hasOtherActive) {
                        rc_mig_set_property_booking_status($conn, $propertyId, 'available');
                    }

                    $conn->commit();
                    $message = 'Booking cancelled successfully.';
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = $e->getMessage();
                }
            } else {
                $error = 'Booking not found or cannot be cancelled.';
            }
        } else {
            $error = 'Unable to verify booking details.';
        }
    }
}

$active_bookings = rc_mig_get_renter_active_bookings($conn, $renter_id);
$active_bookings = is_array($active_bookings) ? $active_bookings : [];

$total_monthly_rent = 0.0;
$next_move_out = null;

foreach ($active_bookings as $booking) {
    $total_monthly_rent += (float) ($booking['monthly_rent'] ?? 0);
    $move_out = (string) ($booking['move_out_date'] ?? '');
    if ($move_out !== '') {
        $ts = strtotime($move_out);
        if ($ts !== false && ($next_move_out === null || $ts < $next_move_out)) {
            $next_move_out = $ts;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="View and manage your active bookings.">
<title>Active Bookings - RentConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --panel: #ffffff;
    --ink: #1f2836;
    --muted: #627089;
    --brand: #1f8f67;
    --line: #d6e2ea;
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
    background: linear-gradient(180deg, rgba(19, 64, 78, 0.96), rgba(16, 50, 63, 0.96));
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

.stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.stat {
    background: var(--panel);
    border: 1px solid var(--line);
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
    border: 1px solid var(--line);
    border-radius: 13px;
    padding: 13px;
    box-shadow: 0 6px 20px rgba(28, 50, 76, 0.05);
}

.panel h2 {
    margin: 0 0 10px;
    font-size: 1.05rem;
    font-family: 'Plus Jakarta Sans', sans-serif;
}

.booking-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 12px;
}

.booking-card {
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 14px;
    border-left: 4px solid var(--brand);
    background: linear-gradient(135deg, #f6f9fc 0%, #f9f6ef 100%);
}

.booking-card h3 {
    margin: 0 0 8px;
    font-size: 1.03rem;
}

.booking-meta {
    margin: 4px 0;
    color: var(--muted);
    font-size: 0.9rem;
}

.booking-rent {
    color: var(--ink);
    font-weight: 800;
    margin: 8px 0;
    font-size: 1.06rem;
}

.booking-dates {
    color: var(--muted);
    font-size: 0.83rem;
    margin-top: 7px;
}

.actions {
    margin-top: 10px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn {
    flex: 1;
    min-width: 120px;
    text-align: center;
    text-decoration: none;
    color: #fff;
    padding: 10px;
    border-radius: 8px;
    font-weight: 800;
    font-size: 0.82rem;
}

.btn.pay {
    background: var(--brand);
}

.btn.manage {
    background: #2276d2;
}

.empty {
    color: var(--muted);
    font-size: 0.92rem;
}

.alert {
    margin-top: 12px;
    padding: 10px 12px;
    border-radius: 9px;
    font-size: 0.9rem;
    font-weight: 700;
}

.alert.success {
    background: #d8f3e6;
    border: 1px solid #8ed1b2;
    color: #0b5d3c;
}

.alert.error {
    background: #fde2e2;
    border: 1px solid #f2a6a6;
    color: #842029;
}

.btn.cancel {
    background: #c0392b;
    border: none;
    cursor: pointer;
}

.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
    place-items: center;
    padding: 16px;
}

.modal.show {
    display: grid;
}

.modal-card {
    width: min(520px, 100%);
    background: #fff;
    border-radius: 12px;
    border: 1px solid var(--line);
    padding: 14px;
}

.modal-card h3 {
    margin: 0 0 8px;
}

.modal-card p {
    margin: 0 0 10px;
    color: var(--muted);
    font-size: 0.9rem;
}

.modal-card textarea {
    width: 100%;
    min-height: 90px;
    border: 1px solid var(--line);
    border-radius: 9px;
    padding: 10px;
    font-family: inherit;
    font-size: 0.92rem;
    resize: vertical;
}

.modal-actions {
    margin-top: 10px;
    display: flex;
    gap: 8px;
}

.modal-actions button {
    flex: 1;
    border: none;
    border-radius: 8px;
    font-weight: 800;
    cursor: pointer;
    padding: 10px;
}

.modal-actions .ghost {
    background: #e6edf3;
    color: #223242;
}

.modal-actions .danger {
    background: #c0392b;
    color: #fff;
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
        grid-template-columns: 1fr;
    }
}

@media (max-width: 560px) {
    .hero {
        flex-direction: column;
        align-items: flex-start;
    }

    .menu {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>
<div class="wrap">
    <header class="hero">
        <div>
            <h1>Active Bookings</h1>
            <p>See all your ongoing rental bookings and manage payments quickly.</p>
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
                <div class="alert success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <section class="stats">
                <article class="stat">
                    <div class="label">Active Bookings</div>
                    <div class="value"><?php echo count($active_bookings); ?></div>
                </article>
                <article class="stat">
                    <div class="label">Total Monthly Rent</div>
                    <div class="value">$<?php echo number_format($total_monthly_rent, 2); ?></div>
                </article>
                <article class="stat">
                    <div class="label">Next Move-Out</div>
                    <div class="value"><?php echo $next_move_out ? date('M d, Y', $next_move_out) : 'N/A'; ?></div>
                </article>
            </section>

            <section class="panel">
                <h2>Booking List</h2>
                <?php if (!empty($active_bookings)): ?>
                    <div class="booking-grid">
                        <?php foreach ($active_bookings as $booking): ?>
                            <article class="booking-card">
                                <h3><?php echo htmlspecialchars((string) ($booking['title'] ?? 'Property')); ?></h3>
                                <p class="booking-meta">📍 <?php echo htmlspecialchars((string) ($booking['location'] ?? '')); ?></p>
                                <p class="booking-meta">👤 <?php echo htmlspecialchars((string) ($booking['landlord_name'] ?? '')); ?></p>
                                <p class="booking-rent">💰 $<?php echo number_format((float) ($booking['monthly_rent'] ?? 0), 2); ?>/month</p>
                                <p class="booking-dates">
                                    <strong>Move-in:</strong> <?php echo date('M d, Y', strtotime((string) $booking['move_in_date'])); ?><br>
                                    <strong>Move-out:</strong> <?php echo date('M d, Y', strtotime((string) $booking['move_out_date'])); ?>
                                </p>
                                <div class="actions">
                                    <a href="rent_payments.php" class="btn pay">💳 Pay Rent</a>
                                    <a href="manage_bookings.php" class="btn manage">📋 Manage</a>
                                    <button type="button" class="btn cancel" onclick="openCancelModal(<?php echo (int) ($booking['id'] ?? 0); ?>)">❌ Cancel</button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="empty">You do not have active bookings yet. Browse properties and send requests to get started.</p>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<div class="modal" id="cancelModal">
    <div class="modal-card">
        <h3>Cancel Booking</h3>
        <p>Cancel this booking and return the property to available status.</p>
        <form method="POST">
            <input type="hidden" name="action" value="cancel_booking">
            <input type="hidden" id="cancelBookingId" name="booking_id" value="">
            <textarea name="cancellation_reason" id="cancellationReason" placeholder="Reason for cancellation" required></textarea>
            <div class="modal-actions">
                <button type="button" class="ghost" onclick="closeCancelModal()">Keep Booking</button>
                <button type="submit" class="danger">Confirm Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCancelModal(bookingId) {
    document.getElementById('cancelBookingId').value = bookingId;
    document.getElementById('cancellationReason').value = '';
    document.getElementById('cancelModal').classList.add('show');
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.remove('show');
}

document.addEventListener('click', function (event) {
    if (event.target && event.target.id === 'cancelModal') {
        closeCancelModal();
    }
});
</script>
</body>
</html>
