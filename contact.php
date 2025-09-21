<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact Us - RentConnect</title>
  <style>
    body { font-family: 'Segoe UI', Tahoma, sans-serif; margin:0; background:#f5f7fb; color:#333; }

    /* Header */
    header {
      background: #fff; padding: 15px 25px;
      display: flex; justify-content: space-between; align-items: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
      position: sticky; top: 0; z-index: 1000;
    }
    header h1 a { text-decoration: none; color: #2E7D32; font-size: 1.6em; font-weight: bold; }
    nav { display: flex; gap: 20px; align-items: center; }
    nav a, nav .dropbtn {
      text-decoration: none; color: #444; font-weight: 500;
      padding: 8px 14px; border-radius: 6px; transition: 0.3s; cursor: pointer;
    }
    nav a:hover, nav .dropbtn:hover { background: #f0f0f0; color: #2E7D32; }
    .upload-btn { background: #2E7D32; color: #fff; }
    .upload-btn:hover { background: #1b5e20; color: #fff; }

    /* Dropdown */
    .dropdown { position: relative; display: inline-block; }
    .dropdown-content {
      display: none; position: absolute; top: 100%; left: 0;
      background: #fff; min-width: 160px; border-radius: 6px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      overflow: hidden; z-index: 999;
    }
    .dropdown-content a {
      display: block; padding: 10px; color: #444; font-size: 0.95em;
    }
    .dropdown-content a:hover { background: #f5f5f5; color: #2E7D32; }
    @media (min-width: 769px) { .dropdown:hover .dropdown-content { display: block; } }
    .dropdown.active .dropdown-content { display: block; }

    /* Banner */
    .banner {
      background: linear-gradient(rgba(0,0,0,0.6),rgba(0,0,0,0.6)), url('images/home-banner-3.png') center/cover no-repeat;
      color: #fff; text-align: center; padding: 100px 20px;
    }
    .banner h2 { font-size: 2.6em; font-weight: 700; margin-bottom: 10px; }
    .banner p { font-size: 1.1em; opacity: 0.9; }

    /* Contact Section */
    .contact-container {
      max-width: 1100px; margin: 50px auto; display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 40px; padding: 0 20px;
    }
    .contact-info {
      background:#fff; padding:30px; border-radius:12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .contact-info h3 { color:#2E7D32; margin-bottom:15px; }
    .contact-info p { margin: 8px 0; }
    .contact-info a { color:#2E7D32; text-decoration:none; }
    .contact-info a:hover { text-decoration:underline; }
    .contact-info .social { margin-top:15px; }
    .contact-info .social a { margin-right:12px; font-size:20px; color:#2E7D32; }

    .contact-form {
      background:#fff; padding:30px; border-radius:12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .contact-form h3 { color:#2E7D32; margin-bottom:20px; }
    .contact-form input, .contact-form textarea {
      width:100%; padding:12px; margin:8px 0;
      border:1px solid #ddd; border-radius:6px; font-size:1em;
    }
    .contact-form textarea { resize:none; height:120px; }
    .contact-form button {
      background:#2E7D32; color:#fff; border:none;
      padding:12px 20px; border-radius:6px;
      font-size:1em; cursor:pointer; transition:0.3s;
    }
    .contact-form button:hover { background:#1b5e20; }

    /* Footer */
    footer {
      background:#222; color:#ccc; text-align:center;
      padding:20px; margin-top:40px;
    }
    footer a { color:#FF9800; text-decoration:none; }
    footer a:hover { text-decoration:underline; }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<header>
  <h1><a href="index.php">🏠 RentConnect</a></h1>
  <nav id="navMenu">
    <a href="about.php">About Us</a>
    <a href="services.php">Services</a>
    <a href="contact.php">Contact</a>

    <!-- Dropdown -->
    <div class="dropdown">
      <button class="dropbtn" onclick="toggleDropdown(event)">Account ▾</button>
      <div class="dropdown-content">
        <a href="login.php">Login</a>
        <a href="signup.php">Sign Up</a>
      </div>
    </div>

    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'landlord'): ?>
        <a href="upload_property.php" class="upload-btn">Upload Property</a>
    <?php else: ?>
        <a href="login.php" class="upload-btn">Upload Property</a>
    <?php endif; ?>
  </nav>
</header>

<!-- Banner -->
<section class="banner">
  <h2>Contact Us</h2>
  <p>We’d love to hear from you! Get in touch with RentConnect.</p>
</section>

<!-- Contact Section -->
<div class="contact-container">
  <!-- Info -->
  <div class="contact-info">
    <h3>📍 Our Office</h3>
    <p>Monrovia, Liberia</p>
    <p><strong>Email:</strong> <a href="mailto:support@rentconnect.com">support@rentconnect.com</a></p>
    <p><strong>Phone:</strong> +231-XXX-XXXX</p>

    <div class="social">
      <a href="#"><i class="fab fa-facebook"></i></a>
      <a href="#"><i class="fab fa-twitter"></i></a>
      <a href="#"><i class="fab fa-instagram"></i></a>
    </div>
  </div>

  <!-- Form -->
  <div class="contact-form">
    <h3>📩 Send a Message</h3>
    <form action="send_message.php" method="POST">
      <input type="text" name="name" placeholder="Your Name" required>
      <input type="email" name="email" placeholder="Your Email" required>
      <input type="text" name="subject" placeholder="Subject" required>
      <textarea name="message" placeholder="Your Message" required></textarea>
      <button type="submit">Send Message</button>
    </form>
  </div>
</div>

<footer>
  <p>&copy; <?php echo date("Y"); ?> RentConnect Liberia. All rights reserved. | <a href="services.php">Our Services</a></p>
</footer>

<script>
function toggleDropdown(event) {
  event.preventDefault();
  const dropdown = event.target.closest(".dropdown");
  dropdown.classList.toggle("active");
}
</script>

</body>
</html>
