<?php
session_start();
include "app_init.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'renter') {
    header("Location: login.php");
    exit;
}

$renter_id = $_SESSION['user_id'];
$message = "";

$active_bookings = rc_mig_get_renter_active_bookings($conn, (int) $renter_id);
$unread_reminders = rc_mig_get_renter_unread_reminders_count($conn, (int) $renter_id);
$pending_payments = rc_mig_get_renter_pending_payments_count($conn, (int) $renter_id);

/* Submit rental request */
if (isset($_GET['request_property'])) {
    $property_id = intval($_GET['request_property']);
    if (rc_mig_create_request_if_missing($conn, (int) $renter_id, $property_id)) {
        $message = "Request submitted!";
    } else {
        $message = "You have already requested this property.";
    }
}

/* Cancel request */
if (isset($_GET['cancel_request'])) {
    $request_id = intval($_GET['cancel_request']);
    if (rc_mig_cancel_pending_request($conn, $request_id, (int) $renter_id)) {
        $message = "Request canceled.";
    }
}

/* Search & fetch properties */
$search = $_GET['search'] ?? '';
$properties = rc_mig_search_approved_properties($conn, (string) $search);

/* Fetch renter's requests */
$requests = rc_mig_get_renter_requests($conn, (int) $renter_id);
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

@media (max-width: 900px) {
    .container {
        width: 100vw;
        padding: 0 2vw;
    }
    .hero {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
        padding: 18px 10px;
    }
    .section {
        padding: 10px 6px;
    }
    .grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    .property-card {
        min-width: 0;
        font-size: 1rem;
    }
    .carousel {
        height: 140px;
    }
    .carousel img {
        height: 140px;
    }
    .modal-content {
        width: 98vw;
        padding: 8px;
    }
    .table-wrap {
        min-width: 0;
        overflow-x: auto;
    }
    table {
        min-width: 480px;
        font-size: 0.92rem;
    }
    .actions {
        flex-direction: column;
        gap: 6px;
    }
    .btn {
        width: 100%;
        text-align: center;
        font-size: 1rem;
        padding: 12px 0;
    }
}

@media (max-width: 500px) {
    .hero h1 {
        font-size: 1.1rem;
    }
    .section h2 {
        font-size: 1.05rem;
    }
    .property-card {
        font-size: 0.98rem;
    }
    .carousel, .carousel img {
        height: 90px;
    }
    .btn {
        font-size: 0.98rem;
        padding: 10px 0;
    }
    .modal-content {
        width: 100vw;
        padding: 2px;
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


        <!-- Modernized Available Properties Section -->
        <section class="section">
            <h2 style="display:flex;align-items:center;gap:8px;">🏡 Available Properties <span style="font-size:0.9rem;color:var(--muted);font-weight:500;">(Not yet booked)</span></h2>
            <div class="grid">
                <?php if (!empty($properties)): ?>
                    <?php foreach ($properties as $row): ?>
                        <?php
                        $imgs = rc_mig_get_property_image_ids($conn, (int) $row['id']);
                        $alreadyBooked = false;
                        foreach ($active_bookings as $booking) {
                            if ($booking['property_id'] == $row['id']) {
                                $alreadyBooked = true;
                                break;
                            }
                        }
                        ?>
                        <article class="property-card" style="position:relative;box-shadow:0 2px 12px rgba(31,143,103,0.07);">
                            <?php if ($alreadyBooked): ?>
                                <span style="position:absolute;top:10px;right:10px;background:#1f8f67;color:#fff;padding:5px 12px;border-radius:8px;font-size:0.85rem;font-weight:700;z-index:2;box-shadow:0 2px 8px rgba(31,143,103,0.13);">Booked</span>
                            <?php else: ?>
                                <span style="position:absolute;top:10px;right:10px;background:#ff7a2f;color:#fff;padding:5px 12px;border-radius:8px;font-size:0.85rem;font-weight:700;z-index:2;box-shadow:0 2px 8px rgba(255,122,47,0.13);">Available</span>
                            <?php endif; ?>
                            <div class="carousel">
                                <?php if (!empty($imgs)): ?>
                                    <?php $first = true; foreach ($imgs as $img): ?>
                                        <img src="display_image.php?img_id=<?php echo $img['id']; ?>" alt="Property image" class="<?php echo $first ? 'active' : ''; ?>">
                                        <?php $first = false; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <img src="images/no-image.png" alt="No image" class="active">
                                <?php endif; ?>
                            </div>
                            <div class="content">
                                <h3 style="display:flex;align-items:center;gap:6px;">
                                    <?php echo htmlspecialchars($row['title']); ?>
                                </h3>
                                <p style="margin-bottom:2px;"><span style="color:#2276d2;font-weight:600;">📍</span> <?php echo htmlspecialchars($row['location']); ?></p>
                                <p style="margin-bottom:2px;"><span style="color:#1f8f67;font-weight:600;">💰</span> $<?php echo number_format($row['price']); ?></p>
                                <?php if (!empty($row['landlord_name'])): ?>
                                    <p style="margin-bottom:2px;"><span style="color:#ff7a2f;font-weight:600;">👤</span> Landlord: <?php echo htmlspecialchars($row['landlord_name']); ?></p>
                                <?php endif; ?>
                                <div class="actions">
                                    <?php if ($alreadyBooked): ?>
                                        <a href="#active-bookings" class="btn detail" style="background:#1f8f67;">View Booking</a>
                                    <?php else: ?>
                                        <a href="?request_property=<?php echo $row['id']; ?>" class="btn request">Request to Rent</a>
                                    <?php endif; ?>
                                    <a href="javascript:void(0)" class="btn detail" onclick="openModal(<?php echo $row['id']; ?>)">View Details</a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No approved properties available yet.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="section">
            <h2>My Requests</h2>
            <?php if (!empty($requests)): ?>
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
                            <?php foreach ($requests as $row): ?>
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
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>You have not made any requests yet.</p>
            <?php endif; ?>
        </section>

        <section class="section">
            <h2>🏠 Active Bookings</h2>
            <?php if (!empty($active_bookings)): ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px;">
                    <?php foreach ($active_bookings as $booking): ?>
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
                    <?php endforeach; ?>
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
