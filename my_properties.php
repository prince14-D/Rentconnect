<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];
$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$message = '';
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM properties WHERE id=? AND landlord_id=?");
    $stmt->bind_param("ii", $delete_id, $landlord_id);
    if ($stmt->execute()) {
        $message = "Property deleted successfully.";
    } else {
        $message = "Failed to delete property.";
    }
}

$sql = "SELECT * FROM properties
        WHERE (landlord_id=? OR (status='pending' AND landlord_id=?))";
$params = [$landlord_id, $landlord_id];
$types = "ii";

if ($status_filter !== 'all') {
    $sql .= " AND status=?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($search !== '') {
    $sql .= " AND (title LIKE ? OR location LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ss";
}

$sql .= " ORDER BY FIELD(status,'pending','approved','taken','inactive'), created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="apple-touch-icon" href="/favicon.svg" />
<link rel="icon" href="/favicon.svg" />
<title>My Properties - RentConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
<style>
:root {
  --ink: #1f2430;
  --muted: #5d6579;
  --brand: #1f8f67;
  --brand-deep: #15543e;
  --accent: #ff7a2f;
  --line: rgba(31, 36, 48, 0.12);
  --shadow: 0 18px 36px rgba(19, 36, 33, 0.14);
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
.container { width: min(1140px, 94vw); margin: 0 auto; }
.header-card {
  margin-top: 22px;
  border-radius: 18px;
  color: #fff;
  padding: 18px;
  background: linear-gradient(140deg, rgba(16, 62, 79, 0.93), rgba(31, 143, 103, 0.86));
  box-shadow: var(--shadow);
}
.header-card h1 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: clamp(1.4rem, 2.8vw, 2rem);
  letter-spacing: -0.02em;
  margin-bottom: 6px;
}
.header-card p { color: rgba(255,255,255,0.92); }

.panel {
  margin-top: 14px;
  background: rgba(255,255,255,0.93);
  border: 1px solid rgba(255,255,255,0.9);
  border-radius: 14px;
  box-shadow: 0 10px 24px rgba(15, 31, 40, 0.09);
  padding: 14px;
}

.message {
  padding: 10px 12px;
  border-radius: 10px;
  margin-bottom: 10px;
  color: #145734;
  background: rgba(39, 165, 106, 0.12);
  border: 1px solid rgba(39, 165, 106, 0.3);
  font-weight: 700;
}

.filter-form {
  display: grid;
  grid-template-columns: 180px 1fr auto;
  gap: 8px;
}
select, input, button {
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 10px 12px;
  font: inherit;
}
button {
  border: none;
  color: #fff;
  background: linear-gradient(140deg, var(--brand), var(--brand-deep));
  font-weight: 700;
  cursor: pointer;
}

.table-wrap { overflow-x: auto; margin-top: 10px; }
table { width: 100%; border-collapse: collapse; min-width: 860px; }
th, td { border-bottom: 1px solid var(--line); padding: 10px; text-align: left; font-size: 0.9rem; }
th { background: #f6f9fc; color: #415067; }

.status-badge {
  display: inline-block;
  padding: 4px 9px;
  border-radius: 999px;
  color: #fff;
  font-size: 0.78rem;
  font-weight: 800;
}
.pending { background: #ff9f2f; }
.approved { background: #1f8f67; }
.taken { background: #2374cc; }
.inactive { background: #8d97ac; }

.actions a {
  text-decoration: none;
  border-radius: 8px;
  padding: 7px 9px;
  font-size: 0.82rem;
  font-weight: 700;
  color: #fff;
  display: inline-block;
  margin-right: 4px;
}
.actions .edit { background: linear-gradient(140deg, #2374cc, #1b5ca3); }
.actions .delete { background: linear-gradient(140deg, #e5554f, #d43c35); }

.back {
  display: inline-block;
  margin-top: 12px;
  text-decoration: none;
  font-weight: 700;
  color: #15543e;
}

@media (max-width: 760px) {
  .filter-form { grid-template-columns: 1fr; }
  button { width: 100%; }
}
</style>
<script>
function confirmDelete(id) {
  if (confirm("Are you sure you want to delete this property?")) {
    window.location.href = "?delete_id=" + id;
  }
}
</script>
</head>
<body>
<main class="container">
  <section class="header-card">
    <h1>My Properties</h1>
    <p>Filter listings, review statuses, and manage your portfolio quickly.</p>
  </section>

  <section class="panel">
    <?php if ($message): ?>
      <p class="message"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form class="filter-form" method="GET">
      <select name="status">
        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All</option>
        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
        <option value="taken" <?php echo $status_filter == 'taken' ? 'selected' : ''; ?>>Taken</option>
        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
      </select>
      <input type="text" name="search" placeholder="Search title or location" value="<?php echo htmlspecialchars($search); ?>">
      <button type="submit">Apply</button>
    </form>

    <?php if ($result && $result->num_rows > 0): ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>ID</th><th>Title</th><th>Location</th><th>Price</th><th>Bedrooms</th><th>Bathrooms</th><th>Submitted</th><th>Status</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
              <tr>
                <td><?php echo (int) $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['title']); ?></td>
                <td><?php echo htmlspecialchars($row['location']); ?></td>
                <td>$<?php echo number_format($row['price'], 2); ?></td>
                <td><?php echo (int) $row['bedrooms']; ?></td>
                <td><?php echo (int) $row['bathrooms']; ?></td>
                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                <td><span class="status-badge <?php echo htmlspecialchars($row['status']); ?>"><?php echo ucfirst($row['status']); ?></span></td>
                <td class="actions">
                  <a href="edit_property.php?id=<?php echo (int) $row['id']; ?>" class="edit">Edit</a>
                  <a href="javascript:void(0)" class="delete" onclick="confirmDelete(<?php echo (int) $row['id']; ?>)">Delete</a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <p>No properties found.</p>
    <?php endif; ?>

    <a href="landlord_dashboard.php" class="back">Back to Dashboard</a>
  </section>
</main>
</body>
</html>
