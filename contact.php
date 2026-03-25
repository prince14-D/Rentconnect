<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Get in touch with the RentConnect team.">
<meta name="theme-color" content="#1f8f67">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="manifest" href="/manifest.json">
<title>Contact Us - RentConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
}
.container { width: min(1120px, 94vw); margin: 0 auto; }

header {
  position: sticky;
  top: 0;
  z-index: 1000;
  backdrop-filter: blur(10px);
  background: rgba(255, 255, 255, 0.83);
  border-bottom: 1px solid var(--line);
}
.topbar {
  min-height: 72px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.brand {
  text-decoration: none;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 1.3rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: #12384b;
}
.menu-toggle {
  display: none;
  border: 1px solid var(--line);
  background: #fff;
  border-radius: 9px;
  width: 40px;
  height: 40px;
  cursor: pointer;
}
nav { display: flex; align-items: center; gap: 8px; }
nav a, .dropbtn {
  text-decoration: none;
  color: #374258;
  font-weight: 700;
  padding: 9px 12px;
  border-radius: 9px;
  border: none;
  background: transparent;
  cursor: pointer;
}
nav a:hover, .dropbtn:hover { background: rgba(31, 143, 103, 0.08); color: var(--brand-deep); }
.upload-btn { background: linear-gradient(140deg, var(--brand), var(--brand-deep)); color: #fff; }

.dropdown { position: relative; }
.dropdown-content {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  min-width: 160px;
  display: none;
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 12px;
  box-shadow: var(--shadow);
  padding: 6px;
}
.dropdown-content a { display: block; padding: 10px 11px; border-radius: 8px; }
.dropdown-content a:hover { background: rgba(31, 143, 103, 0.1); }
.dropdown.active .dropdown-content { display: block; }

.hero {
  margin-top: 24px;
  border-radius: 20px;
  overflow: hidden;
  min-height: 38vh;
  display: grid;
  place-items: center;
  text-align: center;
  color: #fff;
  background:
    linear-gradient(130deg, rgba(11, 31, 49, 0.74), rgba(17, 88, 64, 0.64)),
    url('images/home-banner-2.png') center/cover no-repeat;
  box-shadow: var(--shadow);
}
.hero h1 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: clamp(1.8rem, 4vw, 2.8rem);
  letter-spacing: -0.03em;
  margin-bottom: 10px;
}
.hero p { font-size: clamp(0.95rem, 1.6vw, 1.15rem); max-width: 58ch; color: rgba(255,255,255,0.92); }

.contact-grid {
  margin-top: 18px;
  display: grid;
  grid-template-columns: 1fr 1.1fr;
  gap: 14px;
}
.card {
  background: rgba(255, 255, 255, 0.93);
  border: 1px solid rgba(255,255,255,0.9);
  border-radius: 16px;
  box-shadow: 0 10px 24px rgba(15, 31, 40, 0.09);
  padding: 18px;
}
.card h2 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 1.2rem;
  margin-bottom: 8px;
  color: #103347;
}
.card p { color: var(--muted); line-height: 1.58; margin: 5px 0; }
.card a { color: var(--brand-deep); text-decoration: none; font-weight: 700; }
.social { margin: 10px 0 12px; display: flex; gap: 10px; }
.social a {
  width: 34px;
  height: 34px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  background: rgba(31, 143, 103, 0.12);
}

.map {
  width: 100%;
  height: 250px;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--line);
}

form { display: grid; gap: 10px; }
input, textarea {
  width: 100%;
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 11px 12px;
  font: inherit;
}
textarea { min-height: 140px; resize: vertical; }
button {
  border: none;
  border-radius: 10px;
  padding: 11px 13px;
  color: #fff;
  font-weight: 700;
  background: linear-gradient(140deg, var(--brand), var(--brand-deep));
  cursor: pointer;
}

footer {
  margin-top: 32px;
  border-top: 1px solid var(--line);
  text-align: center;
  color: #5a6476;
  padding: 22px 0 30px;
}
footer a { color: var(--brand-deep); text-decoration: none; font-weight: 700; }

@media (max-width: 900px) {
  .contact-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
  .menu-toggle { display: inline-grid; place-items: center; }
  nav {
    display: none;
    flex-direction: column;
    align-items: stretch;
    position: absolute;
    top: 72px;
    right: 3vw;
    width: min(280px, 92vw);
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 14px;
    box-shadow: var(--shadow);
    padding: 10px;
  }
  nav.active { display: flex; }
  .dropdown-content { position: static; border: none; box-shadow: none; padding: 4px 0 0; }
}
</style>
</head>
<body>
<header>
  <div class="container topbar">
    <a href="index.php" class="brand">RentConnect</a>
    <button class="menu-toggle" onclick="toggleMenu()" aria-label="Toggle menu">☰</button>
    <nav id="navMenu">
      <a href="about.php">About</a>
      <a href="services.php">Services</a>
      <a href="contact.php">Contact</a>
      <div class="dropdown">
        <button class="dropbtn" onclick="toggleDropdown(event)">Account</button>
        <div class="dropdown-content">
          <a href="login.php">Login</a>
          <a href="signup.php">Sign Up</a>
        </div>
      </div>
      <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'landlord'): ?>
        <a href="upload_property.php" class="upload-btn">Upload Property</a>
      <?php else: ?>
        <a href="login.php" class="upload-btn">Upload Property</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main class="container">
  <section class="hero">
    <div>
      <h1>Contact Us</h1>
      <p>Questions, feedback, or support needs? Reach out and our team will respond quickly.</p>
    </div>
  </section>

  <section class="contact-grid">
    <article class="card">
      <h2>Our Office</h2>
      <p>Monrovia, Liberia</p>
      <p><strong>Email:</strong> <a href="mailto:support@rentconnect.com">support@rentconnect.com</a></p>
      <p><strong>Phone:</strong> +231-888-272-360</p>

      <div class="social">
        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
      </div>

      <div class="map">
        <iframe
          src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3972.1587728906827!2d-10.799231785234894!3d6.290743726450797!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xf0e9e5c7ab94a2f%3A0x3a8f39fcbdf4c4df!2sMonrovia%2C%20Liberia!5e0!3m2!1sen!2s!4v1691500000000!5m2!1sen!2s"
          width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"></iframe>
      </div>
    </article>

    <article class="card">
      <h2>Send a Message</h2>
      <form action="send_message.php" method="POST">
        <input type="text" name="name" placeholder="Your Name" required>
        <input type="email" name="email" placeholder="Your Email" required>
        <input type="text" name="subject" placeholder="Subject" required>
        <textarea name="message" placeholder="Your Message" required></textarea>
        <button type="submit">Send Message</button>
      </form>
    </article>
  </section>
</main>

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
  event.target.closest(".dropdown").classList.toggle("active");
}
document.addEventListener("click", (event) => {
  if (!event.target.closest(".dropdown")) {
    document.querySelectorAll(".dropdown").forEach((drop) => drop.classList.remove("active"));
  }
});
</script>

<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/service-worker.js')
      .then((registration) => console.log('[PWA] Service Worker registered'))
      .catch((error) => console.warn('[PWA] Service Worker registration failed:', error));
  });
}
</script>
</body>
</html>
