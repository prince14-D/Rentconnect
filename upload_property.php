<?php
session_start();
include "app_init.php";

// Check if landlord is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = (int) $_SESSION['user_id'];
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $location = trim((string) ($_POST['location'] ?? ''));
    $price = (float) ($_POST['price'] ?? 0);
    $contact = trim((string) ($_POST['contact'] ?? ''));
    $bedrooms = (int) ($_POST['bedrooms'] ?? 0);
    $bathrooms = (int) ($_POST['bathrooms'] ?? 0);
    $description = trim((string) ($_POST['description'] ?? ''));

    if (isset($_FILES['photo']) && (int) ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === 0) {
        $tmpPath = (string) ($_FILES['photo']['tmp_name'] ?? '');
        $imageData = $tmpPath !== '' ? file_get_contents($tmpPath) : false;
        $mimeType = $tmpPath !== '' ? (string) mime_content_type($tmpPath) : '';

        if (!is_string($imageData) || $imageData === '' || $mimeType === '') {
            $message = 'Failed to read uploaded photo.';
      } else {
            $created = rc_mig_create_property($conn, $landlord_id, [
                'title' => $title,
                'location' => $location,
                'price' => $price,
                'contact' => $contact,
                'bedrooms' => $bedrooms,
                'bathrooms' => $bathrooms,
                'description' => $description,
                'status' => 'pending',
            ]);

            if (!empty($created['ok'])) {
                $propertyId = (int) ($created['id'] ?? 0);
          if ($propertyId > 0 && rc_mig_add_property_image($conn, $propertyId, $imageData, $mimeType)) {
            $message = 'Property uploaded successfully.';
          } else {
            $message = 'Property created, but image storage failed.';
                }
            } else {
                $message = 'Failed to upload property: ' . htmlspecialchars((string) ($created['error'] ?? 'unknown error'));
            }
        }
    } else {
        $message = 'Please select a photo.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="apple-touch-icon" href="/favicon.svg" />
<link rel="icon" href="/favicon.svg" />
<title>Upload Property - RentConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
<style>
:root {
  --ink: #1f2430;
  --muted: #5d6579;
  --brand: #1f8f67;
  --brand-deep: #15543e;
  --line: rgba(31, 36, 48, 0.12);
  --shadow: 0 18px 36px rgba(19, 36, 33, 0.14);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Manrope', sans-serif;
  color: var(--ink);
  background:
    radial-gradient(circle at 10% 4%, rgba(255, 122, 47, 0.22), transparent 34%),
    radial-gradient(circle at 92% 8%, rgba(31, 143, 103, 0.2), transparent 30%),
    linear-gradient(165deg, #f9f6ef 0%, #f2f7f8 58%, #fffdfa 100%);
  min-height: 100vh;
}

.container { width: min(960px, 94vw); margin: 0 auto; }

.hero {
  margin-top: 24px;
  border-radius: 20px;
  color: #fff;
  padding: clamp(18px, 3.5vw, 30px);
  background: linear-gradient(140deg, rgba(16, 62, 79, 0.93), rgba(31, 143, 103, 0.86));
  box-shadow: var(--shadow);
}
.hero h1 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: clamp(1.45rem, 3vw, 2.1rem);
  letter-spacing: -0.02em;
  margin-bottom: 6px;
}
.hero p { color: rgba(255, 255, 255, 0.92); }

.card {
  margin-top: 14px;
  background: rgba(255, 255, 255, 0.94);
  border: 1px solid rgba(255, 255, 255, 0.9);
  border-radius: 16px;
  box-shadow: 0 10px 24px rgba(15, 31, 40, 0.09);
  padding: 16px;
}

.alert {
  border-radius: 10px;
  padding: 11px 12px;
  margin-bottom: 10px;
  background: rgba(39, 165, 106, 0.12);
  border: 1px solid rgba(39, 165, 106, 0.3);
  color: #15543e;
  font-weight: 700;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.field { display: grid; gap: 6px; }
.field.full { grid-column: 1 / -1; }

label {
  font-size: 0.9rem;
  color: #445069;
  font-weight: 700;
}

input,
textarea {
  width: 100%;
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 11px 12px;
  font: inherit;
  background: #fff;
}

input:focus,
textarea:focus {
  outline: none;
  border-color: rgba(31, 143, 103, 0.55);
  box-shadow: 0 0 0 3px rgba(31, 143, 103, 0.13);
}

textarea {
  min-height: 112px;
  resize: vertical;
}

.preview {
  margin-top: 8px;
  border: 1px solid var(--line);
  border-radius: 12px;
  overflow: hidden;
  width: min(280px, 100%);
  background: #fff;
  display: none;
}
.preview img {
  width: 100%;
  height: 180px;
  object-fit: cover;
  display: block;
}

.actions {
  display: flex;
  gap: 9px;
  flex-wrap: wrap;
  margin-top: 8px;
}
.actions button,
.actions a {
  text-decoration: none;
  border: none;
  border-radius: 10px;
  padding: 11px 14px;
  font-weight: 700;
  color: #fff;
}
.actions button {
  background: linear-gradient(140deg, var(--brand), var(--brand-deep));
  cursor: pointer;
}
.actions a {
  background: linear-gradient(140deg, #2276d2, #1b5aa8);
}

@media (max-width: 760px) {
  .form-grid { grid-template-columns: 1fr; }
  .actions button,
  .actions a { width: 100%; text-align: center; }
}
</style>
</head>
<body>
<main class="container">
  <section class="hero">
    <h1>Upload New Property</h1>
    <p>This flow uploads one featured image and stores it through Supabase-backed image storage.</p>
  </section>

  <section class="card">
    <?php if ($message): ?>
      <p class="alert"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <div class="form-grid">
        <div class="field">
          <label for="title">Property Title</label>
          <input id="title" type="text" name="title" required>
        </div>

        <div class="field">
          <label for="location">Location</label>
          <input id="location" type="text" name="location" required>
        </div>

        <div class="field">
          <label for="price">Price (USD)</label>
          <input id="price" type="number" step="0.01" name="price" required>
        </div>

        <div class="field">
          <label for="contact">Contact Info</label>
          <input id="contact" type="text" name="contact" required>
        </div>

        <div class="field">
          <label for="bedrooms">Bedrooms</label>
          <input id="bedrooms" type="number" name="bedrooms" min="0" required>
        </div>

        <div class="field">
          <label for="bathrooms">Bathrooms</label>
          <input id="bathrooms" type="number" name="bathrooms" min="0" required>
        </div>

        <div class="field full">
          <label for="description">Description</label>
          <textarea id="description" name="description" placeholder="Property description"></textarea>
        </div>

        <div class="field full">
          <label for="photo">Featured Photo</label>
          <input type="file" id="photo" name="photo" accept="image/*" required>
          <div class="preview" id="photoPreview">
            <img id="previewImage" src="" alt="Selected image preview">
          </div>
        </div>
      </div>

      <div class="actions">
        <button type="submit">Upload Property</button>
        <a href="landlord_dashboard.php">Back to Dashboard</a>
      </div>
    </form>
  </section>
</main>

<script>
const photoInput = document.getElementById('photo');
const previewBox = document.getElementById('photoPreview');
const previewImage = document.getElementById('previewImage');

photoInput.addEventListener('change', () => {
  const file = photoInput.files[0];
  if (!file) {
    previewImage.src = '';
    previewBox.style.display = 'none';
    return;
  }

  const reader = new FileReader();
  reader.onload = (event) => {
    previewImage.src = event.target.result;
    previewBox.style.display = 'block';
  };
  reader.readAsDataURL(file);
});
</script>
</body>
</html>
