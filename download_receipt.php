<?php
session_start();
include "app_init.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die("Unauthorized");
}

$payment_id = intval($_GET['payment_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($payment_id === 0) {
    die("No payment specified");
}

// Fetch payment details
$stmt = $conn->prepare("
    SELECT p.*, b.property_id, pr.title, pr.address, u.name as landlord_name, u.email as landlord_email,
           r.name as renter_name, r.email as renter_email, r.phone as renter_phone
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    JOIN properties pr ON b.property_id = pr.id
    JOIN users u ON p.landlord_id = u.id
    JOIN users r ON p.renter_id = r.id
    WHERE p.id = ? AND (p.renter_id = ? OR p.landlord_id = ?)
");
$stmt->bind_param("iii", $payment_id, $user_id, $user_id);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    die("Payment not found or unauthorized");
}

// Check if payment is confirmed
if ($payment['status'] !== 'confirmed') {
    die("Receipt only available for confirmed payments");
}

// Generate PDF using a simple HTML approach
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="receipt_' . $payment_id . '.pdf"');

// Simple PDF generation using HTML2PDF
$html = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; color: #333; }
  .container { max-width: 600px; margin: 0 auto; padding: 40px; }
  .header { text-align: center; margin-bottom: 40px; border-bottom: 3px solid #1f8f67; padding-bottom: 20px; }
  .logo { font-size: 32px; font-weight: bold; color: #1f8f67; margin-bottom: 10px; }
  .title { font-size: 24px; font-weight: bold; color: #333; margin-bottom: 5px; }
  .subtitle { color: #666; font-size: 14px; }
  .receipt-number { margin-top: 20px; color: #666; font-size: 12px; }
  
  .section { margin: 30px 0; }
  .section-title { font-size: 14px; font-weight: bold; color: #1f8f67; margin-bottom: 15px; text-transform: uppercase; }
  
  .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
  .info-label { font-weight: bold; color: #333; }
  .info-value { color: #666; text-align: right; }
  
  .amount-box { 
    background: #f0f8f5; 
    border-left: 4px solid #1f8f67;
    padding: 20px;
    margin: 20px 0;
    border-radius: 4px;
  }
  
  .amount-row {
    display: flex;
    justify-content: space-between;
    margin: 10px 0;
    font-size: 16px;
  }
  
  .amount-total {
    display: flex;
    justify-content: space-between;
    margin: 10px 0;
    font-size: 20px;
    font-weight: bold;
    color: #1f8f67;
    border-top: 2px solid #ddd;
    padding-top: 10px;
  }
  
  .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; color: #999; font-size: 12px; }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <div class="logo">🏠 RentConnect</div>
    <div class="title">Payment Receipt</div>
    <div class="subtitle">Rent Payment Confirmation</div>
    <div class="receipt-number">Receipt #' . str_pad($payment_id, 6, "0", STR_PAD_LEFT) . '</div>
  </div>

  <div class="section">
    <div class="section-title">Property Details</div>
    <div class="info-row">
      <span class="info-label">Property:</span>
      <span class="info-value">' . htmlspecialchars($payment['title']) . '</span>
    </div>
    <div class="info-row">
      <span class="info-label">Address:</span>
      <span class="info-value">' . htmlspecialchars($payment['address']) . '</span>
    </div>
  </div>

  <div class="section">
    <div class="section-title">Tenant Information</div>
    <div class="info-row">
      <span class="info-label">Name:</span>
      <span class="info-value">' . htmlspecialchars($payment['renter_name']) . '</span>
    </div>
    <div class="info-row">
      <span class="info-label">Email:</span>
      <span class="info-value">' . htmlspecialchars($payment['renter_email']) . '</span>
    </div>
    <div class="info-row">
      <span class="info-label">Phone:</span>
      <span class="info-value">' . htmlspecialchars($payment['renter_phone']) . '</span>
    </div>
  </div>

  <div class="section">
    <div class="section-title">Landlord Information</div>
    <div class="info-row">
      <span class="info-label">Name:</span>
      <span class="info-value">' . htmlspecialchars($payment['landlord_name']) . '</span>
    </div>
    <div class="info-row">
      <span class="info-label">Email:</span>
      <span class="info-value">' . htmlspecialchars($payment['landlord_email']) . '</span>
    </div>
  </div>

  <div class="section">
    <div class="section-title">Payment Information</div>
    <div class="info-row">
      <span class="info-label">Payment For:</span>
      <span class="info-value">' . date('F Y', strtotime($payment['payment_month'])) . '</span>
    </div>
    <div class="info-row">
      <span class="info-label">Payment Method:</span>
      <span class="info-value">' . ucfirst(str_replace('_', ' ', $payment['payment_method'])) . '</span>
    </div>
    <div class="info-row">
      <span class="info-label">Payment Date:</span>
      <span class="info-value">' . date('F d, Y', strtotime($payment['paid_at'] ?? $payment['created_at'])) . '</span>
    </div>
  </div>

  <div class="amount-box">
    <div class="amount-row">
      <span>Rent Amount:</span>
      <span>$' . number_format($payment['amount'], 2) . '</span>
    </div>
    <div class="amount-row">
      <span>Payment Fee:</span>
      <span>$0.00</span>
    </div>
    <div class="amount-total">
      <span>Total Paid:</span>
      <span>$' . number_format($payment['amount'], 2) . '</span>
    </div>
  </div>

  <div class="section">
    <div class="info-row">
      <span class="info-label">Transaction ID:</span>
      <span class="info-value">' . htmlspecialchars($payment['stripe_intent_id'] ?? $payment['reference_number'] ?? 'N/A') . '</span>
    </div>
    <div class="info-row">
      <span class="info-label">Status:</span>
      <span class="info-value" style="color: #27ae60; font-weight: bold;">CONFIRMED</span>
    </div>
  </div>

  <div class="footer">
    <p>This is a computer-generated receipt. No signature required.</p>
    <p style="margin-top: 10px;">Payment received and confirmed on ' . date('F d, Y \a\t H:i A') . '</p>
    <p style="margin-top: 15px;">Thank you for your timely payment!</p>
    <p style="margin-top: 20px;">RentConnect &copy; 2024 | www.rentconnect.com</p>
  </div>
</div>
</body>
</html>
';

// For a simple approach, we output as HTML that can be printed
// For a true PDF, you would need to use a library like mPDF or TCPDF
// For now, we\'ll output as a downloadable HTML file
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: inline; filename="receipt_' . $payment_id . '.html"');
echo $html;
?>
