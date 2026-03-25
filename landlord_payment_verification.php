<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];

// Handle payment approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_id = intval($_POST['payment_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $note = $_POST['note'] ?? '';

    if ($payment_id > 0 && in_array($action, ['approve', 'reject'])) {
        // Verify payment belongs to this landlord
        $verify = $conn->prepare("
            SELECT * FROM payments WHERE id = ? AND landlord_id = ?
        ");
        $verify->bind_param("ii", $payment_id, $landlord_id);
        $verify->execute();
        $payment = $verify->get_result()->fetch_assoc();

        if ($payment) {
            $status = $action === 'approve' ? 'confirmed' : 'rejected';
            
            $update = $conn->prepare("
                UPDATE payments 
                SET status = ?, verified_at = NOW(), verification_note = ?
                WHERE id = ?
            ");
            $update->bind_param("ssi", $status, $note, $payment_id);
            $update->execute();

            // Redirect with message
            header("Location: {$_SERVER['PHP_SELF']}?message=" . urlencode(ucfirst($action) . " successful"));
            exit;
        }
    }
}

// Fetch pagination
$per_page = 15;
$page = intval($_GET['page'] ?? 1);
$offset = ($page - 1) * $per_page;
$filter = $_GET['filter'] ?? 'all';

// Build query based on filter
$where = "p.landlord_id = ?";
if ($filter === 'pending') {
    $where .= " AND p.status = 'pending'";
} elseif ($filter === 'confirmed') {
    $where .= " AND p.status = 'confirmed'";
} elseif ($filter === 'rejected') {
    $where .= " AND p.status = 'rejected'";
} elseif ($filter === 'submitted') {
    $where .= " AND p.status = 'submitted'";
}

// Fetch payments
$sql = "
    SELECT p.*, b.property_id, pr.title, r.name as renter_name, r.email as renter_email, r.phone as renter_phone
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    JOIN properties pr ON b.property_id = pr.id
    JOIN users r ON p.renter_id = r.id
    WHERE $where
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $landlord_id, $per_page, $offset);
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total count
$count_sql = "
    SELECT COUNT(*) as total FROM payments p
    WHERE $where
";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("s", $landlord_id);
$count_stmt->execute();
$count = $count_stmt->get_result()->fetch_assoc();
$total_pages = ceil($count['total'] / $per_page);

// Get statistics
$stats_stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN status = 'confirmed' THEN amount ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'submitted' OR status = 'pending' THEN amount ELSE 0 END) as pending,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count
    FROM payments WHERE landlord_id = ?
");
$stats_stmt->bind_param("i", $landlord_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="badge pending">⏳ Pending</span>',
        'submitted' => '<span class="badge submitted">📤 Submitted for Review</span>',
        'confirmed' => '<span class="badge confirmed">✓ Confirmed</span>',
        'rejected' => '<span class="badge rejected">✗ Rejected</span>'
    ];
    return $badges[$status] ?? $status;
}

function getPaymentMethodIcon($method) {
    $icons = [
        'card' => '💳 Card',
        'momo' => '📱 Lonestar Momo',
        'bank_transfer' => '🏦 Bank Transfer',
        'check' => '✓ Check',
        'money_order' => '📬 Money Order'
    ];
    return $icons[$method] ?? $method;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Verify and manage rental payments from tenants.">
<meta name="theme-color" content="#1f8f67">
<title>Payment Verification - RentConnect</title>
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
  max-width: 1200px;
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

.header-nav a {
  color: white;
  text-decoration: none;
  padding: 10px 16px;
  background: rgba(255,255,255,0.2);
  border-radius: 6px;
  transition: all 0.2s;
  font-weight: 600;
  font-size: 0.9rem;
}

.header-nav a:hover {
  background: rgba(255,255,255,0.3);
}

.container {
  max-width: 1200px;
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
  margin-bottom: 24px;
}

.stat-card {
  background: linear-gradient(135deg, rgba(31, 143, 103, 0.1), rgba(31, 143, 103, 0.05));
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 20px;
  text-align: center;
}

.stat-value {
  font-size: 2rem;
  font-weight: 800;
  color: var(--brand);
  margin-bottom: 8px;
}

.stat-label {
  font-size: 0.85rem;
  color: var(--muted);
  text-transform: uppercase;
}

.filters {
  display: flex;
  gap: 8px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}

.filter-btn {
  padding: 10px 16px;
  border: 2px solid var(--line);
  background: white;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.2s;
  color: var(--ink);
}

.filter-btn:hover {
  border-color: var(--brand);
  color: var(--brand);
}

.filter-btn.active {
  background: var(--brand);
  border-color: var(--brand);
  color: white;
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

.badge {
  display: inline-block;
  padding: 6px 12px;
  border-radius: 6px;
  font-size: 0.85rem;
  font-weight: 600;
}

.badge.pending {
  background: rgba(243, 156, 18, 0.2);
  color: #d68910;
}

.badge.submitted {
  background: rgba(52, 152, 219, 0.2);
  color: #2980b9;
}

.badge.confirmed {
  background: rgba(39, 174, 96, 0.2);
  color: #27ae60;
}

.badge.rejected {
  background: rgba(192, 57, 43, 0.2);
  color: #c0392b;
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

.btn-approve, .btn-reject, .btn-details {
  padding: 8px 12px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  font-size: 0.85rem;
  transition: all 0.2s;
}

.btn-approve {
  background: #27ae60;
  color: white;
}

.btn-approve:hover {
  background: #229954;
}

.btn-reject {
  background: #c0392b;
  color: white;
}

.btn-reject:hover {
  background: #a93226;
}

.btn-details {
  background: var(--brand);
  color: white;
}

.btn-details:hover {
  background: var(--brand-deep);
}

/* Modal */
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

.modal-body {
  margin-bottom: 20px;
}

.modal-info {
  display: flex;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid var(--line);
  font-size: 0.95rem;
}

.modal-label {
  font-weight: 600;
  color: var(--ink);
}

.modal-value {
  color: var(--muted);
}

.form-group {
  margin-bottom: 16px;
}

label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: var(--ink);
  font-size: 0.9rem;
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
}

.btn-close, .btn-submit {
  flex: 1;
  padding: 12px;
  border: none;
  border-radius: 8px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-close {
  background: rgba(31, 36, 48, 0.1);
  color: var(--ink);
}

.btn-close:hover {
  background: rgba(31, 36, 48, 0.15);
}

.btn-submit {
  background: var(--brand);
  color: white;
}

.btn-submit:hover {
  background: var(--brand-deep);
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

@media (max-width: 768px) {
  .header-content {
    flex-direction: column;
    gap: 12px;
  }

  .payments-table {
    font-size: 0.85rem;
  }

  .payments-table th, .payments-table td {
    padding: 12px;
  }

  .action-buttons {
    flex-direction: column;
  }
}
</style>
</head>
<body>
<header>
  <div class="header-content">
    <div class="header-title">
      <h1>💳 Payment Verification</h1>
      <p>Review and approve tenant payments</p>
    </div>
    <div class="header-nav">
      <a href="landlord_dashboard.php">← Back to Dashboard</a>
    </div>
  </div>
</header>

<div class="container">
  <!-- Statistics -->
  <div class="section">
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-value">$<?php echo number_format($stats['confirmed'] ?? 0, 2); ?></div>
        <div class="stat-label">Confirmed</div>
      </div>
      <div class="stat-card">
        <div class="stat-value">$<?php echo number_format($stats['pending'] ?? 0, 2); ?></div>
        <div class="stat-label">Pending Review</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?php echo $stats['rejected_count'] ?? 0; ?></div>
        <div class="stat-label">Rejected</div>
      </div>
    </div>
  </div>

  <!-- Filters and Payments -->
  <div class="section">
    <h2 class="section-title">📋 Payments</h2>

    <?php if (isset($_GET['message'])): ?>
    <div class="message">✓ <?php echo htmlspecialchars($_GET['message']); ?></div>
    <?php endif; ?>

    <div class="filters">
      <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
      <a href="?filter=pending" class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">⏳ Pending</a>
      <a href="?filter=submitted" class="filter-btn <?php echo $filter === 'submitted' ? 'active' : ''; ?>">📤 Submitted</a>
      <a href="?filter=confirmed" class="filter-btn <?php echo $filter === 'confirmed' ? 'active' : ''; ?>">✓ Confirmed</a>
      <a href="?filter=rejected" class="filter-btn <?php echo $filter === 'rejected' ? 'active' : ''; ?>">✗ Rejected</a>
    </div>

    <?php if (!empty($payments)): ?>
    <table class="payments-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Tenant</th>
          <th>Property</th>
          <th>Month</th>
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
          <td><strong><?php echo htmlspecialchars($p['renter_name']); ?></strong></td>
          <td><?php echo htmlspecialchars(substr($p['title'], 0, 20)); ?></td>
          <td><?php echo date('F Y', strtotime($p['payment_month'])); ?></td>
          <td class="amount">$<?php echo number_format($p['amount'], 2); ?></td>
          <td><?php echo getPaymentMethodIcon($p['payment_method']); ?></td>
          <td><?php echo getStatusBadge($p['status']); ?></td>
          <td>
            <div class="action-buttons">
              <?php if ($p['status'] === 'submitted' || $p['status'] === 'pending'): ?>
                <button class="btn-approve" onclick="approvePayment(<?php echo $p['id']; ?>)">✓ Approve</button>
                <button class="btn-reject" onclick="rejectPayment(<?php echo $p['id']; ?>)">✗ Reject</button>
              <?php endif; ?>
              <button class="btn-details" onclick="showDetails(<?php echo $p['id']; ?>)">📋 Details</button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="?page=1&filter=<?php echo $filter; ?>">« First</a>
        <a href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>">Previous</a>
      <?php endif; ?>

      <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
        <?php if ($i === $page): ?>
          <span class="active"><?php echo $i; ?></span>
        <?php else: ?>
          <a href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>"><?php echo $i; ?></a>
        <?php endif; ?>
      <?php endfor; ?>

      <?php if ($page < $total_pages): ?>
        <a href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>">Next</a>
        <a href="?page=<?php echo $total_pages; ?>&filter=<?php echo $filter; ?>">Last »</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <div style="text-align: center; padding: 40px; color: var(--muted);">
      <p style="font-size: 1.2rem; margin-bottom: 12px;">📭 No payments found</p>
      <p>All payments have been processed!</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Approval Modal -->
<div id="approveModal" class="modal">
  <div class="modal-content">
    <div class="modal-title">Approve Payment</div>
    <form id="approveForm" method="POST">
      <input type="hidden" name="payment_id" id="approvePaymentId">
      <input type="hidden" name="action" value="approve">
      
      <div class="form-group">
        <label for="approveNote">Verification Note (Optional)</label>
        <textarea id="approveNote" name="note" placeholder="Add any notes about this payment..."></textarea>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn-close" onclick="closeModal('approveModal')">Cancel</button>
        <button type="submit" class="btn-submit">✓ Confirm Approval</button>
      </div>
    </form>
  </div>
</div>

<!-- Rejection Modal -->
<div id="rejectModal" class="modal">
  <div class="modal-content">
    <div class="modal-title">Reject Payment</div>
    <form id="rejectForm" method="POST">
      <input type="hidden" name="payment_id" id="rejectPaymentId">
      <input type="hidden" name="action" value="reject">
      
      <div class="form-group">
        <label for="rejectNote">Reason for Rejection *</label>
        <textarea id="rejectNote" name="note" placeholder="Why are you rejecting this payment?" required></textarea>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn-close" onclick="closeModal('rejectModal')">Cancel</button>
        <button type="submit" class="btn-submit">✗ Confirm Rejection</button>
      </div>
    </form>
  </div>
</div>

<script>
function approvePayment(paymentId) {
  document.getElementById('approvePaymentId').value = paymentId;
  document.getElementById('approveModal').classList.add('active');
}

function rejectPayment(paymentId) {
  document.getElementById('rejectPaymentId').value = paymentId;
  document.getElementById('rejectModal').classList.add('active');
}

function closeModal(modalId) {
  document.getElementById(modalId).classList.remove('active');
}

function showDetails(paymentId) {
  alert('Payment details view would show full information. Feature coming soon!');
}

// Close modal when clicking outside
document.querySelectorAll('.modal').forEach(modal => {
  modal.addEventListener('click', (e) => {
    if (e.target === modal) {
      modal.classList.remove('active');
    }
  });
});
</script>
</body>
</html>
