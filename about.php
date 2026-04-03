<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Learn about RentConnect, the modern rental platform.">
<meta name="theme-color" content="#1f8f67">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="manifest" href="/manifest.json">
<title>About Us - RentConnect</title>
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
  min-height: 42vh;
  display: grid;
  place-items: center;
  text-align: center;
  color: #fff;
  background:
    linear-gradient(130deg, rgba(11, 31, 49, 0.74), rgba(17, 88, 64, 0.64)),
    url('images/home-banner.png') center/cover no-repeat;
  box-shadow: var(--shadow);
}
.hero h1 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: clamp(1.8rem, 4.2vw, 3rem);
  letter-spacing: -0.03em;
  margin-bottom: 10px;
}
.hero p { font-size: clamp(0.95rem, 1.6vw, 1.15rem); max-width: 58ch; color: rgba(255,255,255,0.92); }

.section { margin-top: 18px; }
.grid { display: grid; gap: 14px; }
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
.card p { color: var(--muted); line-height: 1.58; }

.team {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}
.member {
  text-align: center;
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 14px;
}
.member img {
  width: 96px;
  height: 96px;
  border-radius: 50%;
  object-fit: cover;
  margin-bottom: 8px;
  border: 3px solid rgba(31, 143, 103, 0.35);
}
.member h3 { font-size: 1rem; margin-bottom: 4px; color: #12384b; }
.member p { font-size: 0.88rem; color: var(--muted); }

footer {
  margin-top: 32px;
  border-top: 1px solid var(--line);
  text-align: center;
  color: #5a6476;
  padding: 22px 0 30px;
}
footer a { color: var(--brand-deep); text-decoration: none; font-weight: 700; }

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
      <h1>About RentConnect</h1>
      <p>Your trusted digital rental marketplace, built to make renting in Liberia easier, faster, and more secure.</p>
    </div>
  </section>

  <section class="section grid">
    <article class="card">
      <h2>Who We Are</h2>
      <p>RentConnect is Liberia's trusted platform connecting renters with verified landlords and listings. We focus on making rental discovery straightforward and transparent for everyone.</p>
    </article>
    <article class="card">
      <h2>What We Do</h2>
      <p>We help landlords publish high-quality listings and help renters explore options with clear details, pricing, and communication tools in one place.</p>
    </article>
    <article class="card">
      <h2>Our Mission</h2>
      <p>Our mission is to modernize the rental journey in Liberia by improving trust, speed, and usability from first search to final move-in.</p>
    </article>
    <article class="card">
      <h2>Meet Our Team</h2>
      <div class="team">
        <div class="member"><img src="images/PD.jpeg" alt="Prince W. Dahn Jr"><h3>Prince W. Dahn Jr</h3><p>Founder & CEO</p></div>
        <div class="member"><img src="images/OA.jpeg" alt="Chris G Jackson"><h3>Chris G Jackson</h3><p>Operations Manager</p></div>
        <div class="member"><img src="images/Stanley.png" alt="Stanley S. Garyeazohn"><h3>Stanley S. Garyeazohn</h3><p>Lead Developer</p></div>
        <div class="member"><img src="images/Sam.jpeg" alt="Samuka Teah"><h3>Samuka Teah</h3><p>Marketing & Outreach</p></div>
      </div>
    </article>
  </section>
</main>

<footer>
  <div class="container">
    <p>&copy; <?php echo date("Y"); ?> RentConnect Liberia | All rights reserved. | <a href="contact.php">Contact Us</a></p>
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
