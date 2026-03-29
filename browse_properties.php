<?php
session_start();
include "app_init.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'renter') {
    header("Location: login.php");
    exit;
}

$renter_id = (int) $_SESSION['user_id'];
$message = '';
$message_type = 'success';
$current_page = basename((string) ($_SERVER['PHP_SELF'] ?? 'browse_properties.php'));
$user_role = (string) ($_SESSION['role'] ?? 'renter');

$active_bookings = rc_mig_get_renter_active_bookings($conn, $renter_id);
$search = (string) ($_GET['search'] ?? '');

if (isset($_GET['request_property']) || isset($_GET['book_property'])) {
    $property_id = (int) ($_GET['book_property'] ?? $_GET['request_property']);
    $property = rc_mig_get_property_by_id($conn, $property_id, true);
    $property_status = strtolower((string) ($property['status'] ?? 'approved'));
    $booking_status = strtolower((string) ($property['booking_status'] ?? 'available'));
    $is_unavailable = !$property
        || in_array($property_status, ['taken', 'booked', 'inactive', 'rejected'], true)
        || in_array($booking_status, ['taken', 'booked', 'maintenance'], true);

    if ($is_unavailable) {
        $message = 'This property is no longer available.';
        $message_type = 'error';
    } elseif (rc_mig_create_request_if_missing($conn, $renter_id, $property_id)) {
        $message = 'Booking request submitted successfully!';
        $message_type = 'success';
    } else {
        $message = 'You already have a booking request for this property.';
        $message_type = 'error';
    }
}

$display_properties = rc_mig_get_index_properties(
    $conn,
    $renter_id,
    $user_role,
    $search,
    0,
    0,
    200
);

if (!empty($display_properties)) {
    usort($display_properties, static function (array $a, array $b): int {
        $aStatus = strtolower((string) ($a['status'] ?? ''));
        $bStatus = strtolower((string) ($b['status'] ?? ''));
        $aBooking = strtolower((string) ($a['booking_status'] ?? 'available'));
        $bBooking = strtolower((string) ($b['booking_status'] ?? 'available'));

        $aAvailable = !in_array($aStatus, ['taken', 'booked', 'inactive', 'rejected'], true)
            && !in_array($aBooking, ['taken', 'booked', 'maintenance'], true);
        $bAvailable = !in_array($bStatus, ['taken', 'booked', 'inactive', 'rejected'], true)
            && !in_array($bBooking, ['taken', 'booked', 'maintenance'], true);

        if ($aAvailable !== $bAvailable) {
            return $aAvailable ? -1 : 1;
        }

        $aCreated = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
        $bCreated = strtotime((string) ($b['created_at'] ?? '')) ?: 0;

        return $bCreated <=> $aCreated;
    });
}

foreach ($display_properties as &$property_row) {
    $landlord_id = (int) (($property_row['landlord_id'] ?? 0) ?: ($property_row['owner_id'] ?? 0));
    $landlord = $landlord_id > 0 ? rc_mig_get_user_by_id($conn, $landlord_id) : null;

    $property_row['landlord_id'] = $landlord_id;
    $property_row['landlord_name'] = (string) (($property_row['landlord_name'] ?? '') ?: ($landlord['name'] ?? 'Unknown'));
    $property_row['landlord_email'] = (string) ($landlord['email'] ?? '');
    $property_row['landlord_phone'] = (string) ($landlord['phone'] ?? '');
    $property_row['landlord_photo'] = (string) (($landlord['profile_pic'] ?? '') ?: ($landlord['avatar_url'] ?? ''));
}
unset($property_row);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Browse available rental properties.">
<title>Browse Properties - RentConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --panel: #ffffff;
    --ink: #1f2836;
    --muted: #627089;
    --brand: #1f8f67;
    --line: #d8e4ed;
}
* { box-sizing: border-box; }
body {
    margin: 0;
    font-family: 'Manrope', sans-serif;
    color: var(--ink);
    background: radial-gradient(circle at top right, #f7fbff 0%, #edf3f8 42%, #e6eef5 100%);
}
.wrap { max-width: 1220px; margin: 0 auto; padding: 20px 14px 34px; }
.hero {
    background: linear-gradient(120deg, #123f50 0%, #176480 64%, #1f8f67 100%);
    color: #fff;
    border-radius: 14px;
    padding: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    box-shadow: 0 10px 24px rgba(13, 31, 43, 0.2);
}
.hero h1 { margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.35rem; }
.hero p { margin: 5px 0 0; opacity: 0.92; font-size: 0.92rem; }
.back-home {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    text-decoration: none;
    color: #133744;
    background: #fff;
    border-radius: 10px;
    padding: 9px 12px;
    font-size: 0.86rem;
    font-weight: 800;
}
.layout { margin-top: 14px; display: flex; gap: 14px; align-items: flex-start; }
.side {
    width: 250px;
    flex-shrink: 0;
    background: linear-gradient(180deg, rgba(19, 64, 78, 0.96), rgba(16, 50, 63, 0.96));
    border-radius: 14px;
    padding: 12px;
    box-shadow: 0 10px 24px rgba(15, 31, 40, 0.14);
    position: sticky;
    top: 14px;
}
.side h3 { margin: 4px 4px 10px; color: #fff; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1rem; }
.menu { display: grid; gap: 7px; }
.menu a {
    text-decoration: none;
    color: #eaf4f6;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 10px;
    padding: 9px 10px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.88rem;
    font-weight: 700;
    transition: all 0.2s ease;
}
.menu a:hover,
.menu a.active {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(255, 255, 255, 0.34);
    transform: translateX(2px);
}
.main { flex: 1; min-width: 0; }
.notice {
    margin: 0 0 12px;
    border-radius: 11px;
    border: 1px solid rgba(31, 143, 103, 0.22);
    background: rgba(31, 143, 103, 0.09);
    color: #1f8f67;
    padding: 9px 11px;
    font-size: 0.88rem;
    font-weight: 700;
}
.notice.error {
    border-color: rgba(217, 83, 79, 0.26);
    background: rgba(217, 83, 79, 0.1);
    color: #a83430;
}
.panel {
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: 13px;
    padding: 14px;
    box-shadow: 0 6px 20px rgba(28, 50, 76, 0.05);
    margin-bottom: 12px;
}
.search-form { display: flex; gap: 8px; flex-wrap: wrap; }
.search-form input {
    flex: 1;
    min-width: 220px;
    border: 1px solid #cad7e2;
    border-radius: 8px;
    padding: 10px;
    font: inherit;
}
.search-form button {
    border: none;
    border-radius: 8px;
    padding: 10px 14px;
    font-weight: 800;
    color: #fff;
    cursor: pointer;
    background: linear-gradient(140deg, #1f8f67, #15543e);
}
.property-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; gap: 10px; flex-wrap: wrap; }
.property-count { color: var(--muted); font-size: 0.86rem; font-weight: 700; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(270px, 1fr)); gap: 12px; }
.property-card {
    border: 1px solid #d9e6ef;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 4px 14px rgba(20, 41, 63, 0.06);
    position: relative;
}
.carousel { height: 175px; background: #ecf2f8; position: relative; overflow: hidden; }
.carousel img { width: 100%; height: 100%; object-fit: cover; display: none; }
.carousel img.active { display: block; }
.content { padding: 12px; }
.content h3 { margin: 0 0 6px; font-size: 1rem; }
.content p { margin: 4px 0; font-size: 0.88rem; color: #51617a; }

.landlord-box {
    margin-top: 8px;
    border: 1px solid #dfe9f2;
    border-radius: 9px;
    padding: 8px;
    background: #f8fbfe;
}

.landlord-head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}

.landlord-photo {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid #d6e4ee;
    background: #ebf2f8;
}

.landlord-name {
    font-size: 0.84rem;
    font-weight: 800;
    color: #2b3f58;
}

.landlord-detail {
    display: block;
    font-size: 0.8rem;
    color: #58708a;
    margin: 2px 0;
}
.actions { margin-top: 10px; display: flex; gap: 7px; flex-wrap: wrap; }
.btn {
    text-decoration: none;
    border-radius: 8px;
    padding: 8px 10px;
    font-size: 0.79rem;
    font-weight: 800;
    color: #fff;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.btn.request { background: #1f8f67; }
.btn.detail { background: #2276d2; }
.btn.booked { background: #7a8598; cursor: not-allowed; }

.status-chip {
    position: absolute;
    top: 9px;
    right: 9px;
    color: #fff;
    padding: 5px 10px;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 800;
    z-index: 2;
}

.status-chip.available { background: #ff7a2f; }
.status-chip.booked { background: #7a8598; }
.status-chip.mine { background: #1f8f67; }
.status-chip.new {
    right: auto;
    left: 9px;
    background: #2276d2;
}
@media (max-width: 980px) {
    .layout { flex-direction: column; }
    .side { width: 100%; position: static; }
    .menu { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 560px) {
    .menu { grid-template-columns: 1fr; }
    .hero { flex-direction: column; align-items: flex-start; }
}
</style>
</head>
<body>
<div class="wrap">
    <header class="hero">
        <div>
            <h1>Browse Properties</h1>
            <p>Shows the same property feed as Home, with quick booking and landlord contact details.</p>
        </div>
        <a class="back-home" href="renter_dashboard.php"><i class="fa-solid fa-arrow-left"></i>Dashboard</a>
    </header>

    <div class="layout">
        <aside class="side">
            <h3>Renter Services</h3>
            <nav class="menu">
                <a href="renter_profile.php" class="<?php echo $current_page === 'renter_profile.php' ? 'active' : ''; ?>"><i class="fa-solid fa-user"></i>Profile</a>
                <a href="browse_properties.php" class="<?php echo $current_page === 'browse_properties.php' ? 'active' : ''; ?>"><i class="fa-solid fa-house"></i>Browse Properties</a>
                <a href="my_requests.php" class="<?php echo $current_page === 'my_requests.php' ? 'active' : ''; ?>"><i class="fa-solid fa-envelope-open-text"></i>My Requests</a>
                <a href="active_bookings.php" class="<?php echo $current_page === 'active_bookings.php' ? 'active' : ''; ?>"><i class="fa-solid fa-calendar-check"></i>Active Bookings</a>
                <a href="rent_reminders.php" class="<?php echo $current_page === 'rent_reminders.php' ? 'active' : ''; ?>"><i class="fa-solid fa-bell"></i>Reminders</a>
                <a href="rent_payments.php" class="<?php echo $current_page === 'rent_payments.php' ? 'active' : ''; ?>"><i class="fa-solid fa-credit-card"></i>Payments</a>
                <a href="chat_list.php" class="<?php echo in_array($current_page, ['chat.php', 'chat_list.php'], true) ? 'active' : ''; ?>"><i class="fa-solid fa-comments"></i>Chat</a>
                <a href="index.php"><i class="fa-solid fa-house-circle-check"></i>Home</a>
            </nav>
        </aside>

        <main class="main">
            <?php if ($message !== ''): ?>
                <p class="notice <?php echo $message_type === 'error' ? 'error' : ''; ?>"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>

            <section class="panel">
                <form class="search-form" method="get">
                    <input type="text" name="search" placeholder="Search by location (same filter as Home)" value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit">Search</button>
                </form>
            </section>

            <section class="panel">
                <div class="property-toolbar">
                    <h2 style="margin:0;font-size:1.03rem;font-family:'Plus Jakarta Sans',sans-serif;">Home Feed Listings</h2>
                    <p class="property-count"><?php echo count($display_properties); ?> result(s)</p>
                </div>

                <div class="grid">
                    <?php if (!empty($display_properties)): ?>
                        <?php foreach ($display_properties as $row): ?>
                            <?php
                            $imgs = rc_mig_get_property_image_ids($conn, (int) $row['id']);
                            $propertyStatus = strtolower((string) ($row['status'] ?? 'approved'));
                            $bookingStatus = strtolower((string) ($row['booking_status'] ?? 'available'));
                            $isAvailable = !in_array($propertyStatus, ['taken', 'booked', 'inactive', 'rejected'], true)
                                && !in_array($bookingStatus, ['taken', 'booked', 'maintenance'], true);
                            $isNew = false;
                            if (!empty($row['created_at'])) {
                                $createdTs = strtotime((string) $row['created_at']);
                                $isNew = $createdTs !== false && $createdTs >= strtotime('-7 days');
                            }
                            $alreadyBooked = false;
                            foreach ($active_bookings as $booking) {
                                if ((int) ($booking['property_id'] ?? 0) === (int) $row['id']) {
                                    $alreadyBooked = true;
                                    break;
                                }
                            }
                            ?>
                            <article class="property-card">
                                <?php if ($isNew): ?>
                                    <span class="status-chip new">Just Added</span>
                                <?php endif; ?>
                                <?php if ($alreadyBooked): ?>
                                    <span class="status-chip mine">Your Booking</span>
                                <?php elseif ($isAvailable): ?>
                                    <span class="status-chip available">Available</span>
                                <?php else: ?>
                                    <span class="status-chip booked">Booked</span>
                                <?php endif; ?>

                                <div class="carousel">
                                    <?php if (!empty($imgs)): ?>
                                        <?php $first = true; foreach ($imgs as $img): ?>
                                            <img src="display_image.php?img_id=<?php echo (int) $img['id']; ?>" alt="Property image" class="<?php echo $first ? 'active' : ''; ?>">
                                            <?php $first = false; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <img src="images/no-image.png" alt="No image" class="active">
                                    <?php endif; ?>
                                </div>

                                <div class="content">
                                    <h3><?php echo htmlspecialchars((string) ($row['title'] ?? 'Property')); ?></h3>
                                    <p>📍 <?php echo htmlspecialchars((string) ($row['location'] ?? '')); ?></p>
                                    <p>💰 $<?php echo number_format((float) ($row['price'] ?? 0)); ?></p>
                                    <div class="landlord-box">
                                        <div class="landlord-head">
                                            <img src="<?php echo htmlspecialchars((string) (($row['landlord_photo'] ?? '') !== '' ? $row['landlord_photo'] : 'images/default-avatar.png')); ?>" alt="Landlord photo" class="landlord-photo">
                                            <div class="landlord-name"><?php echo htmlspecialchars((string) ($row['landlord_name'] ?? 'Landlord')); ?></div>
                                        </div>
                                        <?php if (!empty($row['landlord_email'])): ?>
                                            <span class="landlord-detail">✉ <?php echo htmlspecialchars((string) $row['landlord_email']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($row['landlord_phone'])): ?>
                                            <span class="landlord-detail">☎ <?php echo htmlspecialchars((string) $row['landlord_phone']); ?></span>
                                        <?php endif; ?>
                                        <span class="landlord-detail">📍 Property location: <?php echo htmlspecialchars((string) ($row['location'] ?? '')); ?></span>
                                    </div>

                                    <div class="actions">
                                        <?php if ($alreadyBooked): ?>
                                            <a href="active_bookings.php" class="btn detail">View Booking</a>
                                        <?php elseif (!$isAvailable): ?>
                                            <span class="btn booked">Currently Booked</span>
                                        <?php else: ?>
                                            <a href="?search=<?php echo urlencode($search); ?>&book_property=<?php echo (int) $row['id']; ?>" class="btn request">Book Property</a>
                                        <?php endif; ?>
                                        <a href="property.php?id=<?php echo (int) $row['id']; ?>" class="btn detail">View Property</a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color:#5f6f87;">No properties match your search right now.</p>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</div>
</body>
</html>
