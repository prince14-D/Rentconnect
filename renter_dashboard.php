<?php
session_start();
include "db.php";

// Ensure only renters can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'renter') {
    header("Location: login.php");
    exit;
}

$renter_id = $_SESSION['user_id'];
$message = '';

/* -------------------------
   HANDLE REQUEST SUBMISSION
------------------------- */
if (isset($_GET['request_property'])) {
    $property_id = intval($_GET['request_property']);

    // Prevent duplicate request
    $check = $conn->prepare("SELECT id FROM requests WHERE user_id=? AND property_id=? LIMIT 1");
    $check->bind_param("ii", $renter_id, $property_id);
    $check->execute();
    $check_res = $check->get_result();

    if ($check_res->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO requests (user_id, property_id, status, created_at) VALUES (?, ?, 'pending', NOW())");
        $stmt->bind_param("ii", $renter_id, $property_id);
        $stmt->execute();
        $message = "✅ Request submitted!";
    } else {
        $message = "⚠️ You have already requested this property.";
    }
}

/* -------------------------
   HANDLE CANCEL REQUEST
------------------------- */
if (isset($_GET['cancel_request'])) {
    $request_id = intval($_GET['cancel_request']);
    $stmt = $conn->prepare("DELETE FROM requests WHERE id=? AND user_id=? AND status='pending'");
    $stmt->bind_param("ii", $request_id, $renter_id);
    $stmt->execute();
    $message = "✅ Request canceled.";
}

/* -------------------------
   FETCH AVAILABLE PROPERTIES (only approved)
------------------------- */
$search = $_GET['search'] ?? '';
$searchTerm = "%$search%";

$sql = "SELECT * FROM properties 
        WHERE status='approved' 
          AND (title LIKE ? OR location LIKE ? OR price LIKE ?)
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
$stmt->execute();
$properties = $stmt->get_result();

/* -------------------------
   FETCH RENTER REQUESTS
------------------------- */
$sql = "
    SELECT r.id AS request_id, r.status, r.created_at,
           p.id AS property_id, p.title, p.price, p.location, p.owner_id AS landlord_id,
           u.name AS landlord_name, u.email AS landlord_email
    FROM requests r
    JOIN properties p ON r.property_id = p.id
    JOIN users u ON p.owner_id = u.id
    WHERE r.user_id=?
    ORDER BY r.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $renter_id);
$stmt->execute();
$requests = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Renter Dashboard - RentConnect</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; padding:20px; }
h2 { color:#2E7D32; }
a.logout { float:right; margin-top:-40px; color:#f44336; text-decoration:none; font-weight:bold; }
.section { margin-top:40px; background:white; padding:20px; border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
.section h3 { margin-top:0; color:#333; }

/* Property Grid */
.property-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:20px; }
.property-card { border:1px solid #ddd; border-radius:10px; overflow:hidden; background:white; box-shadow:0 2px 6px rgba(0,0,0,0.1); transition:0.3s; }
.property-card:hover { transform: scale(1.03); }
.property-card .carousel { position: relative; width:100%; height:180px; overflow:hidden; }
.property-card .carousel img { width:100%; height:180px; object-fit:cover; display:none; }
.property-card .carousel img.active { display:block; }
.property-card .carousel .prev, .property-card .carousel .next { position:absolute; top:50%; transform:translateY(-50%); background:rgba(0,0,0,0.5); color:white; border:none; padding:6px 10px; cursor:pointer; border-radius:4px; }
.property-card .carousel .prev { left:10px; }
.property-card .carousel .next { right:10px; }
.property-card .content { padding:15px; }
.property-card h4 { margin:0; color:#2E7D32; font-size:1.1em; }
.property-card p { margin:5px 0; color:#555; font-size:0.9em; }
.button { display:inline-block; padding:10px 16px; border-radius:6px; text-decoration:none; font-size:0.9em; margin-top:8px; text-align:center; }
.view { background: #4CAF50; color: white; }
.chat { background: #2196F3; color: white; }

/* Search Bar */
.search-bar { text-align:center; margin-bottom:20px; }
.search-bar input { padding:12px; width:70%; max-width:400px; border-radius:6px; border:1px solid #ccc; }
.search-bar button { padding:12px 18px; border:none; border-radius:6px; background:#2E7D32; color:white; cursor:pointer; }

/* Table */
table { width:100%; border-collapse:collapse; margin-top:15px; }
table th, table td { border:1px solid #ddd; padding:10px; text-align:center; font-size:0.9em; }
table th { background:#f2f2f2; color:#333; }
.table-wrapper { overflow-x:auto; }

/* Modal */
.modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); justify-content:center; align-items:center; padding:10px; }
.modal-content { background:white; padding:20px; border-radius:10px; width:100%; max-width:600px; max-height:90vh; overflow-y:auto; }
.close { float:right; cursor:pointer; font-size:18px; color:#f44336; }

/* Responsive Design */
@media (max-width: 768px) {
    body { padding:10px; }
    h2 { font-size:1.4em; }
    .section { padding:15px; }
    .search-bar input { width:100%; margin-bottom:10px; }
    .search-bar button { width:100%; }
    .button { display:block; width:100%; margin:6px 0; }
}
</style>
</head>
<body>
<h2>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?> (Renter)</h2>
<a href="logout.php" class="logout">Logout</a>

<?php if($message) echo "<p style='color:green; font-weight:bold;'>$message</p>"; ?>

<!-- Property Search -->
<div class="section search-bar">
    <form method="get">
        <input type="text" name="search" placeholder="Search by title, location, or price..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit">Search</button>
    </form>
</div>

<!-- Property Listings -->
<div class="section">
    <h3>🏠 Available Properties</h3>
    <div class="property-grid">
        <?php if ($properties->num_rows > 0): ?>
            <?php while ($row = $properties->fetch_assoc()): ?>
                <div class="property-card">
                    <?php
                    $img_stmt = $conn->prepare("SELECT id FROM property_images WHERE property_id=?");
                    $img_stmt->bind_param("i", $row['id']);
                    $img_stmt->execute();
                    $imgs = $img_stmt->get_result();
                    ?>
                    <div class="carousel">
                        <?php if ($imgs->num_rows > 0): ?>
                            <?php while ($img = $imgs->fetch_assoc()): ?>
                                <img src="display_image.php?img_id=<?php echo $img['id']; ?>" alt="Property">
                            <?php endwhile; ?>
                        <?php else: ?>
                            <img src="images/no-image.png" alt="No Image" class="active">
                        <?php endif; ?>
                    </div>
                    <div class="content">
                        <h4><?php echo htmlspecialchars($row['title']); ?></h4>
                        <p>📍 <?php echo htmlspecialchars($row['location']); ?></p>
                        <p>💲 $<?php echo number_format($row['price']); ?></p>
                        
                        <a href="?request_property=<?php echo $row['id']; ?>" class="button view">Request to Rent</a>
                        <a href="javascript:void(0)" class="button view" onclick="openModal(<?php echo $row['id']; ?>)">View Details</a>

                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No approved properties available yet.</p>
        <?php endif; ?>
    </div>
</div>

<!-- My Requests -->
<div class="section">
    <h3>📋 My Rental Requests</h3>
    <?php if ($requests->num_rows > 0): ?>
        <div class="table-wrapper">
            <table>
                <tr>
                    <th>Property</th>
                    <th>Price</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Requested At</th>
                    <th>Action</th>
                </tr>
                <?php while ($row = $requests->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                        <td>$<?php echo number_format($row['price']); ?></td>
                        <td><?php echo htmlspecialchars($row['location']); ?></td>
                        <td><?php echo ucfirst($row['status']); ?></td>
                        <td><?php echo $row['created_at']; ?></td>
                        <td>
                            <?php if ($row['status'] == 'pending'): ?>
                                <a href="?cancel_request=<?php echo $row['request_id']; ?>" class="button cancel">❌ Cancel</a>
                            <?php elseif ($row['status'] == 'approved'): ?>
                                <a href="chat.php?property_id=<?php echo $row['property_id']; ?>&with=<?php echo $row['landlord_id']; ?>" class="button chat">💬 Message Landlord</a>
                            <?php else: ?>
                                No action
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>
    <?php else: ?>
        <p>You have not made any requests yet.</p>
    <?php endif; ?>
</div>

<!-- Property Detail Modal -->
<div id="propertyModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">✖</span>
        <div id="modal-body">Loading...</div>
    </div>
</div>

<script>
function openModal(id){
    fetch("property_detail.php?id="+id)
    .then(res=>res.text())
    .then(data=>{
        document.getElementById("modal-body").innerHTML=data;
        document.getElementById("propertyModal").style.display="flex";
    });
}
function closeModal(){ document.getElementById("propertyModal").style.display="none"; }

// Carousel functionality
document.addEventListener("DOMContentLoaded", ()=>{
    document.querySelectorAll('.carousel').forEach(carousel=>{
        let imgs=carousel.querySelectorAll('img'); let index=0;
        if(imgs.length>0) imgs[0].classList.add('active');
        if(imgs.length>1){
            let prev=document.createElement('button'); prev.innerText='❮'; prev.classList.add('prev'); carousel.appendChild(prev);
            let next=document.createElement('button'); next.innerText='❯'; next.classList.add('next'); carousel.appendChild(next);
            function show(i){ imgs.forEach(img=>img.classList.remove('active')); imgs[i].classList.add('active'); }
            prev.onclick=()=>{ index=(index-1+imgs.length)%imgs.length; show(index); }
            next.onclick=()=>{ index=(index+1)%imgs.length; show(index); }
            let startX=0;
            carousel.addEventListener("touchstart", e=>{ startX=e.touches[0].clientX; });
            carousel.addEventListener("touchend", e=>{ let endX=e.changedTouches[0].clientX; if(startX-endX>50){ index=(index+1)%imgs.length; show(index); } else if(endX-startX>50){ index=(index-1+imgs.length)%imgs.length; show(index); }});
            setInterval(()=>{ index=(index+1)%imgs.length; show(index); },5000);
        }
    });
});
</script>
</body>
</html>
