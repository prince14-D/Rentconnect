<?php
session_start();
include "app_init.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$message = "";
$error = "";
$back_url = ($role === 'landlord') ? 'landlord_dashboard.php' : 'renter_dashboard.php';

// Handle booking cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $booking_id = intval($_POST['booking_id'] ?? 0);
    $reason = $_POST['cancellation_reason'] ?? '';
    
    // Verify ownership
    if ($role === 'renter') {
        $check = $conn->prepare("SELECT property_id, landlord_id FROM bookings WHERE id = ? AND renter_id = ? AND status = 'active'");
        $check->bind_param("ii", $booking_id, $user_id);
    } else {
        $check = $conn->prepare("SELECT property_id FROM bookings WHERE id = ? AND landlord_id = ? AND status = 'active'");
        $check->bind_param("ii", $booking_id, $user_id);
    }
    
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        $booking_data = $result->fetch_assoc();
        $property_id = $booking_data['property_id'];
        
        // Update booking status
        $stmt = $conn->prepare("
            UPDATE bookings 
            SET status = 'cancelled', cancelled_by = ?, cancellation_reason = ?, cancelled_at = NOW()
            WHERE id = ?
        ");
        $cancelled_by = ($role === 'renter') ? 'renter' : 'landlord';
        $stmt->bind_param("ssi", $cancelled_by, $reason, $booking_id);
        
        if ($stmt->execute()) {
            // Update property booking status back to available
          if (rc_mig_set_property_booking_status($conn, (int) $property_id, 'available')) {
            $message = "✓ Booking cancelled successfully. Property is now available.";
          } else {
            $message = "✓ Booking cancelled, but property availability update is pending.";
          }
        } else {
            $error = "Failed to cancel booking";
        }
    } else {
        $error = "Booking not found or you don't have permission to cancel it";
    }
}

// Handle rent reminder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_reminder') {
    $booking_id = intval($_POST['booking_id'] ?? 0);
    $payment_month = $_POST['payment_month'] ?? '';
    $reminder_message = $_POST['reminder_message'] ?? '';
    
    // Verify landlord ownership
    $check = $conn->prepare("SELECT renter_id FROM bookings WHERE id = ? AND landlord_id = ? AND status = 'active'");
    $check->bind_param("ii", $booking_id, $user_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        $booking_data = $result->fetch_assoc();
        $renter_id = $booking_data['renter_id'];
        
        $stmt = $conn->prepare("
            INSERT INTO rent_reminders (booking_id, landlord_id, renter_id, payment_month, reminder_type, message)
            VALUES (?, ?, ?, ?, 'manual', ?)
        ");
        $stmt->bind_param("iisss", $booking_id, $user_id, $renter_id, $payment_month, $reminder_message);
        
        if ($stmt->execute()) {
            $message = "✓ Reminder sent to renter!";
        } else {
            $error = "Failed to send reminder";
        }
    } else {
        $error = "Booking not found";
    }
}

// Fetch bookings based on role
if ($role === 'landlord') {
    $sql = "
    SELECT 
        b.id, b.property_id, b.renter_id, b.move_in_date, b.monthly_rent, b.status,
        p.title, p.location,
        u.name AS renter_name, u.email AS renter_email,
        (SELECT COUNT(*) FROM payments WHERE booking_id = b.id AND status = 'pending') AS pending_payments,
        (SELECT COUNT(*) FROM payments WHERE booking_id = b.id AND status = 'approved') AS approved_payments
    FROM bookings b
    JOIN properties p ON b.property_id = p.id
    JOIN users u ON b.renter_id = u.id
    WHERE b.landlord_id = ?
    ORDER BY b.status = 'active' DESC, b.created_at DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
} else {
    $sql = "
    SELECT 
        b.id, b.property_id, b.landlord_id, b.move_in_date, b.monthly_rent, b.status,
        p.title, p.location,
        u.name AS landlord_name, u.email AS landlord_email,
        (SELECT COUNT(*) FROM payments WHERE booking_id = b.id AND status = 'pending') AS pending_payments,
        (SELECT COUNT(*) FROM payments WHERE booking_id = b.id AND status = 'approved') AS approved_payments
    FROM bookings b
    JOIN properties p ON b.property_id = p.id
    JOIN users u ON b.landlord_id = u.id
    WHERE b.renter_id = ?
    ORDER BY b.status = 'active' DESC, b.created_at DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$bookings = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Manage your rental bookings.">
<meta name="theme-color" content="#1f8f67">
<link rel="apple-touch-icon" href="/favicon.svg" />
<link rel="icon" href="/favicon.svg" />
<link rel="manifest" href="/manifest.json">
<title>Bookings - RentConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
  --ink: #1f2430;
  --muted: #5d6579;
  --brand: #1f8f67;
  --brand-deep: #15543e;
  --accent: #ff7a2f;
  --line: rgba(31, 36, 48, 0.12);
  --shadow: 0 18px 36px rgba(19, 36, 33, 0.14);
  --success: #27ae60;
  --warning: #f39c12;
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
  padding: 28px 0;
}
.container { width: min(1000px, 94vw); margin: 0 auto; }
.hero {
  background: linear-gradient(140deg, rgba(16, 62, 79, 0.93), rgba(31, 143, 103, 0.86));
  color: #fff;
  border-radius: 20px;
  padding: clamp(18px, 4vw, 28px);
  margin-bottom: 28px;
  box-shadow: var(--shadow);
}
.hero h1 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: clamp(1.5rem, 3vw, 2rem);
  letter-spacing: -0.02em;
  margin-bottom: 8px;
}
.hero-actions {
  margin-top: 14px;
}
.hero-back {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #fff;
  text-decoration: none;
  font-weight: 700;
  border: 1px solid rgba(255,255,255,0.45);
  border-radius: 10px;
  padding: 9px 12px;
  background: rgba(255,255,255,0.12);
}
.hero-back:hover {
  background: rgba(255,255,255,0.2);
}
.alert {
  padding: 12px 16px;
  border-radius: 8px;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 12px;
}
.alert.error { background: #fadbd8; color: #c0392b; border-left: 4px solid #c0392b; }
.alert.success { background: #d5f4e6; color: #27ae60; border-left: 4px solid #27ae60; }
.bookings-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 20px;
}
.booking-card {
  background: rgba(255,255,255,0.93);
  border: 1px solid rgba(255,255,255,0.9);
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 10px 24px rgba(15, 31, 40, 0.09);
  transition: all 0.2s;
}
.booking-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 15px 35px rgba(15, 31, 40, 0.15);
}
.booking-status {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  margin-bottom: 12px;
}
.status-active { background: #d1ecf1; color: #0c5460; }
.status-cancelled { background: #f8d7da; color: #721c24; }
.booking-title {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--ink);
  margin-bottom: 12px;
}
.booking-detail {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
  color: var(--muted);
  margin: 8px 0;
}
.booking-detail i { color: var(--brand); width: 16px; }
.booking-rent {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--brand);
  margin: 16px 0;
}
.booking-actions {
  display: flex;
  gap: 10px;
  margin-top: 16px;
}
.btn {
  flex: 1;
  padding: 10px;
  border: none;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 0.85rem;
}
.btn-primary {
  background: var(--brand);
  color: #fff;
}
.btn-primary:hover {
  background: var(--brand-deep);
}
.btn-secondary {
  background: rgba(31, 36, 48, 0.1);
  color: var(--ink);
}
.btn-secondary:hover {
  background: rgba(31, 36, 48, 0.15);
}
.btn-danger {
  background: #c0392b;
  color: #fff;
}
.btn-danger:hover {
  background: #a93226;
}
.modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.5);
  z-index: 1000;
  place-items: center;
}
.modal.active { display: grid; }
.modal-content {
  background: #fff;
  border-radius: 12px;
  max-width: 500px;
  width: 90vw;
  padding: 30px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
.modal-header { margin-bottom: 20px; }
.modal-header h2 { color: var(--ink); margin-bottom: 8px; }
.form-group {
  margin-bottom: 16px;
}
.form-group label {
  display: block;
  color: var(--ink);
  font-weight: 600;
  margin-bottom: 6px;
  font-size: 0.9rem;
}
.form-group input,
.form-group textarea,
.form-group select {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--line);
  border-radius: 6px;
  font-family: inherit;
  font-size: 0.95rem;
}
.form-group textarea {
  resize: vertical;
  min-height: 80px;
}
.modal-footer {
  display: flex;
  gap: 10px;
  margin-top: 20px;
}
.empty-message {
  text-align: center;
  padding: 60px 20px;
  color: var(--muted);
}
.empty-message i { font-size: 3rem; margin-bottom: 16px; opacity: 0.3; }
</style>
</head>
<body>
<div class="container">
  <div class="hero">
    <h1>📋 <?php echo ($role === 'landlord') ? 'Booked Properties' : 'My Bookings'; ?></h1>
    <p><?php echo ($role === 'landlord') ? 'Manage renter agreements and track payments' : 'View your rental agreements'; ?></p>
    <div class="hero-actions">
      <a class="hero-back" href="<?php echo htmlspecialchars($back_url); ?>"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <?php if ($bookings->num_rows === 0): ?>
    <div class="empty-message">
      <i class="fas fa-inbox"></i>
      <h3>No Bookings Yet</h3>
    </div>
  <?php else: ?>
    <div class="bookings-grid">
      <?php while ($booking = $bookings->fetch_assoc()):  ?>
        <div class="booking-card">
          <div class="booking-status status-<?php echo strtolower($booking['status']); ?>">
            <?php echo ucfirst($booking['status']); ?>
          </div>
          
          <h3 class="booking-title"><?php echo htmlspecialchars($booking['title']); ?></h3>
          
          <div class="booking-detail">
            <i class="fas fa-map-marker-alt"></i>
            <span><?php echo htmlspecialchars($booking['location']); ?></span>
          </div>
          
          <?php if ($role === 'landlord'): ?>
            <div class="booking-detail">
              <i class="fas fa-user"></i>
              <span><?php echo htmlspecialchars($booking['renter_name']); ?></span>
            </div>
            <div class="booking-detail">
              <i class="fas fa-envelope"></i>
              <span><?php echo htmlspecialchars($booking['renter_email']); ?></span>
            </div>
          <?php else: ?>
            <div class="booking-detail">
              <i class="fas fa-user-tie"></i>
              <span><?php echo htmlspecialchars($booking['landlord_name']); ?></span>
            </div>
          <?php endif; ?>
          
          <div class="booking-detail">
            <i class="fas fa-calendar-alt"></i>
            <span>Since: <?php echo date('M d, Y', strtotime($booking['move_in_date'])); ?></span>
          </div>
          
          <div class="booking-rent">$<?php echo number_format($booking['monthly_rent'], 2); ?>/month</div>
          
          <?php if ($booking['pending_payments'] > 0): ?>
            <div class="booking-detail" style="color: #f39c12;">
              <i class="fas fa-hourglass-end"></i>
              <span><?php echo $booking['pending_payments']; ?> pending payment(s)</span>
            </div>
          <?php endif; ?>
          
          <div class="booking-actions">
            <?php if ($role === 'landlord' && $booking['status'] === 'active'): ?>
              <button class="btn btn-primary" onclick="openReminderModal(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['renter_name']); ?>')">
                <i class="fas fa-bell"></i> Remind
              </button>
              <button class="btn btn-danger" onclick="openCancelModal(<?php echo $booking['id']; ?>, 'landlord')">
                <i class="fas fa-times"></i> Cancel
              </button>
            <?php elseif ($role === 'renter' && $booking['status'] === 'active'): ?>
              <button class="btn btn-primary" onclick="window.location.href='rent_payments.php'">
                <i class="fas fa-credit-card"></i> Pay Rent
              </button>
              <button class="btn btn-danger" onclick="openCancelModal(<?php echo $booking['id']; ?>, 'renter')">
                <i class="fas fa-times"></i> Cancel
              </button>
            <?php endif; ?>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Cancel Booking Modal -->
<div id="cancelModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Cancel Booking</h2>
      <p>Are you sure you want to cancel this booking?</p>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="cancel">
      <input type="hidden" id="cancelBookingId" name="booking_id">
      <div class="form-group">
        <label for="cancelReason">Reason for Cancellation</label>
        <textarea id="cancelReason" name="cancellation_reason" required></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('cancelModal')">No, Keep It</button>
        <button type="submit" class="btn btn-danger">Yes, Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Send Reminder Modal -->
<div id="reminderModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Send Rent Reminder</h2>
      <p id="reminderRenterName"></p>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="send_reminder">
      <input type="hidden" id="reminderBookingId" name="booking_id">
      <div class="form-group">
        <label for="reminderMonth">Payment Month</label>
        <input type="month" id="reminderMonth" name="payment_month" required>
      </div>
      <div class="form-group">
        <label for="reminderMsg">Message</label>
        <textarea id="reminderMsg" name="reminder_message" placeholder="e.g., 'Rent is due on the 5th. Please pay on time.'"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('reminderModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Send Reminder</button>
      </div>
    </form>
  </div>
</div>

<script>
function openCancelModal(bookingId, role) {
  document.getElementById('cancelBookingId').value = bookingId;
  document.getElementById('cancelModal').classList.add('active');
}

function openReminderModal(bookingId, renterName) {
  document.getElementById('reminderBookingId').value = bookingId;
  document.getElementById('reminderRenterName').textContent = 'Remind ' + renterName + ' about their rent';
  document.getElementById('reminderModal').classList.add('active');
}

function closeModal(modalId) {
  document.getElementById(modalId).classList.remove('active');
}

document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal')) {
    e.target.classList.remove('active');
  }
});

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
