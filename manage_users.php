<?php
session_start();
include "app_init.php";

// Ensure admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Handle block/unblock/delete
if (isset($_GET['block'])) {
    $id = intval($_GET['block']);
    $conn->query("UPDATE users SET status='blocked' WHERE id=$id");
    header("Location: manage_users.php");
    exit;
}
if (isset($_GET['unblock'])) {
    $id = intval($_GET['unblock']);
    $conn->query("UPDATE users SET status='active' WHERE id=$id");
    header("Location: manage_users.php");
    exit;
}
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM users WHERE id=$id");
    header("Location: manage_users.php");
    exit;
}

// Fetch all users
$users = $conn->query("SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="apple-touch-icon" href="/favicon.svg" />
<link rel="icon" href="/favicon.svg" />
<title>Manage Users - Admin</title>
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
table { width: 100%; border-collapse: collapse; min-width: 820px; }
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
.status.active { background: #1f8f67; }
.status.blocked { background: #e5554f; }

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
.block { background: linear-gradient(140deg, #e5554f, #d43c35); }
.unblock { background: linear-gradient(140deg, #1f8f67, #15543e); }
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
    <h1>Manage Users</h1>
  </section>

  <section class="panel">
    <?php if ($users->num_rows > 0): ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($user = $users->fetch_assoc()): ?>
              <?php $status = $user['status'] ?? 'active'; ?>
              <tr>
                <td><?php echo (int) $user['id']; ?></td>
                <td><?php echo htmlspecialchars($user['name']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo ucfirst(htmlspecialchars($user['role'])); ?></td>
                <td><span class="status <?php echo htmlspecialchars($status); ?>"><?php echo ucfirst(htmlspecialchars($status)); ?></span></td>
                <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                <td class="actions">
                  <?php if ($status !== 'blocked'): ?>
                    <a href="?block=<?php echo (int) $user['id']; ?>" class="block">Block</a>
                  <?php else: ?>
                    <a href="?unblock=<?php echo (int) $user['id']; ?>" class="unblock">Unblock</a>
                  <?php endif; ?>
                  <a href="?delete=<?php echo (int) $user['id']; ?>" class="delete" onclick="return confirm('Delete this user?')">Delete</a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p>No users found.</p>
    <?php endif; ?>

    <a href="admin_dashboard.php" class="back">Back to Dashboard</a>
  </section>
</main>
</body>
</html>
