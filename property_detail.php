<?php
session_start();
include "db.php";

// Validate property ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<p>Invalid property.</p>";
    exit;
}
$property_id = intval($_GET['id']);

// Fetch property + landlord info (using landlord_id)
$sql = "SELECT p.*, u.name AS landlord_name, u.email AS landlord_email
        FROM properties p
        JOIN users u ON p.landlord_id = u.id
        WHERE p.id = ? AND p.status='approved' LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $property_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "<p>Property not found or not approved yet.</p>";
    exit;
}

$property = $result->fetch_assoc();

// Check booking status
$booking_status = $property['booking_status'] ?? 'available';

// Fetch images
$img_stmt = $conn->prepare("SELECT id FROM property_images WHERE property_id=?");
$img_stmt->bind_param("i", $property_id);
$img_stmt->execute();
$imgs = $img_stmt->get_result();
?>

<style>
    .detail-shell {
        font-family: 'Manrope', sans-serif;
        color: #1f2430;
    }

    .detail-title {
        margin: 0 0 14px;
        font-family: 'Plus Jakarta Sans', sans-serif;
        font-size: 1.5rem;
        letter-spacing: -0.02em;
    }

    .detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .detail-images {
        display: grid;
        gap: 10px;
        max-height: 420px;
        overflow: auto;
        padding-right: 4px;
    }

    .detail-images img {
        width: 100%;
        max-width: 420px;
        border-radius: 12px;
        object-fit: cover;
        border: 1px solid rgba(31, 36, 48, 0.12);
    }

    .meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 10px;
    }

    .meta span {
        display: inline-block;
        background: #f4f7f9;
        border: 1px solid rgba(31, 36, 48, 0.12);
        border-radius: 999px;
        padding: 6px 10px;
        font-size: 0.9rem;
        color: #4c566f;
    }

    .detail-price {
        margin: 0 0 10px;
        font-size: 1.1rem;
        font-weight: 800;
        color: #15543e;
    }

    .detail-description {
        line-height: 1.58;
        color: #4b556a;
        margin-bottom: 16px;
    }

    .landlord-box {
        background: #f9fbfc;
        border: 1px solid rgba(31, 36, 48, 0.12);
        border-radius: 12px;
        padding: 12px;
    }

    .landlord-box h4 {
        margin: 0 0 8px;
        font-size: 1rem;
    }

    .landlord-box p {
        margin: 4px 0;
        color: #4b556a;
        font-size: 0.94rem;
    }

    .detail-actions {
        margin-top: 14px;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .detail-actions a {
        text-decoration: none;
        color: #fff;
        font-size: 0.92rem;
        font-weight: 700;
        padding: 10px 13px;
        border-radius: 10px;
    }

    .btn-request {
        background: linear-gradient(140deg, #1f8f67, #15543e);
    }

    .btn-chat {
        background: linear-gradient(140deg, #2276d2, #1b5aa8);
    }

    .btn-book {
        background: linear-gradient(140deg, #27ae60, #1e8449);
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

    @media (max-width: 760px) {
        .detail-grid {
            grid-template-columns: 1fr;
        }

        .detail-images {
            max-height: none;
        }
    }
</style>

<div class="detail-shell">
    <h2 class="detail-title"><?php echo htmlspecialchars($property['title']); ?></h2>

    <div class="detail-grid">
        <div class="detail-images">
            <?php if ($imgs->num_rows > 0): ?>
                <?php while ($img = $imgs->fetch_assoc()): ?>
                    <img src="display_image.php?img_id=<?php echo $img['id']; ?>" alt="Property image">
                <?php endwhile; ?>
            <?php else: ?>
                <img src="images/no-image.png" alt="No image available">
            <?php endif; ?>
        </div>

        <div>
            <div class="meta">
                <span><?php echo htmlspecialchars($property['location']); ?></span>
                <span>Approved Listing</span>
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

            <p class="detail-price">$<?php echo number_format($property['price']); ?></p>
            <p class="detail-description"><?php echo nl2br(htmlspecialchars($property['description'])); ?></p>

            <div class="landlord-box">
                <h4>Landlord Contact</h4>
                <p>Name: <?php echo htmlspecialchars($property['landlord_name']); ?></p>
                <p>Email: <?php echo htmlspecialchars($property['landlord_email']); ?></p>
            </div>

            <div class="detail-actions">
                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'renter'): ?>
                    <?php if ($booking_status === 'available'): ?>
                        <a class="btn-book" href="rental_agreement.php?property_id=<?php echo $property['id']; ?>">Book Now</a>
                    <?php else: ?>
                        <p style="padding: 10px 13px; background: #f0f0f0; border-radius: 10px; color: #666; font-weight: 700;">Not Available</p>
                    <?php endif; ?>
                    <a class="btn-chat" href="chat.php?property_id=<?php echo $property['id']; ?>&with=<?php echo $property['landlord_id']; ?>">Message Landlord</a>
                <?php elseif (!isset($_SESSION['user_id'])): ?>
                    <a class="btn-request" href="login.php">Login to Book</a>
                    <a class="btn-chat" href="login.php">Contact Landlord</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
