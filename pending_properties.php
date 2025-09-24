<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT p.id, p.title, p.location, p.price, p.bedrooms, p.bathrooms, p.created_at 
                        FROM properties p 
                        WHERE p.landlord_id=? AND p.status='pending' 
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
<title>Pending Properties - RentConnect</title>
<style>
body {
    margin:0;
    font-family:Arial,sans-serif;
    background:#f7f9f7;
}

header {
    background:#ffa000;
    color:white;
    text-align:center;
    padding:20px;
    font-size:1.5em;
}

.container {
    max-width:1000px;
    margin:20px auto;
    padding:15px;
    background:white;
    border-radius:12px;
    box-shadow:0 4px 10px rgba(0,0,0,0.1);
    overflow-x:auto;
}

h2 { text-align:center; color:#ffa000; margin-bottom:20px; }

table {
    width:100%;
    border-collapse:collapse;
    min-width:700px; /* allows horizontal scroll on mobile */
}

th, td {
    padding:12px;
    border-bottom:1px solid #ddd;
    text-align:left;
}

th {
    background:#f0f0f0;
}

.status-badge {
    padding:5px 12px;
    border-radius:12px;
    color:white;
    font-weight:bold;
    font-size:0.85em;
}

.pending { background:#ffa000; }

a.back {
    display:block;
    text-align:center;
    margin-top:15px;
    color:#2e7d32;
    text-decoration:none;
    font-weight:bold;
}

.actions {
    display:flex;
    gap:6px;
    flex-wrap:wrap;
}

.action-btn {
    padding:6px 12px;
    border-radius:8px;
    text-decoration:none;
    font-size:0.85em;
    font-weight:bold;
    color:#fff;
    transition:0.2s;
}

.edit {
    background:#2E7D32;
}
.edit:hover { background:#1b5e20; }

.delete {
    background:#d32f2f;
}
.delete:hover { background:#9a0007; }

/* Responsive for small screens */
@media(max-width:600px){
    th, td {
        padding:8px;
        font-size:0.85em;
    }
    .action-btn {
        padding:5px 10px;
        font-size:0.8em;
    }
    .container {
        padding:10px;
    }
}
</style>
</head>
<body>

<header>My Pending Properties</header>
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
                <th>Submitted On</th>
                <th>Status</th>
                <th>Actions</th>
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
                <td><span class="status-badge pending">Pending</span></td>
                <td class="actions">
                    <a href="edit_property.php?id=<?= $row['id'] ?>" class="action-btn edit">Edit</a>
                    <a href="delete_property.php?id=<?= $row['id'] ?>" class="action-btn delete" onclick="return confirm('Are you sure?')">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p style="text-align:center; color:#555;">You have no pending properties.</p>
    <?php endif; ?>

    <a href="landlord_dashboard.php" class="back">⬅ Back to Dashboard</a>
</div>

</body>
</html>
