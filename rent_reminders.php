<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'renter') {
    header("Location: login.php");
    exit;
}

$renter_id = $_SESSION['user_id'];
$message = "";

// Mark reminder as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $reminder_id = intval($_GET['mark_read']);
    $stmt = $conn->prepare("UPDATE rent_reminders SET is_read = 1, read_at = NOW() WHERE id = ? AND renter_id = ?");
    $stmt->bind_param("ii", $reminder_id, $renter_id);
    if ($stmt->execute()) {
        header("Location: rent_reminders.php");
        exit;
    }
}

// Fetch reminders
$sql = "
SELECT 
    rr.id, rr.booking_id, rr.payment_month, rr.reminder_type, rr.message, rr.is_read, rr.created_at,
    p.title AS property_title, p.location,
    u.name AS landlord_name,
    b.monthly_rent,
    py.status AS payment_status
FROM rent_reminders rr
JOIN bookings b ON rr.booking_id = b.id
JOIN properties p ON b.property_id = p.id
JOIN users u ON rr.landlord_id = u.id
LEFT JOIN payments py ON b.id = py.booking_id AND py.payment_month = rr.payment_month
WHERE rr.renter_id = ?
ORDER BY rr.is_read ASC, rr.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $renter_id);
$stmt->execute();
$reminders = $stmt->get_result();

// Count unread reminders
$unread_sql = "SELECT COUNT(*) as unread_count FROM rent_reminders WHERE renter_id = ? AND is_read = 0";
$stmt = $conn->prepare($unread_sql);
$stmt->bind_param("i", $renter_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['unread_count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="View rental payment reminders from landlords.">
<meta name="theme-color" content="#1f8f67">
<link rel="apple-touch-icon" href="/favicon.svg" />
<link rel="icon" href="/favicon.svg" />
<link rel="manifest" href="/manifest.json">
<title>Rent Reminders - RentConnect</title>
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
  --warning: #f39c12;
  --danger: #c0392b;
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
.container { width: min(900px, 94vw); margin: 0 auto; }
.hero {
  background: linear-gradient(140deg, rgba(16, 62, 79, 0.93), rgba(31, 143, 103, 0.86));
  color: #fff;
  border-radius: 20px;
  padding: clamp(18px, 4vw, 28px);
  margin-bottom: 28px;
  box-shadow: var(--shadow);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.hero h1 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: clamp(1.5rem, 3vw, 2rem);
  letter-spacing: -0.02em;
}
.unread-badge {
  background: #ff6b6b;
  color: #fff;
  border-radius: 50%;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.85rem;
}
.reminder-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.reminder-card {
  background: rgba(255,255,255,0.93);
  border: 1px solid rgba(255,255,255,0.9);
  border-radius: 12px;
  padding: 20px;
  box-shadow: 0 10px 24px rgba(15, 31, 40, 0.09);
  border-left: 4px solid var(--warning);
  transition: all 0.2s;
  position: relative;
}
.reminder-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 14px 28px rgba(15, 31, 40, 0.12);
}
.reminder-card.read {
  border-left-color: var(--brand);
  opacity: 0.85;
}
.reminder-card.read::before {
  content: '✓ Read';
  position: absolute;
  top: 12px;
  right: 12px;
  font-size: 0.7rem;
  background: rgba(31, 143, 103, 0.2);
  color: var(--brand);
  padding: 4px 8px;
  border-radius: 12px;
}
.reminder-header {
  display: flex;
  justify-content: space-between;
  align-items: start;
  margin-bottom: 12px;
}
.reminder-title {
  font-weight: 700;
  color: var(--ink);
  font-size: 1.05rem;
}
.reminder-type {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
}
.type-due-soon {
  background: #fff3cd;
  color: #856404;
}
.type-overdue {
  background: #f8d7da;
  color: #721c24;
}
.type-manual {
  background: #d1ecf1;
  color: #0c5460;
}
.property-info {
  background: rgba(31, 143, 103, 0.08);
  padding: 12px;
  border-radius: 8px;
  margin: 12px 0;
  font-size: 0.9rem;
}
.property-info-label {
  display: block;
  color: var(--muted);
  font-size: 0.75rem;
  text-transform: uppercase;
  font-weight: 600;
  margin-bottom: 4px;
}
.property-info-value {
  color: var(--ink);
  font-weight: 600;
}
.reminder-details {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 12px;
  margin: 12px 0;
  font-size: 0.85rem;
}
.detail-item {
  color: var(--muted);
}
.detail-label {
  font-weight: 600;
  color: var(--ink);
  display: block;
  font-size: 0.75rem;
  text-transform: uppercase;
  margin-bottom: 2px;
}
.reminder-message {
  background: linear-gradient(135deg, #f6f9fc 0%, #f9f6ef 100%);
  padding: 14px;
  border-radius: 8px;
  margin: 14px 0;
  border-left: 3px solid var(--accent);
  font-style: italic;
  color: var(--ink);
  line-height: 1.5;
}
.payment-status {
  background: rgba(31, 143, 103, 0.1);
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 0.85rem;
  margin: 12px 0;
}
.status-badge {
  display: inline-block;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
}
.status-paid {
  background: #d1ecf1;
  color: #0c5460;
}
.status-pending {
  background: #fef3cd;
  color: #856404;
}
.status-unpaid {
  background: #f8d7da;
  color: #721c24;
}
.action-button {
  display: inline-block;
  padding: 8px 16px;
  background: var(--brand);
  color: #fff;
  text-decoration: none;
  border-radius: 6px;
  font-weight: 600;
  font-size: 0.85rem;
  cursor: pointer;
  border: none;
  transition: all 0.2s;
}
.action-button:hover {
  background: var(--brand-deep);
}
.empty-message {
  text-align: center;
  padding: 60px 20px;
  color: var(--muted);
}
.empty-message i { font-size: 3rem; margin-bottom: 16px; opacity: 0.2; }
</style>
</head>
<body>
<div class="container">
  <div class="hero">
    <div>
      <h1>🔔 Rent Reminders</h1>
      <p>Keep track of payment reminders from your landlords</p>
    </div>
    <?php if ($unread_count > 0): ?>
      <div class="unread-badge"><?php echo $unread_count; ?></div>
    <?php endif; ?>
  </div>

  <div class="reminder-list">
    <?php if ($reminders->num_rows === 0): ?>
      <div class="empty-message">
        <i class="fas fa-bell-slash"></i>
        <h3>No Reminders Yet</h3>
        <p>Your landlords will send you rent reminders here</p>
      </div>
    <?php else: ?>
      <?php while ($reminder = $reminders->fetch_assoc()): ?>
      <div class="reminder-card <?php echo ($reminder['is_read']) ? 'read' : ''; ?>">
        <div class="reminder-header">
          <div>
            <div class="reminder-title"><?php echo htmlspecialchars($reminder['property_title']); ?></div>
            <span class="reminder-type type-<?php echo strtolower($reminder['reminder_type']); ?>">
              <?php 
              $type_icons = ['due_soon' => '⏰', 'overdue' => '⛔', 'manual' => '💬'];
              echo ($type_icons[$reminder['reminder_type']] ?? '') . ' ' . ucfirst(str_replace('_', ' ', $reminder['reminder_type']));
              ?>
            </span>
          </div>
          <?php if (!$reminder['is_read']): ?>
            <a href="rent_reminders.php?mark_read=<?php echo $reminder['id']; ?>" class="action-button">
              Mark Read
            </a>
          <?php endif; ?>
        </div>

        <div class="property-info">
          <div class="property-info-label">📍 Location</div>
          <div class="property-info-value"><?php echo htmlspecialchars($reminder['location']); ?></div>
          <div style="margin-top: 8px;">
            <span class="property-info-label">From</span>
            <?php echo htmlspecialchars($reminder['landlord_name']); ?>
          </div>
        </div>

        <div class="reminder-details">
          <div class="detail-item">
            <span class="detail-label">Payment For</span>
            <?php echo date('F Y', strtotime($reminder['payment_month'])); ?>
          </div>
          <div class="detail-item">
            <span class="detail-label">Monthly Rent</span>
            $<?php echo number_format($reminder['monthly_rent'], 2); ?>
          </div>
          <div class="detail-item">
            <span class="detail-label">Sent</span>
            <?php echo date('M d, Y \a\t H:i', strtotime($reminder['created_at'])); ?>
          </div>
        </div>

        <div class="reminder-message">
          "<?php echo nl2br(htmlspecialchars($reminder['message'])); ?>"
        </div>

        <?php if ($reminder['payment_status']): ?>
          <div class="payment-status">
            <strong>Payment Status:</strong>
            <span class="status-badge status-<?php echo strtolower($reminder['payment_status']); ?>">
              <?php echo ucfirst($reminder['payment_status']); ?>
            </span>
          </div>
        <?php else: ?>
          <div class="payment-status">
            <strong>Payment Status:</strong>
            <span class="status-badge status-unpaid">Not Paid</span>
          </div>
        <?php endif; ?>

        <div style="margin-top: 14px;">
          <a href="rent_payments.php" class="action-button">
            <i class="fas fa-credit-card"></i> View Payment Options
          </a>
        </div>
      </div>
      <?php endwhile; ?>
    <?php endif; ?>
  </div>
</div>

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
