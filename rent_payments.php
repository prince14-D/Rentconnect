<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'renter') {
    header('Location: login.php');
    exit;
}

$renter_id = (int) $_SESSION['user_id'];
$message = '';
$error = '';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rent Payments - RentConnect</title>
<style>
body { font-family: Arial, sans-serif; margin: 0; background: #f4f7fb; color: #1f2430; }
.wrap { width: min(1000px, 94vw); margin: 24px auto; }
.card { background: #fff; border-radius: 10px; padding: 18px; margin-bottom: 16px; box-shadow: 0 4px 12px rgba(0,0,0,.08); }
h1 { margin: 0 0 6px; }
.alert { padding: 10px; border-radius: 8px; margin-bottom: 12px; }
.alert.ok { background: #e8f7ef; color: #146c43; }
.alert.err { background: #fdecec; color: #9f2d2d; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; }
.booking { border: 1px solid #dfe6ef; border-radius: 8px; padding: 10px; }
.booking strong { display: block; margin-bottom: 6px; }
form .row { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 10px; }
input, select { width: 100%; padding: 9px; border: 1px solid #cad4e3; border-radius: 7px; }
button { padding: 10px 12px; border: 0; border-radius: 7px; background: #1f8f67; color: #fff; font-weight: 700; cursor: pointer; }
table { width: 100%; border-collapse: collapse; font-size: 14px; }
th, td { text-align: left; padding: 10px; border-bottom: 1px solid #e9eef5; }
.small { color: #5d6579; font-size: 13px; }
@media (max-width: 760px) { form .row { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>Rent Payments</h1>
    <p class="small">Submit rent and track payment history</p>
  </div>

  <?php if ($message !== ''): ?><div class="alert ok"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
  <?php if ($error !== ''): ?><div class="alert err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <div class="card">
    <h2>Active Bookings</h2>
    <?php if (count($bookings) === 0): ?>
      <p class="small">No active bookings found.</p>
    <?php else: ?>
      <div class="grid">
        <?php foreach ($bookings as $booking): ?>
          <div class="booking" onclick="pickBooking(<?php echo (int) $booking['id']; ?>, <?php echo (float) $booking['monthly_rent']; ?>)">
            <strong><?php echo htmlspecialchars($booking['title']); ?></strong>
            <div class="small"><?php echo htmlspecialchars($booking['location']); ?></div>
            <div class="small">Landlord: <?php echo htmlspecialchars($booking['landlord_name']); ?></div>
            <div>$<?php echo number_format((float) $booking['monthly_rent'], 2); ?>/month</div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Submit Payment</h2>
    <form method="POST">
      <div class="row">
        <div>
          <label>Booking</label>
          <select name="booking_id" id="booking_id" required onchange="syncAmount()">
            <option value="">Choose</option>
            <?php foreach ($bookings as $booking): ?>
              <option value="<?php echo (int) $booking['id']; ?>" data-rent="<?php echo (float) $booking['monthly_rent']; ?>">
                <?php echo htmlspecialchars($booking['title']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Payment Month</label>
          <input type="month" name="payment_month" required>
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
      <div style="margin-top:12px;"><button type="submit">Submit Payment</button></div>
    </form>
  </div>

  <div class="card">
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
            <tr>
              <td><?php echo htmlspecialchars($payment['property_title']); ?></td>
              <td>$<?php echo number_format((float) $payment['amount'], 2); ?></td>
              <td><?php echo date('M Y', strtotime($payment['payment_month'])); ?></td>
              <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $payment['payment_method']))); ?></td>
              <td><?php echo htmlspecialchars(ucfirst((string) $payment['status'])); ?></td>
              <td><?php echo date('M d, Y', strtotime($payment['payment_date'] ?? $payment['created_at'])); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
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
}
</script>
</body>
</html>
