<?php
session_start();
include "db.php";

// Only allow super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: super_admin_login.php");
    exit();
}

// Handle actions
if (isset($_GET['action'], $_GET['id'])) {
    $id = intval($_GET['id']);
    if ($_GET['action'] === 'approve') {
        $stmt = $conn->prepare("UPDATE properties SET status='approved' WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    } elseif ($_GET['action'] === 'hide') {
        $stmt = $conn->prepare("UPDATE properties SET status='inactive' WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    } elseif ($_GET['action'] === 'delete') {
        $img_stmt = $conn->prepare("DELETE FROM property_images WHERE property_id=?");
        $img_stmt->bind_param("i", $id);
        $img_stmt->execute();
        $stmt = $conn->prepare("DELETE FROM properties WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
    header("Location: super_admin_dashboard.php");
    exit();
}

// Filter + Search
$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Pagination
$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Base query
$sql = "FROM properties p JOIN users l ON p.landlord_id = l.id WHERE 1=1";
$params = [];
$types = "";

// Apply status filter
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

// Count total records
$count_sql = "SELECT COUNT(*) " . $sql;
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$count_stmt->bind_result($total_records);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = ceil($total_records / $limit);

// Fetch paginated data
$data_sql = "SELECT p.id, p.title, p.description, p.status, p.created_at, l.name AS landlord_name "
          . $sql
          . " ORDER BY FIELD(p.status,'pending','approved','taken','inactive'), p.created_at DESC LIMIT ?, ?";
$stmt = $conn->prepare($data_sql);

if (!empty($params)) {
    $all_params = array_merge($params, [$offset, $limit]);
    $stmt->bind_param($types."ii", ...$all_params);
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { font-family:Arial,sans-serif; background:#f5f7fb; margin:0; padding:0; }
header { background:#2E7D32; color:#fff; padding:15px 25px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; }
header h1 { margin:0; font-size:1.4em; }
a.logout { background:#d32f2f; color:#fff; padding:8px 12px; border-radius:6px; text-decoration:none; margin-top:5px; }
.container { max-width:1200px; margin:20px auto; background:#fff; padding:20px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
.filter-bar { margin-bottom:20px; display:flex; flex-wrap:wrap; gap:10px; justify-content:space-between; }
.filter-bar select, .filter-bar input { padding:8px 12px; border-radius:6px; border:1px solid #ccc; font-size:0.95em; }
.filter-bar button { padding:8px 14px; border:none; border-radius:6px; background:#2E7D32; color:#fff; cursor:pointer; }
.filter-bar button:hover { background:#1b5e20; }

/* Table */
table { width:100%; border-collapse: collapse; margin-top:10px; }
table th, table td { padding:12px; text-align:left; border-bottom:1px solid #eee; vertical-align:top; }
table th { background:#f0f0f0; font-weight:600; }
.badge { padding:4px 10px; border-radius:6px; font-size:0.85em; white-space:nowrap; }
.pending { background:#ff9800; color:#fff; }
.approved { background:#2E7D32; color:#fff; }
.taken { background:#1976D2; color:#fff; }
.inactive { background:#9e9e9e; color:#fff; }
a.btn { padding:6px 10px; border-radius:6px; text-decoration:none; font-size:0.85em; margin:2px 2px 2px 0; display:inline-block; text-align:center; }
.approve { background:#2E7D32; color:#fff; }
.approve:hover { background:#1b5e20; }
.hide { background:#9e9e9e; color:#fff; }
.hide:hover { background:#616161; }
.delete { background:#d32f2f; color:#fff; }
.delete:hover { background:#9a0007; }
.img-container { display:flex; gap:5px; flex-wrap:wrap; }
.img-container img { width:60px; height:50px; object-fit:cover; cursor:pointer; border-radius:4px; border:1px solid #ccc; }
.pagination { margin-top:20px; text-align:center; }
.pagination a { display:inline-block; padding:8px 12px; margin:0 3px; border:1px solid #ccc; border-radius:6px; text-decoration:none; color:#333; }
.pagination a.active { background:#2E7D32; color:#fff; border-color:#2E7D32; }
.pagination a:hover { background:#f0f0f0; }

/* Lightbox */
#lightbox { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); justify-content:center; align-items:center; z-index:1000; }
#lightbox img { max-width:90%; max-height:90%; border-radius:10px; }

/* Mobile responsive: table → cards */
@media (max-width:768px){
    table, thead, tbody, th, td, tr { display:block; }
    thead { display:none; }
    tr { margin-bottom:20px; border:1px solid #ddd; border-radius:8px; padding:10px; background:#fff; }
    td { border:none; padding:8px; text-align:left; position:relative; }
    td:before { content: attr(data-label); font-weight:bold; display:block; margin-bottom:4px; color:#333; }
    .img-container img { width:100%; max-width:180px; height:auto; }
    a.btn { display:block; width:100%; margin:5px 0; }
    .filter-bar { flex-direction:column; align-items:flex-start; }
}
</style>
</head>
<body>

<header>
<h1>Super Admin Dashboard</h1>
<a href="super_admin_logout.php" class="logout">Logout</a>
</header>

<div class="container">
<div class="filter-bar">
<form method="GET" action="">
<label>Status:</label>
<select name="status">
<option value="all" <?= $status_filter=='all'?'selected':'' ?>>All</option>
<option value="pending" <?= $status_filter=='pending'?'selected':'' ?>>Pending</option>
<option value="approved" <?= $status_filter=='approved'?'selected':'' ?>>Approved</option>
<option value="taken" <?= $status_filter=='taken'?'selected':'' ?>>Taken</option>
<option value="inactive" <?= $status_filter=='inactive'?'selected':'' ?>>Inactive</option>
</select>
<input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
<button type="submit">Apply</button>
</form>
</div>

<table>
<thead>
<tr>
<th>ID</th>
<th>Landlord</th>
<th>Title</th>
<th>Status</th>
<th>Images</th>
<th>Created</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php if($result && $result->num_rows>0): ?>
<?php while($row = $result->fetch_assoc()): ?>
<tr>
<td data-label="ID"><?= $row['id'] ?></td>
<td data-label="Landlord"><?= htmlspecialchars($row['landlord_name']) ?></td>
<td data-label="Title"><?= htmlspecialchars($row['title']) ?></td>
<td data-label="Status"><span class="badge <?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
<td data-label="Images">
<div class="img-container">
<?php
$img_stmt = $conn->prepare("SELECT image, mime_type FROM property_images WHERE property_id=?");
$img_stmt->bind_param("i", $row['id']);
$img_stmt->execute();
$img_result = $img_stmt->get_result();
while($img = $img_result->fetch_assoc()):
$base64 = 'data:'.$img['mime_type'].';base64,'.base64_encode($img['image']);
?>
<img src="<?= $base64 ?>" onclick="openLightbox('<?= $base64 ?>')">
<?php endwhile; ?>
</div>
</td>
<td data-label="Created"><?= $row['created_at'] ?></td>
<td data-label="Actions">
<?php if($row['status']=='pending'): ?>
<a href="?action=approve&id=<?= $row['id'] ?>" class="btn approve">Approve</a>
<?php endif; ?>
<?php if($row['status'] !== 'inactive'): ?>
<a href="?action=hide&id=<?= $row['id'] ?>" class="btn hide">Hide</a>
<?php endif; ?>
<a href="?action=delete&id=<?= $row['id'] ?>" class="btn delete" onclick="return confirm('Are you sure?')">Delete</a>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="7" style="text-align:center;color:#999;">No properties found</td></tr>
<?php endif; ?>
</tbody>
</table>

<div class="pagination">
<?php if($page>1): ?>
<a href="?status=<?= $status_filter ?>&search=<?= urlencode($search) ?>&page=<?= $page-1 ?>">&laquo; Prev</a>
<?php endif; ?>
<?php for($i=1;$i<=$total_pages;$i++): ?>
<a href="?status=<?= $status_filter ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>" class="<?= $i==$page?'active':'' ?>"><?= $i ?></a>
<?php endfor; ?>
<?php if($page<$total_pages): ?>
<a href="?status=<?= $status_filter ?>&search=<?= urlencode($search) ?>&page=<?= $page+1 ?>">Next &raquo;</a>
<?php endif; ?>
</div>
</div>

<div id="lightbox" onclick="closeLightbox()">
<img id="lightbox-img" src="">
</div>

<script>
function openLightbox(src){
    const lb = document.getElementById('lightbox');
    const lbImg = document.getElementById('lightbox-img');
    lbImg.src = src;
    lb.style.display = 'flex';
}
function closeLightbox(){
    document.getElementById('lightbox').style.display='none';
}
</script>
</body>
</html>
