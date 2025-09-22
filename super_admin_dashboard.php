<?php
session_start();
include "db.php"; // your DB connection

// Only allow super admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: login.php");
    exit();
}

// Handle approve / disable actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($_GET['action'] === 'approve') {
        $stmt = $conn->prepare("UPDATE properties SET status='approved' WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    if ($_GET['action'] === 'disable') {
        $stmt = $conn->prepare("UPDATE properties SET status='inactive' WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    header("Location: super_admin_dashboard.php");
    exit();
}

// Filter + Search
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination setup
$limit = 10; // number of properties per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Base query
$sql = "FROM properties p JOIN landlords l ON p.landlord_id = l.id WHERE 1=1";
$params = [];
$types = "";

// Apply filter
if ($status_filter !== 'all') {
    $sql .= " AND p.status=?";
    $params[] = $status_filter;
    $types .= "s";
}

// Apply search
if ($search !== '') {
    $sql .= " AND (p.title LIKE ? OR l.name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ss";
}

// Count total records for pagination
$count_sql = "SELECT COUNT(*) " . $sql;
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_stmt->bind_result($total_records);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = ceil($total_records / $limit);

// Fetch paginated data
$data_sql = "SELECT p.id, p.title, p.description, p.status, p.created_at, l.name AS landlord_name " . $sql . " ORDER BY p.created_at DESC LIMIT ?, ?";
$stmt = $conn->prepare($data_sql);

if (!empty($params)) {
    $stmt->bind_param($types . "ii", ...$params, $offset, $limit);
} else {
    $stmt->bind_param("ii", $offset, $limit);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Super Admin Dashboard - RentConnect</title>
  <style>
    body { font-family: Arial, sans-serif; background:#f5f7fb; margin:0; padding:0; }
    header { background:#2E7D32; color:#fff; padding:15px 25px; }
    header h1 { margin:0; font-size:1.4em; }
    .container { max-width:1100px; margin:40px auto; background:#fff; padding:25px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
    h2 { margin-top:0; }
    .filter-bar { margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; }
    .filter-bar form { display:flex; gap:10px; align-items:center; }
    .filter-bar select, .filter-bar input[type=text] {
      padding:8px 12px; border-radius:6px; border:1px solid #ccc;
      font-size:0.95em;
    }
    .filter-bar button {
      padding:8px 14px; border:none; border-radius:6px;
      background:#2E7D32; color:#fff; font-size:0.9em; cursor:pointer;
    }
    .filter-bar button:hover { background:#1b5e20; }
    table { width:100%; border-collapse: collapse; margin-top:10px; }
    table th, table td { padding:12px; text-align:left; border-bottom:1px solid #eee; }
    table th { background:#f0f0f0; }
    .badge { padding:4px 10px; border-radius:6px; font-size:0.85em; }
    .pending { background:#ff9800; color:#fff; }
    .approved { background:#2E7D32; color:#fff; }
    .taken { background:#1976D2; color:#fff; }
    .inactive { background:#9e9e9e; color:#fff; }
    a.btn { padding:8px 14px; border-radius:6px; text-decoration:none; font-size:0.9em; margin-right:6px; }
    .approve { background:#2E7D32; color:#fff; }
    .approve:hover { background:#1b5e20; }
    .disable { background:#d32f2f; color:#fff; }
    .disable:hover { background:#9a0007; }
    .pagination { margin-top:20px; text-align:center; }
    .pagination a {
      display:inline-block; padding:8px 12px; margin:0 3px;
      border:1px solid #ccc; border-radius:6px; text-decoration:none; color:#333;
    }
    .pagination a.active { background:#2E7D32; color:#fff; border-color:#2E7D32; }
    .pagination a:hover { background:#f0f0f0; }
  </style>
</head>
<body>

<header>
  <header style="display:flex; justify-content:space-between; align-items:center;">
  <h1>Super Admin Dashboard</h1>
  <a href="super_admin_logout.php" style="
      background:#d32f2f; color:#fff; padding:8px 12px; border-radius:6px; text-decoration:none; font-size:0.9em;
  ">Logout</a>
</header>

</header>

<div class="container">
  <h2>Manage Properties</h2>

  <!-- Filter + Search -->
  <div class="filter-bar">
    <form method="GET" action="">
      <label for="status">Filter:</label>
      <select name="status" id="status">
        <option value="all" <?= $status_filter=='all' ? 'selected' : '' ?>>All</option>
        <option value="pending" <?= $status_filter=='pending' ? 'selected' : '' ?>>Pending</option>
        <option value="approved" <?= $status_filter=='approved' ? 'selected' : '' ?>>Approved</option>
        <option value="taken" <?= $status_filter=='taken' ? 'selected' : '' ?>>Taken</option>
        <option value="inactive" <?= $status_filter=='inactive' ? 'selected' : '' ?>>Inactive</option>
      </select>

      <input type="text" name="search" placeholder="Search landlord or property..." value="<?= htmlspecialchars($search) ?>">
      
      <button type="submit">Apply</button>
    </form>
  </div>

  <!-- Table -->
  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Landlord</th>
        <th>Title</th>
        <th>Status</th>
        <th>Created</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while($row = $result->fetch_assoc()): ?>
        <tr>
          <td><?= $row['id'] ?></td>
          <td><?= htmlspecialchars($row['landlord_name']) ?></td>
          <td><?= htmlspecialchars($row['title']) ?></td>
          <td><span class="badge <?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
          <td><?= $row['created_at'] ?></td>
          <td>
            <?php if ($row['status'] === 'pending'): ?>
              <a href="?action=approve&id=<?= $row['id'] ?>" class="btn approve">Approve</a>
            <?php endif; ?>
            
            <?php if ($row['status'] === 'taken'): ?>
              <a href="?action=disable&id=<?= $row['id'] ?>" class="btn disable">Take Down</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="6" style="text-align:center; color:#999;">No properties found</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- Pagination -->
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="?status=<?= $status_filter ?>&search=<?= urlencode($search) ?>&page=<?= $page-1 ?>">« Prev</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
      <a href="?status=<?= $status_filter ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>

    <?php if ($page < $total_pages): ?>
      <a href="?status=<?= $status_filter ?>&search=<?= urlencode($search) ?>&page=<?= $page+1 ?>">Next »</a>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
