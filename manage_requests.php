<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: login.php");
    exit;
}

$requests = $conn->query("
SELECT r.id AS request_id, r.status, r.created_at,
       p.title AS property_title, p.price AS property_price,
       u.name AS renter_name, u.email AS renter_email
FROM requests r
JOIN users u ON r.user_id = u.id
JOIN properties p ON r.property_id = p.id
ORDER BY r.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="apple-touch-icon" href="/favicon.svg" />
<link rel="icon" href="/favicon.svg" />
<title>Manage Requests - Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
<style>
:root {
  --ink: #1f2430;
  --muted: #5d6579;
  --brand: #1f8f67;
  --line: rgba(31, 36, 48, 0.12);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Manrope', sans-serif;
  color: var(--ink);
  background:
    radial-gradient(circle at 10% 4%, rgba(255, 122, 47, 0.22), transparent 34%),
    radial-gradient(circle at 92% 8%, rgba(31, 143, 103, 0.2), transparent 30%),
    linear-gradient(165deg, #f9f6ef 0%, #f2f7f8 58%, #fffdfa 100%);
}
.container { width: min(1120px, 94vw); margin: 0 auto; }
.hero {
  margin-top: 22px;
  border-radius: 18px;
  color: #fff;
  padding: 18px;
  background: linear-gradient(140deg, rgba(16, 62, 79, 0.93), rgba(31, 143, 103, 0.86));
}
.hero h1 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: clamp(1.4rem, 2.8vw, 2rem);
  letter-spacing: -0.02em;
  margin-bottom: 6px;
}
.panel {
  margin-top: 14px;
  background: rgba(255,255,255,0.93);
  border: 1px solid rgba(255,255,255,0.9);
  border-radius: 14px;
  padding: 14px;
  box-shadow: 0 10px 24px rgba(15, 31, 40, 0.09);
}
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; min-width: 760px; }
th, td { border-bottom: 1px solid var(--line); padding: 10px; text-align: left; font-size: 0.9rem; }
th { background: #f6f9fc; color: #415067; }
.status {
  display: inline-block;
  border-radius: 999px;
  padding: 4px 8px;
  color: #fff;
  font-size: 0.78rem;
  font-weight: 800;
}
.status.pending { background: #ff9f2f; }
.status.approved { background: #1f8f67; }
.status.declined { background: #e5554f; }
.back {
  display: inline-block;
  margin-top: 12px;
  text-decoration: none;
  color: var(--brand);
  font-weight: 700;
}
</style>
</head>
<body>
<main class="container">
  <section class="hero">
    <h1>All Rental Requests</h1>
    <p>Admin overview of renter demand across the platform.</p>
  </section>

  <section class="panel">
    <?php if ($requests->num_rows > 0): ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Renter</th>
              <th>Email</th>
              <th>Property</th>
              <th>Price</th>
              <th>Status</th>
              <th>Requested At</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($req = $requests->fetch_assoc()): ?>
              <tr>
                <td><?php echo (int) $req['request_id']; ?></td>
                <td><?php echo htmlspecialchars($req['renter_name']); ?></td>
                <td><?php echo htmlspecialchars($req['renter_email']); ?></td>
                <td><?php echo htmlspecialchars($req['property_title']); ?></td>
                <td>$<?php echo number_format($req['property_price']); ?></td>
                <td><span class="status <?php echo htmlspecialchars($req['status']); ?>"><?php echo ucfirst($req['status']); ?></span></td>
                <td><?php echo htmlspecialchars($req['created_at']); ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p>No rental requests found.</p>
    <?php endif; ?>

    <a href="admin_dashboard.php" class="back">Back to Dashboard</a>
  </section>
</main>
</body>
</html>
