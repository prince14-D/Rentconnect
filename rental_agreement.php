<?php
session_start();
include "app_init.php";
include "email_notifications.php";

// Ensure renter access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'renter') {
    header("Location: login.php");
    exit;
}

$renter_id = $_SESSION['user_id'];
$property_id = intval($_GET['property_id'] ?? 0);
$message = "";
$error = "";

// Fetch property details
$stmt = $conn->prepare("SELECT p.*, u.name AS landlord_name, u.email AS landlord_email FROM properties p JOIN users u ON p.owner_id = u.id WHERE p.id = ? AND p.status = 'approved'");
$stmt->bind_param("i", $property_id);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();

if (!$property) {
    die("Property not found or not approved for rental.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $move_in_date = $_POST['move_in_date'] ?? '';
    $move_out_date = $_POST['move_out_date'] ?? '';
    $monthly_rent = floatval($_POST['monthly_rent'] ?? 0);
    $lease_duration = intval($_POST['lease_duration_months'] ?? 12);
    $deposit_amount = floatval($_POST['deposit_amount'] ?? 0);
    $utilities_included = isset($_POST['utilities_included']) ? 1 : 0;
    $pets_allowed = isset($_POST['pets_allowed']) ? 1 : 0;
    $parking = isset($_POST['parking']) ? 1 : 0;
    $additional_terms = $_POST['additional_terms'] ?? '';
    $agree_terms = isset($_POST['agree_terms']) ? 1 : 0;

    // Validation
    if (!$move_in_date || !$monthly_rent || !$lease_duration || !$deposit_amount) {
        $error = "All required fields must be filled.";
    } elseif (!$agree_terms) {
        $error = "You must agree to the rental terms.";
    } elseif (strtotime($move_in_date) <= time()) {
        $error = "Move-in date must be in the future.";
    } elseif ($move_out_date && strtotime($move_out_date) <= strtotime($move_in_date)) {
        $error = "Move-out date must be after move-in date.";
    } else {
        $landlord_id = $property['landlord_id'] ?? $property['owner_id'];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Create booking
            $stmt = $conn->prepare("
                INSERT INTO bookings (property_id, renter_id, landlord_id, move_in_date, move_out_date, monthly_rent, lease_duration_months, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->bind_param("iiisddi", $property_id, $renter_id, $landlord_id, $move_in_date, $move_out_date, $monthly_rent, $lease_duration);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to create booking: " . $stmt->error);
            }
            
            $booking_id = $stmt->insert_id;
            
            // Create rental agreement
            $stmt2 = $conn->prepare("
                INSERT INTO rental_agreements 
                (booking_id, property_id, renter_id, landlord_id, monthly_rent, lease_start, lease_end, duration_months, deposit_amount, utilities_included, pets_allowed, parking, additional_terms, signed_by_renter, renter_signature_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt2->bind_param("iiiidssdiiis", $booking_id, $property_id, $renter_id, $landlord_id, $monthly_rent, $move_in_date, $move_out_date, $lease_duration, $deposit_amount, $utilities_included, $pets_allowed, $parking, $additional_terms);
            
            if (!$stmt2->execute()) {
                throw new Exception("Failed to create rental agreement: " . $stmt2->error);
            }
            
            // Update property booking status
            if (!rc_mig_set_property_booking_status($conn, (int) $property_id, 'booked')) {
              throw new Exception('Failed to update property booking status.');
            }
            
            // Fetch renter and landlord details for email
            $renter_stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
            $renter_stmt->bind_param("i", $renter_id);
            $renter_stmt->execute();
            $renter_user = $renter_stmt->get_result()->fetch_assoc();
            
            // Send booking confirmation emails
            @send_booking_confirmation_renter(
                $renter_user['email'],
                $renter_user['name'],
                $property['title'],
                $property['location'],
                $move_in_date,
                $move_out_date,
                $monthly_rent,
                $property['landlord_name'],
                $booking_id
            );
            
            @send_booking_confirmation_landlord(
                $property['landlord_email'],
                $property['landlord_name'],
                $property['title'],
                $renter_user['name'],
                $move_in_date,
                $monthly_rent,
                $booking_id
            );
            
            $conn->commit();
            
            $_SESSION['success_message'] = "✓ Rental agreement submitted! Confirmation emails have been sent.";
            header("Location: renter_dashboard.php");
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Create a rental agreement for a property.">
<meta name="theme-color" content="#1f8f67">
<link rel="apple-touch-icon" href="/favicon.svg" />
<link rel="icon" href="/favicon.svg" />
<link rel="manifest" href="/manifest.json">
<title>Rental Agreement - RentConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
<style>
:root {
  --ink: #1f2430;
  --muted: #5d6579;
  --brand: #1f8f67;
  --brand-deep: #15543e;
  --accent: #ff7a2f;
  --line: rgba(31, 36, 48, 0.12);
  --shadow: 0 18px 36px rgba(19, 36, 33, 0.14);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Manrope', sans-serif;
  color: var(--ink);
  background:
    radial-gradient(circle at 10% 4%, rgba(255, 122, 47, 0.22), transparent 34%),
    radial-gradient(circle at 92% 8%, rgba(31, 143, 103, 0.2), transparent 30%),
    linear-gradient(165deg, #f9f6ef 0%, #f2f7f8 58%, #fffdfa 100%);
  min-height: 100vh;
}
.container { width: min(900px, 94vw); margin: 0 auto; padding: 28px 0; }
.hero {
  background: linear-gradient(140deg, rgba(16, 62, 79, 0.93), rgba(31, 143, 103, 0.86));
  color: #fff;
  border-radius: 20px;
  padding: clamp(18px, 4vw, 28px);
  margin-bottom: 28px;
  box-shadow: var(--shadow);
}
.hero h1 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: clamp(1.5rem, 3vw, 2rem);
  letter-spacing: -0.02em;
  margin-bottom: 8px;
}
.hero p { font-size: 0.95rem; opacity: 0.95; }
.property-card {
  background: rgba(255,255,255,0.93);
  border: 1px solid rgba(255,255,255,0.9);
  border-radius: 14px;
  padding: 20px;
  margin-bottom: 24px;
  box-shadow: 0 10px 24px rgba(15, 31, 40, 0.09);
}
.property-card h2 {
  color: var(--ink);
  margin-bottom: 12px;
  font-size: 1.3rem;
}
.property-details {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
  margin: 16px 0;
}
.detail-item {
  background: #f6f9fc;
  padding: 12px;
  border-radius: 8px;
  font-size: 0.9rem;
}
.detail-label {
  color: var(--muted);
  font-weight: 600;
  font-size: 0.75rem;
  text-transform: uppercase;
}
.detail-value {
  color: var(--ink);
  font-weight: 700;
  margin-top: 4px;
}
.form-section {
  background: rgba(255,255,255,0.93);
  border: 1px solid rgba(255,255,255,0.9);
  border-radius: 14px;
  padding: 24px;
  margin-bottom: 20px;
  box-shadow: 0 10px 24px rgba(15, 31, 40, 0.09);
}
.form-section h3 {
  color: var(--ink);
  font-size: 1.1rem;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 2px solid var(--line);
}
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 16px;
  margin-bottom: 16px;
}
.form-group {
  display: flex;
  flex-direction: column;
}
label {
  color: var(--ink);
  font-weight: 600;
  margin-bottom: 6px;
  font-size: 0.9rem;
}
input, select, textarea {
  padding: 10px 12px;
  border: 1px solid var(--line);
  border-radius: 8px;
  font-family: inherit;
  font-size: 0.95rem;
  transition: border-color 0.2s;
}
input:focus, select:focus, textarea:focus {
  outline: none;
  border-color: var(--brand);
  box-shadow: 0 0 0 3px rgba(31, 143, 103, 0.1);
}
textarea {
  resize: vertical;
  min-height: 80px;
}
.checkbox-group {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  margin: 12px 0;
}
.checkbox-item {
  display: flex;
  align-items: center;
  gap: 8px;
}
.checkbox-item input[type="checkbox"] {
  width: 20px;
  height: 20px;
  cursor: pointer;
}
.checkbox-item label {
  margin: 0;
  cursor: pointer;
  font-weight: 500;
}
.alert {
  padding: 12px 16px;
  border-radius: 8px;
  margin-bottom: 16px;
  font-weight: 500;
}
.alert.error {
  background: #fadbd8;
  color: #c0392b;
  border-left: 4px solid #c0392b;
}
.alert.success {
  background: #d5f4e6;
  color: #27ae60;
  border-left: 4px solid #27ae60;
}
.terms-box {
  background: #f9f6ef;
  padding: 16px;
  border-radius: 8px;
  max-height: 200px;
  overflow-y: auto;
  margin-bottom: 12px;
  border-left: 3px solid var(--accent);
}
.terms-box h4 { margin-bottom: 8px; color: var(--ink); }
.terms-box p { font-size: 0.85rem; line-height: 1.5; color: var(--muted); margin: 6px 0; }
.button-group {
  display: flex;
  gap: 12px;
  margin-top: 24px;
}
.btn {
  flex: 1;
  padding: 12px 20px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 0.95rem;
}
.btn-primary {
  background: linear-gradient(135deg, var(--brand), var(--brand-deep));
  color: #fff;
}
.btn-primary:hover {
  box-shadow: 0 10px 24px rgba(31, 143, 103, 0.3);
  transform: translateY(-2px);
}
.btn-secondary {
  background: rgba(31, 36, 48, 0.1);
  color: var(--ink);
}
.btn-secondary:hover {
  background: rgba(31, 36, 48, 0.15);
}
</style>
</head>
<body>
<div class="container">
  <div class="hero">
    <h1>📋 Rental Agreement</h1>
    <p>Complete this form to book the property and create a rental agreement</p>
  </div>

  <?php if ($error): ?>
    <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <div class="property-card">
    <h2><?php echo htmlspecialchars($property['title']); ?></h2>
    <div class="property-details">
      <div class="detail-item">
        <div class="detail-label">Location</div>
        <div class="detail-value"><?php echo htmlspecialchars($property['location']); ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">Bedrooms</div>
        <div class="detail-value"><?php echo (int)$property['bedrooms']; ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">Bathrooms</div>
        <div class="detail-value"><?php echo (int)$property['bathrooms']; ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">Listed Price</div>
        <div class="detail-value">$<?php echo number_format($property['price'], 2); ?>/mo</div>
      </div>
      <div class="detail-item">
        <div class="detail-label">Landlord</div>
        <div class="detail-value"><?php echo htmlspecialchars($property['landlord_name'] ?? $property['owner_name'] ?? 'Unknown'); ?></div>
      </div>
    </div>
  </div>

  <form method="POST">
    <div class="form-section">
      <h3>Lease Terms</h3>
      <div class="form-grid">
        <div class="form-group">
          <label for="move_in_date">Move-In Date *</label>
          <input type="date" id="move_in_date" name="move_in_date" required>
        </div>
        <div class="form-group">
          <label for="move_out_date">Move-Out Date (Optional)</label>
          <input type="date" id="move_out_date" name="move_out_date">
        </div>
        <div class="form-group">
          <label for="lease_duration_months">Lease Duration (Months) *</label>
          <input type="number" id="lease_duration_months" name="lease_duration_months" min="1" max="60" value="12" required>
        </div>
      </div>
    </div>

    <div class="form-section">
      <h3>Financial Details</h3>
      <div class="form-grid">
        <div class="form-group">
          <label for="monthly_rent">Monthly Rent ($) *</label>
          <input type="number" id="monthly_rent" name="monthly_rent" min="0" step="0.01" value="<?php echo $property['price']; ?>" required>
        </div>
        <div class="form-group">
          <label for="deposit_amount">Security Deposit ($) *</label>
          <input type="number" id="deposit_amount" name="deposit_amount" min="0" step="0.01" required>
        </div>
      </div>
    </div>

    <div class="form-section">
      <h3>Included Services & Amenities</h3>
      <div class="checkbox-group">
        <div class="checkbox-item">
          <input type="checkbox" id="utilities_included" name="utilities_included">
          <label for="utilities_included">Utilities Included</label>
        </div>
        <div class="checkbox-item">
          <input type="checkbox" id="pets_allowed" name="pets_allowed">
          <label for="pets_allowed">Pets Allowed</label>
        </div>
        <div class="checkbox-item">
          <input type="checkbox" id="parking" name="parking">
          <label for="parking">Parking Available</label>
        </div>
      </div>
    </div>

    <div class="form-section">
      <h3>Additional Terms & Conditions</h3>
      <div class="form-group">
        <label for="additional_terms">Special Requirements or Rules</label>
        <textarea id="additional_terms" name="additional_terms" placeholder="e.g., 'No smoking inside', 'Quiet hours 10pm-7am', etc."></textarea>
      </div>
    </div>

    <div class="form-section">
      <h3>Agreement Acknowledgment</h3>
      <div class="terms-box">
        <h4>Terms of Rental Agreement</h4>
        <p>✓ Monthly rent is due on or before the agreed date each month</p>
        <p>✓ Renter agrees to maintain the property in good condition</p>
        <p>✓ Landlord agrees to ensure property meets habitability standards</p>
        <p>✓ Either party may cancel with written notice as per local laws</p>
        <p>✓ Security deposit will be returned after move-out inspection</p>
        <p>✓ All payments and communications tracked through RentConnect</p>
      </div>
      <div class="checkbox-item">
        <input type="checkbox" id="agree_terms" name="agree_terms" required>
        <label for="agree_terms">I agree to the rental terms and conditions *</label>
      </div>
    </div>

    <div class="button-group">
      <button type="button" class="btn btn-secondary" onclick="history.back()">Cancel</button>
      <button type="submit" class="btn btn-primary">Submit Agreement</button>
    </div>
  </form>
</div>

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
