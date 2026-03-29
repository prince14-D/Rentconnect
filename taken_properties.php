<?php
session_start();
include "app_init.php";

// Ensure landlord access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = (int) $_SESSION['user_id'];
$message = "";

// Optional search by title or location
$search = trim((string) ($_GET['search'] ?? ''));

$result = rc_mig_get_landlord_properties($conn, (int) $landlord_id, 'taken', (string) $search);

$taken_count = count($result);
$total_price = 0.0;
$latest_taken = null;
foreach ($result as $row) {
  $total_price += (float) ($row['price'] ?? 0);
  $created_at = (string) ($row['created_at'] ?? '');
  $ts = strtotime($created_at);
  if ($ts !== false && ($latest_taken === null || $ts > $latest_taken)) {
    $latest_taken = $ts;
  }
}
$avg_price = $taken_count > 0 ? ($total_price / $taken_count) : 0.0;

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
<title>Taken Properties - RentConnect</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #2E7D32;
    --primary-light: #4CAF50;
    --secondary: #FF9800;
    --info: #1976d2;
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
    max-width: 1100px;
    margin: 2rem auto;
    padding: 0 1.5rem;
}

.content {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: var(--shadow);
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

.taken-note {
  border: 1px solid #bbdefb;
  background: #eef6ff;
  color: #0d47a1;
  border-radius: 10px;
  padding: 0.85rem;
  margin-bottom: 1rem;
  font-size: 0.9rem;
}

.search-bar {
    margin-bottom: 2rem;
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.search-meta {
  color: var(--text-light);
  font-size: 0.9rem;
  font-weight: 600;
  margin-bottom: 0.75rem;
}

.search-bar form {
    display: flex;
    gap: 0.75rem;
    flex: 1;
    min-width: 300px;
}

.search-bar input[type="text"] {
    flex: 1;
    padding: 0.75rem 1rem;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    font-family: inherit;
    transition: border-color 0.2s;
}

.search-bar input[type="text"]:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
}

.search-bar button {
    padding: 0.75rem 1.5rem;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;
}

.search-bar button:hover {
    background: var(--primary-light);
    transform: translateY(-1px);
    box-shadow: var(--shadow);
}

.toolbar-actions {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.toolbar-actions a {
  padding: 0.75rem 1rem;
  border-radius: 6px;
  text-decoration: none;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-bookings {
  background: #1976d2;
  color: #fff;
}

.btn-income {
  background: #455a64;
  color: #fff;
}

.table-wrapper {
  overflow-x: auto;
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

.table-wrapper {
    overflow-x: auto;
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
    background: var(--info);
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
}

.empty-state-icon {
    font-size: 3rem;
    color: var(--info);
    margin-bottom: 1rem;
}

.empty-state h2 {
    color: var(--text-dark);
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: var(--text-light);
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

    .search-bar form {
        flex-direction: column;
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

    .table-wrapper {
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
  <h1>My Taken Properties</h1>
  <p>Properties that have been rented out</p>
</div>
<div class="container">
  <div class="stats">
    <article class="stat-card">
      <div class="stat-label">Taken Listings</div>
      <div class="stat-value"><?= (int) $taken_count ?></div>
    </article>
    <article class="stat-card">
      <div class="stat-label">Average Rent Value</div>
      <div class="stat-value">$<?= number_format($avg_price, 2) ?></div>
    </article>
    <article class="stat-card">
      <div class="stat-label">Latest Taken Date</div>
      <div class="stat-value"><?= $latest_taken ? date('M d, Y', $latest_taken) : 'N/A' ?></div>
    </article>
  </div>

  <div class="content">
    <div class="taken-note">
      <i class="fas fa-house-circle-check"></i>
      These listings are occupied. Use bookings and income pages to track tenant status and cash flow.
    </div>

    <div class="search-meta">Search and review occupied properties quickly.</div>
    <div class="search-bar">
      <form method="GET">
        <input type="text" name="search" placeholder="Search by title or location..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit"><i class="fas fa-search"></i> Search</button>
      </form>
      <div class="toolbar-actions">
        <?php if ($search): ?>
          <a href="taken_properties.php" style="background: var(--border-color); color: var(--text-dark);">
            <i class="fas fa-times"></i> Clear
          </a>
        <?php endif; ?>
        <a href="manage_bookings.php" class="btn-bookings"><i class="fas fa-calendar-check"></i> Manage Bookings</a>
        <a href="landlord_income.php" class="btn-income"><i class="fas fa-chart-line"></i> Income</a>
      </div>
    </div>

    <?php if(!empty($result)): ?>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Title</th>
            <th>Location</th>
            <th>Price</th>
            <th>Bedrooms</th>
            <th>Bathrooms</th>
            <th>Date</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($result as $row): ?>
          <tr>
            <td><strong><?= htmlspecialchars($row['title']) ?></strong></td>
            <td><?= htmlspecialchars($row['location']) ?></td>
            <td><strong>$<?= number_format($row['price'], 2) ?></strong></td>
            <td><?= (int) $row['bedrooms'] ?></td>
            <td><?= (int) $row['bathrooms'] ?></td>
            <td><?= format_date((string) $row['created_at']) ?></td>
            <td><span class="status-badge">Taken</span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="mobile-cards">
      <?php foreach($result as $row): ?>
      <article class="property-card">
        <h3><?= htmlspecialchars($row['title']) ?></h3>
        <p class="property-meta"><i class="fas fa-location-dot"></i> <?= htmlspecialchars($row['location']) ?></p>
        <p class="property-meta"><i class="fas fa-dollar-sign"></i> $<?= number_format($row['price'], 2) ?></p>
        <p class="property-meta"><i class="fas fa-bed"></i> <?= (int) $row['bedrooms'] ?> bed | <i class="fas fa-bath"></i> <?= (int) $row['bathrooms'] ?> bath</p>
        <p class="property-meta"><i class="fas fa-calendar"></i> Since <?= format_date((string) $row['created_at']) ?></p>
        <p class="property-meta"><span class="status-badge">Taken</span></p>
      </article>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
      <div class="empty-state-icon">
        <i class="fas fa-home"></i>
      </div>
      <h2><?= $search ? 'No Results Found' : 'No Taken Properties' ?></h2>
      <p><?= $search ? "No properties match your search: \"" . htmlspecialchars($search) . "\"" : "You have no rented out properties yet." ?></p>
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
