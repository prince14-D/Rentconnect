<?php
session_start();
include "app_init.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: super_admin_login.php");
    exit;
}

// Handle refund requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $payment_id = intval($_POST['payment_id'] ?? 0);
    $reason = $_POST['reason'] ?? '';

    if ($action === 'refund' && $payment_id > 0) {
        // Get payment details
        $stmt = $conn->prepare("
            SELECT * FROM payments WHERE id = ?
        ");
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();

        if ($payment && $payment['status'] === 'confirmed') {
            // Process refund (simplified)
            $status = 'refunded';
            $update = $conn->prepare("
                UPDATE payments 
                SET status = ?, refund_reason = ?, refunded_at = NOW()
                WHERE id = ?
            ");
            $update->bind_param("ssi", $status, $reason, $payment_id);
            $update->execute();

            header("Location: {$_SERVER['PHP_SELF']}?message=" . urlencode("Refund processed successfully"));
            exit;
        }
    }
}

// Fetch statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_payments,
        COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed,
        COUNT(CASE WHEN status = 'pending' OR status = 'submitted' THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed,
        COUNT(CASE WHEN status = 'refunded' THEN 1 END) as refunded,
        SUM(CASE WHEN status = 'confirmed' THEN amount ELSE 0 END) as total_confirmed,
        SUM(CASE WHEN status = 'pending' OR status = 'submitted' THEN amount ELSE 0 END) as total_pending,
        SUM(CASE WHEN status = 'refunded' THEN amount ELSE 0 END) as total_refunded
    FROM payments
");
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Fetch recent payments
$per_page = 20;
$page = intval($_GET['page'] ?? 1);
$offset = ($page - 1) * $per_page;

$payments_stmt = $conn->prepare("
    SELECT p.*, u_l.name as landlord_name, u_r.name as renter_name, pr.title
    FROM payments p
    JOIN users u_l ON p.landlord_id = u_l.id
    JOIN users u_r ON p.renter_id = u_r.id
    JOIN properties pr ON p.property_id = pr.id
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");
$payments_stmt->bind_param("ii", $per_page, $offset);
$payments_stmt->execute();
$payments = $payments_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total count
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM payments");
$count_stmt->execute();
$count = $count_stmt->get_result()->fetch_assoc();
$total_pages = ceil($count['total'] / $per_page);

// Get payments by method
$method_stmt = $conn->prepare("
    SELECT payment_method, COUNT(*) as count, SUM(amount) as total
    FROM payments
    WHERE status = 'confirmed'
    GROUP BY payment_method
");
$method_stmt->execute();
$payment_methods = $method_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get monthly revenue
$monthly_stmt = $conn->prepare("
    SELECT 
        DATE_TRUNC('month', paid_at) as month,
        COUNT(*) as count,
        SUM(amount) as total
    FROM payments
    WHERE status = 'confirmed' AND paid_at IS NOT NULL
    GROUP BY DATE_TRUNC('month', paid_at)
    ORDER BY month DESC
    LIMIT 12
");
$monthly_stmt->execute();
$monthly_data = $monthly_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function getStatusBadge($status) {
    $colors = [
        'pending' => '#f39c12',
        'submitted' => '#3498db',
        'confirmed' => '#27ae60',
        'failed' => '#c0392b',
        'refunded' => '#95a5a6'
    ];
    $badges = [
        'pending' => '⏳ Pending',
        'submitted' => '📤 Submitted',
        'confirmed' => '✓ Confirmed',
        'failed' => '✗ Failed',
        'refunded' => '↩️ Refunded'
    ];
    $color = $colors[$status] ?? '#95a5a6';
    return '<span style="background: ' . $color . '20; color: ' . $color . '; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 0.85rem;">' . $badges[$status] . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Admin payment management and reporting dashboard.">
<meta name="theme-color" content="#1f8f67">
<title>Payment Management - RentConnect Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
<style>
:root {
  --ink: #1f2430;
  --muted: #5d6579;
  --brand: #1f8f67;
  --brand-deep: #15543e;
  --line: rgba(31, 36, 48, 0.12);
  --shadow: 0 18px 36px rgba(19, 36, 33, 0.14);
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: 'Manrope', sans-serif;
  color: var(--ink);
  background: linear-gradient(165deg, #f9f6ef 0%, #f2f7f8 58%, #fffdfa 100%);
  min-height: 100vh;
}

header {
  background: linear-gradient(135deg, var(--brand), var(--brand-deep));
  color: white;
  padding: 24px;
  box-shadow: var(--shadow);
}

.header-content {
  max-width: 1400px;
  margin: 0 auto;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.header-title h1 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 1.8rem;
  margin-bottom: 4px;
}

.header-title p {
  font-size: 0.9rem;
  opacity: 0.9;
}

.btn-header {
  padding: 10px 16px;
  background: rgba(255,255,255,0.2);
  border-radius: 6px;
  color: white;
  text-decoration: none;
  transition: all 0.2s;
  font-weight: 600;
}

.btn-header:hover {
  background: rgba(255,255,255,0.3);
}

.container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 32px 24px;
}

.section {
  background: rgba(255,255,255,0.95);
  border-radius: 16px;
  padding: 32px;
  margin-bottom: 24px;
  box-shadow: var(--shadow);
}

.section-title {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 1.4rem;
  margin-bottom: 24px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 32px;
}

.stat-card {
  background: linear-gradient(135deg, rgba(31, 143, 103, 0.1), rgba(31, 143, 103, 0.05));
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 20px;
  text-align: center;
}

.stat-value {
  font-size: 1.8rem;
  font-weight: 800;
  color: var(--brand);
  margin-bottom: 8px;
}

.stat-label {
  font-size: 0.85rem;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.payment-methods-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 16px;
  margin-bottom: 32px;
}

.method-card {
  background: #f6f9fc;
  border-radius: 12px;
  padding: 20px;
  border-left: 4px solid var(--brand);
}

.method-name {
  font-weight: 700;
  margin-bottom: 12px;
  color: var(--ink);
}

.method-stat {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px solid var(--line);
  font-size: 0.9rem;
}

.payments-table {
  width: 100%;
  border-collapse: collapse;
}

.payments-table thead {
  background: #f6f9fc;
  border-bottom: 2px solid var(--line);
}

.payments-table th {
  padding: 16px;
  text-align: left;
  font-weight: 700;
  color: var(--ink);
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.payments-table td {
  padding: 16px;
  border-bottom: 1px solid var(--line);
  font-size: 0.95rem;
}

.payments-table tbody tr:hover {
  background: rgba(31, 143, 103, 0.05);
}

.amount {
  font-weight: 700;
  color: var(--brand);
  font-size: 1.1rem;
}

.action-buttons {
  display: flex;
  gap: 8px;
}

.btn-refund {
  padding: 8px 12px;
  background: #e74c3c;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  font-size: 0.85rem;
  transition: all 0.2s;
}

.btn-refund:hover {
  background: #c0392b;
}

.pagination {
  display: flex;
  justify-content: center;
  gap: 8px;
  margin-top: 24px;
}

.pagination a, .pagination span {
  padding: 8px 12px;
  border: 1px solid var(--line);
  border-radius: 6px;
  text-decoration: none;
  color: var(--ink);
  transition: all 0.2s;
}

.pagination a:hover {
  background: var(--brand);
  color: white;
}

.pagination .active {
  background: var(--brand);
  color: white;
}

.modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 1000;
  align-items: center;
  justify-content: center;
}

.modal.active {
  display: flex;
}

.modal-content {
  background: white;
  border-radius: 12px;
  padding: 32px;
  max-width: 500px;
  width: 90%;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.modal-title {
  font-size: 1.4rem;
  font-weight: 700;
  margin-bottom: 20px;
  color: var(--ink);
}

.form-group {
  margin-bottom: 16px;
}

label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: var(--ink);
}

textarea {
  width: 100%;
  padding: 12px;
  border: 1px solid var(--line);
  border-radius: 8px;
  font-family: inherit;
  resize: vertical;
  min-height: 80px;
}

.modal-actions {
  display: flex;
  gap: 12px;
  margin-top: 24px;
}

.btn-submit, .btn-close {
  flex: 1;
  padding: 12px;
  border: none;
  border-radius: 8px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-submit {
  background: #e74c3c;
  color: white;
}

.btn-submit:hover {
  background: #c0392b;
}

.btn-close {
  background: rgba(31, 36, 48, 0.1);
  color: var(--ink);
}

.message {
  background: rgba(39, 174, 96, 0.1);
  border: 1px solid #27ae60;
  border-radius: 8px;
  padding: 12px 16px;
  color: #27ae60;
  margin-bottom: 20px;
  font-weight: 600;
}

@media (max-width: 768px) {
  .header-content {
    flex-direction: column;
    gap: 12px;
  }

  .stats-grid {
    grid-template-columns: 1fr 1fr;
  }

  .payments-table {
    font-size: 0.85rem;
  }

  .payments-table th, .payments-table td {
    padding: 12px;
  }
}
</style>
</head>
<body>
<header>
  <div class="header-content">
    <div class="header-title">
      <h1>💳 Payment Management</h1>
      <p>Platform payment analytics and administration</p>
    </div>
    <div>
      <a href="super_admin_dashboard.php" class="btn-header">← Back to Dashboard</a>
    </div>
  </div>
</header>

<div class="container">
  <!-- Message -->
  <?php if (isset($_GET['message'])): ?>
  <div class="message">✓ <?php echo htmlspecialchars($_GET['message']); ?></div>
  <?php endif; ?>

  <!-- Statistics -->
  <div class="section">
    <h2 class="section-title">📊 Overview</h2>
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-value"><?php echo $stats['total_payments']; ?></div>
        <div class="stat-label">Total Payments</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?php echo $stats['confirmed']; ?></div>
        <div class="stat-label">Confirmed</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?php echo $stats['pending']; ?></div>
        <div class="stat-label">Pending</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?php echo $stats['failed']; ?></div>
        <div class="stat-label">Failed</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">$<?php echo number_format($stats['total_confirmed'] ?? 0, 2); ?></div>
        <div class="stat-label">Total Confirmed</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">$<?php echo number_format($stats['total_refunded'] ?? 0, 2); ?></div>
        <div class="stat-label">Total Refunded</div>
      </div>
    </div>
  </div>

  <!-- Payment Methods -->
  <?php if (!empty($payment_methods)): ?>
  <div class="section">
    <h2 class="section-title">💳 Payment Methods</h2>
    <div class="payment-methods-grid">
      <?php foreach ($payment_methods as $method): ?>
      <div class="method-card">
        <div class="method-name"><?php echo ucfirst(str_replace('_', ' ', $method['payment_method'])); ?></div>
        <div class="method-stat">
          <span>Count:</span>
          <span style="font-weight: 700;"><?php echo $method['count']; ?></span>
        </div>
        <div class="method-stat">
          <span>Total:</span>
          <span style="font-weight: 700; color: var(--brand);">$<?php echo number_format($method['total'], 2); ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Recent Payments -->
  <div class="section">
    <h2 class="section-title">📋 Recent Payments</h2>
    <table class="payments-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Landlord</th>
          <th>Tenant</th>
          <th>Property</th>
          <th>Amount</th>
          <th>Method</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
        <tr>
          <td><?php echo date('M d, Y', strtotime($p['created_at'])); ?></td>
          <td><strong><?php echo htmlspecialchars($p['landlord_name']); ?></strong></td>
          <td><?php echo htmlspecialchars($p['renter_name']); ?></td>
          <td><?php echo htmlspecialchars(substr($p['title'], 0, 20)); ?></td>
          <td class="amount">$<?php echo number_format($p['amount'], 2); ?></td>
          <td><?php echo ucfirst(str_replace('_', ' ', $p['payment_method'])); ?></td>
          <td><?php echo getStatusBadge($p['status']); ?></td>
          <td>
            <div class="action-buttons">
              <?php if ($p['status'] === 'confirmed'): ?>
                <button class="btn-refund" onclick="openRefundModal(<?php echo $p['id']; ?>)">↩️ Refund</button>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="?page=1">« First</a>
        <a href="?page=<?php echo $page - 1; ?>">Previous</a>
      <?php endif; ?>

      <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
        <?php if ($i === $page): ?>
          <span class="active"><?php echo $i; ?></span>
        <?php else: ?>
          <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
        <?php endif; ?>
      <?php endfor; ?>

      <?php if ($page < $total_pages): ?>
        <a href="?page=<?php echo $page + 1; ?>">Next</a>
        <a href="?page=<?php echo $total_pages; ?>">Last »</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Refund Modal -->
<div id="refundModal" class="modal">
  <div class="modal-content">
    <div class="modal-title">Process Refund</div>
    <form method="POST">
      <input type="hidden" name="action" value="refund">
      <input type="hidden" name="payment_id" id="refundPaymentId">

      <div class="form-group">
        <label for="refundReason">Reason for Refund *</label>
        <textarea id="refundReason" name="reason" placeholder="Explain why this payment is being refunded..." required></textarea>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn-close" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn-submit">↩️ Process Refund</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRefundModal(paymentId) {
  document.getElementById('refundPaymentId').value = paymentId;
  document.getElementById('refundModal').classList.add('active');
}

function closeModal() {
  document.getElementById('refundModal').classList.remove('active');
}

document.getElementById('refundModal').addEventListener('click', (e) => {
  if (e.target.id === 'refundModal') {
    closeModal();
  }
});
</script>
</body>
</html>
