<?php
session_start();
include "db.php";

// Ensure landlord access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];
$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Handle delete action
$message = '';
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt = $conn->prepare("DELETE FROM properties WHERE id=? AND landlord_id=?");
    $stmt->bind_param("ii", $delete_id, $landlord_id);
    if ($stmt->execute()) {
        $message = "✅ Property deleted successfully.";
    } else {
        $message = "❌ Failed to delete property.";
    }
}

// Base query: landlord's properties OR pending properties assigned to them
$sql = "SELECT * FROM properties 
        WHERE (landlord_id=? OR (status='pending' AND landlord_id=?))";
$params = [$landlord_id, $landlord_id];
$types = "ii";

// Apply status filter
if ($status_filter !== 'all') {
    $sql .= " AND status=?";
    $params[] = $status_filter;
    $types .= "s";
}

// Apply search filter
if ($search !== '') {
    $sql .= " AND (title LIKE ? OR location LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ss";
}

// Order by status priority and creation date
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
<title>My Properties - RentConnect</title>
<style>
body { margin:0; font-family:Arial,sans-serif; background:#f7f9f7; }
header { background:#2e7d32; color:white; text-align:center; padding:20px; font-size:1.5em; }
.container { max-width:1100px; margin:20px auto; padding:15px; background:white; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.1); }
h2 { text-align:center; color:#2e7d32; margin-bottom:20px; }

.filter-bar { display:flex; flex-wrap:wrap; gap:10px; justify-content:space-between; margin-bottom:20px; }
.filter-bar form { display:flex; flex-wrap:wrap; gap:10px; width:100%; }
.filter-bar select, .filter-bar input[type=text] { padding:8px 12px; border-radius:6px; border:1px solid #ccc; flex:1; min-width:120px; }
.filter-bar button { padding:8px 14px; border:none; border-radius:6px; background:#2e7d32; color:#fff; cursor:pointer; }
.filter-bar button:hover { background:#1b5e20; }

table { width:100%; border-collapse:collapse; }
th, td { padding:10px; border-bottom:1px solid #ddd; text-align:left; font-size:0.95em; }
th { background:#f0f0f0; }
.status-badge { padding:4px 10px; border-radius:6px; color:white; font-weight:bold; font-size:0.85em; }
.pending { background:#ffa000; }
.approved { background:#2e7d32; }
.taken { background:#1976d2; }
.inactive { background:#9e9e9e; }

a.btn { padding:6px 12px; border-radius:6px; text-decoration:none; font-size:0.85em; margin:2px 2px 2px 0; color:white; display:inline-block; }
.edit { background:#1976d2; }
.edit:hover { background:#0d47a1; }
.delete { background:#f44336; }
.delete:hover { background:#b71c1c; }

.message { text-align:center; color:green; font-weight:bold; margin-bottom:15px; }
a.back { display:block; text-align:center; margin-top:15px; color:#2e7d32; text-decoration:none; font-weight:bold; }

/* Responsive */
@media(max-width:768px){
    table, thead, tbody, th, td, tr { display:block; }
    tr { margin-bottom:15px; border-bottom:1px solid #ccc; }
    th { display:none; }
    td { display:flex; justify-content:space-between; padding:8px; border:none; border-bottom:1px solid #eee; }
    td::before { content: attr(data-label); font-weight:bold; color:#555; flex:1; }
    td span.status-badge { display:inline-block; }
    .filter-bar form { flex-direction:column; align-items:flex-start; }
}
</style>
<script>
function confirmDelete(id){
    if(confirm("Are you sure you want to delete this property?")){
        window.location.href = "?delete_id=" + id;
    }
}
</script>
</head>
<body>

<header>My Properties</header>
<div class="container">
    <?php if($message) echo "<p class='message'>$message</p>"; ?>

    <div class="filter-bar">
        <form method="GET">
            <select name="status">
                <option value="all" <?= $status_filter=='all'?'selected':'' ?>>All</option>
                <option value="pending" <?= $status_filter=='pending'?'selected':'' ?>>Pending</option>
                <option value="approved" <?= $status_filter=='approved'?'selected':'' ?>>Approved</option>
                <option value="taken" <?= $status_filter=='taken'?'selected':'' ?>>Taken</option>
                <option value="inactive" <?= $status_filter=='inactive'?'selected':'' ?>>Inactive</option>
            </select>
            <input type="text" name="search" placeholder="Search title or location..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit">Apply</button>
        </form>
    </div>

    <?php if($result && $result->num_rows>0): ?>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Location</th>
                <th>Price</th>
                <th>Bedrooms</th>
                <th>Bathrooms</th>
                <th>Submitted On</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td data-label="ID"><?= $row['id'] ?></td>
                <td data-label="Title"><?= htmlspecialchars($row['title']) ?></td>
                <td data-label="Location"><?= htmlspecialchars($row['location']) ?></td>
                <td data-label="Price">$<?= number_format($row['price'],2) ?></td>
                <td data-label="Bedrooms"><?= $row['bedrooms'] ?></td>
                <td data-label="Bathrooms"><?= $row['bathrooms'] ?></td>
                <td data-label="Submitted On"><?= $row['created_at'] ?></td>
                <td data-label="Status"><span class="status-badge <?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
                <td data-label="Actions">
                    <a href="edit_property.php?id=<?= $row['id'] ?>" class="btn edit">Edit</a>
                    <a href="javascript:void(0)" onclick="confirmDelete(<?= $row['id'] ?>)" class="btn delete">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p style="text-align:center; color:#555;">No properties found.</p>
    <?php endif; ?>

    <a href="landlord_dashboard.php" class="back">⬅ Back to Dashboard</a>
</div>

</body>
</html>
