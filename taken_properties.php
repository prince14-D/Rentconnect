<?php
session_start();
include "db.php";

// Ensure landlord access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];
$message = "";

// Optional search by title or location
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch taken properties
$sql = "SELECT * FROM properties WHERE owner_id=? AND status='taken'";
$params = [$landlord_id];
$types = "i";

if ($search !== '') {
    $sql .= " AND (title LIKE ? OR location LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ss";
}

$sql .= " ORDER BY created_at DESC";
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
<title>Taken Properties - RentConnect</title>
<style>
body { margin:0; font-family:Arial,sans-serif; background:#f7f9f7; }
header { background:#2e7d32; color:white; text-align:center; padding:20px; font-size:1.5em; }
.container { max-width:1100px; margin:30px auto; padding:20px; background:white; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.1); }
h2 { text-align:center; color:#2e7d32; margin-bottom:20px; }

.filter-bar { margin-bottom:20px; display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px; }
.filter-bar form { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
.filter-bar input[type=text] { padding:8px 12px; border-radius:6px; border:1px solid #ccc; }
.filter-bar button { padding:8px 14px; border:none; border-radius:6px; background:#2e7d32; color:#fff; cursor:pointer; }
.filter-bar button:hover { background:#1b5e20; }

table { width:100%; border-collapse:collapse; }
th, td { padding:12px; border-bottom:1px solid #ddd; text-align:left; }
th { background:#f0f0f0; }
.status-badge { padding:4px 10px; border-radius:6px; color:white; font-weight:bold; font-size:0.9em; }
.taken { background:#1976d2; }

a.back { display:block; text-align:center; margin-top:15px; color:#2e7d32; text-decoration:none; font-weight:bold; }
</style>
</head>
<body>

<header>Taken Properties</header>
<div class="container">
    <div class="filter-bar">
        <form method="GET">
            <input type="text" name="search" placeholder="Search title or location..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit">Search</button>
        </form>
    </div>

    <?php if($result && $result->num_rows > 0): ?>
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
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['title']) ?></td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td>$<?= number_format($row['price'],2) ?></td>
                <td><?= $row['bedrooms'] ?></td>
                <td><?= $row['bathrooms'] ?></td>
                <td><?= $row['created_at'] ?></td>
                <td><span class="status-badge taken">Taken</span></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p style="text-align:center; color:#555;">No taken properties found.</p>
    <?php endif; ?>

    <a href="landlord_dashboard.php" class="back">⬅ Back to Dashboard</a>
</div>

</body>
</html>
