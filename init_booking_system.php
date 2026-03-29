<?php
/**
 * Database initialization script for booking and payment system
 * Run this once to create necessary tables
 */

session_start();
include "app_init.php";

// Check admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. Admin only.");
}

$errors = [];
$success = [];

// 1. Create bookings table
$sql_bookings = "
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    renter_id INT NOT NULL,
    landlord_id INT NOT NULL,
    move_in_date DATE NOT NULL,
    move_out_date DATE,
    monthly_rent DECIMAL(10,2) NOT NULL,
    lease_duration_months INT DEFAULT 12,
    status ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
    cancelled_by ENUM('renter', 'landlord'),
    cancellation_reason TEXT,
    cancelled_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (property_id) REFERENCES properties(id),
    FOREIGN KEY (renter_id) REFERENCES users(id),
    FOREIGN KEY (landlord_id) REFERENCES users(id),
    INDEX (property_id),
    INDEX (renter_id),
    INDEX (landlord_id),
    INDEX (status)
)";

if ($conn->query($sql_bookings)) {
    $success[] = "✓ Bookings table created";
} else {
    $errors[] = "✗ Bookings table: " . $conn->error;
}

// 2. Create rental_agreements table
$sql_agreements = "
CREATE TABLE IF NOT EXISTS rental_agreements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL UNIQUE,
    property_id INT NOT NULL,
    renter_id INT NOT NULL,
    landlord_id INT NOT NULL,
    monthly_rent DECIMAL(10,2) NOT NULL,
    lease_start DATE NOT NULL,
    lease_end DATE,
    duration_months INT,
    deposit_amount DECIMAL(10,2),
    utilities_included TINYINT(1) DEFAULT 0,
    pets_allowed TINYINT(1) DEFAULT 0,
    parking TINYINT(1) DEFAULT 0,
    additional_terms TEXT,
    signed_by_renter TINYINT(1) DEFAULT 0,
    signed_by_landlord TINYINT(1) DEFAULT 0,
    renter_signature_date TIMESTAMP,
    landlord_signature_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (property_id) REFERENCES properties(id),
    FOREIGN KEY (renter_id) REFERENCES users(id),
    FOREIGN KEY (landlord_id) REFERENCES users(id),
    INDEX (property_id),
    INDEX (renter_id),
    INDEX (landlord_id)
)";

if ($conn->query($sql_agreements)) {
    $success[] = "✓ Rental agreements table created";
} else {
    $errors[] = "✗ Rental agreements table: " . $conn->error;
}

// 3. Create payments table
$sql_payments = "
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    renter_id INT NOT NULL,
    landlord_id INT NOT NULL,
    property_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_month DATE NOT NULL,
    payment_method ENUM('card', 'momo', 'bank_transfer') NOT NULL,
    payment_date TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected', 'refunded') DEFAULT 'pending',
    reference_number VARCHAR(100),
    landlord_verified TINYINT(1) DEFAULT 0,
    verified_at TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (renter_id) REFERENCES users(id),
    FOREIGN KEY (landlord_id) REFERENCES users(id),
    FOREIGN KEY (property_id) REFERENCES properties(id),
    INDEX (booking_id),
    INDEX (renter_id),
    INDEX (landlord_id),
    INDEX (status),
    UNIQUE KEY unique_booking_month (booking_id, payment_month)
)";

if ($conn->query($sql_payments)) {
    $success[] = "✓ Payments table created";
} else {
    $errors[] = "✗ Payments table: " . $conn->error;
}

// 4. Create rent_reminders table
$sql_reminders = "
CREATE TABLE IF NOT EXISTS rent_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    landlord_id INT NOT NULL,
    renter_id INT NOT NULL,
    payment_month DATE NOT NULL,
    reminder_type ENUM('due_soon', 'overdue', 'manual') DEFAULT 'manual',
    message TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_by_renter TINYINT(1) DEFAULT 0,
    read_at TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (landlord_id) REFERENCES users(id),
    FOREIGN KEY (renter_id) REFERENCES users(id),
    INDEX (booking_id),
    INDEX (landlord_id),
    INDEX (renter_id)
)";

if ($conn->query($sql_reminders)) {
    $success[] = "✓ Rent reminders table created";
} else {
    $errors[] = "✗ Rent reminders table: " . $conn->error;
}

// 5. Add booking_status column to properties if not exists
$check_column = $conn->query("SHOW COLUMNS FROM properties LIKE 'booking_status'");
if ($check_column->num_rows === 0) {
    $sql_alter = "ALTER TABLE properties ADD COLUMN booking_status ENUM('available', 'booked', 'maintenance') DEFAULT 'available' AFTER status";
    if ($conn->query($sql_alter)) {
        $success[] = "✓ Added booking_status to properties table";
    } else {
        $errors[] = "✗ Failed to add booking_status: " . $conn->error;
    }
} else {
    $success[] = "✓ booking_status already exists";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Initialization - RentConnect</title>
    <style>
        body {
            font-family: 'Manrope', Arial, sans-serif;
            background: linear-gradient(135deg, #1f8f67 0%, #15543e 100%);
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 20px;
            color: #333;
        }
        .container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #1f8f67;
            margin-bottom: 30px;
            text-align: center;
        }
        .success {
            color: #27ae60;
            background: #d5f4e6;
            padding: 12px;
            margin: 8px 0;
            border-radius: 6px;
            border-left: 4px solid #27ae60;
        }
        .error {
            color: #c0392b;
            background: #fadbd8;
            padding: 12px;
            margin: 8px 0;
            border-radius: 6px;
            border-left: 4px solid #c0392b;
        }
        .note {
            background: #f9f6ef;
            padding: 15px;
            border-radius: 6px;
            margin-top: 20px;
            font-size: 0.9rem;
            color: #666;
        }
        .redirect {
            text-align: center;
            margin-top: 30px;
        }
        a {
            color: #1f8f67;
            text-decoration: none;
            font-weight: 600;
        }
        a:hover {
            text-decoration: underline;
        }

        @media (max-width: 640px) {
            body {
                padding: 12px;
            }

            .container {
                padding: 18px;
            }

            h1 {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ Database Initialization</h1>
        
        <?php if (!empty($success)): ?>
            <h3 style="color: #27ae60; margin-top: 20px;">Success:</h3>
            <?php foreach ($success as $msg): ?>
                <div class="success"><?php echo htmlspecialchars($msg); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <h3 style="color: #c0392b; margin-top: 20px;">Errors:</h3>
            <?php foreach ($errors as $msg): ?>
                <div class="error"><?php echo htmlspecialchars($msg); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (empty($errors)): ?>
            <div class="note">
                <strong>✓ All systems initialized!</strong><br>
                The database is now ready for the booking and payment system.
            </div>
        <?php endif; ?>

        <div class="redirect">
            <p><a href="admin_dashboard.php">← Back to Admin Dashboard</a></p>
        </div>
    </div>
</body>
</html>
