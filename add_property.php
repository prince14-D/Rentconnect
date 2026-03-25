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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $location = $_POST['location'];
    $price = $_POST['price'];
    $contact = $_POST['contact'];
    $bedrooms = $_POST['bedrooms'] ?? 0;
    $bathrooms = $_POST['bathrooms'] ?? 0;
    $description = $_POST['description'] ?? "";

    // Insert property as 'pending'
    $stmt = $conn->prepare("
        INSERT INTO properties
        (landlord_id, title, location, price, contact, bedrooms, bathrooms, description, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->bind_param(
        "issdsdds",
        $landlord_id,
        $title,
        $location,
        $price,
        $contact,
        $bedrooms,
        $bathrooms,
        $description
    );

    if ($stmt->execute()) {
        $property_id = $stmt->insert_id;

        // Handle Multiple Image Uploads
        if (!empty($_FILES['photos']['name'][0])) {
            $total_files = count($_FILES['photos']['name']);

            for ($i = 0; $i < $total_files; $i++) {
                if ($_FILES['photos']['error'][$i] == 0) {
                    $imageData = file_get_contents($_FILES['photos']['tmp_name'][$i]);
                    $mimeType = mime_content_type($_FILES['photos']['tmp_name'][$i]);

                    $img_stmt = $conn->prepare("INSERT INTO property_images (property_id, image, mime_type) VALUES (?, ?, ?)");

                    $null = null;
                    $img_stmt->bind_param("ibs", $property_id, $null, $mimeType);
                    $img_stmt->send_long_data(1, $imageData);

                    if (!$img_stmt->execute()) {
                        $message .= "Error uploading image: " . $img_stmt->error . "<br>";
                    }
                }
            }
        }

        $message .= "Property uploaded successfully. Please contact admin to approve +231888-272-360.";
    } else {
        $message = "Failed to upload property: " . $stmt->error;
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
          <input id="bedrooms" type="number" name="bedrooms" min="0" value="0">
        </div>

        <div class="field">
          <label for="bathrooms">Bathrooms</label>
          <input id="bathrooms" type="number" name="bathrooms" min="0" value="0">
        </div>

        <div class="field full">
          <label for="description">Description</label>
          <textarea id="description" name="description" placeholder="Describe key features, neighborhood, and amenities."></textarea>
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
