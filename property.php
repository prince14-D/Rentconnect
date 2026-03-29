<?php
session_start();
include "app_init.php";

// Get property ID
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}
$property_id = intval($_GET['id']);

// Fetch property from the shared migration layer.
$property = rc_mig_get_property_by_id($conn, $property_id, false);

if (!$property) {
    echo "Property not found.";
    exit;
}

// Check booking status
$booking_status = $property['booking_status'] ?? 'available';

$propertyImages = rc_mig_get_property_image_ids($conn, (int) $property_id);
$firstImageId = isset($propertyImages[0]['id']) ? (int) $propertyImages[0]['id'] : 0;
$heroImageSrc = $firstImageId > 0
    ? 'display_image.php?img_id=' . $firstImageId
    : 'images/no-image.png';

$back_url = 'index.php';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $referer_path = parse_url((string) $_SERVER['HTTP_REFERER'], PHP_URL_PATH);
    if (is_string($referer_path) && $referer_path !== '') {
        $back_url = ltrim($referer_path, '/');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Property details - <?php echo htmlspecialchars(substr($property['title'], 0, 50)); ?>">
    <meta name="theme-color" content="#1f8f67">
    <link rel="apple-touch-icon" href="/favicon.svg" />
    <link rel="icon" href="/favicon.svg" />
    <link rel="manifest" href="/manifest.json">
    <title><?php echo htmlspecialchars($property['title']); ?> - RentConnect</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #222936;
            --muted: #5b6579;
            --brand: #1f8f67;
            --brand-deep: #15543e;
            --accent: #ff7a2f;
            --line: rgba(34, 41, 54, 0.12);
            --card: rgba(255, 255, 255, 0.94);
            --shadow: 0 20px 42px rgba(24, 34, 48, 0.16);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Manrope', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 8% 8%, rgba(255, 122, 47, 0.24), transparent 36%),
                radial-gradient(circle at 92% 12%, rgba(31, 143, 103, 0.2), transparent 34%),
                linear-gradient(160deg, #f9f6ef, #f2f7f8 58%, #fffdfa);
            min-height: 100vh;
        }

        .container {
            width: min(1080px, 94vw);
            margin: 0 auto;
        }

        header {
            position: sticky;
            top: 0;
            z-index: 20;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.82);
            border-bottom: 1px solid var(--line);
        }

        .topbar {
            min-height: 72px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .brand {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.3rem;
            letter-spacing: -0.02em;
            font-weight: 800;
            text-decoration: none;
            color: #113749;
        }

        .top-links {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .top-links a {
            text-decoration: none;
            color: #33405a;
            border-radius: 9px;
            padding: 9px 12px;
            font-weight: 700;
        }

        .top-links a:hover {
            background: rgba(31, 143, 103, 0.08);
            color: #15543e;
        }

        .page {
            padding: 28px 0 48px;
        }

        .property-card {
            background: var(--card);
            border: 1px solid rgba(255, 255, 255, 0.88);
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .hero-image {
            width: 100%;
            height: min(52vh, 460px);
            object-fit: cover;
            display: block;
        }

        .content {
            padding: clamp(18px, 3vw, 28px);
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 20px;
        }

        h1 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: clamp(1.5rem, 2.4vw, 2.1rem);
            margin-bottom: 10px;
            letter-spacing: -0.02em;
        }

        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }

        .meta span {
            padding: 7px 11px;
            border-radius: 999px;
            background: #f5f8fa;
            border: 1px solid var(--line);
            color: #49556d;
            font-size: 0.9rem;
        }

        .price {
            font-size: 1.35rem;
            font-weight: 800;
            color: #15543e;
            margin-bottom: 10px;
        }

        .description {
            line-height: 1.62;
            color: var(--muted);
        }

        .side {
            display: grid;
            gap: 14px;
            align-content: start;
        }

        .box {
            background: #f9fbfc;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px;
        }

        .box h3 {
            margin-bottom: 8px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1rem;
        }

        .box p {
            margin: 4px 0;
            color: #4c566d;
            font-size: 0.94rem;
        }

        .actions {
            display: grid;
            gap: 8px;
        }

        .actions a,
        .actions p {
            display: inline-block;
            text-decoration: none;
            border-radius: 10px;
            padding: 11px 12px;
            font-size: 0.93rem;
            font-weight: 700;
            text-align: center;
        }

        .btn-request { color: #fff; background: linear-gradient(140deg, var(--brand), var(--brand-deep)); }
        .btn-favorite { color: #fff; background: linear-gradient(140deg, var(--accent), #f15f13); }
        .btn-login { color: #fff; background: linear-gradient(140deg, #2276d2, #1b5aa8); }

        .owner-note {
            color: #15543e;
            border: 1px dashed rgba(21, 84, 62, 0.35);
            background: rgba(31, 143, 103, 0.07);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .status-available {
            background: rgba(39, 174, 96, 0.2);
            color: #27ae60;
        }

        .status-booked {
            background: rgba(192, 57, 43, 0.2);
            color: #c0392b;
        }

        .status-maintenance {
            background: rgba(241, 196, 15, 0.2);
            color: #f39c12;
        }

        .back-row {
            margin-bottom: 10px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            border: 1px solid var(--line);
            color: #30435c;
            background: #fff;
            border-radius: 9px;
            padding: 8px 11px;
            font-size: 0.84rem;
            font-weight: 800;
        }

        .back-btn:hover {
            background: #f3f8fb;
            color: #1f8f67;
        }

        .btn-book {
            color: #fff !important;
            background: linear-gradient(140deg, #27ae60, #1e8449) !important;
        }

        @media (max-width: 820px) {
            .content {
                grid-template-columns: 1fr;
            }

            .hero-image {
                height: 34vh;
            }
        }
    </style>
</head>
<body>
<header>
    <div class="container topbar">
        <a class="brand" href="index.php">RentConnect</a>
        <nav class="top-links">
            <a href="index.php">Home</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="<?php echo $_SESSION['role'] == 'landlord' ? 'landlord_dashboard.php' : 'renter_dashboard.php'; ?>">Dashboard</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="signup.php">Sign Up</a>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="page">
    <div class="container">
        <div class="back-row">
            <a href="<?php echo htmlspecialchars($back_url); ?>" class="back-btn">&larr; Back</a>
        </div>

        <article class="property-card">
            <img src="<?php echo htmlspecialchars($heroImageSrc); ?>" alt="Property" class="hero-image">

            <div class="content">
                <section>
                    <h1><?php echo htmlspecialchars($property['title']); ?></h1>
                    <div class="meta">
                        <span><?php echo htmlspecialchars($property['location']); ?></span>
                        <span>Listed Property</span>
                    </div>

                    <div class="status-badge status-<?php echo htmlspecialchars($booking_status); ?>">
                        <?php 
                            $status_text = [
                                'available' => '✓ Available for Booking',
                                'booked' => '◆ Currently Booked',
                                'maintenance' => '⚠ Under Maintenance'
                            ];
                            echo $status_text[$booking_status] ?? 'Unknown Status';
                        ?>
                    </div>

                    <p class="price">$<?php echo number_format($property['price']); ?></p>
                    <p class="description"><?php echo nl2br(htmlspecialchars($property['description'])); ?></p>
                </section>

                <aside class="side">
                    <div class="actions">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="login.php" class="btn-login">Login to Book</a>
                            <a href="signup.php" class="btn-favorite">Create Account</a>
                        <?php elseif ($_SESSION['role'] == 'renter'): ?>
                            <?php if ($booking_status === 'available'): ?>
                                <a href="rental_agreement.php?property_id=<?php echo $property['id']; ?>" class="btn-book btn-request">Book Now</a>
                            <?php else: ?>
                                <p class="owner-note">This property is not available for booking.</p>
                            <?php endif; ?>
                            <a href="<?php echo isset($_SESSION['user_id']) ? 'chat.php?property_id=' . $property['id'] . '&with=' . $property['landlord_id'] : '#'; ?>" class="btn-favorite">Contact Landlord</a>
                        <?php elseif ($_SESSION['role'] == 'landlord' && $_SESSION['user_id'] == $property['owner_id']): ?>
                            <div class="owner-note">
                                <strong>This is your property</strong>
                                <p style="margin-top: 8px; font-size: 0.9rem;">Current Status: <span style="color: inherit; font-weight: 700;">
                                    <?php 
                                        $status_text = [
                                            'available' => 'Available',
                                            'booked' => 'Booked',
                                            'maintenance' => 'Maintenance'
                                        ];
                                        echo $status_text[$booking_status] ?? 'Unknown';
                                    ?>
                                </span></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="box">
                        <h3>Contact Landlord</h3>
                        <p>Name: <?php echo htmlspecialchars($property['owner_name']); ?></p>
                        <p>Email: <?php echo htmlspecialchars($property['owner_email']); ?></p>
                        <p>Phone: <?php echo htmlspecialchars((string) (($property['contact'] ?? '') !== '' ? $property['contact'] : ($property['owner_phone'] ?? 'Not provided'))); ?></p>
                        <p>Location: <?php echo htmlspecialchars((string) ($property['location'] ?? 'Not provided')); ?></p>
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'renter'): ?>
                            <p><a href="chat.php?property_id=<?php echo $property['id']; ?>&with=<?php echo $_SESSION['user_id'] == $property['owner_id'] ? 'self' : $property['owner_id']; ?>" style="color: var(--brand); text-decoration: none; font-weight: 700;">Start Conversation →</a></p>
                        <?php else: ?>
                            <p>You can chat with landlord after booking.</p>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>
        </article>
    </div>
</main>
</body>
</html>
