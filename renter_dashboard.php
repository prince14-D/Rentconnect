<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'renter') {
    header("Location: login.php");
    exit;
}

$renter_id = $_SESSION['user_id'];
$message = "";

// Fetch active bookings with landlord info
$bookings_sql = "
SELECT 
    b.id, b.property_id, b.move_in_date, b.move_out_date, b.monthly_rent, b.status,
    p.title, p.location,
    u.name AS landlord_name
FROM bookings b
JOIN properties p ON b.property_id = p.id
JOIN users u ON b.landlord_id = u.id
WHERE b.renter_id = ? AND b.status = 'active'
ORDER BY b.move_in_date DESC
";
$stmt_bookings = $conn->prepare($bookings_sql);
$stmt_bookings->bind_param("i", $renter_id);
$stmt_bookings->execute();
$active_bookings = $stmt_bookings->get_result();

// Fetch pending rent reminders
$reminders_sql = "
SELECT COUNT(*) as unread_reminders 
FROM rent_reminders 
WHERE renter_id = ? AND is_read = 0
";
$stmt_reminders = $conn->prepare($reminders_sql);
$stmt_reminders->bind_param("i", $renter_id);
$stmt_reminders->execute();
$reminders_result = $stmt_reminders->get_result()->fetch_assoc();
$unread_reminders = $reminders_result['unread_reminders'];

// Fetch pending payments
$payments_sql = "
SELECT COUNT(*) as pending_payments 
FROM payments 
WHERE renter_id = ? AND status = 'pending'
";
$stmt_payments = $conn->prepare($payments_sql);
$stmt_payments->bind_param("i", $renter_id);
$stmt_payments->execute();
$payments_result = $stmt_payments->get_result()->fetch_assoc();
$pending_payments = $payments_result['pending_payments'];

/* Submit rental request */
if (isset($_GET['request_property'])) {
    $property_id = intval($_GET['request_property']);
    $check = $conn->prepare("SELECT id FROM requests WHERE user_id=? AND property_id=? LIMIT 1");
    $check->bind_param("ii", $renter_id, $property_id);
    $check->execute();
    $check_res = $check->get_result();

    if ($check_res->num_rows == 0) {
        $stmt = $conn->prepare("INSERT INTO requests (user_id, property_id, status, created_at) VALUES (?, ?, 'pending', NOW())");
        $stmt->bind_param("ii", $renter_id, $property_id);
        $stmt->execute();
        $message = "Request submitted!";
    } else {
        $message = "You have already requested this property.";
    }
}

/* Cancel request */
if (isset($_GET['cancel_request'])) {
    $request_id = intval($_GET['cancel_request']);
    $stmt = $conn->prepare("DELETE FROM requests WHERE id=? AND user_id=? AND status='pending'");
    $stmt->bind_param("ii", $request_id, $renter_id);
    $stmt->execute();
    $message = "Request canceled.";
}

/* Search & fetch properties */
$search = $_GET['search'] ?? '';
$searchTerm = "%$search%";
$sql = "SELECT * FROM properties WHERE status='approved' AND (title LIKE ? OR location LIKE ? OR price LIKE ?) ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
$stmt->execute();
$properties = $stmt->get_result();

/* Fetch renter's requests */
$sql = "
SELECT r.id AS request_id, r.status, r.created_at,
       p.id AS property_id, p.title, p.price, p.location,
       p.owner_id AS landlord_id,
       u.name AS landlord_name, u.email AS landlord_email
FROM requests r
INNER JOIN properties p ON r.property_id = p.id
INNER JOIN users u ON p.owner_id = u.id
WHERE r.user_id = ?
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Manage your rental requests and properties.">
<meta name="theme-color" content="#1f8f67">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="manifest" href="/manifest.json">
<title>Renter Dashboard - RentConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --ink: #212733;
    --muted: #59627a;
    --brand: #1f8f67;
    --brand-deep: #15543e;
    --accent: #ff7a2f;
    --line: rgba(33, 39, 51, 0.12);
    --card: rgba(255, 255, 255, 0.92);
    --shadow: 0 18px 38px rgba(20, 31, 44, 0.15);
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    min-height: 100vh;
    font-family: 'Manrope', sans-serif;
    color: var(--ink);
    background:
        radial-gradient(circle at 8% 8%, rgba(255, 122, 47, 0.2), transparent 34%),
        radial-gradient(circle at 92% 8%, rgba(31, 143, 103, 0.18), transparent 30%),
        linear-gradient(165deg, #f9f6ef 0%, #f2f7f8 56%, #fffdfa 100%);
}

.container {
    width: min(1140px, 94vw);
    margin: 0 auto;
}

.top {
    padding: 28px 0 10px;
}

.hero {
    background: linear-gradient(140deg, rgba(17, 59, 72, 0.94), rgba(31, 143, 103, 0.88));
    color: #fff;
    border-radius: 22px;
    padding: clamp(18px, 4vw, 28px);
    box-shadow: var(--shadow);
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: center;
    flex-wrap: wrap;
}

.hero h1 {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: clamp(1.35rem, 3vw, 2rem);
    letter-spacing: -0.02em;
    margin-bottom: 4px;
}

.hero p { color: rgba(255, 255, 255, 0.9); }

.hero .logout {
    text-decoration: none;
    padding: 10px 13px;
    border-radius: 10px;
    font-weight: 700;
    color: #fff;
    background: rgba(255, 255, 255, 0.2);
}

.hero .logout:hover { background: rgba(255, 255, 255, 0.28); }

.notice {
    margin-top: 12px;
    padding: 11px 13px;
    border-radius: 12px;
    border: 1px solid rgba(39, 165, 106, 0.35);
    background: rgba(39, 165, 106, 0.12);
    color: #165a39;
    font-weight: 700;
}

.section {
    margin-top: 16px;
    background: var(--card);
    border: 1px solid rgba(255, 255, 255, 0.9);
    border-radius: 16px;
    box-shadow: 0 10px 24px rgba(15, 31, 40, 0.09);
    padding: 16px;
}

.section h2 {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1.25rem;
    margin-bottom: 12px;
    letter-spacing: -0.02em;
}

.search-form {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 8px;
}

.search-form input {
    width: 100%;
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 11px 12px;
    font: inherit;
}

.search-form button {
    border: none;
    border-radius: 10px;
    padding: 11px 14px;
    color: #fff;
    background: linear-gradient(140deg, var(--brand), var(--brand-deep));
    font-weight: 700;
    cursor: pointer;
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 12px;
}

.property-card {
    border: 1px solid var(--line);
    border-radius: 14px;
    overflow: hidden;
    background: #fff;
}

.carousel {
    position: relative;
    height: 175px;
    background: #e9edf1;
    overflow: hidden;
}

.carousel img {
    width: 100%;
    height: 175px;
    object-fit: cover;
    display: none;
}

.carousel img.active { display: block; }

.carousel button {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    width: 28px;
    height: 28px;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.48);
    color: #fff;
    cursor: pointer;
}

.carousel .prev { left: 8px; }
.carousel .next { right: 8px; }

.content { padding: 12px; }

.content h3 {
    font-family: 'Plus Jakarta Sans', sans-serif;
    margin-bottom: 7px;
    font-size: 1.02rem;
}

.content p {
    color: var(--muted);
    margin: 4px 0;
    font-size: 0.92rem;
}

.actions {
    margin-top: 10px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.btn {
    display: inline-block;
    text-decoration: none;
    border-radius: 9px;
    padding: 9px 10px;
    font-size: 0.88rem;
    font-weight: 700;
}

.btn.request { color: #fff; background: linear-gradient(140deg, var(--brand), var(--brand-deep)); }
.btn.detail { color: #fff; background: linear-gradient(140deg, #2276d2, #1b5aa8); }
.btn.cancel { color: #fff; background: linear-gradient(140deg, #e5554f, #d43c35); }
.btn.chat { color: #fff; background: linear-gradient(140deg, #2276d2, #1b5aa8); }

.table-wrap { overflow-x: auto; }

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 700px;
}

th,
td {
    border-bottom: 1px solid var(--line);
    padding: 10px;
    text-align: left;
    font-size: 0.9rem;
}

th {
    color: #3f4960;
    background: #f6f9fc;
}

.badge {
    display: inline-block;
    border-radius: 999px;
    padding: 4px 8px;
    font-size: 0.78rem;
    font-weight: 800;
    color: #fff;
}

.pending { background: #ff8b2f; }
.approved { background: #1f8f67; }
.declined { background: #e04d45; }

.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.62);
    z-index: 50;
    padding: 14px;
    align-items: center;
    justify-content: center;
}

.modal-content {
    width: min(960px, 96vw);
    max-height: 90vh;
    overflow: auto;
    background: #fff;
    border-radius: 14px;
    padding: 16px;
}

.close {
    float: right;
    border: none;
    background: transparent;
    color: #cc3d37;
    font-size: 1.15rem;
    font-weight: 700;
    cursor: pointer;
}

@media (max-width: 700px) {
    .search-form {
        grid-template-columns: 1fr;
    }

    .search-form button {
        width: 100%;
    }

    .hero .logout {
        width: 100%;
        text-align: center;
    }
}
</style>
</head>
<body>
<section class="top">
    <div class="container">
        <div class="hero">
            <div>
                <h1>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></h1>
                <p>Browse approved homes, submit requests, and track updates in one dashboard.</p>
            </div>
            <a href="logout.php" class="logout">Logout</a>
        </div>

        <?php if ($message): ?>
            <p class="notice"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <section class="section">
            <h2>Search Properties</h2>
            <form class="search-form" method="get">
                <input type="text" name="search" placeholder="Search by title, location, or price" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit">Search</button>
            </form>
        </section>

        <section class="section">
            <h2>Available Properties</h2>
            <div class="grid">
                <?php if ($properties->num_rows > 0): ?>
                    <?php while ($row = $properties->fetch_assoc()): ?>
                        <article class="property-card">
                            <?php
                            $img_stmt = $conn->prepare("SELECT id FROM property_images WHERE property_id=?");
                            $img_stmt->bind_param("i", $row['id']);
                            $img_stmt->execute();
                            $imgs = $img_stmt->get_result();
                            ?>
                            <div class="carousel">
                                <?php if ($imgs->num_rows > 0): ?>
                                    <?php $first = true; while ($img = $imgs->fetch_assoc()): ?>
                                        <img src="display_image.php?img_id=<?php echo $img['id']; ?>" alt="Property image" class="<?php echo $first ? 'active' : ''; ?>">
                                        <?php $first = false; ?>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <img src="images/no-image.png" alt="No image" class="active">
                                <?php endif; ?>
                            </div>

                            <div class="content">
                                <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                                <p><?php echo htmlspecialchars($row['location']); ?></p>
                                <p>$<?php echo number_format($row['price']); ?></p>
                                <div class="actions">
                                    <a href="?request_property=<?php echo $row['id']; ?>" class="btn request">Request to Rent</a>
                                    <a href="javascript:void(0)" class="btn detail" onclick="openModal(<?php echo $row['id']; ?>)">View Details</a>
                                </div>
                            </div>
                        </article>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No approved properties available yet.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="section">
            <h2>My Requests</h2>
            <?php if ($requests->num_rows > 0): ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Property</th>
                                <th>Price</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Requested At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $requests->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                                    <td>$<?php echo number_format($row['price']); ?></td>
                                    <td><?php echo htmlspecialchars($row['location']); ?></td>
                                    <td>
                                        <?php if ($row['status'] == 'pending'): ?>
                                            <span class="badge pending">Pending</span>
                                        <?php elseif ($row['status'] == 'approved'): ?>
                                            <span class="badge approved">Approved</span>
                                        <?php else: ?>
                                            <span class="badge declined">Declined</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                                    <td>
                                        <?php if ($row['status'] == 'pending'): ?>
                                            <a href="?cancel_request=<?php echo $row['request_id']; ?>" class="btn cancel">Cancel</a>
                                        <?php elseif ($row['status'] == 'approved'): ?>
                                            <a href="chat.php?property_id=<?php echo $row['property_id']; ?>&with=<?php echo $row['landlord_id']; ?>" class="btn chat">Chat</a>
                                        <?php else: ?>
                                            <span>Rejected</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>You have not made any requests yet.</p>
            <?php endif; ?>
        </section>

        <section class="section">
            <h2>🏠 Active Bookings</h2>
            <?php if ($active_bookings->num_rows > 0): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px;">
                    <?php while ($booking = $active_bookings->fetch_assoc()): ?>
                    <div style="background: linear-gradient(135deg, #f6f9fc 0%, #f9f6ef 100%); border: 1px solid var(--line); border-radius: 12px; padding: 14px; border-left: 4px solid var(--brand);">
                        <h3 style="font-size: 1.05rem; margin-bottom: 8px; color: var(--ink);"><?php echo htmlspecialchars($booking['title']); ?></h3>
                        <p style="color: var(--muted); font-size: 0.9rem; margin: 4px 0;">📍 <?php echo htmlspecialchars($booking['location']); ?></p>
                        <p style="color: var(--muted); font-size: 0.9rem; margin: 4px 0;">👤 <?php echo htmlspecialchars($booking['landlord_name']); ?></p>
                        <p style="color: var(--ink); font-weight: 700; font-size: 1.1rem; margin: 8px 0;">💰 $<?php echo number_format($booking['monthly_rent'], 2); ?>/month</p>
                        <p style="color: var(--muted); font-size: 0.85rem; margin: 8px 0;">
                            <strong>Move-in:</strong> <?php echo date('M d, Y', strtotime($booking['move_in_date'])); ?><br>
                            <strong>Move-out:</strong> <?php echo date('M d, Y', strtotime($booking['move_out_date'])); ?>
                        </p>
                        <div style="display: flex; gap: 8px; margin-top: 10px;">
                            <a href="rent_payments.php" style="flex: 1; display: inline-block; text-align: center; padding: 10px; background: var(--brand); color: #fff; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 0.85rem;">💳 Pay Rent</a>
                            <a href="manage_bookings.php" style="flex: 1; display: inline-block; text-align: center; padding: 10px; background: #2276d2; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 0.85rem;">📋 Manage</a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p>You have no active bookings yet. <a href="#" style="color: var(--brand); font-weight: 700;">Browse and book properties</a> to get started!</p>
            <?php endif; ?>
        </section>

        <section class="section">
            <h2>🔔 Notifications & Links</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                <a href="rent_reminders.php" style="display: flex; align-items: center; gap: 8px; padding: 12px; background: linear-gradient(135deg, rgba(255, 122, 47, 0.15), rgba(255, 122, 47, 0.08)); border: 1px solid rgba(255, 122, 47, 0.3); border-radius: 10px; text-decoration: none; color: var(--ink); font-weight: 700; font-size: 0.9rem; transition: all 0.2s;">
                    <span style="font-size: 1.3rem;">🔔</span>
                    <div>
                        <div>Rent Reminders</div>
                        <div style="font-size: 0.75rem; color: var(--muted); font-weight: 600;">
                            <?php echo $unread_reminders > 0 ? "$unread_reminders unread" : "No reminders"; ?>
                        </div>
                    </div>
                </a>
                <a href="rent_payments.php" style="display: flex; align-items: center; gap: 8px; padding: 12px; background: linear-gradient(135deg, rgba(31, 143, 103, 0.15), rgba(31, 143, 103, 0.08)); border: 1px solid rgba(31, 143, 103, 0.3); border-radius: 10px; text-decoration: none; color: var(--ink); font-weight: 700; font-size: 0.9rem; transition: all 0.2s;">
                    <span style="font-size: 1.3rem;">💳</span>
                    <div>
                        <div>Pay Rent</div>
                        <div style="font-size: 0.75rem; color: var(--muted); font-weight: 600;">
                            <?php echo $pending_payments > 0 ? "$pending_payments pending" : "All paid up"; ?>
                        </div>
                    </div>
                </a>
                <a href="chat.php" style="display: flex; align-items: center; gap: 8px; padding: 12px; background: linear-gradient(135deg, rgba(34, 118, 210, 0.15), rgba(34, 118, 210, 0.08)); border: 1px solid rgba(34, 118, 210, 0.3); border-radius: 10px; text-decoration: none; color: var(--ink); font-weight: 700; font-size: 0.9rem; transition: all 0.2s;">
                    <span style="font-size: 1.3rem;">💬</span>
                    <div>
                        <div>Chat</div>
                        <div style="font-size: 0.75rem; color: var(--muted); font-weight: 600;">Message landlords</div>
                    </div>
                </a>
            </div>
        </section>
    </div>
</section>

<div id="propertyModal" class="modal">
    <div class="modal-content">
        <button class="close" onclick="closeModal()">Close</button>
        <div id="modal-body">Loading...</div>
    </div>
</div>

<script>
function openModal(id) {
    fetch("property_detail.php?id=" + id)
        .then((res) => res.text())
        .then((data) => {
            document.getElementById("modal-body").innerHTML = data;
            document.getElementById("propertyModal").style.display = "flex";
        });
}

function closeModal() {
    document.getElementById("propertyModal").style.display = "none";
}

document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('.carousel').forEach((carousel) => {
        const imgs = carousel.querySelectorAll('img');
        if (imgs.length <= 1) {
            return;
        }

        let index = 0;
        const prev = document.createElement('button');
        prev.innerText = '❮';
        prev.classList.add('prev');

        const next = document.createElement('button');
        next.innerText = '❯';
        next.classList.add('next');

        carousel.appendChild(prev);
        carousel.appendChild(next);

        function show(i) {
            imgs.forEach((img) => img.classList.remove('active'));
            imgs[i].classList.add('active');
        }

        prev.onclick = () => {
            index = (index - 1 + imgs.length) % imgs.length;
            show(index);
        };

        next.onclick = () => {
            index = (index + 1) % imgs.length;
            show(index);
        };

        let startX = 0;
        carousel.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
        });

        carousel.addEventListener('touchend', (e) => {
            const endX = e.changedTouches[0].clientX;
            if (startX - endX > 50) {
                index = (index + 1) % imgs.length;
                show(index);
            } else if (endX - startX > 50) {
                index = (index - 1 + imgs.length) % imgs.length;
                show(index);
            }
        });

        setInterval(() => {
            index = (index + 1) % imgs.length;
            show(index);
        }, 5000);
    });

    window.addEventListener('click', (event) => {
        const modal = document.getElementById('propertyModal');
        if (event.target === modal) {
            closeModal();
        }
    });
});
</script>

<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js')
            .then((registration) => console.log('[PWA] Service Worker registered'))
            .catch((error) => console.warn('[PWA] Service Worker registration failed:', error));
    });
}
</script>
</body>
</html>
