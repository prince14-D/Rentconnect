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

$landlord_profile = rc_mig_get_user_profile($conn, $landlord_id) ?: [];
$default_phone = trim((string) ($landlord_profile['phone'] ?? ''));

$form = [
  'title' => '',
  'location' => '',
  'address' => '',
  'price' => '',
  'purpose' => 'rent',
  'contact' => $default_phone,
  'bedrooms' => '0',
  'bathrooms' => '0',
  'available_from' => '',
  'amenities' => '',
  'description' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  foreach ($form as $key => $value) {
    if (isset($_POST[$key])) {
      $form[$key] = trim((string) $_POST[$key]);
    }
  }
}

// Handle form submission
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

  $created = ['ok' => false];
  if ($message === '') {
    $created = rc_mig_create_property($conn, $landlord_id, [
      'title' => $title,
      'location' => $location,
      'price' => $price,
      'contact' => $contact,
      'bedrooms' => $bedrooms,
      'bathrooms' => $bathrooms,
      'description' => $full_description,
      'status' => 'pending',
    ]);
  }

  if ($message !== '') {
    // Keep validation message.
  } elseif (!empty($created['ok'])) {
        $property_id = (int) ($created['id'] ?? 0);

        if (!empty($_FILES['photos']['name'][0])) {
            $total_files = count($_FILES['photos']['name']);
            for ($i = 0; $i < $total_files; $i++) {
                if (($_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === 0) {
                    $tmpPath = (string) ($_FILES['photos']['tmp_name'][$i] ?? '');
                    $imageData = $tmpPath !== '' ? file_get_contents($tmpPath) : false;
                    $mimeType = $tmpPath !== '' ? (string) mime_content_type($tmpPath) : '';

                    if (!is_string($imageData) || $imageData === '' || $mimeType === '' || !rc_mig_add_property_image($conn, $property_id, $imageData, $mimeType)) {
                        $message .= "Error uploading one of the images.<br>";
                    }
                }
            }
        }

        $message .= "Property uploaded successfully. Please contact admin to approve +231888-272-360.";
    } else {
        $message = "Failed to upload property: " . htmlspecialchars((string) ($created['error'] ?? 'unknown error'));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Add a new property listing to RentConnect.">
<meta name="theme-color" content="#1f8f67">
<link rel="apple-touch-icon" href="/favicon.svg" />
<link rel="icon" href="/favicon.svg" />
<link rel="manifest" href="/manifest.json">
<title>Add Property - RentConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
<style>
:root {
  --ink: #1f2430;
  --muted: #5d6579;
  --brand: #1f8f67;
  --brand-deep: #15543e;
  --accent: #ff7a2f;
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

.form-card {
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

.actions {
  display: flex;
  gap: 9px;
  flex-wrap: wrap;
  margin-top: 6px;
}

button {
  border: none;
  border-radius: 10px;
  padding: 11px 14px;
  font-weight: 700;
  color: #fff;
  background: linear-gradient(140deg, var(--brand), var(--brand-deep));
  cursor: pointer;
}

.back {
  display: inline-block;
  text-decoration: none;
  border-radius: 10px;
  padding: 11px 14px;
  font-weight: 700;
  color: #fff;
  background: linear-gradient(140deg, #2276d2, #1b5aa8);
}

.upload-hint {
  font-size: 0.85rem;
  color: var(--muted);
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

@media (max-width: 760px) {
  .form-grid { grid-template-columns: 1fr; }
  .actions button,
  .actions .back { width: 100%; text-align: center; }
}
</style>
</head>
<body>
<main class="container">
  <section class="hero">
    <h1>Add Property</h1>
    <p>Create a new listing. All new uploads are submitted for admin approval first.</p>
  </section>

  <section class="form-card">
    <?php if ($message): ?>
      <p class="alert"><?php echo $message; ?></p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <div class="form-grid">
        <div class="field">
          <label for="title">Property Title</label>
          <input id="title" type="text" name="title" required value="<?php echo htmlspecialchars($form['title']); ?>">
        </div>

        <div class="field">
          <label for="location">Location</label>
          <input id="location" type="text" name="location" required value="<?php echo htmlspecialchars($form['location']); ?>">
        </div>

        <div class="field">
          <label for="address">Full Address</label>
          <input id="address" type="text" name="address" placeholder="Street / community / nearby landmark" value="<?php echo htmlspecialchars($form['address']); ?>">
        </div>

        <div class="field">
          <label for="price">Price (USD)</label>
          <input id="price" type="number" step="0.01" name="price" min="1" required value="<?php echo htmlspecialchars($form['price']); ?>">
        </div>

        <div class="field">
          <label for="purpose">Listing Purpose</label>
          <input id="purpose" type="text" name="purpose" placeholder="Rent / Lease" value="<?php echo htmlspecialchars($form['purpose']); ?>">
        </div>

        <div class="field">
          <label for="contact">Landlord Phone Number</label>
          <input id="contact" type="text" name="contact" placeholder="e.g. +231775637587" required value="<?php echo htmlspecialchars($form['contact']); ?>">
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
          <textarea id="description" name="description" placeholder="Describe key features, neighborhood, and amenities."><?php echo htmlspecialchars($form['description']); ?></textarea>
        </div>

        <div class="field full">
          <label for="photos">Property Photos</label>
          <input type="file" id="photos" name="photos[]" multiple accept="image/*">
          <p class="upload-hint">Upload multiple images. You can remove previews before submitting.</p>
          <div id="preview" class="preview-container"></div>
        </div>
      </div>

      <div class="actions">
        <button type="submit">Submit Property</button>
        <a href="landlord_dashboard.php" class="back">Back to Dashboard</a>
      </div>
    </form>
  </section>
</main>

<script>
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
</script>
</body>
</html>
