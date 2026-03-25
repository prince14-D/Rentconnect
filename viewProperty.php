<?php
session_start();
include "db.php";

// Validate property ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("HTTP/1.1 400 Bad Request");
    header("Location: property.php");
    exit;
}

$property_id = intval($_GET['id']);

// Fetch property details via shared migration layer.
$row = rc_mig_get_property_by_id($conn, $property_id, true);

if ($row) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<title><?= htmlspecialchars($row['title']) ?> - RentConnect</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #2E7D32;
    --primary-light: #4CAF50;
    --secondary: #FF9800;
    --danger: #d32f2f;
    --light-bg: #f5f7fa;
    --border-color: #e0e0e0;
    --text-dark: #2c3e50;
    --text-light: #666;
    --shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    --shadow-lg: 0 4px 16px rgba(0, 0, 0, 0.12);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    background: var(--light-bg);
    color: var(--text-dark);
    line-height: 1.6;
}

header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
    padding: 1.5rem;
    box-shadow: var(--shadow);
    position: sticky;
    top: 0;
    z-index: 100;
}

header .header-content {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

header nav {
    display: flex;
    gap: 1.5rem;
    align-items: center;
}

header a {
    color: white;
    text-decoration: none;
    font-weight: 500;
    transition: opacity 0.2s;
}

header a:hover {
    opacity: 0.8;
}

.profile-menu {
    position: relative;
}

.profile-toggle {
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.dropdown-content {
    display: none;
    position: absolute;
    background: white;
    min-width: 150px;
    box-shadow: var(--shadow-lg);
    border-radius: 8px;
    top: 100%;
    right: 0;
    margin-top: 0.5rem;
    z-index: 1000;
}

.dropdown-content a {
    display: block;
    padding: 0.75rem 1rem;
    color: var(--text-dark);
    text-decoration: none;
    transition: background 0.2s;
    border-bottom: 1px solid var(--border-color);
}

.dropdown-content a:last-child {
    border-bottom: none;
}

.dropdown-content a:hover {
    background: var(--light-bg);
}

.profile-menu.active .dropdown-content {
    display: block;
}

.container {
    max-width: 1100px;
    margin: 2rem auto;
    padding: 0 1.5rem;
}

.property-hero {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
    padding: 2rem 1.5rem;
    margin-bottom: 2rem;
    border-radius: 12px;
}

.property-hero h1 {
    font-size: 2.5rem;
    margin-bottom: 0.5rem;
    font-weight: 700;
}

.property-hero .meta {
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;
    opacity: 0.95;
}

.property-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

.carousel-container {
    position: relative;
    width: 100%;
    height: 450px;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
}

.carousel {
    position: relative;
    width: 100%;
    height: 100%;
}

.carousel-img {
    position: absolute;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: none;
    opacity: 0;
    transition: opacity 0.5s ease;
}

.carousel-img.active {
    display: block;
    opacity: 1;
}

.carousel-controls {
    position: absolute;
    bottom: 1rem;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 0.5rem;
    z-index: 10;
}

.carousel-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.5);
    cursor: pointer;
    transition: all 0.3s;
    border: none;
}

.carousel-dot.active {
    background: white;
    transform: scale(1.2);
}

.carousel-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.4);
    color: white;
    border: none;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    transition: all 0.3s;
    z-index: 5;
}

.carousel-btn:hover {
    background: rgba(0, 0, 0, 0.6);
}

.carousel-btn.prev {
    left: 1rem;
}

.carousel-btn.next {
    right: 1rem;
}

.property-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.info-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: var(--shadow);
}

.info-card h3 {
    font-size: 0.9rem;
    text-transform: uppercase;
    color: var(--text-light);
    margin-bottom: 0.5rem;
    font-weight: 700;
}

.price-display {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--primary);
    margin: 0.5rem 0 1.5rem 0;
}

.features {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin: 1.5rem 0;
}

.feature-item {
    text-align: center;
    padding: 1rem;
    background: var(--light-bg);
    border-radius: 8px;
}

.feature-item i {
    font-size: 1.5rem;
    color: var(--primary);
    margin-bottom: 0.5rem;
    display: block;
}

.feature-item span {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--text-dark);
}

.feature-item small {
    display: block;
    color: var(--text-light);
    font-size: 0.85rem;
    margin-top: 0.25rem;
}

.landlord-card {
    border-top: 2px solid var(--border-color);
    padding-top: 1.5rem;
}

.landlord-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.landlord-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.5rem;
}

.landlord-details h4 {
    margin-bottom: 0.25rem;
    color: var(--text-dark);
}

.landlord-details a {
    color: var(--primary);
    text-decoration: none;
    font-size: 0.9rem;
    transition: color 0.2s;
}

.landlord-details a:hover {
    color: var(--primary-light);
}

.property-details {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
}

.property-details h2 {
    color: var(--primary);
    margin-bottom: 1.5rem;
    font-size: 1.5rem;
}

.description {
    line-height: 1.8;
    color: var(--text-light);
    margin-bottom: 2rem;
}

.meta-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.meta-item i {
    font-size: 1.5rem;
    color: var(--primary);
}

.meta-item strong {
    display: block;
    color: var(--text-dark);
    margin-bottom: 0.25rem;
}

.meta-item span {
    color: var(--text-light);
    font-size: 0.9rem;
}

.action-buttons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.btn {
    padding: 1rem 2rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1rem;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-light);
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-secondary {
    background: var(--secondary);
    color: white;
}

.btn-secondary:hover {
    background: #F57C00;
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.btn-outline {
    background: transparent;
    color: var(--primary);
    border: 2px solid var(--primary);
}

.btn-outline:hover {
    background: var(--primary);
    color: white;
}

footer {
    background: var(--text-dark);
    color: white;
    text-align: center;
    padding: 2rem;
    margin-top: 3rem;
}

footer a {
    color: var(--secondary);
    text-decoration: none;
    transition: opacity 0.2s;
}

footer a:hover {
    opacity: 0.8;
}

@media (max-width: 1024px) {
    .property-layout {
        grid-template-columns: 1fr;
    }

    .carousel-container {
        height: 400px;
    }
}

@media (max-width: 768px) {
    header .header-content {
        flex-direction: column;
        gap: 1rem;
    }

    .property-hero h1 {
        font-size: 1.8rem;
    }

    .property-hero .meta {
        gap: 1rem;
        flex-direction: column;
    }

    .features {
        grid-template-columns: 1fr;
    }

    .meta-info {
        grid-template-columns: 1fr;
    }

    .action-buttons {
        flex-direction: column;
    }

    .btn {
        justify-content: center;
    }

    .carousel-container {
        height: 300px;
    }
}
</style>
</head>
<body>

<header>
  <div class="header-content">
    <div>
      <h2 style="margin: 0; font-size: 1.5rem;">RentConnect</h2>
    </div>
    <nav>
      <a href="index.php">Home</a>
      <a href="property.php">Browse</a>
      <?php if (!isset($_SESSION['user_id'])): ?>
        <a href="login.php">Login</a>
      <?php else: ?>
        <div class="profile-menu">
          <div class="profile-toggle" onclick="toggleDropdown(event)">
            <i class="fas fa-user-circle"></i>
            <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
            <i class="fas fa-chevron-down"></i>
          </div>
          <div class="dropdown-content">
            <a href="<?= $_SESSION['role'] === 'landlord' ? 'landlord_dashboard.php' : 'renter_dashboard.php' ?>">Dashboard</a>
            <a href="contact.php">Contact</a>
            <a href="logout.php">Logout</a>
          </div>
        </div>
      <?php endif; ?>
    </nav>
  </div>
</header>

<div class="container">
  <div class="property-hero">
    <h1><?= htmlspecialchars($row['title']) ?></h1>
    <div class="meta">
      <div>
        <i class="fas fa-map-marker-alt"></i>
        <?= htmlspecialchars($row['location']) ?>
      </div>
      <div>
        <i class="fas fa-calendar-alt"></i>
        Posted <?= date("M d, Y", strtotime($row['created_at'])) ?>
      </div>
    </div>
  </div>

  <div class="property-layout">
    <div>
      <!-- Image Carousel -->
      <div class="carousel-container">
        <div class="carousel">
          <?php
            $imgs = rc_mig_get_property_image_ids($conn, $property_id);

            if (!empty($imgs)) {
              $i = 0;
              foreach ($imgs as $img) {
                  $active = ($i == 0) ? "active" : "";
                  echo '<img src="display_image.php?img_id='.$img['id'].'" class="carousel-img '.$active.'" alt="Property image '.($i+1).'">';
                  $i++;
              }
          } else {
              echo '<img src="images/no-image.png" alt="No image available" class="carousel-img active">';
          }
          ?>
        </div>
        <button class="carousel-btn prev" onclick="changeSlide(-1)"><i class="fas fa-chevron-left"></i></button>
        <button class="carousel-btn next" onclick="changeSlide(1)"><i class="fas fa-chevron-right"></i></button>
        <div class="carousel-controls">
          <?php
            $image_count = count($imgs);
            if ($image_count <= 0) {
              $image_count = 1;
            }

          for ($j = 0; $j < $image_count; $j++) {
              $active = ($j == 0) ? "active" : "";
              echo '<button class="carousel-dot '.$active.'" onclick="currentSlide('.$j.')"></button>';
          }
          ?>
        </div>
      </div>

      <!-- Property Details -->
      <div class="property-details">
        <h2><i class="fas fa-info-circle"></i> About This Property</h2>
        <div class="description">
          <?= nl2br(htmlspecialchars($row['description'])) ?>
        </div>

        <div class="meta-info">
          <div class="meta-item">
            <i class="fas fa-bed"></i>
            <div>
              <strong><?= (int)$row['bedrooms'] ?></strong>
              <span>Bedrooms</span>
            </div>
          </div>
          <div class="meta-item">
            <i class="fas fa-bath"></i>
            <div>
              <strong><?= (int)$row['bathrooms'] ?></strong>
              <span>Bathrooms</span>
            </div>
          </div>
          <div class="meta-item">
            <i class="fas fa-map-marker-alt"></i>
            <div>
              <strong><?= htmlspecialchars($row['location']) ?></strong>
              <span>Location</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Sidebar -->
    <div class="property-sidebar">
      <!-- Price Card -->
      <div class="info-card">
        <h3>Monthly Rental</h3>
        <div class="price-display">$<?= number_format($row['price'], 2) ?></div>
        
        <div class="features">
          <div class="feature-item">
            <i class="fas fa-bed"></i>
            <span><?= (int)$row['bedrooms'] ?></span>
            <small>Bedrooms</small>
          </div>
          <div class="feature-item">
            <i class="fas fa-bath"></i>
            <span><?= (int)$row['bathrooms'] ?></span>
            <small>Bathrooms</small>
          </div>
        </div>
      </div>

      <!-- Landlord Card -->
      <div class="info-card landlord-card">
        <h3>Contact Information</h3>
        <div class="landlord-info">
          <div class="landlord-avatar">
            <?= strtoupper(substr(htmlspecialchars($row['landlord_name']), 0, 1)) ?>
          </div>
          <div class="landlord-details">
            <h4><?= htmlspecialchars($row['landlord_name']) ?></h4>
            <a href="mailto:<?= htmlspecialchars($row['landlord_email']) ?>">
              <i class="fas fa-envelope"></i> <?= htmlspecialchars($row['landlord_email']) ?>
            </a>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="action-buttons" style="flex-direction: column; gap: 1rem;">
        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'renter'): ?>
          <a href="rental_requests.php?request=<?= $row['id'] ?>" class="btn btn-primary">
            <i class="fas fa-check-circle"></i> Request to Rent
          </a>
        <?php elseif (!isset($_SESSION['user_id'])): ?>
          <a href="login.php" class="btn btn-primary">
            <i class="fas fa-sign-in-alt"></i> Login to Request
          </a>
        <?php endif; ?>
        <a href="property.php" class="btn btn-outline">
          <i class="fas fa-arrow-left"></i> Back to Browse
        </a>
      </div>
    </div>
  </div>
</div>

<footer>
  <div class="container">
    <p>&copy; <?php echo date("Y"); ?> RentConnect Liberia | All rights reserved. | <a href="services.php">Our Services</a></p>
  </div>
</footer>

<script>
let currentSlideIndex = 0;
const imageCount = <?= $image_count ?>;

function changeSlide(n) {
    currentSlideIndex = (currentSlideIndex + n + imageCount) % imageCount;
    showSlide(currentSlideIndex);
}

function currentSlide(n) {
    currentSlideIndex = n;
    showSlide(currentSlideIndex);
}

function showSlide(n) {
    const slides = document.querySelectorAll('.carousel-img');
    const dots = document.querySelectorAll('.carousel-dot');

    if (slides.length === 0) return;

    slides.forEach(slide => slide.classList.remove('active'));
    dots.forEach(dot => dot.classList.remove('active'));

    slides[n].classList.add('active');
    if (dots[n]) dots[n].classList.add('active');
}

// Auto-advance carousel every 5 seconds if more than one image
if (imageCount > 1) {
    setInterval(() => {
        changeSlide(1);
    }, 5000);
}

function toggleDropdown(event) {
  event.preventDefault();
  event.stopPropagation();
  event.target.closest(".profile-menu").classList.toggle("active");
}

document.addEventListener("click", (event) => {
  if (!event.target.closest(".profile-menu")) {
    document.querySelectorAll(".profile-menu").forEach((menu) => menu.classList.remove("active"));
  }
});

document.addEventListener("DOMContentLoaded", () => {
    showSlide(0);
});
</script>

</body>
</html>
<?php
} else {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Not Found - RentConnect</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    :root {
        --primary: #2E7D32;
        --light-bg: #f5f7fa;
        --text-dark: #2c3e50;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: "Plus Jakarta Sans", sans-serif; background: var(--light-bg); }
    header { background: linear-gradient(135deg, var(--primary) 0%, #4CAF50 100%); color: white; padding: 1.5rem; text-align: center; }
    .container { max-width: 1000px; margin: 3rem auto; padding: 0 1.5rem; }
    .error { background: white; padding: 3rem; border-radius: 12px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    .error i { font-size: 4rem; color: #d32f2f; margin-bottom: 1rem; }
    .error h1 { color: var(--text-dark); margin-bottom: 1rem; }
    .error p { color: #666; margin-bottom: 2rem; }
    .error a { display: inline-block; padding: 0.75rem 2rem; background: var(--primary); color: white; text-decoration: none; border-radius: 6px; font-weight: 600; }
    footer { text-align: center; padding: 2rem; color: #666; }
    </style>
    </head>
    <body>
    <header>
        <h1>RentConnect</h1>
    </header>
    <div class="container">
        <div class="error">
            <i class="fas fa-exclamation-circle"></i>
            <h1>Property Not Found</h1>
            <p>This property doesn't exist or hasn't been approved yet.</p>
            <a href="property.php"><i class="fas fa-arrow-left"></i> Back to Browse</a>
        </div>
    </div>
    <footer>
        <p>&copy; <?php echo date("Y"); ?> RentConnect Liberia | All rights reserved.</p>
    </footer>
    </body>
    </html>
    <?php
}
