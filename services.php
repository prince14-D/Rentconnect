<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Our Services - RentConnect</title>
  <style>
    body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f5f7fb; margin: 0; color: #333; }

    /* Header */
    header {
      background: #fff; padding: 0px 0px;
      display: flex; justify-content: space-between; align-items: center;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      position: sticky; top: 0; z-index: 1000;
    }
    header h1 a { text-decoration: none; color: #2E7D32; font-size: 0.9em; font-weight: bold; }
    nav { display: flex; gap: 20px; align-items: center; }
    nav a, nav .dropbtn {
      text-decoration: none; color: #444; font-weight: 500;
      padding: 8px 14px; border-radius: 6px; transition: 0.3s; cursor: pointer;
    }
    nav a:hover, nav .dropbtn:hover { background: #f0f0f0; color: #2E7D32; }
    .upload-btn { background: #2E7D32; color: #fff; }
    .upload-btn:hover { background: #1b5e20; color: #fff; }

    /* Mobile Menu */
    .menu-toggle {
      display: none;
      font-size: 1.8em;
      background: none;
      border: none;
      cursor: pointer;
      color: #2E7D32;
    }
    @media (max-width: 768px) {
      nav { 
        display: none; 
        flex-direction: column; 
        gap: 10px; 
        background: #fff; 
        position: absolute; 
        top: 70px; left: 0; width: 100%; 
        padding: 15px; 
        box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
      }
      nav.show { display: flex; }
      .menu-toggle { display: block; }
    }

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
      background: linear-gradient(rgba(0,0,0,0.6),rgba(0,0,0,0.6)), url('images/home-banner-2.png') center/cover no-repeat;
      color: #fff; text-align: center; padding: 100px 20px;
    }
    .banner h2 { font-size: 2.6em; font-weight: 700; margin-bottom: 10px; }
    .banner p { font-size: 1.1em; opacity: 0.9; }
    @media (max-width: 768px) {
      .banner h2 { font-size: 1.8em; }
      .banner p { font-size: 1em; }
    }

    /* Services Section */
    .container { max-width: 1100px; margin: 40px auto; padding: 0 20px; }
    .services {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 25px;
    }
    .service-card {
      background: #fff; padding: 25px; border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      text-align: center; transition: transform 0.3s ease;
    }
    .service-card:hover { transform: translateY(-6px); }
    .service-card i { font-size: 40px; color: #2E7D32; margin-bottom: 15px; }
    .service-card h3 { color: #2E7D32; font-size: 1.3em; margin-bottom: 10px; }
    .service-card p { font-size: 0.95em; color: #555; }

    /* Why Choose Us */
    .why-choose {
      background: linear-gradient(rgba(46,125,50,0.9), rgba(27,94,32,0.9)), url('images/home-banner.png') center/cover no-repeat;
      color: #fff; text-align: center; padding: 80px 20px; margin-top: 50px;
    }
    .why-choose h2 { font-size: 2.4em; margin-bottom: 25px; }
    .why-choose-items {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 25px; max-width: 1000px; margin: 0 auto;
    }
    .why-item {
      background: rgba(255,255,255,0.1);
      padding: 25px; border-radius: 12px;
      transition: transform 0.3s ease, background 0.3s ease;
    }
    .why-item:hover { transform: translateY(-6px); background: rgba(255,255,255,0.2); }
    .why-item i { font-size: 40px; margin-bottom: 15px; display: block; }
    .why-item h3 { font-size: 1.3em; margin-bottom: 10px; color: #fff; }
    .why-item p { font-size: 0.95em; color: #eee; }

    /* Footer */
    footer {
      background: #222; color: #ccc; text-align: center;
      padding: 20px; margin-top: 40px;
    }
    footer a { color: #FF9800; text-decoration: none; }
    footer a:hover { text-decoration: underline; }
  </style>
  <!-- Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<header>
  <h1><a href="index.php">RentConnect</a></h1>
  <button class="menu-toggle" onclick="toggleMenu()">☰</button>
  <nav id="navMenu">
    <a href="about.php">About Us</a>
    <a href="services.php">Services</a>
    <a href="contact.php">Contact</a>

    <!-- Dropdown -->
    <!-- Dropdown -->
<div class="dropdown">
  <button class="dropbtn" onclick="toggleDropdown(event)">Account ▾</button>
  <div class="dropdown-content">
    <a href="login.php" onclick="closeMenu()">Login</a>
    <a href="signup.php" onclick="closeMenu()">Sign Up</a>
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
  <h2>Our Services</h2>
  <p>Making renting in Liberia simple, secure, and reliable</p>
</section>

<!-- Services Section -->
<div class="container">
  <div class="services">
    <div class="service-card">
      <i class="fas fa-home"></i>
      <h3>Property Listings</h3>
      <p>Browse verified rental homes across Liberia with detailed photos and descriptions.</p>
    </div>
    <div class="service-card">
      <i class="fas fa-shield-alt"></i>
      <h3>Verified Landlords</h3>
      <p>All landlords are screened and verified to ensure safety and trust in every rental.</p>
    </div>
    <div class="service-card">
      <i class="fas fa-search"></i>
      <h3>Smart Search</h3>
      <p>Find homes easily with powerful filters for pricing, locations, and properties types.</p>
    </div>
    <div class="service-card">
      <i class="fas fa-handshake"></i>
      <h3>Secure Connections</h3>
      <p>Direct communication between renters and landlords, manage securely on our platform.</p>
    </div>
    <div class="service-card">
      <i class="fas fa-mobile-alt"></i>
      <h3>Mobile Friendly</h3>
      <p>Access RentConnect anytime, anywhere with our smooth mobile experiences.</p>
    </div>
    <div class="service-card">
      <i class="fas fa-headset"></i>
      <h3>Support</h3>
      <p>Our support team is always ready to assist you with properties inquiries or technical supports.</p>
    </div>
  </div>
</div>

<!-- Why Choose Us -->
<section class="why-choose">
  <h2>Why Choose RentConnect?</h2>
  <div class="why-choose-items">
    <div class="why-item">
      <i class="fas fa-check-circle"></i>
      <h3>Trusted Platform</h3>
      <p>All listings and landlords are verified, ensuring safe and transparent experiences.</p>
    </div>
    <div class="why-item">
      <i class="fas fa-bolt"></i>
      <h3>Fast & Easy</h3>
      <p>Our smart tools help you find the right property in just a few clicks.</p>
    </div>
    <div class="why-item">
      <i class="fas fa-user-shield"></i>
      <h3>Secure System</h3>
      <p>Protecting your data and connections with the latest security measures.</p>
    </div>
  </div>
</section>

<footer>
  <p>&copy; <?php echo date("Y"); ?> RentConnect Liberia. All rights reserved. | <a href="contact.php">Contact Us</a></p>
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


</script>

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
  // Collapse both the mobile menu and dropdown after clicking
  document.getElementById("navMenu").classList.remove("show");
  const dropdowns = document.querySelectorAll(".dropdown");
  dropdowns.forEach(d => d.classList.remove("active"));
}
</script>


</body>
</html>
