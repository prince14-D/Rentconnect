<?php
session_start();
include "app_init.php";

// Only allow super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: login.php");
    exit();
}

// Handle actions
if (isset($_GET['action'], $_GET['id'])) {
    $id = intval($_GET['id']);
    if ($_GET['action'] === 'approve') {
    rc_mig_set_property_status($conn, $id, 'approved');
    } elseif ($_GET['action'] === 'hide') {
    rc_mig_set_property_status($conn, $id, 'inactive');
    } elseif ($_GET['action'] === 'delete') {
    rc_mig_delete_property_with_images($conn, $id);
    }
    header("Location: super_admin_dashboard.php");
    exit();
}

// Fetch dashboard stats
$stats = rc_mig_get_super_admin_property_stats($conn);

// Filter + Search
$status_filter = $_GET['status'] ?? 'all';
$contact_filter = $_GET['contact_filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Pagination
$limit = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$pageData = rc_mig_get_super_admin_properties_page($conn, (string) $status_filter, (string) $search, (int) $page, (int) $limit, (string) $contact_filter);
$result = $pageData['rows'];
$total_records = (int) ($pageData['total_records'] ?? 0);
$total_pages = (int) ($pageData['total_pages'] ?? 1);

$landlordContacts = [];
foreach ($result as $row) {
  $name = trim((string) ($row['landlord_name'] ?? ''));
  $contact = trim((string) ($row['contact'] ?? ''));
  if ($name === '') {
    $name = 'Unknown';
  }
  if ($contact === '') {
    continue;
  }
  $key = strtolower($name . '|' . $contact);
  $landlordContacts[$key] = ['name' => $name, 'contact' => $contact];
}

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
<title>Super Admin Dashboard - RentConnect</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #2E7D32;
    --primary-light: #4CAF50;
    --secondary: #FF9800;
    --danger: #d32f2f;
    --warning: #FFA726;
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
    box-shadow: var(--shadow-lg);
    position: sticky;
    top: 0;
    z-index: 100;
}

header .header-content {
    max-width: 1400px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

header h1 {
    font-size: 1.8rem;
    font-weight: 700;
}

header a.logout {
    padding: 0.75rem 1.5rem;
    background: var(--danger);
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 600;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

header a.logout:hover {
    background: #b71c1c;
    transform: translateY(-1px);
    box-shadow: var(--shadow-lg);
}

.container {
    max-width: 1400px;
    margin: 2rem auto;
    padding: 0 1.5rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: var(--shadow);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-card i {
    font-size: 2rem;
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.stat-card.total i { background: var(--primary); }
.stat-card.pending i { background: var(--secondary); }
.stat-card.approved i { background: var(--info); }
.stat-card.taken i { background: #9C27B0; }
.stat-card.inactive i { background: #757575; }

.stat-content h3 {
    font-size: 0.9rem;
    text-transform: uppercase;
    color: var(--text-light);
    margin-bottom: 0.25rem;
    font-weight: 700;
}

.stat-content p {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-dark);
}

.content-card {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: var(--shadow);
    margin-bottom: 2rem;
}

.search-bar {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 2rem;
}

.contacts-panel {
  background: #f8fbff;
  border: 1px solid #dbe8f7;
  border-radius: 10px;
  padding: 1rem;
  margin-bottom: 1.25rem;
}

.contacts-panel h3 {
  margin-bottom: 0.65rem;
  color: var(--info);
  font-size: 1rem;
}

.contact-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.contact-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.45rem 0.75rem;
  border-radius: 999px;
  background: #fff;
  border: 1px solid #d6e3f1;
  font-size: 0.85rem;
}

.contact-chip a {
  color: var(--primary);
  text-decoration: none;
  font-weight: 700;
}

.search-bar form {
    display: flex;
    gap: 1rem;
    flex: 1;
    min-width: 300px;
    align-items: center;
    flex-wrap: wrap;
}

.search-bar label {
    font-weight: 600;
    color: var(--text-dark);
}

.search-bar select,
.search-bar input {
    padding: 0.75rem 1rem;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    font-family: inherit;
    font-size: 0.95rem;
    transition: all 0.2s;
}

.search-bar select {
    min-width: 150px;
}

.search-bar input {
    flex: 1;
    min-width: 200px;
}

.search-bar select:focus,
.search-bar input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
}

.search-bar button {
    padding: 0.75rem 2rem;
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
    margin-bottom: 2rem;
}

th {
    background: var(--light-bg);
    padding: 1rem;
    text-align: left;
    font-weight: 700;
    color: var(--text-dark);
    border-bottom: 2px solid var(--border-color);
    font-size: 0.9rem;
    text-transform: uppercase;
}

td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
}

tr:hover {
    background: var(--light-bg);
}

.badge {
    display: inline-block;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    white-space: nowrap;
}

.badge-pending { background: var(--secondary); color: white; }
.badge-approved { background: var(--primary); color: white; }
.badge-taken { background: var(--info); color: white; }
.badge-inactive { background: #9e9e9e; color: white; }

.img-thumbnails {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.img-thumb {
    width: 50px;
    height: 50px;
    border-radius: 6px;
    object-fit: cover;
    cursor: pointer;
    border: 2px solid var(--border-color);
    transition: all 0.2s;
}

.img-thumb:hover {
    border-color: var(--primary);
    transform: scale(1.05);
}

.actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
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
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.btn-approve {
    background: var(--primary);
    color: white;
}

.btn-approve:hover {
    background: var(--primary-light);
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.btn-hide {
    background: #9e9e9e;
    color: white;
}

.btn-hide:hover {
    background: #757575;
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.btn-delete {
    background: var(--danger);
    color: white;
}

.btn-delete:hover {
    background: #b71c1c;
    transform: translateY(-2px);
    box-shadow: var(--shadow);
}

.empty-state {
    text-align: center;
    padding: 3rem;
}

.empty-state i {
    font-size: 3rem;
    color: var(--secondary);
    margin-bottom: 1rem;
}

.empty-state p {
    color: var(--text-light);
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: 2rem;
}

.pagination a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 1rem;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    text-decoration: none;
    color: var(--text-dark);
    transition: all 0.2s;
    min-width: 40px;
}

.pagination a:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.pagination a.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

/* Lightbox */
#lightbox {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.9);
    justify-content: center;
    align-items: center;
    z-index: 2000;
    padding: 2rem;
}

#lightbox.active {
    display: flex;
}

#lightbox-img {
    max-width: 90%;
    max-height: 90%;
    border-radius: 10px;
}

.lightbox-close {
    position: absolute;
    top: 2rem;
    right: 2rem;
    color: white;
    cursor: pointer;
    font-size: 2rem;
    z-index: 2001;
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
    .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
}

@media (max-width: 768px) {
    header .header-content {
        flex-direction: column;
        gap: 1rem;
    }

    header h1 {
        font-size: 1.3rem;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .search-bar form {
        flex-direction: column;
    }

    .search-bar input {
        width: 100%;
    }

    .table-wrapper {
        margin-bottom: 1rem;
    }

    table, thead, tbody, th, td, tr {
        display: block;
    }

    thead {
        display: none;
    }

    tr {
        margin-bottom: 1.5rem;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 1rem;
        background: white;
    }

    td {
        border: none;
        padding: 0.5rem 0;
        text-align: left;
    }

    td::before {
        content: attr(data-label);
        font-weight: 700;
        display: block;
        margin-bottom: 0.5rem;
        color: var(--text-dark);
    }

    .img-thumbnails {
        width: 100%;
    }

    .img-thumb {
        width: 70px;
        height: 70px;
    }

    .actions {
        flex-direction: column;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }

    .content-card {
        padding: 1rem;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .stat-card {
        flex-direction: column;
        text-align: center;
    }

    .pagination a {
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
    }
}
</style>
</head>
<body>

<header>
  <div class="header-content">
    <h1><i class="fas fa-crown"></i> Admin Dashboard</h1>
    <a href="super_admin_logout.php" class="logout">
      <i class="fas fa-sign-out-alt"></i> Logout
    </a>
  </div>
</header>

<div class="container">
  <!-- Statistics -->
  <div class="stats-grid">
    <div class="stat-card total">
      <i class="fas fa-home"></i>
      <div class="stat-content">
        <h3>Total Properties</h3>
        <p><?= $stats['total_properties'] ?? 0 ?></p>
      </div>
    </div>
    <div class="stat-card pending">
      <i class="fas fa-hourglass-half"></i>
      <div class="stat-content">
        <h3>Pending</h3>
        <p><?= $stats['pending_count'] ?? 0 ?></p>
      </div>
    </div>
    <div class="stat-card approved">
      <i class="fas fa-check-circle"></i>
      <div class="stat-content">
        <h3>Approved</h3>
        <p><?= $stats['approved_count'] ?? 0 ?></p>
      </div>
    </div>
    <div class="stat-card taken">
      <i class="fas fa-ban"></i>
      <div class="stat-content">
        <h3>Taken</h3>
        <p><?= $stats['taken_count'] ?? 0 ?></p>
      </div>
    </div>
    <div class="stat-card inactive">
      <i class="fas fa-eye-slash"></i>
      <div class="stat-content">
        <h3>Inactive</h3>
        <p><?= $stats['inactive_count'] ?? 0 ?></p>
      </div>
    </div>
  </div>

  <!-- Properties Section -->
  <div class="content-card">
    <h2 style="margin-bottom: 1.5rem; color: var(--primary); display: flex; align-items: center; gap: 0.5rem;">
      <i class="fas fa-list"></i> Property Listings
    </h2>

    <!-- Search & Filter -->
    <div class="search-bar">
      <form method="GET" action="">
        <label for="status">Status:</label>
        <select name="status" id="status">
          <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
          <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
          <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
          <option value="taken" <?= $status_filter === 'taken' ? 'selected' : '' ?>>Taken</option>
          <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
        <label for="contact_filter">Phone:</label>
        <select name="contact_filter" id="contact_filter">
          <option value="all" <?= $contact_filter === 'all' ? 'selected' : '' ?>>All</option>
          <option value="with_number" <?= $contact_filter === 'with_number' ? 'selected' : '' ?>>With Number</option>
          <option value="missing_number" <?= $contact_filter === 'missing_number' ? 'selected' : '' ?>>Missing Number</option>
        </select>
        <input type="text" name="search" placeholder="Search by title, landlord name, or number..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit"><i class="fas fa-search"></i> Search</button>
      </form>
    </div>

    <div class="contacts-panel">
      <h3><i class="fas fa-address-book"></i> Landlord Contacts (Current Results)</h3>
      <?php if (!empty($landlordContacts)): ?>
        <div class="contact-chips">
          <?php foreach ($landlordContacts as $contactRow): ?>
            <span class="contact-chip">
              <strong><?= htmlspecialchars($contactRow['name']) ?></strong>
              <a href="tel:<?= htmlspecialchars($contactRow['contact']) ?>"><?= htmlspecialchars($contactRow['contact']) ?></a>
            </span>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p style="color: var(--text-light);">No landlord numbers in the current filtered results.</p>
      <?php endif; ?>
    </div>

    <!-- Table -->
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Landlord</th>
            <th>Landlord Number</th>
            <th>Property</th>
            <th>Price</th>
            <th>Status</th>
            <th>Images</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($result)): ?>
            <?php foreach ($result as $row): ?>
            <tr>
              <td data-label="ID"><?= $row['id'] ?></td>
              <td data-label="Landlord">
                <strong><?= htmlspecialchars($row['landlord_name']) ?></strong>
              </td>
              <td data-label="Landlord Number">
                <?= htmlspecialchars((string) ($row['contact'] ?? 'N/A')) ?>
              </td>
              <td data-label="Property">
                <strong><?= htmlspecialchars($row['title']) ?></strong><br>
                <small style="color: var(--text-light);"><?= htmlspecialchars(substr($row['description'], 0, 50)) ?>...</small>
              </td>
              <td data-label="Price"><strong>$<?= number_format($row['price'], 2) ?></strong></td>
              <td data-label="Status">
                <span class="badge badge-<?= $row['status'] ?>">
                  <?= ucfirst($row['status']) ?>
                </span>
              </td>
              <td data-label="Images">
                <div class="img-thumbnails">
                  <?php
                  $img_result = rc_mig_get_property_image_ids($conn, (int) $row['id']);
                  $img_count = 0;
                  foreach ($img_result as $img):
                    $img_count++;
                  ?>
                    <img src="display_image.php?img_id=<?= $img['id'] ?>" class="img-thumb" onclick="openLightbox(event, 'display_image.php?img_id=<?= $img['id'] ?>')" alt="Property image">
                  <?php endforeach; ?>
                  <?php if ($img_count === 0): ?>
                    <span style="color: var(--text-light); font-size: 0.9rem;">No images</span>
                  <?php endif; ?>
                </div>
              </td>
              <td data-label="Created"><?= format_date($row['created_at']) ?></td>
              <td data-label="Actions">
                <div class="actions">
                  <?php if ($row['status'] === 'pending'): ?>
                    <a href="?action=approve&id=<?= $row['id'] ?>" class="btn btn-approve" title="Approve this property">
                      <i class="fas fa-check"></i> Approve
                    </a>
                  <?php endif; ?>
                  <?php if ($row['status'] !== 'inactive'): ?>
                    <a href="?action=hide&id=<?= $row['id'] ?>" class="btn btn-hide" title="Hide this property">
                      <i class="fas fa-eye-slash"></i> Hide
                    </a>
                  <?php endif; ?>
                  <a href="?action=delete&id=<?= $row['id'] ?>" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this property and all its images?')" title="Delete this property">
                    <i class="fas fa-trash"></i> Delete
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="9">
                <div class="empty-state">
                  <i class="fas fa-search"></i>
                  <p>No properties found matching your criteria.</p>
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="?status=<?= $status_filter ?>&contact_filter=<?= urlencode($contact_filter) ?>&search=<?= urlencode($search) ?>&page=<?= $page - 1 ?>" title="Previous page">
          <i class="fas fa-chevron-left"></i> Prev
        </a>
      <?php endif; ?>
      
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?status=<?= $status_filter ?>&contact_filter=<?= urlencode($contact_filter) ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>" 
           class="<?= $i === $page ? 'active' : '' ?>" 
           title="Go to page <?= $i ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>
      
      <?php if ($page < $total_pages): ?>
        <a href="?status=<?= $status_filter ?>&contact_filter=<?= urlencode($contact_filter) ?>&search=<?= urlencode($search) ?>&page=<?= $page + 1 ?>" title="Next page">
          Next <i class="fas fa-chevron-right"></i>
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Lightbox Modal -->
<div id="lightbox" onclick="closeLightbox()">
    <span class="lightbox-close" onclick="closeLightbox()"><i class="fas fa-times"></i></span>
    <img id="lightbox-img" src="" alt="Property image" onclick="event.stopPropagation()">
</div>

<footer>
  <div class="container">
    <p>&copy; <?php echo date("Y"); ?> RentConnect Liberia | All rights reserved. | <a href="services.php">Our Services</a></p>
  </div>
</footer>

<script>
function openLightbox(event, src) {
    event.stopPropagation();
    document.getElementById('lightbox').classList.add('active');
    document.getElementById('lightbox-img').src = src;
}

function closeLightbox() {
    document.getElementById('lightbox').classList.remove('active');
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeLightbox();
});
</script>

</body>
</html>
