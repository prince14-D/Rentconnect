<?php
/**
 * Email Notification System for RentConnect
 * Handles all transactional emails: bookings, payments, reminders, confirmations
 */

// Email configuration
define('SENDER_EMAIL', 'noreply@rentconnect.local');
define('SENDER_NAME', 'RentConnect');
define('SITE_URL', 'http://localhost/rentconnect'); // Change to your domain in production

/**
 * Send email with HTML template
 */
function send_email($to, $subject, $html_body, $plain_text = null) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SENDER_NAME . " <" . SENDER_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . SENDER_EMAIL . "\r\n";
    
    // For development/testing: log emails to file instead of sending
    if (defined('EMAIL_LOG_PATH')) {
        $log_entry = "\n--- Email sent at " . date('Y-m-d H:i:s') . " ---\n";
        $log_entry .= "To: $to\nSubject: $subject\n\n$html_body\n";
        file_put_contents(EMAIL_LOG_PATH, $log_entry, FILE_APPEND);
        return true;
    }
    
    // In production, use mail() function
    return mail($to, $subject, $html_body, $headers);
}

/**
 * Email template wrapper with RentConnect styling
 */
function email_template($content, $title = '') {
    $header = !empty($title) ? "<h2 style=\"color: #1f8f67; font-family: 'Plus Jakarta Sans', sans-serif; margin-bottom: 20px;\">$title</h2>" : '';
    
    return "
<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <style>
        body { font-family: 'Manrope', sans-serif; color: #1f2430; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: linear-gradient(140deg, rgba(31, 143, 103, 0.9), rgba(21, 84, 62, 0.95)); color: #fff; padding: 28px; text-align: center; border-radius: 12px 12px 0 0; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { background: #f9f9f9; padding: 28px; border-radius: 0 0 12px 12px; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #888; border-top: 1px solid #e0e0e0; margin-top: 20px; }
        .button { display: inline-block; padding: 12px 24px; margin: 16px 0; background: #1f8f67; color: #fff; text-decoration: none; border-radius: 8px; }
        .info-box { background: #f0f8f5; border-left: 4px solid #1f8f67; padding: 16px; margin: 16px 0; border-radius: 4px; }
        .price { font-size: 24px; color: #1f8f67; font-weight: 700; }
    </style>
</head>
<body>
    <div class=\"container\">
        <div class=\"header\">
            <h1>🏠 RentConnect</h1>
            <p>Your Trusted Rental Platform</p>
        </div>
        <div class=\"content\">
            $header
            $content
        </div>
        <div class=\"footer\">
            <p>&copy; " . date('Y') . " RentConnect. All rights reserved.</p>
            <p><a href=\"" . SITE_URL . "\" style=\"color: #1f8f67; text-decoration: none;\">Visit RentConnect</a></p>
        </div>
    </div>
</body>
</html>";
}

/**
 * EMAIL 1: Booking Confirmation (to Renter)
 */
function send_booking_confirmation_renter($renter_email, $renter_name, $property_title, $location, $move_in, $move_out, $monthly_rent, $landlord_name, $booking_id) {
    $move_in_date = date('M d, Y', strtotime($move_in));
    $move_out_date = date('M d, Y', strtotime($move_out));
    
    $content = "
        <p>Hi <strong>$renter_name</strong>,</p>
        
        <p>Great news! Your rental booking has been confirmed. Here are the details:</p>
        
        <div class=\"info-box\">
            <h3 style=\"margin-top: 0; color: #1f8f67;\">📍 Property Details</h3>
            <p><strong>Property:</strong> $property_title</p>
            <p><strong>Location:</strong> $location</p>
            <p><strong>Move-in Date:</strong> $move_in_date</p>
            <p><strong>Move-out Date:</strong> $move_out_date</p>
            <p><strong>Monthly Rent:</strong> <span class=\"price\">\$" . number_format($monthly_rent, 2) . "</span></p>
            <p><strong>Landlord:</strong> $landlord_name</p>
            <p><strong>Booking ID:</strong> #$booking_id</p>
        </div>
        
        <p><strong>Next Steps:</strong></p>
        <ol>
            <li>Review the rental agreement carefully</li>
            <li>Ensure all terms are acceptable</li>
            <li>Prepare for the move-in date</li>
            <li>Contact the landlord if you have any questions</li>
        </ol>
        
        <p>You can view your booking and manage payments from your RentConnect dashboard.</p>
        
        <center>
            <a href=\"" . SITE_URL . "/manage_bookings.php\" class=\"button\">View Booking</a>
        </center>
        
        <p>If you have any questions, please don't hesitate to reach out to us.</p>
        
        <p>Best regards,<br><strong>The RentConnect Team</strong></p>
    ";
    
    return send_email($renter_email, "Booking Confirmed - $property_title", email_template($content, "✓ Booking Confirmed"));
}

/**
 * EMAIL 2: Booking Confirmation (to Landlord)
 */
function send_booking_confirmation_landlord($landlord_email, $landlord_name, $property_title, $renter_name, $move_in, $monthly_rent, $booking_id) {
    $move_in_date = date('M d, Y', strtotime($move_in));
    
    $content = "
        <p>Hi <strong>$landlord_name</strong>,</p>
        
        <p>Excellent news! Your property has been booked. Here are the details:</p>
        
        <div class=\"info-box\">
            <h3 style=\"margin-top: 0; color: #1f8f67;\">📍 Booking Information</h3>
            <p><strong>Property:</strong> $property_title</p>
            <p><strong>Tenant:</strong> $renter_name</p>
            <p><strong>Move-in Date:</strong> $move_in_date</p>
            <p><strong>Monthly Rent:</strong> <span class=\"price\">\$" . number_format($monthly_rent, 2) . "</span></p>
            <p><strong>Booking ID:</strong> #$booking_id</p>
        </div>
        
        <p><strong>What's Next:</strong></p>
        <ol>
            <li>The rental agreement has been signed by the tenant</li>
            <li>You can review the signed agreement in your dashboard</li>
            <li>Set up rent reminders for regular payment tracking</li>
            <li>Communicate with the tenant through the messaging system</li>
        </ol>
        
        <p>Manage this booking from your landlord dashboard.</p>
        
        <center>
            <a href=\"" . SITE_URL . "/manage_bookings.php\" class=\"button\">View Booking</a>
        </center>
        
        <p>Thank you for choosing RentConnect!</p>
        
        <p>Best regards,<br><strong>The RentConnect Team</strong></p>
    ";
    
    return send_email($landlord_email, "New Booking - $property_title", email_template($content, "🎉 Property Booked"));
}

/**
 * EMAIL 3: Payment Received (Awaiting Approval) - to Landlord
 */
function send_payment_received_notification($landlord_email, $landlord_name, $renter_name, $property_title, $amount, $payment_month, $payment_method, $payment_id) {
    $payment_date = date('M d, Y');
    $method_display = ucfirst(str_replace('_', ' ', $payment_method));
    
    $content = "
        <p>Hi <strong>$landlord_name</strong>,</p>
        
        <p>A payment has been received for your property and is awaiting your approval.</p>
        
        <div class=\"info-box\">
            <h3 style=\"margin-top: 0; color: #1f8f67;\">💳 Payment Details</h3>
            <p><strong>Property:</strong> $property_title</p>
            <p><strong>From Tenant:</strong> $renter_name</p>
            <p><strong>Amount:</strong> <span class=\"price\">\$" . number_format($amount, 2) . "</span></p>
            <p><strong>Payment For:</strong> " . date('F Y', strtotime($payment_month)) . "</p>
            <p><strong>Payment Method:</strong> $method_display</p>
            <p><strong>Received Date:</strong> $payment_date</p>
            <p><strong>Status:</strong> <span style=\"color: #f39c12; font-weight: 700;\">Pending Approval</span></p>
        </div>
        
        <p>Please review the payment details and approve or reject it from your payment approval dashboard.</p>
        
        <center>
            <a href=\"" . SITE_URL . "/payment_approval.php\" class=\"button\">Review Payment</a>
        </center>
        
        <p>Best regards,<br><strong>The RentConnect Team</strong></p>
    ";
    
    return send_email($landlord_email, "Payment Received - Awaiting Approval", email_template($content, "💰 Payment Received"));
}

/**
 * EMAIL 4: Payment Approved (to Renter)
 */
function send_payment_approved_notification($renter_email, $renter_name, $property_title, $amount, $payment_month) {
    $payment_date = date('M d, Y');
    
    $content = "
        <p>Hi <strong>$renter_name</strong>,</p>
        
        <p>Your rent payment has been approved by your landlord! ✓</p>
        
        <div class=\"info-box\">
            <h3 style=\"margin-top: 0; color: #1f8f67;\">✅ Payment Approved</h3>
            <p><strong>Amount Approved:</strong> <span class=\"price\">\$" . number_format($amount, 2) . "</span></p>
            <p><strong>Property:</strong> $property_title</p>
            <p><strong>Payment For:</strong> " . date('F Y', strtotime($payment_month)) . "</p>
            <p><strong>Approval Date:</strong> $payment_date</p>
        </div>
        
        <p>Thank you for your timely payment. Your landlord has confirmed receipt and approval of your payment.</p>
        
        <center>
            <a href=\"" . SITE_URL . "/rent_payments.php\" class=\"button\">View Payment History</a>
        </center>
        
        <p>Best regards,<br><strong>The RentConnect Team</strong></p>
    ";
    
    return send_email($renter_email, "Payment Approved", email_template($content, "✓ Payment Approved"));
}

/**
 * EMAIL 5: Rent Reminder (to Renter)
 */
function send_rent_reminder($renter_email, $renter_name, $property_title, $monthly_rent, $due_date, $landlord_name, $message = null) {
    $due_date_formatted = date('M d, Y', strtotime($due_date));
    
    $custom_message = '';
    if (!empty($message)) {
        $custom_message = "
        <div class=\"info-box\" style=\"background: #fff3cd; border-left-color: #f39c12;\">
            <p><strong>Message from your landlord:</strong></p>
            <p style=\"font-style: italic; margin: 10px 0; padding: 10px; background: rgba(0,0,0,0.05); border-radius: 4px;\">\"" . nl2br(htmlspecialchars($message)) . "\"</p>
        </div>";
    }
    
    $content = "
        <p>Hi <strong>$renter_name</strong>,</p>
        
        <p>This is a reminder from your landlord, <strong>$landlord_name</strong>, about your upcoming rent payment.</p>
        
        <div class=\"info-box\">
            <h3 style=\"margin-top: 0; color: #1f8f67;\">🔔 Rent Reminder</h3>
            <p><strong>Property:</strong> $property_title</p>
            <p><strong>Monthly Rent:</strong> <span class=\"price\">\$" . number_format($monthly_rent, 2) . "</span></p>
            <p><strong>Due Date:</strong> $due_date_formatted</p>
        </div>
        
        $custom_message
        
        <p><strong>Payment Options:</strong></p>
        <ul>
            <li>💳 Credit/Debit Card</li>
            <li>📱 Lonestar Momo</li>
            <li>🏦 Bank Transfer</li>
        </ul>
        
        <center>
            <a href=\"" . SITE_URL . "/rent_payments.php\" class=\"button\">Pay Now</a>
        </center>
        
        <p>Questions? Contact your landlord through the RentConnect messaging system.</p>
        
        <p>Best regards,<br><strong>The RentConnect Team</strong></p>
    ";
    
    return send_email($renter_email, "Rent Payment Reminder - $property_title", email_template($content, "🔔 Rent Reminder"));
}

/**
 * EMAIL 6: Booking Cancellation (to Both Parties)
 */
function send_booking_cancellation_landlord($landlord_email, $landlord_name, $property_title, $renter_name, $cancelled_by, $reason = null) {
    $cancelled_date = date('M d, Y');
    
    $reason_text = '';
    if (!empty($reason)) {
        $reason_text = "<p><strong>Cancellation Reason:</strong> $reason</p>";
    }
    
    $content = "
        <p>Hi <strong>$landlord_name</strong>,</p>
        
        <p>A booking for your property has been cancelled.</p>
        
        <div class=\"info-box\" style=\"background: #fadbd8; border-left-color: #c0392b;\">
            <h3 style=\"margin-top: 0; color: #c0392b;\">⚠ Booking Cancelled</h3>
            <p><strong>Property:</strong> $property_title</p>
            <p><strong>Former Tenant:</strong> $renter_name</p>
            <p><strong>Cancelled By:</strong> " . ucfirst($cancelled_by) . "</p>
            <p><strong>Cancellation Date:</strong> $cancelled_date</p>
            $reason_text
        </div>
        
        <p>Your property is now available for new bookings. You can list it again from your dashboard.</p>
        
        <center>
            <a href=\"" . SITE_URL . "/my_properties.php\" class=\"button\">View Properties</a>
        </center>
        
        <p>Best regards,<br><strong>The RentConnect Team</strong></p>
    ";
    
    return send_email($landlord_email, "Booking Cancelled - $property_title", email_template($content, "❌ Booking Cancelled"));
}

function send_booking_cancellation_renter($renter_email, $renter_name, $property_title, $landlord_name, $reason = null) {
    $cancelled_date = date('M d, Y');
    
    $reason_text = '';
    if (!empty($reason)) {
        $reason_text = "<p><strong>Cancellation Reason:</strong> $reason</p>";
    }
    
    $content = "
        <p>Hi <strong>$renter_name</strong>,</p>
        
        <p>Your rental booking has been cancelled.</p>
        
        <div class=\"info-box\" style=\"background: #fadbd8; border-left-color: #c0392b;\">
            <h3 style=\"margin-top: 0; color: #c0392b;\">⚠ Booking Cancelled</h3>
            <p><strong>Property:</strong> $property_title</p>
            <p><strong>Landlord:</strong> $landlord_name</p>
            <p><strong>Cancellation Date:</strong> $cancelled_date</p>
            $reason_text
        </div>
        
        <p>You are no longer obligated to pay rent for this property. If there are any outstanding payments, please check your payment history.</p>
        
        <p>You can now search for other properties on RentConnect.</p>
        
        <center>
            <a href=\"" . SITE_URL . "/index.php\" class=\"button\">Browse Properties</a>
        </center>
        
        <p>Best regards,<br><strong>The RentConnect Team</strong></p>
    ";
    
    return send_email($renter_email, "Booking Cancelled", email_template($content, "❌ Booking Cancelled"));
}

/**
 * Helper function to send email and log errors
 */
function send_notification_safe($to, $subject, $html_body) {
    try {
        if (send_email($to, $subject, $html_body)) {
            return ['success' => true];
        } else {
            error_log("Email notification failed - To: $to, Subject: $subject");
            return ['success' => false, 'error' => 'Email notification failed'];
        }
    } catch (Exception $e) {
        error_log("Email exception - " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>
