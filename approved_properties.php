<?php
session_start();
include "app_init.php";

// Ensure landlord access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = (int) $_SESSION['user_id'];
$properties = rc_mig_get_landlord_properties($conn, $landlord_id, 'approved', '');

$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
  $properties = array_values(array_filter($properties, static function (array $row) use ($q): bool {
    $title = (string) ($row['title'] ?? '');
    $location = (string) ($row['location'] ?? '');
    return stripos($title, $q) !== false || stripos($location, $q) !== false;
  }));
}

$approved_count = count($properties);
$total_price = 0.0;
$latest_approved = null;
foreach ($properties as $row) {
  $total_price += (float) ($row['price'] ?? 0);
  $created_at = (string) ($row['created_at'] ?? '');
  $ts = strtotime($created_at);
  if ($ts !== false && ($latest_approved === null || $ts > $latest_approved)) {
    $latest_approved = $ts;
  }
}
$avg_price = $approved_count > 0 ? ($total_price / $approved_count) : 0.0;

function format_date($date_str) {
    return date("M d, Y", strtotime($date_str));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<title>Approved Properties - RentConnect</title>
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

.hero {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
    color: white;
    padding: 3rem 1.5rem;
    text-align: center;
}

.hero h1 {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    font-weight: 700;
}

.hero p {
    font-size: 1rem;
    opacity: 0.9;
}

.container {
    max-width: 1000px;
    margin: 2rem auto;
    padding: 0 1.5rem;
}

.content {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: var(--shadow);
    overflow-x: auto;
}

.stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.9rem;
  margin-bottom: 1rem;
}

.stat-card {
  border: 1px solid var(--border-color);
  border-radius: 10px;
  padding: 0.9rem;
  background: #fff;
}

.stat-label {
  color: var(--text-light);
  font-size: 0.8rem;
  font-weight: 600;
}

.stat-value {
  margin-top: 0.2rem;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--text-dark);
}

.live-note {
  border: 1px solid #c8e6c9;
  background: #eef9ef;
  color: #1b5e20;
  border-radius: 10px;
  padding: 0.85rem;
  margin-bottom: 1rem;
  font-size: 0.9rem;
}

.toolbar {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  align-items: center;
  flex-wrap: wrap;
  margin-bottom: 1rem;
}

.search-form {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.search-form input {
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 0.55rem 0.75rem;
  min-width: 220px;
  font: inherit;
}

.btn {
  padding: 0.5rem 1rem;
  border-radius: 6px;
  text-decoration: none;
  font-size: 0.85rem;
  font-weight: 600;
  transition: all 0.2s;
  border: none;
  cursor: pointer;
  display: inline-block;
}

.btn-primary {
  background: var(--primary);
  color: white;
}

.btn-primary:hover {
  background: var(--primary-light);
  transform: translateY(-1px);
  box-shadow: var(--shadow);
}

.btn-neutral {
  background: #455a64;
  color: white;
}

.btn-neutral:hover {
  background: #37474f;
  transform: translateY(-1px);
  box-shadow: var(--shadow);
}

.btn-link {
  background: #1976d2;
  color: white;
}

.btn-link:hover {
  background: #1159a8;
  transform: translateY(-1px);
  box-shadow: var(--shadow);
}

.table-wrap {
  overflow-x: auto;
}

.actions {
  display: flex;
  gap: 0.45rem;
  flex-wrap: wrap;
}

.mobile-cards {
  display: none;
  gap: 0.75rem;
}

.property-card {
  border: 1px solid var(--border-color);
  border-radius: 10px;
  padding: 0.9rem;
  background: #fff;
}

.property-card h3 {
  margin: 0 0 0.45rem;
  font-size: 1rem;
}

.property-meta {
  color: var(--text-light);
  font-size: 0.88rem;
  margin: 0.2rem 0;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: var(--light-bg);
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--text-dark);
    border-bottom: 2px solid var(--border-color);
}

td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
}

tr:hover {
    background: var(--light-bg);
}

.status-badge {
    display: inline-block;
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    color: white;
    background: var(--primary);
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
}

.empty-state-icon {
    font-size: 3rem;
    color: var(--primary);
    margin-bottom: 1rem;
}

.empty-state h2 {
    color: var(--text-dark);
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: var(--text-light);
    margin-bottom: 2rem;
}

.back-link {
    display: inline-block;
    margin-top: 2rem;
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s;
}

.back-link:hover {
    gap: 0.5rem;
    transform: translateX(-4px);
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

@media (max-width: 768px) {
    header .header-content {
        flex-direction: column;
        gap: 1rem;
    }

    .hero h1 {
        font-size: 1.5rem;
    }

  .stats {
    grid-template-columns: 1fr;
  }

    th, td {
        padding: 0.75rem;
        font-size: 0.9rem;
    }

    .content {
        padding: 1rem;
    }

  .table-wrap {
    display: none;
  }

  .mobile-cards {
    display: grid;
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
      <a href="landlord_dashboard.php">Dashboard</a>
      <a href="my_properties.php">My Properties</a>
      <a href="rental_requests.php">Requests</a>
      <div class="profile-menu">
        <div class="profile-toggle" onclick="toggleDropdown(event)">
          <i class="fas fa-user-circle"></i>
          <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
          <i class="fas fa-chevron-down"></i>
        </div>
        <div class="dropdown-content">
          <a href="settings.php">Settings</a>
          <a href="logout.php">Logout</a>
        </div>
      </div>
    </nav>
  </div>
</header>

<div class="hero">
  <h1>My Approved Properties</h1>
  <p>Properties that have been approved and are live</p>
</div>
<div class="container">
  <div class="stats">
    <article class="stat-card">
      <div class="stat-label">Approved Listings</div>
      <div class="stat-value"><?= (int) $approved_count ?></div>
    </article>
    <article class="stat-card">
      <div class="stat-label">Average Price</div>
      <div class="stat-value">$<?= number_format($avg_price, 2) ?></div>
    </article>
    <article class="stat-card">
      <div class="stat-label">Latest Approved</div>
      <div class="stat-value"><?= $latest_approved ? date('M d, Y', $latest_approved) : 'N/A' ?></div>
    </article>
  </div>

  <div class="content">
    <div class="live-note">
      <i class="fas fa-circle-check"></i>
      Approved listings are live and visible to renters. Keep details accurate for better booking performance.
    </div>

    <div class="toolbar">
      <form method="get" class="search-form">
        <input type="text" name="q" placeholder="Search title or location" value="<?= htmlspecialchars($q) ?>">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
        <?php if ($q !== ''): ?>
          <a href="approved_properties.php" class="btn btn-neutral"><i class="fas fa-rotate-left"></i> Reset</a>
        <?php endif; ?>
      </form>
      <div class="actions">
        <a href="add_property.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Property</a>
        <a href="manage_bookings.php" class="btn btn-link"><i class="fas fa-calendar-check"></i> Bookings</a>
      </div>
    </div>

    <?php if(!empty($properties)): ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Title</th>
            <th>Location</th>
            <th>Price</th>
            <th>Bedrooms</th>
            <th>Bathrooms</th>
            <th>Status</th>
            <th>Approved</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($properties as $row): ?>
          <tr>
            <td><strong><?= htmlspecialchars($row['title']) ?></strong></td>
            <td><?= htmlspecialchars($row['location']) ?></td>
            <td><strong>$<?= number_format($row['price'], 2) ?></strong></td>
            <td><?= (int) $row['bedrooms'] ?></td>
            <td><?= (int) $row['bathrooms'] ?></td>
            <td><span class="status-badge">Approved</span></td>
            <td><?= format_date((string) $row['created_at']) ?></td>
            <td class="actions">
              <a href="edit_property.php?id=<?= (int) $row['id'] ?>" class="btn btn-primary"><i class="fas fa-pen"></i> Edit</a>
              <a href="property.php?id=<?= (int) $row['id'] ?>" class="btn btn-link"><i class="fas fa-eye"></i> View</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="mobile-cards">
      <?php foreach($properties as $row): ?>
      <article class="property-card">
        <h3><?= htmlspecialchars($row['title']) ?></h3>
        <p class="property-meta"><i class="fas fa-location-dot"></i> <?= htmlspecialchars($row['location']) ?></p>
        <p class="property-meta"><i class="fas fa-dollar-sign"></i> $<?= number_format($row['price'], 2) ?></p>
        <p class="property-meta"><i class="fas fa-bed"></i> <?= (int) $row['bedrooms'] ?> bed | <i class="fas fa-bath"></i> <?= (int) $row['bathrooms'] ?> bath</p>
        <p class="property-meta"><i class="fas fa-calendar"></i> Approved <?= format_date((string) $row['created_at']) ?></p>
        <p class="property-meta"><span class="status-badge">Approved</span></p>
        <div class="actions" style="margin-top: 0.6rem;">
          <a href="edit_property.php?id=<?= (int) $row['id'] ?>" class="btn btn-primary"><i class="fas fa-pen"></i> Edit</a>
          <a href="property.php?id=<?= (int) $row['id'] ?>" class="btn btn-link"><i class="fas fa-eye"></i> View</a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon">
        <i class="fas fa-check-circle"></i>
      </div>
      <h2><?= $q !== '' ? 'No Matching Approved Properties' : 'No Approved Properties Yet' ?></h2>
      <p>
        <?php if ($q !== ''): ?>
          No approved listings match your search. Try a different keyword.
        <?php else: ?>
          You don't have any approved properties yet. Submit properties for approval and they will appear here once reviewed.
        <?php endif; ?>
      </p>
      <a href="add_property.php" class="btn btn-primary" style="display: inline-block; margin-top: 1rem;">
        <i class="fas fa-plus"></i> Add Property
      </a>
    </div>
    <?php endif; ?>
  </div>

  <a href="landlord_dashboard.php" class="back-link">
    <i class="fas fa-arrow-left"></i> Back to Dashboard
  </a>
</div>

<footer>
  <div class="container">
    <p>&copy; <?php echo date("Y"); ?> RentConnect Liberia | All rights reserved. | <a href="services.php">Our Services</a></p>
  </div>
</footer>

<script>
function toggleMenu() {
  document.getElementById("navMenu").classList.toggle("active");
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
</script>

</body>
</html>
