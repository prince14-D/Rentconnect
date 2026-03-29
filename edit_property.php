<?php
session_start();
include "app_init.php";

// Ensure landlord is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = (int) $_SESSION['user_id'];
$message = "";
$prop_id = intval($_GET['id'] ?? 0);

// Fetch existing property
$property = rc_mig_get_property_for_landlord($conn, $prop_id, $landlord_id);

if (!$property) {
    die("Property not found.");
}

$extract_meta = static function (string $description, string $label): string {
    $pattern = '/^\s*' . preg_quote($label, '/') . ':\s*(.+)$/mi';
    if (preg_match($pattern, $description, $matches)) {
        return trim((string) ($matches[1] ?? ''));
    }
    return '';
};

$strip_meta = static function (string $description): string {
    $clean = preg_replace('/^\s*(Address|Purpose|Available From|Amenities):.*$/mi', '', $description) ?? $description;
    $clean = preg_replace("/\n{3,}/", "\n\n", $clean) ?? $clean;
    return trim($clean);
};

$existing_description = (string) ($property['description'] ?? '');
$form = [
    'title' => (string) ($property['title'] ?? ''),
    'location' => (string) ($property['location'] ?? ''),
    'address' => $extract_meta($existing_description, 'Address'),
    'price' => (string) ($property['price'] ?? ''),
    'purpose' => strtolower($extract_meta($existing_description, 'Purpose') ?: 'rent'),
    'contact' => (string) ($property['contact'] ?? ''),
    'bedrooms' => (string) ($property['bedrooms'] ?? '0'),
    'bathrooms' => (string) ($property['bathrooms'] ?? '0'),
    'available_from' => $extract_meta($existing_description, 'Available From'),
    'amenities' => $extract_meta($existing_description, 'Amenities'),
    'description' => $strip_meta($existing_description),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $value) {
        if (isset($_POST[$key])) {
            $form[$key] = trim((string) $_POST[$key]);
        }
    }
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title = $form['title'];
  $location = $form['location'];
  $address = $form['address'];
  $price = (float) ($form['price'] !== '' ? $form['price'] : 0);
  $purpose = strtolower($form['purpose']);
  $contact = $form['contact'];
  $bedrooms = (int) ($form['bedrooms'] !== '' ? $form['bedrooms'] : 0);
  $bathrooms = (int) ($form['bathrooms'] !== '' ? $form['bathrooms'] : 0);
  $available_from = $form['available_from'];
  $amenities = $form['amenities'];
  $description = $form['description'];

  if ($title === '' || $location === '' || $price <= 0 || $contact === '') {
    $message = 'Title, location, landlord number, and price are required.';
  } elseif (!preg_match('/^[0-9+()\-\s]{7,20}$/', $contact)) {
    $message = 'Please provide a valid landlord phone number.';
  }

  $extra_details = [];
  if ($address !== '') {
    $extra_details[] = 'Address: ' . $address;
  }
  if ($purpose !== '') {
    $extra_details[] = 'Purpose: ' . ucfirst($purpose);
  }
  if ($available_from !== '') {
    $extra_details[] = 'Available From: ' . $available_from;
  }
  if ($amenities !== '') {
    $extra_details[] = 'Amenities: ' . $amenities;
  }
  $full_description = trim($description);
  if (!empty($extra_details)) {
    $full_description = trim($full_description . "\n\n" . implode("\n", $extra_details));
  }

  $updated = ['ok' => false, 'error' => 'Validation failed'];
  if ($message === '') {
    $updated = rc_mig_update_property_for_landlord($conn, $prop_id, $landlord_id, [
      'title' => $title,
      'location' => $location,
      'price' => $price,
      'contact' => $contact,
      'bedrooms' => $bedrooms,
      'bathrooms' => $bathrooms,
      'description' => $full_description,
    ]);
  }

  if ($message !== '') {
    // Keep validation message.
  } elseif (!empty($updated['ok'])) {
    $failedImages = 0;
        // Handle new image uploads
        if (!empty($_FILES['photos']['name'][0])) {
            $total_files = count($_FILES['photos']['name']);
            for ($i = 0; $i < $total_files; $i++) {
        if (($_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === 0) {
          $tmpPath = (string) ($_FILES['photos']['tmp_name'][$i] ?? '');
          $imageData = $tmpPath !== '' ? file_get_contents($tmpPath) : false;
          $mimeType = $tmpPath !== '' ? (string) mime_content_type($tmpPath) : '';

          if (!is_string($imageData) || $imageData === '' || $mimeType === '' || !rc_mig_add_property_image($conn, $prop_id, $imageData, $mimeType)) {
            $failedImages++;
          }
                }
            }
        }

    $message = $failedImages > 0
      ? "Property updated, but {$failedImages} image(s) failed to upload."
      : "Property updated successfully.";
    } else {
    $message = "Update failed: " . htmlspecialchars((string) ($updated['error'] ?? 'unknown error'));
    }

  $property = rc_mig_get_property_for_landlord($conn, $prop_id, $landlord_id);
}

// Fetch property images
$images = rc_mig_get_property_image_ids($conn, $prop_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="apple-touch-icon" href="/favicon.svg" />
<link rel="icon" href="/favicon.svg" />
<title>Edit Property - RentConnect</title>
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

.container { width: min(980px, 94vw); margin: 0 auto; }

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

.carousel {
  position: relative;
  width: 100%;
  height: 250px;
  border-radius: 12px;
  overflow: hidden;
  background: #ecf1f5;
  margin-bottom: 12px;
}
.carousel img {
  width: 100%;
  height: 250px;
  object-fit: cover;
  display: none;
}
.carousel img.active { display: block; }
.carousel button {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  border: none;
  width: 32px;
  height: 32px;
  border-radius: 999px;
  background: rgba(0, 0, 0, 0.48);
  color: #fff;
  cursor: pointer;
}
.carousel .prev { left: 10px; }
.carousel .next { right: 10px; }

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

.preview-container {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 10px;
  margin-top: 8px;
}
.preview-item {
  border: 1px solid var(--line);
  border-radius: 10px;
  overflow: hidden;
  background: #fff;
}
.preview-item img {
  width: 100%;
  height: 96px;
  object-fit: cover;
  display: block;
}
.remove-btn {
  width: 100%;
  border: none;
  border-top: 1px solid var(--line);
  background: #f9eceb;
  color: #b53a34;
  padding: 6px;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
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
    <h1>Edit Property</h1>
    <p>Update your listing details and add fresh photos anytime.</p>
  </section>

  <section class="card">
    <?php if ($message): ?>
      <p class="alert"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <?php if (count($images) > 0): ?>
      <div class="carousel" id="carousel-<?php echo $prop_id; ?>">
        <?php foreach ($images as $row): ?>
          <img src="display_image.php?img_id=<?php echo $row['id']; ?>" alt="Property Image">
        <?php endforeach; ?>
        <button type="button" class="prev">&#10094;</button>
        <button type="button" class="next">&#10095;</button>
      </div>
    <?php else: ?>
      <p style="margin-bottom: 10px; color: var(--muted);">No images uploaded yet.</p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <div class="form-grid">
        <div class="field">
          <label for="title">Property Title</label>
          <input id="title" type="text" name="title" value="<?php echo htmlspecialchars($form['title']); ?>" required>
        </div>

        <div class="field">
          <label for="location">Location</label>
          <input id="location" type="text" name="location" value="<?php echo htmlspecialchars($form['location']); ?>" required>
        </div>

        <div class="field">
          <label for="address">Full Address</label>
          <input id="address" type="text" name="address" placeholder="Street / community / nearby landmark" value="<?php echo htmlspecialchars($form['address']); ?>">
        </div>

        <div class="field">
          <label for="price">Price (USD)</label>
          <input id="price" type="number" name="price" step="0.01" min="1" value="<?php echo htmlspecialchars($form['price']); ?>" required>
        </div>

        <div class="field">
          <label for="purpose">Listing Purpose</label>
          <input id="purpose" type="text" name="purpose" placeholder="Rent / Lease" value="<?php echo htmlspecialchars($form['purpose']); ?>">
        </div>

        <div class="field">
          <label for="contact">Landlord Phone Number</label>
          <input id="contact" type="text" name="contact" value="<?php echo htmlspecialchars($form['contact']); ?>" required>
        </div>

        <div class="field">
          <label for="bedrooms">Bedrooms</label>
          <input id="bedrooms" type="number" name="bedrooms" min="0" value="<?php echo htmlspecialchars($form['bedrooms']); ?>">
        </div>

        <div class="field">
          <label for="bathrooms">Bathrooms</label>
          <input id="bathrooms" type="number" name="bathrooms" min="0" value="<?php echo htmlspecialchars($form['bathrooms']); ?>">
        </div>

        <div class="field">
          <label for="available_from">Available From</label>
          <input id="available_from" type="date" name="available_from" value="<?php echo htmlspecialchars($form['available_from']); ?>">
        </div>

        <div class="field">
          <label for="amenities">Amenities (comma separated)</label>
          <input id="amenities" type="text" name="amenities" placeholder="Parking, Security, Generator" value="<?php echo htmlspecialchars($form['amenities']); ?>">
        </div>

        <div class="field full">
          <label for="description">Description</label>
          <textarea id="description" name="description"><?php echo htmlspecialchars($form['description']); ?></textarea>
        </div>

        <div class="field full">
          <label for="photos">Upload New Photos (Optional)</label>
          <input type="file" id="photos" name="photos[]" multiple accept="image/*">
          <div id="preview" class="preview-container"></div>
        </div>
      </div>

      <div class="actions">
        <button type="submit">Update Property</button>
        <a href="landlord_dashboard.php">Back to Dashboard</a>
      </div>
    </form>
  </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const carousel = document.getElementById('carousel-<?php echo $prop_id; ?>');
  if (carousel) {
    const imgs = carousel.querySelectorAll('img');
    let index = 0;

    if (imgs.length > 0) {
      imgs[0].classList.add('active');
    }

    if (imgs.length > 1) {
      setInterval(() => {
        imgs[index].classList.remove('active');
        index = (index + 1) % imgs.length;
        imgs[index].classList.add('active');
      }, 3200);

      carousel.querySelector('.prev').addEventListener('click', () => {
        imgs[index].classList.remove('active');
        index = (index - 1 + imgs.length) % imgs.length;
        imgs[index].classList.add('active');
      });

      carousel.querySelector('.next').addEventListener('click', () => {
        imgs[index].classList.remove('active');
        index = (index + 1) % imgs.length;
        imgs[index].classList.add('active');
      });
    }
  }

  const input = document.getElementById('photos');
  const preview = document.getElementById('preview');

  input.addEventListener('change', () => {
    preview.innerHTML = '';

    Array.from(input.files).forEach((file, index) => {
      const reader = new FileReader();
      reader.onload = (event) => {
        const card = document.createElement('div');
        card.className = 'preview-item';
        card.innerHTML = `
          <img src="${event.target.result}" alt="Preview image">
          <button type="button" class="remove-btn" data-index="${index}">Remove</button>
        `;
        preview.appendChild(card);
      };
      reader.readAsDataURL(file);
    });
  });

  preview.addEventListener('click', (event) => {
    if (!event.target.classList.contains('remove-btn')) {
      return;
    }

    const removeIndex = parseInt(event.target.getAttribute('data-index'), 10);
    const dt = new DataTransfer();

    Array.from(input.files).forEach((file, idx) => {
      if (idx !== removeIndex) {
        dt.items.add(file);
      }
    });

    input.files = dt.files;
    input.dispatchEvent(new Event('change'));
  });
});
</script>
</body>
</html>
