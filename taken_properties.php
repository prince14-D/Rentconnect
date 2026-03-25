<?php
session_start();
include "db.php";

// Ensure landlord access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];
$message = "";

// Optional search by title or location
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch taken properties
$sql = "SELECT * FROM properties WHERE owner_id=? AND status='taken'";
$params = [$landlord_id];
$types = "i";

if ($search !== '') {
    $sql .= " AND (title LIKE ? OR location LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "ss";
}

$sql .= " ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

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

.search-bar {
    margin-bottom: 2rem;
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
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

    th, td {
        padding: 0.75rem;
        font-size: 0.9rem;
    }

    .content {
        padding: 1rem;
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
  <div class="content">
    <div class="search-bar">
      <form method="GET">
        <input type="text" name="search" placeholder="Search by title or location..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit"><i class="fas fa-search"></i> Search</button>
      </form>
      <?php if ($search): ?>
        <a href="taken_properties.php" style="padding: 0.75rem 1.5rem; background: var(--border-color); color: var(--text-dark); border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem;">
          <i class="fas fa-times"></i> Clear
        </a>
      <?php endif; ?>
    </div>

    <?php if($result && $result->num_rows > 0): ?>
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
          <?php while($row = $result->fetch_assoc()): ?>
          <tr>
            <td><strong><?= htmlspecialchars($row['title']) ?></strong></td>
            <td><?= htmlspecialchars($row['location']) ?></td>
            <td><strong>$<?= number_format($row['price'], 2) ?></strong></td>
            <td><?= $row['bedrooms'] ?></td>
            <td><?= $row['bathrooms'] ?></td>
            <td><?= format_date($row['created_at']) ?></td>
            <td><span class="status-badge">Taken</span></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
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
