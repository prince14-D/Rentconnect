<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];

// Get overall income stats
$overall_sql = "
SELECT 
    COALESCE(SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END), 0) AS total_collected,
    COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS total_pending,
    COUNT(DISTINCT CASE WHEN status = 'approved' THEN id END) AS total_payments_approved,
    COUNT(DISTINCT CASE WHEN status = 'pending' THEN id END) AS total_payments_pending
FROM payments
WHERE landlord_id = ?
";
$stmt = $conn->prepare($overall_sql);
$stmt->bind_param("i", $landlord_id);
$stmt->execute();
$overall_stats = $stmt->get_result()->fetch_assoc();

// Get income by property
$property_sql = "
SELECT 
    p.id, p.title, p.location,
    COALESCE(SUM(CASE WHEN py.status = 'approved' THEN py.amount ELSE 0 END), 0) AS collected,
    COALESCE(SUM(CASE WHEN py.status = 'pending' THEN py.amount ELSE 0 END), 0) AS pending,
    COUNT(DISTINCT CASE WHEN py.status = 'approved' THEN py.id END) AS payments_received,
    COUNT(DISTINCT b.id) AS active_bookings
FROM properties p
LEFT JOIN bookings b ON p.id = b.property_id AND b.status = 'active'
LEFT JOIN payments py ON b.id = py.booking_id
WHERE p.landlord_id = ?
GROUP BY p.id, p.title, p.location
ORDER BY collected DESC
";
$stmt = $conn->prepare($property_sql);
$stmt->bind_param("i", $landlord_id);
$stmt->execute();
$property_income = $stmt->get_result();

// Get monthly income trend (last 12 months)
$monthly_sql = "
SELECT 
    DATE_FORMAT(payment_month, '%Y-%m') AS month,
    COALESCE(SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END), 0) AS amount
FROM payments
WHERE landlord_id = ? AND status = 'approved'
GROUP BY DATE_FORMAT(payment_month, '%Y-%m')
ORDER BY month DESC
LIMIT 12
";
$stmt = $conn->prepare($monthly_sql);
$stmt->bind_param("i", $landlord_id);
$stmt->execute();
$monthly_data = [];
while ($row = $stmt->get_result()->fetch_assoc()) {
    $monthly_data[] = $row;
}
$monthly_data = array_reverse($monthly_data);

// Get recent transactions
$recent_sql = "
SELECT 
    py.id, py.amount, py.status, py.created_at,
    p.title, u.name AS renter_name,
    DATE_FORMAT(py.payment_month, '%M %Y') AS payment_for
FROM payments py
JOIN properties p ON py.property_id = p.id
JOIN users u ON py.renter_id = u.id
WHERE py.landlord_id = ?
ORDER BY py.created_at DESC
LIMIT 10
";
$stmt = $conn->prepare($recent_sql);
$stmt->bind_param("i", $landlord_id);
$stmt->execute();
$recent_payments = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Landlord income analytics and payment tracking.">
<meta name="theme-color" content="#1f8f67">
<link rel="apple-touch-icon" href="/favicon.svg" />
<link rel="icon" href="/favicon.svg" />
<link rel="manifest" href="/manifest.json">
<title>Income Analytics - RentConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
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
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 16px;
  margin-bottom: 28px;
}
.stat-card {
  background: rgba(255,255,255,0.93);
  border-radius: 10px;
  padding: 20px;
  box-shadow: 0 10px 24px rgba(15, 31, 40, 0.09);
  border-left: 4px solid var(--brand);
  position: relative;
  overflow: hidden;
}
.stat-card::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -50%;
  width: 200px;
  height: 200px;
  background: radial-gradient(circle, rgba(31, 143, 103, 0.1), transparent);
  border-radius: 50%;
}
.stat-card-content {
  position: relative;
  z-index: 1;
}
.stat-label {
  color: var(--muted);
  font-size: 0.85rem;
  text-transform: uppercase;
  font-weight: 600;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 6px;
}
.stat-value {
  font-size: 2.2rem;
  font-weight: 800;
  color: var(--brand);
  font-family: 'Plus Jakarta Sans', sans-serif;
  letter-spacing: -0.01em;
}
.stat-subtext {
  font-size: 0.75rem;
  color: var(--muted);
  margin-top: 4px;
}
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
.chart-container {
  position: relative;
  height: 300px;
  margin-bottom: 16px;
}
.property-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 16px;
}
.property-card {
  background: linear-gradient(135deg, #f6f9fc 0%, #f9f6ef 100%);
  padding: 16px;
  border-radius: 10px;
  border-left: 4px solid var(--brand);
}
.property-name {
  font-weight: 700;
  color: var(--ink);
  margin-bottom: 4px;
}
.property-location {
  font-size: 0.85rem;
  color: var(--muted);
  margin-bottom: 12px;
}
.income-figures {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}
.figure {
  background: rgba(255,255,255,0.5);
  padding: 8px;
  border-radius: 6px;
}
.figure-label {
  font-size: 0.75rem;
  text-transform: uppercase;
  color: var(--muted);
  font-weight: 600;
}
.figure-value {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--brand);
  margin-top: 2px;
}
.pending-badge {
  color: var(--warning);
}
.table-responsive {
  overflow-x: auto;
}
.transaction-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}
.transaction-table th {
  background: rgba(31, 143, 103, 0.1);
  padding: 12px;
  text-align: left;
  font-weight: 600;
  color: var(--ink);
  border-bottom: 2px solid var(--line);
}
.transaction-table td {
  padding: 12px;
  border-bottom: 1px solid var(--line);
}
.transaction-table tr:hover {
  background: rgba(31, 143, 103, 0.05);
}
.status-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
}
.status-approved {
  background: #d1ecf1;
  color: #0c5460;
}
.status-pending {
  background: #fef3cd;
  color: #856404;
}
.status-rejected {
  background: #f8d7da;
  color: #721c24;
}
.empty-message {
  text-align: center;
  padding: 40px 20px;
  color: var(--muted);
}
.empty-message i { font-size: 2.5rem; margin-bottom: 12px; opacity: 0.3; }
@media (max-width: 768px) {
  .stats-grid { grid-template-columns: 1fr; }
  .property-grid { grid-template-columns: 1fr; }
  .income-figures { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<div class="container">
  <div class="hero">
    <h1>📈 Income Analytics</h1>
    <p>Track your rental income and payment performance</p>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-card-content">
        <div class="stat-label">💵 Total Collected</div>
        <div class="stat-value">$<?php echo number_format($overall_stats['total_collected'], 2); ?></div>
        <div class="stat-subtext"><?php echo $overall_stats['total_payments_approved']; ?> payments</div>
      </div>
    </div>
    <div class="stat-card" style="border-left-color: var(--warning);">
      <div class="stat-card-content">
        <div class="stat-label" style="color: var(--warning);">⏳ Pending Approval</div>
        <div class="stat-value" style="color: var(--warning);">$<?php echo number_format($overall_stats['total_pending'], 2); ?></div>
        <div class="stat-subtext"><?php echo $overall_stats['total_payments_pending']; ?> payments waiting</div>
      </div>
    </div>
    <div class="stat-card" style="border-left-color: var(--accent);">
      <div class="stat-card-content">
        <div class="stat-label" style="color: var(--accent);">📊 Total Income</div>
        <div class="stat-value" style="color: var(--accent);">$<?php echo number_format($overall_stats['total_collected'] + $overall_stats['total_pending'], 2); ?></div>
        <div class="stat-subtext">All status combined</div>
      </div>
    </div>
  </div>

  <?php if (count($monthly_data) > 0): ?>
  <div class="section">
    <h2>📅 Monthly Income Trend</h2>
    <div class="chart-container">
      <canvas id="monthlyChart"></canvas>
    </div>
  </div>
  <?php endif; ?>

  <div class="section">
    <h2>🏠 Income by Property</h2>
    <?php if ($property_income->num_rows === 0): ?>
      <div class="empty-message">
        <i class="fas fa-inbox"></i>
        <h3>No Properties Yet</h3>
        <p>Properties income will appear here once renters book and pay</p>
      </div>
    <?php else: ?>
      <div class="property-grid">
        <?php while ($prop = $property_income->fetch_assoc()): ?>
        <div class="property-card">
          <div class="property-name"><?php echo htmlspecialchars($prop['title']); ?></div>
          <div class="property-location">📍 <?php echo htmlspecialchars($prop['location']); ?></div>
          <div class="income-figures">
            <div class="figure">
              <div class="figure-label">Collected</div>
              <div class="figure-value">$<?php echo number_format($prop['collected'], 2); ?></div>
            </div>
            <div class="figure">
              <div class="figure-label pending-badge">Pending</div>
              <div class="figure-value pending-badge">$<?php echo number_format($prop['pending'], 2); ?></div>
            </div>
            <div class="figure">
              <div class="figure-label">Payments</div>
              <div class="figure-value"><?php echo $prop['payments_received']; ?></div>
            </div>
            <div class="figure">
              <div class="figure-label">Active</div>
              <div class="figure-value"><?php echo $prop['active_bookings']; ?></div>
            </div>
          </div>
        </div>
        <?php endwhile; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="section">
    <h2>💳 Recent Transactions</h2>
    <?php if ($recent_payments->num_rows === 0): ?>
      <div class="empty-message">
        <i class="fas fa-history"></i>
        <h3>No Transactions Yet</h3>
        <p>Payment history will appear here</p>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="transaction-table">
          <thead>
            <tr>
              <th>Property</th>
              <th>Renter</th>
              <th>Amount</th>
              <th>For</th>
              <th>Status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($txn = $recent_payments->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($txn['title']); ?></td>
              <td><?php echo htmlspecialchars($txn['renter_name']); ?></td>
              <td><strong>$<?php echo number_format($txn['amount'], 2); ?></strong></td>
              <td><?php echo htmlspecialchars($txn['payment_for']); ?></td>
              <td><span class="status-badge status-<?php echo strtolower($txn['status']); ?>"><?php echo ucfirst($txn['status']); ?></span></td>
              <td><?php echo date('M d, Y', strtotime($txn['created_at'])); ?></td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
<?php if (count($monthly_data) > 0): ?>
// Monthly income chart
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
const monthlyChart = new Chart(monthlyCtx, {
  type: 'bar',
  data: {
    labels: <?php echo json_encode(array_map(fn($d) => date('M Y', strtotime($d['month'])), $monthly_data)); ?>,
    datasets: [{
      label: 'Approved Income',
      data: <?php echo json_encode(array_column($monthly_data, 'amount')); ?>,
      backgroundColor: 'rgba(31, 143, 103, 0.8)',
      borderColor: '#1f8f67',
      borderWidth: 1,
      borderRadius: 6
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false }
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: {
          callback: function(value) { return '$' + value.toFixed(0); }
        }
      }
    }
  }
});
<?php endif; ?>

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
