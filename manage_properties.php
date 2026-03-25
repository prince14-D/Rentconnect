<?php
session_start();
include "app_init.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Approve / Decline / Delete
if (isset($_GET['approve'])) {
    $id = intval($_GET['approve']);
  rc_mig_set_property_status($conn, $id, 'approved');
    header("Location: manage_properties.php");
    exit;
}
if (isset($_GET['decline'])) {
    $id = intval($_GET['decline']);
  rc_mig_set_property_status($conn, $id, 'declined');
    header("Location: manage_properties.php");
    exit;
}
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
  rc_mig_delete_property_with_images($conn, $id);
    header("Location: manage_properties.php");
    exit;
}

// Fetch properties
$properties = rc_mig_get_manage_properties_rows($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="apple-touch-icon" href="/favicon.svg" />
<link rel="icon" href="/favicon.svg" />
<title>Manage Properties - Admin</title>
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
}
.container { width: min(1180px, 95vw); margin: 0 auto; }
.hero {
  margin-top: 22px;
  border-radius: 20px;
  color: #fff;
  padding: clamp(18px, 4vw, 28px);
  background: linear-gradient(140deg, rgba(16, 62, 79, 0.93), rgba(31, 143, 103, 0.86));
}
.hero h1 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: clamp(1.45rem, 2.8vw, 2.05rem);
  letter-spacing: -0.02em;
}
.panel {
  margin-top: 12px;
  background: rgba(255, 255, 255, 0.93);
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: 14px;
  box-shadow: 0 10px 24px rgba(15, 31, 40, 0.09);
  padding: 12px;
}
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; min-width: 860px; }
th, td { border-bottom: 1px solid var(--line); padding: 10px; text-align: left; font-size: 0.9rem; }
th { background: #f6f9fc; color: #415067; }

.status {
  display: inline-block;
  border-radius: 999px;
  padding: 4px 9px;
  color: #fff;
  font-size: 0.78rem;
  font-weight: 800;
}
.status.pending { background: #ff9f2f; }
.status.approved { background: #1f8f67; }
.status.declined { background: #e5554f; }
.status.taken { background: #2374cc; }

.actions a {
  display: inline-block;
  text-decoration: none;
  color: #fff;
  font-size: 0.82rem;
  font-weight: 700;
  border-radius: 8px;
  padding: 7px 10px;
  margin-right: 4px;
}
.approve { background: linear-gradient(140deg, #1f8f67, #15543e); }
.decline { background: linear-gradient(140deg, #e5554f, #d43c35); }
.delete { background: linear-gradient(140deg, #5a6478, #414a5d); }
.back {
  display: inline-block;
  margin-top: 10px;
  text-decoration: none;
  color: var(--brand-deep);
  font-weight: 700;
}
</style>
</head>
<body>
<main class="container">
  <section class="hero">
    <h1>Manage Properties</h1>
  </section>

  <section class="panel">
    <?php if (count($properties) > 0): ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Title</th>
              <th>Location</th>
              <th>Price</th>
              <th>Owner</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($properties as $prop): ?>
              <tr>
                <td><?php echo (int) $prop['id']; ?></td>
                <td><?php echo htmlspecialchars($prop['title']); ?></td>
                <td><?php echo htmlspecialchars($prop['location']); ?></td>
                <td>$<?php echo number_format($prop['price']); ?></td>
                <td><?php echo htmlspecialchars($prop['owner']); ?></td>
                <td><span class="status <?php echo htmlspecialchars($prop['status']); ?>"><?php echo ucfirst(htmlspecialchars($prop['status'])); ?></span></td>
                <td class="actions">
                  <?php if ($prop['status'] == 'pending'): ?>
                    <a href="?approve=<?php echo (int) $prop['id']; ?>" class="approve">Approve</a>
                    <a href="?decline=<?php echo (int) $prop['id']; ?>" class="decline">Decline</a>
                  <?php endif; ?>
                  <a href="?delete=<?php echo (int) $prop['id']; ?>" class="delete" onclick="return confirm('Delete this property?')">Delete</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p>No properties found.</p>
    <?php endif; ?>

    <a href="admin_dashboard.php" class="back">Back to Dashboard</a>
  </section>
</main>
</body>
</html>
