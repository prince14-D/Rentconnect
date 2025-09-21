<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>About Us - RentConnect</title>
<style>
/* === Global Reset === */
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f5f7fb; color: #333; line-height: 1.6; }

/* === Header === */
header {
  background: #fff;
  padding: 0px 0px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  position: sticky;
  top: 0;
  z-index: 1000;
}

header h1 a {
  text-decoration: none;
  color: #2E7D32;
  font-size: 1.1em;
  font-weight: bold;
}

nav {
  display: flex;
  gap: 20px;
  align-items: center;
  transition: all 0.3s ease;
}

nav a, nav .dropbtn, .upload-btn {
  text-decoration: none;
  color: #444;
  font-weight: 500;
  padding: 8px 14px;
  border-radius: 6px;
  transition: 0.3s;
  cursor: pointer;
}

nav a:hover, nav .dropbtn:hover {
  background: #f0f0f0;
  color: #2E7D32;
}

.upload-btn {
  background: #2E7D32;
  color: #fff;
}
.upload-btn:hover { background: #1b5e20; color: #fff; }

/* Dropdown */
.dropdown { position: relative; display: inline-block; }
.dropdown-content {
  display: none;
  position: absolute;
  top: 100%;
  left: 0;
  background: #fff;
  min-width: 160px;
  border-radius: 6px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  overflow: hidden;
  z-index: 999;
}
.dropdown-content a {
  display: block;
  padding: 10px;
  color: #444;
  font-size: 0.95em;
}
.dropdown-content a:hover { background: #f5f5f5; color: #2E7D32; }
.dropdown.active .dropdown-content { display: block; }

/* Hamburger Menu */
.hamburger {
  display: none;
  flex-direction: column;
  gap: 4px;
  cursor: pointer;
}
.hamburger div {
  width: 25px;
  height: 3px;
  background-color: #2E7D32;
  border-radius: 2px;
}

/* === Banner === */
.banner {
  background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('images/home-banner.png') center/cover no-repeat;
  color: #fff;
  text-align: center;
  padding: 100px 20px;
}
.banner h2 { font-size: 2.4em; margin-bottom: 10px; font-weight: 700; opacity:0; transform:translateY(20px); transition: all 1s ease-out;}
.banner p { font-size: 1.1em; opacity:0; transform:translateY(20px); transition: all 1s ease-out;}
.banner.visible h2, .banner.visible p { opacity:1; transform:translateY(0); transition-delay:0.2s; }

/* === Container & About Sections === */
.container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
.about-section {
  background: #fff;
  padding: 25px;
  border-radius: 12px;
  box-shadow: 0 4px 14px rgba(0,0,0,0.06);
  margin-bottom: 30px;
  transition: transform 0.3s;
  opacity:0; transform:translateY(20px);
}
.about-section:hover { transform: translateY(-4px); }
.about-section.visible { opacity:1; transform:translateY(0); }
.about-section h3 { color: #2E7D32; margin-bottom: 15px; font-size: 1.4em; }

/* === Team === */
.team {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 20px;
  margin-top: 20px;
}
.team-member {
  background: #fff;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  text-align: center;
  transition: transform 0.3s ease;
  opacity:0; transform:translateY(20px);
}
.team-member:hover { transform: translateY(-6px); }
.team-member.visible { opacity:1; transform:translateY(0); }
.team-member img {
  width: 100px;
  height: 100px;
  object-fit: cover;
  border-radius: 50%;
  margin-bottom: 10px;
  border: 3px solid #2E7D32;
  box-shadow: 0 0 10px rgba(46,125,50,0.3);
}
.team-member h4 { color: #2E7D32; margin: 8px 0 5px; font-size: 1.1em; }
.team-member p { font-size: 0.85em; color: #666; }

/* === Footer === */
footer { background: #222; color: #ccc; text-align: center; padding: 20px; margin-top: 40px; }
footer a { color: #FF9800; text-decoration: none; }
footer a:hover { text-decoration: underline; }

/* === Responsive Styles === */
@media (max-width: 768px){
  nav { 
    position: absolute; top: 60px; right:0; 
    background:#fff; flex-direction:column; width:220px; gap:0; border-radius:8px;
    box-shadow:0 4px 12px rgba(0,0,0,0.1); display:none;
  }
  nav.active { display:flex; }
  nav a, nav .dropbtn, .upload-btn { width:100%; text-align:left; padding:12px 20px; }
  .hamburger { display:flex; }
}
</style>
</head>
<body>

<header>
  <h1><a href="index.php">RentConnect</a></h1>
  <div class="hamburger" onclick="toggleMenu(this)">
    <div></div><div></div><div></div>
  </div>
  <nav id="navMenu">
    <a href="about.php">About Us</a>
    <a href="services.php">Services</a>
    <a href="contact.php">Contact</a>
    <div class="dropdown">
      <button class="dropbtn" onclick="toggleDropdown(event)">Account ▾</button>
      <div class="dropdown-content">
        <a href="login.php">Login</a>
        <a href="signup.php">Sign Up</a>
      </div>
    </div>
    <?php if(isset($_SESSION['user_id']) && $_SESSION['role']==='landlord'): ?>
      <a href="upload_property.php" class="upload-btn">Upload Property</a>
    <?php else: ?>
      <a href="login.php" class="upload-btn">Upload Property</a>
    <?php endif; ?>
  </nav>
</header>

<section class="banner">
  <h2>About RentConnect</h2>
  <p>Your trusted rental marketplace in Liberia</p>
</section>

<div class="container">
  <div class="about-section"> <h3>Who We Are</h3>
    <p>RentConnect is Liberia’s trusted platform that connects renters with verified landlords...</p>
  </div>
  <div class="about-section"> <h3>What We Do</h3>
    <p>We allow landlords to showcase their properties with ease...</p>
  </div>
  <div class="about-section"> <h3>Our Mission</h3>
    <p>Our mission is to make finding and renting homes in Liberia easy, secure, and affordable...</p>
  </div>

  <div class="about-section">
    <h3>Meet Our Team</h3>
    <div class="team">
      <div class="team-member"><img src="images/team1.png"><h4>Prince W. Dahn Jr</h4><p>Founder & CEO</p></div>
      <div class="team-member"><img src="images/team1.png"><h4>Sarah Johnson</h4><p>Operations Manager</p></div>
      <div class="team-member"><img src="images/team1.png"><h4>Michael Doe</h4><p>Lead Developer</p></div>
      <div class="team-member"><img src="images/team1.png"><h4>Grace Koffa</h4><p>Marketing & Outreach</p></div>
    </div>
  </div>
</div>

<footer>
  <p>&copy; <?php echo date("Y"); ?> RentConnect Liberia. All rights reserved. | <a href="contact.php">Contact Us</a></p>
</footer>

<script>
// Dropdown & Mobile Menu
function toggleDropdown(e){
  e.preventDefault();
  e.target.closest(".dropdown").classList.toggle("active");
}
function toggleMenu(menu){
  document.getElementById('navMenu').classList.toggle('active');
  menu.classList.toggle('open');
}

// Scroll animations
const faders = document.querySelectorAll('.about-section, .team-member');
const banner = document.querySelector('.banner');
const observer = new IntersectionObserver(entries=>{
  entries.forEach(entry=>{
    if(entry.isIntersecting){ entry.target.classList.add('visible'); observer.unobserve(entry.target); }
  });
},{threshold:0.2});

faders.forEach(el=>observer.observe(el));
window.addEventListener('load',()=>banner.classList.add('visible'));
</script>

</body>
</html>
