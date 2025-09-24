<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact Us - RentConnect</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { font-family: 'Segoe UI', Tahoma, sans-serif; margin:0; background:#f5f7fb; color:#333; }

    /* Header */
    header {
      background: #fff; padding: 15px 25px;
      display: flex; justify-content: space-between; align-items: center;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      position: sticky; top: 0; z-index: 1000;
    }
    header h1 a { text-decoration: none; color: #2E7D32; font-size: 1.6em; font-weight: bold; }

    nav {
      display: flex; gap: 20px; align-items: center;
    }
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

    /* Hamburger (mobile menu button) */
    .hamburger {
      display: none; flex-direction: column; cursor: pointer; gap: 4px;
    }
    .hamburger div {
      width: 25px; height: 3px; background: #333; border-radius: 2px;
    }

    /* Mobile nav */
    @media (max-width: 768px) {
      nav {
        display: none;
        flex-direction: column;
        background: #fff;
        position: absolute; top: 65px; right: 0; width: 220px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        padding: 10px;
      }
      nav.show { display: flex; }
      .hamburger { display: flex; }
      nav a, nav .dropbtn { width: 100%; text-align: left; padding: 12px; }
      .dropdown-content { position: relative; box-shadow: none; }
    }

    /* Banner */
    .banner {
      background: linear-gradient(rgba(0,0,0,0.5),rgba(0,0,0,0.5)), url('images/home-banner-2.png') center/cover no-repeat;
      color: #fff; text-align: center; padding: 80px 20px;
    }
    .banner h2 { font-size: 2.6em; font-weight: 700; margin-bottom: 10px; }
    .banner p { font-size: 1.1em; opacity: 0.9; }

    /* Contact Section */
    .contact-section {
      max-width: 900px; margin: 50px auto; padding: 0 20px;
      display: flex; flex-direction: column; align-items: center; gap: 40px;
    }

    .contact-cards {
      display: flex; flex-wrap: wrap; justify-content: center; gap: 30px;
      width: 100%;
    }

    .contact-info, .contact-form {
      background:#fff; padding:30px; border-radius:12px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.08); flex: 1 1 350px;
    }

    .contact-info h3, .contact-form h3 { color:#2E7D32; margin-bottom:15px; }
    .contact-info p { margin: 8px 0; font-size:0.95em; }
    .contact-info a { color:#2E7D32; text-decoration:none; }
    .contact-info a:hover { text-decoration:underline; }
    .contact-info .social { margin-top:15px; }
    .contact-info .social a { margin-right:12px; font-size:22px; color:#2E7D32; transition:0.3s; }
    .contact-info .social a:hover { color:#1b5e20; }

    .contact-form input, .contact-form textarea {
      width:100%; padding:14px; margin:10px 0;
      border:1px solid #ddd; border-radius:8px; font-size:1em;
      transition:0.3s;
    }
    .contact-form input:focus, .contact-form textarea:focus { border-color: #2E7D32; outline:none; }

    .contact-form textarea { resize:none; height:140px; }
    .contact-form button {
      background:#2E7D32; color:#fff; border:none;
      padding:14px 25px; border-radius:8px;
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

    /* Google Maps */
    .map-container {
      width: 100%; height: 300px; border-radius:12px; overflow:hidden;
      box-shadow: 0 6px 20px rgba(0,0,0,0.08);
    }

    @media(max-width:768px){
      .contact-cards { flex-direction: column; }
    }

  </style>
</head>
<body>

<header>
  <h1><a href="index.php">RentConnect</a></h1>

  <!-- Hamburger -->
  <div class="hamburger" onclick="toggleMenu()">
    <div></div><div></div><div></div>
  </div>

  <nav id="navMenu">
    <a href="about.php" onclick="closeMenu()">About Us</a>
    <a href="services.php" onclick="closeMenu()">Services</a>
    <a href="contact.php" onclick="closeMenu()">Contact</a>

    <!-- Dropdown -->
    <div class="dropdown">
      <button class="dropbtn" onclick="toggleDropdown(event)">Account ▾</button>
      <div class="dropdown-content">
        <a href="login.php" onclick="closeMenu()">Login</a>
        <a href="signup.php" onclick="closeMenu()">Sign Up</a>
      </div>
    </div>

    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'landlord'): ?>
        <a href="upload_property.php" class="upload-btn" onclick="closeMenu()">Upload Property</a>
    <?php else: ?>
        <a href="login.php" class="upload-btn" onclick="closeMenu()">Upload Property</a>
    <?php endif; ?>
  </nav>
</header>

<!-- Banner -->
<section class="banner">
  <h2>Contact Us</h2>
  <p>We’d love to hear from you! Get in touch with RentConnect.</p>
</section>

<!-- Contact Section -->
<section class="contact-section">

  <div class="contact-cards">
    <!-- Info -->
    <div class="contact-info">
      <h3>📍 Our Office</h3>
      <p>Monrovia, Liberia</p>
      <p><strong>Email:</strong> <a href="mailto:support@rentconnect.com">support@rentconnect.com</a></p>
      <p><strong>Phone:</strong> +231-888-272-360</p>

      <div class="social">
        <a href="#"><i class="fab fa-facebook"></i></a>
        <a href="#"><i class="fab fa-twitter"></i></a>
        <a href="#"><i class="fab fa-instagram"></i></a>
      </div>

      <div class="map-container" style="margin-top:20px;">
        <iframe 
          src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3972.1587728906827!2d-10.799231785234894!3d6.290743726450797!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xf0e9e5c7ab94a2f%3A0x3a8f39fcbdf4c4df!2sMonrovia%2C%20Liberia!5e0!3m2!1sen!2s!4v1691500000000!5m2!1sen!2s" 
          width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" 
          referrerpolicy="no-referrer-when-downgrade">
        </iframe>
      </div>
    </div>

    <!-- Form -->
    <div class="contact-form">
      <h3>Send a Message</h3>
      <form action="send_message.php" method="POST">
        <input type="text" name="name" placeholder="Your Name" required>
        <input type="email" name="email" placeholder="Your Email" required>
        <input type="text" name="subject" placeholder="Subject" required>
        <textarea name="message" placeholder="Your Message" required></textarea>
        <button type="submit">Send Message</button>
      </form>
    </div>
  </div>

</section>

<footer>
  <p>&copy; <?php echo date("Y"); ?> RentConnect Liberia. All rights reserved. | <a href="services.php">Our Services</a></p>
</footer>

<script>
function toggleDropdown(event) {
  event.preventDefault();
  const dropdown = event.target.closest(".dropdown");
  dropdown.classList.toggle("active");
}
function toggleMenu() {
  document.getElementById("navMenu").classList.toggle("show");
}
function closeMenu() {
  document.getElementById("navMenu").classList.remove("show");
  const dropdowns = document.querySelectorAll(".dropdown");
  dropdowns.forEach(d => d.classList.remove("active"));
}
</script>

</body>
</html>
