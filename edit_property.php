<?php
session_start();
include "db.php";

// Ensure landlord is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];
$message = "";
$prop_id = intval($_GET['id'] ?? 0);

// Fetch existing property
$stmt = $conn->prepare("SELECT * FROM properties WHERE id=? AND (owner_id=? OR landlord_id=?)");
$stmt->bind_param("iii", $prop_id, $landlord_id, $landlord_id);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();

if (!$property) {
    die("Property not found.");
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $location = $_POST['location'];
    $price = $_POST['price'];
    $contact = $_POST['contact'];
    $bedrooms = $_POST['bedrooms'];
    $bathrooms = $_POST['bathrooms'];
    $description = $_POST['description'];

    // Update property info
    $stmt = $conn->prepare("UPDATE properties
        SET title=?, location=?, price=?, contact=?, bedrooms=?, bathrooms=?, description=?
        WHERE id=? AND (owner_id=? OR landlord_id=?)");
    $stmt->bind_param(
        "ssdsiisiii",
        $title,
        $location,
        $price,
        $contact,
        $bedrooms,
        $bathrooms,
        $description,
        $prop_id,
        $landlord_id,
        $landlord_id
    );

    if ($stmt->execute()) {
        // Handle new image uploads
        if (!empty($_FILES['photos']['name'][0])) {
            $total_files = count($_FILES['photos']['name']);
            for ($i = 0; $i < $total_files; $i++) {
                if ($_FILES['photos']['error'][$i] === 0) {
                    $imageData = file_get_contents($_FILES['photos']['tmp_name'][$i]);
                    $mimeType = mime_content_type($_FILES['photos']['tmp_name'][$i]);

                    $img_stmt = $conn->prepare("INSERT INTO property_images (property_id, image, mime_type) VALUES (?, ?, ?)");
                    $null = null;
                    $img_stmt->bind_param("ibs", $prop_id, $null, $mimeType);
                    $img_stmt->send_long_data(1, $imageData);
                    $img_stmt->execute();
                }
            }
        }
        $message = "Property updated successfully.";
    } else {
        $message = "Database error: " . $stmt->error;
    }
}

// Fetch property images
$img_stmt = $conn->prepare("SELECT id FROM property_images WHERE property_id=?");
$img_stmt->bind_param("i", $prop_id);
$img_stmt->execute();
$images = $img_stmt->get_result();
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

    <?php if ($images->num_rows > 0): ?>
      <div class="carousel" id="carousel-<?php echo $prop_id; ?>">
        <?php while ($row = $images->fetch_assoc()): ?>
          <img src="display_image.php?img_id=<?php echo $row['id']; ?>" alt="Property Image">
        <?php endwhile; ?>
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
          <input id="title" type="text" name="title" value="<?php echo htmlspecialchars($property['title']); ?>" required>
        </div>

        <div class="field">
          <label for="location">Location</label>
          <input id="location" type="text" name="location" value="<?php echo htmlspecialchars($property['location']); ?>" required>
        </div>

        <div class="field">
          <label for="price">Price (USD)</label>
          <input id="price" type="number" name="price" step="0.01" value="<?php echo htmlspecialchars((string) $property['price']); ?>" required>
        </div>

        <div class="field">
          <label for="contact">Contact</label>
          <input id="contact" type="text" name="contact" value="<?php echo htmlspecialchars($property['contact']); ?>" required>
        </div>

        <div class="field">
          <label for="bedrooms">Bedrooms</label>
          <input id="bedrooms" type="number" name="bedrooms" min="0" value="<?php echo htmlspecialchars((string) $property['bedrooms']); ?>">
        </div>

        <div class="field">
          <label for="bathrooms">Bathrooms</label>
          <input id="bathrooms" type="number" name="bathrooms" min="0" value="<?php echo htmlspecialchars((string) $property['bathrooms']); ?>">
        </div>

        <div class="field full">
          <label for="description">Description</label>
          <textarea id="description" name="description"><?php echo htmlspecialchars($property['description']); ?></textarea>
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
