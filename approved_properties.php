<?php
session_start();
include "db.php";

// Ensure landlord access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];

// Fetch approved properties
$stmt = $conn->prepare("SELECT p.id, p.title, p.location, p.price, p.bedrooms, p.bathrooms, p.created_at 
                        FROM properties p 
                        WHERE p.landlord_id=? AND p.status='approved' 
                        ORDER BY p.created_at DESC");
$stmt->bind_param("i", $landlord_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Approved Properties - RentConnect</title>
<style>
body { margin:0; font-family:Arial,sans-serif; background:#f7f9f7; }
header { background:#2e7d32; color:white; text-align:center; padding:20px; font-size:1.5em; }
.container { max-width:900px; margin:30px auto; padding:20px; background:white; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.1); }
h2 { text-align:center; color:#2e7d32; margin-bottom:20px; }
table { width:100%; border-collapse:collapse; }
th, td { padding:12px; border-bottom:1px solid #ddd; text-align:left; font-size:0.95em; }
th { background:#f0f0f0; }
.status-badge { padding:4px 10px; border-radius:6px; color:white; font-weight:bold; font-size:0.85em; }
.approved { background:#2E7D32; }
a.back { display:block; text-align:center; margin-top:15px; color:#2e7d32; text-decoration:none; font-weight:bold; }

/* Responsive */
@media(max-width:768px){
    table, thead, tbody, th, td, tr { display:block; }
    tr { margin-bottom:15px; border-bottom:1px solid #ccc; }
    th { display:none; }
    td { display:flex; justify-content:space-between; padding:8px; border:none; border-bottom:1px solid #eee; }
    td::before { content: attr(data-label); font-weight:bold; color:#555; flex:1; }
}
</style>
</head>
<body>

<header>My Approved Properties</header>
<div class="container">
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
                <th>Created</th>
                <th>Status</th>
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
                <td data-label="Created"><?= $row['created_at'] ?></td>
                <td data-label="Status"><span class="status-badge approved">Approved</span></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p style="text-align:center; color:#555;">You have no approved properties yet.</p>
    <?php endif; ?>

    <a href="landlord_dashboard.php" class="back">⬅ Back to Dashboard</a>
</div>

</body>
</html>
