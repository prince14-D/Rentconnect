<?php
session_start();
include "app_init.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];
$message = "";
$error = "";
$back_url = 'landlord_dashboard.php';

// Handle payment approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_id = intval($_POST['payment_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if ($action === 'approve') {
        $stmt = $conn->prepare("
            UPDATE payments 
            SET status = 'approved', landlord_verified = 1, verified_at = NOW(), notes = ?
            WHERE id = ? AND landlord_id = ?
        ");
        $stmt->bind_param("sii", $notes, $payment_id, $landlord_id);
        
        if ($stmt->execute()) {
            $message = "✓ Payment approved!";
        } else {
            $error = "Failed to approve payment";
        }
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("
            UPDATE payments 
            SET status = 'rejected', notes = ?
            WHERE id = ? AND landlord_id = ?
        ");
        $stmt->bind_param("sii", $notes, $payment_id, $landlord_id);
        
        if ($stmt->execute()) {
            $message = "✓ Payment rejected!";
        } else {
            $error = "Failed to reject payment";
        }
    }
}

// Fetch pending payments
$sql = "
SELECT 
    py.id, py.booking_id, py.renter_id, py.amount, py.payment_month, py.payment_method, py.reference_number, py.status, py.created_at,
    b.monthly_rent,
    p.title AS property_title, p.location,
    u.name AS renter_name, u.email AS renter_email
FROM payments py
JOIN bookings b ON py.booking_id = b.id
JOIN properties p ON py.property_id = p.id
JOIN users u ON py.renter_id = u.id
WHERE py.landlord_id = ?
ORDER BY py.status = 'pending' DESC, py.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $landlord_id);
$stmt->execute();
$payments = $stmt->get_result();

// Calculate total income
$income_sql = "
SELECT 
    COALESCE(SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END), 0) AS total_approved,
    COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS total_pending,
    COALESCE(SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END), 0) AS all_time_income
FROM payments
WHERE landlord_id = ?
";
$stmt_income = $conn->prepare($income_sql);
$stmt_income->bind_param("i", $landlord_id);
$stmt_income->execute();
$income = $stmt_income->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Manage and approve rental payments.">
<meta name="theme-color" content="#1f8f67">
<link rel="apple-touch-icon" href="/favicon.svg" />
<link rel="icon" href="/favicon.svg" />
<link rel="manifest" href="/manifest.json">
<title>Payment Management - RentConnect</title>
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
.container { width: min(1100px, 94vw); margin: 0 auto; }
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
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 16px;
  margin-bottom: 28px;
}
.stat-card {
  background: rgba(255,255,255,0.93);
  border-left: 4px solid var(--brand);
  border-radius: 10px;
  padding: 20px;
  box-shadow: 0 10px 24px rgba(15, 31, 40, 0.09);
}
.stat-label {
  color: var(--muted);
  font-size: 0.85rem;
  text-transform: uppercase;
  font-weight: 600;
  margin-bottom: 8px;
}
.stat-value {
  font-size: 2rem;
  font-weight: 700;
  color: var(--brand);
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
.section {
  background: rgba(255,255,255,0.93);
  border: 1px solid rgba(255,255,255,0.9);
  border-radius: 14px;
  padding: 24px;
  margin-bottom: 24px;
  box-shadow: 0 10px 24px rgba(15, 31, 40, 0.09);
}
.section h2 {
  color: var(--ink);
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 2px solid var(--line);
  font-size: 1.3rem;
}
.payment-card {
  background: linear-gradient(135deg, #f6f9fc 0%, #f9f6ef 100%);
  padding: 16px;
  border-radius: 10px;
  margin-bottom: 16px;
  border-left: 4px solid var(--warning);
}
.payment-card.approved {
  border-left-color: var(--success);
  background: linear-gradient(135deg, #d1ecf1 0%, #e8f8f5 100%);
}
.payment-header {
  display: flex;
  justify-content: space-between;
  align-items: start;
  margin-bottom: 12px;
}
.payment-title {
  font-weight: 700;
  color: var(--ink);
}
.status-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
}
.status-pending {
  background: #fef3cd;
  color: #856404;
}
.status-approved {
  background: #d1ecf1;
  color: #0c5460;
}
.status-rejected {
  background: #f8d7da;
  color: #721c24;
}
.payment-details {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 12px;
  margin: 12px 0;
  font-size: 0.9rem;
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
}
.amount {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--brand);
  margin: 12px 0;
}
.renter-info {
  background: rgba(255,255,255,0.6);
  padding: 10px;
  border-radius: 6px;
  margin: 12px 0;
  font-size: 0.85rem;
}
.renter-info strong { color: var(--ink); }
.form-group {
  margin-bottom: 12px;
}
.form-group label {
  display: block;
  color: var(--ink);
  font-weight: 600;
  margin-bottom: 4px;
  font-size: 0.85rem;
}
.form-group textarea {
  width: 100%;
  padding: 8px;
  border: 1px solid var(--line);
  border-radius: 6px;
  font-family: inherit;
  font-size: 0.85rem;
  min-height: 50px;
}
.action-buttons {
  display: flex;
  gap: 10px;
  margin-top: 12px;
}
.btn {
  flex: 1;
  padding: 8px 12px;
  border: none;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 0.85rem;
}
.btn-approve {
  background: var(--success);
  color: #fff;
}
.btn-approve:hover { background: #229954; }
.btn-reject {
  background: var(--danger);
  color: #fff;
}
.btn-reject:hover { background: #a93226; }
.empty-message {
  text-align: center;
  padding: 40px 20px;
  color: var(--muted);
}
.empty-message i { font-size: 2.5rem; margin-bottom: 12px; opacity: 0.3; }
</style>
</head>
<body>
<div class="container">
  <div class="hero">
    <h1>💰 Payment Management</h1>
    <p>Review and approve rental payments from renters</p>
    <div class="hero-actions">
      <a class="hero-back" href="<?php echo htmlspecialchars($back_url); ?>"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">Approved Income</div>
      <div class="stat-value">$<?php echo number_format($income['total_approved'], 2); ?></div>
    </div>
    <div class="stat-card" style="border-left-color: var(--warning);">
      <div class="stat-label" style="color: var(--warning);">Pending Approval</div>
      <div class="stat-value" style="color: var(--warning);">$<?php echo number_format($income['total_pending'], 2); ?></div>
    </div>
    <div class="stat-card" style="border-left-color: var(--accent);">
      <div class="stat-label" style="color: var(--accent);">All-Time Income</div>
      <div class="stat-value" style="color: var(--accent);">$<?php echo number_format($income['all_time_income'], 2); ?></div>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="alert success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <div class="section">
    <h2>📊 Payment Review</h2>
    
    <?php if ($payments->num_rows === 0): ?>
      <div class="empty-message">
        <i class="fas fa-inbox"></i>
        <h3>No Payments Yet</h3>
        <p>Rental payments from renters will appear here</p>
      </div>
    <?php else: ?>
      <?php while ($payment = $payments->fetch_assoc()): ?>
        <div class="payment-card <?php echo ($payment['status'] === 'approved') ? 'approved' : ''; ?>">
          <div class="payment-header">
            <div>
              <div class="payment-title"><?php echo htmlspecialchars($payment['property_title']); ?></div>
              <div style="font-size: 0.85rem; color: var(--muted);"><?php echo htmlspecialchars($payment['location']); ?></div>
            </div>
            <span class="status-badge status-<?php echo strtolower($payment['status']); ?>">
              <?php echo ucfirst($payment['status']); ?>
            </span>
          </div>

          <div class="amount">
            $<?php echo number_format($payment['amount'], 2); ?>
            <span style="font-size: 0.8rem; color: var(--muted); font-weight: 400;">
              for <?php echo date('F Y', strtotime($payment['payment_month'])); ?>
            </span>
          </div>

          <div class="renter-info">
            <strong>📌 Renter:</strong> <?php echo htmlspecialchars($payment['renter_name']); ?> (<?php echo htmlspecialchars($payment['renter_email']); ?>)
          </div>

          <div class="payment-details">
            <div class="detail-item">
              <span class="detail-label">Method</span>
              <?php 
              $method_icons = ['card' => '💳', 'momo' => '📱', 'bank_transfer' => '🏦'];
              echo ($method_icons[$payment['payment_method']] ?? '');
              echo ucfirst(str_replace('_', ' ', $payment['payment_method']));
              ?>
            </div>
            <div class="detail-item">
              <span class="detail-label">Reference</span>
              <?php echo htmlspecialchars($payment['reference_number'] ?: 'N/A'); ?>
            </div>
            <div class="detail-item">
              <span class="detail-label">Submitted</span>
              <?php echo date('M d, Y', strtotime($payment['created_at'])); ?>
            </div>
          </div>

          <?php if ($payment['status'] === 'pending'): ?>
            <form method="POST" class="payment-form">
              <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
              <div class="form-group">
                <label>Notes (Optional)</label>
                <textarea name="notes" placeholder="Add verification notes..."></textarea>
              </div>
              <div class="action-buttons">
                <button type="submit" name="action" value="approve" class="btn btn-approve">
                  <i class="fas fa-check"></i> Approve
                </button>
                <button type="submit" name="action" value="reject" class="btn btn-reject">
                  <i class="fas fa-times"></i> Reject
                </button>
              </div>
            </form>
          <?php endif; ?>
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
