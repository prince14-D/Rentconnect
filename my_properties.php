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

/* DELETE PROPERTY */
if (isset($_GET['delete'])) {
    $prop_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM properties WHERE id=? AND owner_id=?");
    $stmt->bind_param("ii", $prop_id, $landlord_id);
    if ($stmt->execute()) $message = "✅ Property deleted successfully!";
    else $message = "❌ Could not delete property.";
}

/* FETCH PROPERTIES */
$stmt = $conn->prepare("SELECT * FROM properties WHERE owner_id=? ORDER BY created_at DESC");
$stmt->bind_param("i", $landlord_id);
$stmt->execute();
$properties = $stmt->get_result();
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
.container { max-width:900px; margin:30px auto; padding:0 15px; display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:20px; }
.card { background:white; border-radius:12px; box-shadow:0 4px 10px rgba(0,0,0,0.1); overflow:hidden; transition:0.3s; }
.card:hover { transform:translateY(-5px); box-shadow:0 6px 14px rgba(0,0,0,0.15); }
.card img { width:100%; height:180px; object-fit:cover; }
.card-body { padding:15px; }
.card-body h3 { margin:0 0 8px; color:#2e7d32; }
.card-body p { margin:4px 0; color:#555; font-size:0.9em; }
.card-actions { display:flex; justify-content:space-between; margin-top:10px; }
.btn { padding:8px 12px; border-radius:6px; font-size:0.9em; text-decoration:none; color:white; }
.btn-edit { background:#4CAF50; }
.btn-delete { background:#f44336; }
.message { text-align:center; color:green; font-weight:bold; margin-bottom:15px; }
a.back { display:block; margin:20px auto; text-align:center; color:#2e7d32; text-decoration:none; font-weight:bold; }
@media(max-width:500px){ .container { grid-template-columns:1fr; } }
</style>
</head>
<body>

<header>My Properties</header>
<?php if($message) echo "<p class='message'>$message</p>"; ?>

<div class="container">
<?php if ($properties->num_rows > 0): ?>
    <?php while ($row = $properties->fetch_assoc()): ?>
        <div class="card">
            <img src="get_image.php?id=<?php echo $row['id']; ?>" alt="Property">
            <div class="card-body">
                <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                <p>📍 <?php echo htmlspecialchars($row['location']); ?></p>
                <p>💲 $<?php echo $row['price']; ?></p>
                <p>📞 <?php echo htmlspecialchars($row['contact']); ?></p>
                <div class="card-actions">
                    <a href="edit_property.php?id=<?php echo $row['id']; ?>" class="btn btn-edit">✏️ Edit</a>
                    <a href="my_properties.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Delete this property?')" class="btn btn-delete">🗑️ Delete</a>
                </div>
            </div>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <p style="text-align:center; color:#555;">You have not uploaded any properties yet.</p>
<?php endif; ?>
</div>

<a href="landlord_dashboard.php" class="back">⬅ Back to Dashboard</a>

</body>
</html>
