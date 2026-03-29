<?php
session_start();
include 'app_init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'renter') {
    header('Location: login.php');
    exit;
}

$renter_id = (int) $_SESSION['user_id'];
$message = '';
$error = '';
$current_page = basename((string) ($_SERVER['PHP_SELF'] ?? 'rent_payments.php'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = (int) ($_POST['booking_id'] ?? 0);
    $amount = (float) ($_POST['amount'] ?? 0);
    $payment_method = trim((string) ($_POST['payment_method'] ?? ''));
    $payment_month = trim((string) ($_POST['payment_month'] ?? ''));
    $reference = trim((string) ($_POST['reference_number'] ?? ''));

    if ($booking_id <= 0 || $amount <= 0 || $payment_method === '' || $payment_month === '') {
        $error = 'All fields are required.';
    } elseif (rc_mig_payment_exists_for_month($conn, $booking_id, $payment_month)) {
        $error = 'Payment already submitted for this month.';
    } else {
        $newPaymentId = rc_mig_create_payment_from_booking($conn, $booking_id, $renter_id, $amount, $payment_month, $payment_method, 'pending');
        if ($newPaymentId <= 0) {
            $error = 'Failed to submit payment.';
        } else {
            if ($reference !== '') {
                rc_mig_update_payment_status_reference($conn, $newPaymentId, 'pending', $reference);
            }
            $message = 'Payment submitted. Waiting for landlord approval.';
        }
    }
}

$bookings = rc_mig_get_active_bookings_with_stats($conn, $renter_id);
$payment_history = rc_mig_get_payment_history($conn, $renter_id, 20);
$bookings = is_array($bookings) ? $bookings : [];
$payment_history = is_array($payment_history) ? $payment_history : [];

$total_monthly_due = 0.0;
foreach ($bookings as $booking) {
    $total_monthly_due += (float) ($booking['monthly_rent'] ?? 0);
}

$pending_count = 0;
$approved_count = 0;
foreach ($payment_history as $payment) {
    $status = strtolower((string) ($payment['status'] ?? 'pending'));
    if ($status === 'approved' || $status === 'paid') {
        $approved_count++;
    } elseif ($status === 'pending') {
        $pending_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Submit rent and track payment history.">
<title>Rent Payments - RentConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --ink: #1f2836;
    --muted: #627089;
    --brand: #1f8f67;
    --brand-dark: #14674a;
    --line: #d8e4ed;
    --panel: #ffffff;
    --warn: #ff9d3b;
    --ok: #2e9e68;
    --danger: #d9534f;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    color: var(--ink);
    font-family: 'Manrope', sans-serif;
    background: radial-gradient(circle at top right, #f7fbff 0%, #edf3f8 42%, #e6eef5 100%);
}

.wrap {
    width: min(1220px, 96vw);
    margin: 20px auto 30px;
}

.hero {
    background: linear-gradient(120deg, #123f50 0%, #176480 64%, #1f8f67 100%);
    color: #fff;
    border-radius: 14px;
    padding: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    box-shadow: 0 10px 24px rgba(13, 31, 43, 0.2);
}

.hero h1 {
    margin: 0;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1.3rem;
}

.hero p {
    margin: 4px 0 0;
    opacity: 0.92;
    font-size: 0.9rem;
}

.back-btn {
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
    font-size: 1rem;
    margin: 4px 4px 10px;
    font-family: 'Plus Jakarta Sans', sans-serif;
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

.main-content {
    flex: 1;
    min-width: 0;
}

.alert {
    padding: 10px;
    border-radius: 9px;
    margin-bottom: 12px;
    font-size: 0.88rem;
    font-weight: 700;
}

.alert.ok {
    background: rgba(31, 143, 103, 0.12);
    color: #13694a;
    border: 1px solid rgba(31, 143, 103, 0.28);
}

.alert.err {
    background: rgba(217, 83, 79, 0.12);
    color: #a73734;
    border: 1px solid rgba(217, 83, 79, 0.3);
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
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
    font-size: 1.18rem;
    font-weight: 800;
}

.card {
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 14px;
    margin-top: 12px;
    box-shadow: 0 6px 20px rgba(28, 50, 76, 0.05);
}

.card h2 {
    margin: 0 0 10px;
    font-size: 1.04rem;
    font-family: 'Plus Jakarta Sans', sans-serif;
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 11px;
}

.booking {
    border: 1px solid #dfe6ef;
    border-radius: 9px;
    padding: 11px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.booking:hover {
    border-color: #90b3cf;
    box-shadow: 0 4px 12px rgba(24, 60, 95, 0.09);
}

.booking strong {
    display: block;
    margin-bottom: 6px;
}

.small {
    color: #5d6579;
    font-size: 0.83rem;
}

form .row {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
}

label {
    display: block;
    font-size: 0.82rem;
    margin-bottom: 5px;
    color: #4f6078;
    font-weight: 700;
}

input,
select {
    width: 100%;
    padding: 9px;
    border: 1px solid #cad4e3;
    border-radius: 8px;
    font: inherit;
}

button {
    padding: 10px 14px;
    border: 0;
    border-radius: 8px;
    background: linear-gradient(140deg, var(--brand), var(--brand-dark));
    color: #fff;
    font-weight: 800;
    cursor: pointer;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.88rem;
}

th,
td {
    text-align: left;
    padding: 10px;
    border-bottom: 1px solid #e9eef5;
}

.status-badge {
    display: inline-block;
    border-radius: 999px;
    padding: 4px 10px;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.status-badge.pending {
    background: rgba(255, 157, 59, 0.18);
    color: #8e4f0b;
}

.status-badge.approved,
.status-badge.paid {
    background: rgba(46, 158, 104, 0.18);
    color: #19633f;
}

.status-badge.declined,
.status-badge.rejected,
.status-badge.failed {
    background: rgba(217, 83, 79, 0.18);
    color: #8f2f2c;
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

    .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    form .row {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 620px) {
    .hero {
        flex-direction: column;
        align-items: flex-start;
    }

    .service-btns,
    .stats,
    form .row {
        grid-template-columns: 1fr;
    }
}
</style>
</head>
<body>
<div class="wrap">
    <header class="hero">
        <div>
            <h1>Rent Payments</h1>
            <p>Submit your monthly rent and track approval status in one place.</p>
        </div>
        <a class="back-btn" href="renter_dashboard.php">&larr; Back to Dashboard</a>
    </header>

    <div class="dashboard-shell">
        <aside class="side-services">
            <h3>Renter Services</h3>
            <div class="service-btns">
                <a href="renter_profile.php" class="service-btn <?php echo $current_page === 'renter_profile.php' ? 'active' : ''; ?>">👤 Profile</a>
                <a href="browse_properties.php" class="service-btn <?php echo $current_page === 'browse_properties.php' ? 'active' : ''; ?>">🏡 Browse Properties</a>
                <a href="my_requests.php" class="service-btn <?php echo $current_page === 'my_requests.php' ? 'active' : ''; ?>">📨 My Requests</a>
                <a href="active_bookings.php" class="service-btn <?php echo $current_page === 'active_bookings.php' ? 'active' : ''; ?>">📋 Active Bookings</a>
                <a href="rent_reminders.php" class="service-btn <?php echo $current_page === 'rent_reminders.php' ? 'active' : ''; ?>">🔔 Reminders</a>
                <a href="rent_payments.php" class="service-btn <?php echo $current_page === 'rent_payments.php' ? 'active' : ''; ?>">💳 Payments</a>
                <a href="chat_list.php" class="service-btn <?php echo in_array($current_page, ['chat.php', 'chat_list.php'], true) ? 'active' : ''; ?>">💬 Chat</a>
                <a href="index.php" class="service-btn">↩ Back to Home</a>
            </div>
        </aside>

        <main class="main-content">
            <?php if ($message !== ''): ?><div class="alert ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error !== ''): ?><div class="alert err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <section class="stats">
                <article class="stat">
                    <div class="label">Active Bookings</div>
                    <div class="value"><?php echo count($bookings); ?></div>
                </article>
                <article class="stat">
                    <div class="label">Monthly Due</div>
                    <div class="value">$<?php echo number_format($total_monthly_due, 2); ?></div>
                </article>
                <article class="stat">
                    <div class="label">Pending Payments</div>
                    <div class="value"><?php echo $pending_count; ?></div>
                </article>
                <article class="stat">
                    <div class="label">Approved/Paid</div>
                    <div class="value"><?php echo $approved_count; ?></div>
                </article>
            </section>

            <section class="card">
                <h2>Choose Active Booking</h2>
                <?php if (count($bookings) === 0): ?>
                    <p class="small">No active bookings found.</p>
                <?php else: ?>
                    <div class="grid">
                        <?php foreach ($bookings as $booking): ?>
                            <div class="booking" onclick="pickBooking(<?php echo (int) $booking['id']; ?>, <?php echo (float) $booking['monthly_rent']; ?>)">
                                <strong><?php echo htmlspecialchars((string) ($booking['title'] ?? 'Property')); ?></strong>
                                <div class="small"><?php echo htmlspecialchars((string) ($booking['location'] ?? '')); ?></div>
                                <div class="small">Landlord: <?php echo htmlspecialchars((string) ($booking['landlord_name'] ?? '')); ?></div>
                                <div>$<?php echo number_format((float) ($booking['monthly_rent'] ?? 0), 2); ?>/month</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card">
                <h2>Submit Payment</h2>
                <form method="POST">
                    <div class="row">
                        <div>
                            <label>Booking</label>
                            <select name="booking_id" id="booking_id" required onchange="syncAmount()">
                                <option value="">Choose a booking</option>
                                <?php foreach ($bookings as $booking): ?>
                                    <option value="<?php echo (int) $booking['id']; ?>" data-rent="<?php echo (float) $booking['monthly_rent']; ?>">
                                        <?php echo htmlspecialchars((string) ($booking['title'] ?? 'Property')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Payment Month</label>
                            <input type="month" name="payment_month" required value="<?php echo htmlspecialchars(date('Y-m')); ?>">
                        </div>
                        <div>
                            <label>Amount</label>
                            <input type="number" name="amount" id="amount" min="0" step="0.01" required readonly>
                        </div>
                        <div>
                            <label>Method</label>
                            <select name="payment_method" required>
                                <option value="card">Card</option>
                                <option value="momo">Lonestar Momo</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-top:10px;">
                        <label>Reference (optional)</label>
                        <input type="text" name="reference_number" placeholder="Transaction reference">
                    </div>
                    <div style="margin-top:12px;">
                        <button type="submit" <?php echo count($bookings) === 0 ? 'disabled style="opacity:.6;cursor:not-allowed;"' : ''; ?>>Submit Payment</button>
                    </div>
                </form>
            </section>

            <section class="card">
                <h2>Payment History</h2>
                <?php if (count($payment_history) === 0): ?>
                    <p class="small">No payment records yet.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Property</th>
                                <th>Amount</th>
                                <th>Month</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payment_history as $payment): ?>
                                <?php $status = strtolower((string) ($payment['status'] ?? 'pending')); ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string) ($payment['property_title'] ?? 'Property')); ?></td>
                                    <td>$<?php echo number_format((float) ($payment['amount'] ?? 0), 2); ?></td>
                                    <td><?php echo date('M Y', strtotime((string) ($payment['payment_month'] ?? date('Y-m-01')))); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', (string) ($payment['payment_method'] ?? '')))); ?></td>
                                    <td><span class="status-badge <?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span></td>
                                    <td><?php echo date('M d, Y', strtotime((string) ($payment['payment_date'] ?? $payment['created_at'] ?? 'now'))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>

<script>
function syncAmount() {
    const select = document.getElementById('booking_id');
    const option = select.options[select.selectedIndex];
    document.getElementById('amount').value = option ? (option.getAttribute('data-rent') || '') : '';
}

function pickBooking(id, amount) {
    const select = document.getElementById('booking_id');
    select.value = String(id);
    document.getElementById('amount').value = String(amount);
    select.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>
</body>
</html>
