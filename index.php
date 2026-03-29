<?php
session_start();
include "app_init.php";

// --- Search Filters ---
$search_location = isset($_GET['location']) ? trim($_GET['location']) : '';
$min_price = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 0;

// --- User Info ---
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? null;

$properties = rc_mig_get_index_properties(
    $conn,
    $user_id !== null ? (int) $user_id : null,
    $user_role !== null ? (string) $user_role : null,
    $search_location,
    $min_price,
    $max_price,
    12
);

if (!empty($properties)) {
    usort($properties, static function (array $a, array $b): int {
        $aStatus = strtolower((string) ($a['status'] ?? ''));
        $bStatus = strtolower((string) ($b['status'] ?? ''));
        $aBooking = strtolower((string) ($a['booking_status'] ?? 'available'));
        $bBooking = strtolower((string) ($b['booking_status'] ?? 'available'));

        $aAvailable = !in_array($aStatus, ['taken', 'booked', 'inactive', 'rejected'], true)
            && !in_array($aBooking, ['taken', 'booked', 'maintenance'], true);
        $bAvailable = !in_array($bStatus, ['taken', 'booked', 'inactive', 'rejected'], true)
            && !in_array($bBooking, ['taken', 'booked', 'maintenance'], true);

        if ($aAvailable !== $bAvailable) {
            return $aAvailable ? -1 : 1;
        }

        $aCreated = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
        $bCreated = strtotime((string) ($b['created_at'] ?? '')) ?: 0;

        return $bCreated <=> $aCreated;
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Find and manage rental properties with ease. Connect landlords and renters seamlessly.">
    <meta name="theme-color" content="#1f8f67">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="manifest" href="/manifest.json">
    <title>RentConnect - Find Homes in Liberia</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f7f4ee;
            --surface: #fffaf2;
            --surface-2: #ffffff;
            --ink: #22252e;
            --muted: #5f6678;
            --brand: #1f8f67;
            --brand-deep: #146248;
            --accent: #ff7a2f;
            --line: rgba(34, 37, 46, 0.12);
            --shadow: 0 14px 42px rgba(19, 37, 30, 0.12);
            --radius-lg: 22px;
            --radius-md: 14px;
            --radius-sm: 10px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Manrope', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 8% 5%, rgba(255, 122, 47, 0.22), transparent 38%),
                radial-gradient(circle at 92% 10%, rgba(31, 143, 103, 0.22), transparent 34%),
                linear-gradient(165deg, #f9f5ef 0%, #f1f6f8 52%, #fffdf8 100%);
            min-height: 100vh;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .container {
            width: min(1180px, 94vw);
            margin: 0 auto;
        }

        header {
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
            background: rgba(255, 250, 242, 0.9);
            border-bottom: 1px solid var(--line);
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 74px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .brand-mark {
            display: inline-grid;
            place-items: center;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            color: #fff;
            background: linear-gradient(140deg, var(--brand), var(--brand-deep));
            font-size: 0.95rem;
        }

        .menu-toggle {
            display: none;
            border: 1px solid var(--line);
            background: #fff;
            border-radius: 8px;
            width: 40px;
            height: 40px;
            font-size: 1.15rem;
            cursor: pointer;
        }

        nav {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        nav a,
        .dropbtn {
            padding: 10px 14px;
            border-radius: 9px;
            font-weight: 600;
            color: #333a49;
            border: 1px solid transparent;
            background: transparent;
            cursor: pointer;
            transition: all 0.22s ease;
        }

        nav a:hover,
        .dropbtn:hover {
            background: rgba(31, 143, 103, 0.08);
            color: var(--brand-deep);
        }

        .upload-btn {
            color: #fff;
            background: linear-gradient(140deg, var(--brand), var(--brand-deep));
            box-shadow: 0 8px 20px rgba(20, 98, 72, 0.22);
        }

        .upload-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(20, 98, 72, 0.28);
        }

        .dropdown {
            position: relative;
        }

        .dropdown-content {
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            min-width: 165px;
            display: none;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 6px;
        }

        .dropdown-content a {
            display: block;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 0.95rem;
        }

        .dropdown-content a:hover {
            background: rgba(31, 143, 103, 0.1);
        }

        .dropdown.active .dropdown-content {
            display: block;
        }

        .hero-wrap {
            padding: 28px 0 8px;
        }

        .hero-carousel {
            position: relative;
            border-radius: var(--radius-lg);
            overflow: hidden;
            min-height: 60vh;
            box-shadow: var(--shadow);
        }

        .hero-carousel .slide {
            position: absolute;
            inset: 0;
            opacity: 0;
            transform: scale(1.02);
            transition: opacity 0.85s ease, transform 1.2s ease;
        }

        .hero-carousel .slide.active {
            opacity: 1;
            transform: scale(1);
        }

        .hero-carousel img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(130deg, rgba(19, 30, 33, 0.74) 15%, rgba(19, 30, 33, 0.32) 75%);
        }

        .caption {
            position: absolute;
            left: clamp(18px, 4vw, 52px);
            bottom: clamp(18px, 5vw, 56px);
            max-width: 640px;
            color: #fff;
            animation: rise 700ms ease forwards;
        }

        .caption h2 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: clamp(1.8rem, 4.8vw, 3.2rem);
            line-height: 1.1;
            margin-bottom: 10px;
            letter-spacing: -0.03em;
        }

        .caption p {
            color: rgba(255, 255, 255, 0.92);
            font-size: clamp(0.98rem, 1.7vw, 1.2rem);
        }

        .hero-dots {
            position: absolute;
            right: 22px;
            bottom: 20px;
            display: flex;
            gap: 6px;
            z-index: 2;
        }

        .hero-dot {
            width: 9px;
            height: 9px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.7);
            background: transparent;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .hero-dot.active {
            width: 28px;
            background: #fff;
        }

        .search-panel {
            margin: -48px auto 0;
            position: relative;
            z-index: 3;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow);
            padding: 16px;
            width: min(980px, 96vw);
            backdrop-filter: blur(8px);
        }

        .search-panel form {
            display: grid;
            grid-template-columns: 1.35fr 1fr 1fr auto;
            gap: 10px;
        }

        .search-panel input,
        .search-panel button {
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            border: 1px solid var(--line);
            font-size: 0.96rem;
            font-family: inherit;
        }

        .search-panel input {
            background: #fff;
        }

        .search-panel input:focus {
            outline: none;
            border-color: rgba(31, 143, 103, 0.55);
            box-shadow: 0 0 0 3px rgba(31, 143, 103, 0.16);
        }

        .search-panel button {
            border: none;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(140deg, var(--accent), #f95d11);
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 12px 22px rgba(249, 93, 17, 0.3);
        }

        .search-panel button:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 24px rgba(249, 93, 17, 0.34);
        }

        .section {
            padding: 70px 0 0;
        }

        .section-head {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 24px;
        }

        .section-head h3 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: clamp(1.5rem, 2.3vw, 2.1rem);
            letter-spacing: -0.02em;
            margin-bottom: 8px;
        }

        .section-head p {
            color: var(--muted);
            max-width: 530px;
        }

        .properties {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
            gap: 20px;
        }

        .property {
            background: var(--surface-2);
            border: 1px solid rgba(28, 40, 67, 0.09);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 10px 28px rgba(21, 28, 42, 0.08);
            animation: rise 650ms ease both;
        }

        .property:nth-child(2) { animation-delay: 80ms; }
        .property:nth-child(3) { animation-delay: 120ms; }
        .property:nth-child(4) { animation-delay: 160ms; }

        .carousel {
            position: relative;
            height: 214px;
            background: #e6ecef;
            overflow: hidden;
        }

        .carousel img {
            width: 100%;
            height: 214px;
            object-fit: cover;
            display: none;
        }

        .carousel img.active {
            display: block;
        }

        .carousel button {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 999px;
            color: #fff;
            background: rgba(17, 23, 34, 0.55);
            cursor: pointer;
        }

        .carousel .prev {
            left: 10px;
        }

        .carousel .next {
            right: 10px;
        }

        .content {
            padding: 16px;
        }

        .content h4 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.15rem;
            margin-bottom: 8px;
            letter-spacing: -0.01em;
        }

        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            color: var(--muted);
            font-size: 0.94rem;
            margin-bottom: 8px;
        }

        .meta span {
            padding: 6px 10px;
            background: var(--surface);
            border: 1px solid rgba(28, 40, 67, 0.09);
            border-radius: 999px;
        }

        .status-chip.available {
            color: #0c5f44;
            background: rgba(31, 143, 103, 0.12);
            border-color: rgba(31, 143, 103, 0.24);
            font-weight: 700;
        }

        .status-chip.booked {
            color: #5d6576;
            background: rgba(121, 132, 150, 0.14);
            border-color: rgba(121, 132, 150, 0.24);
            font-weight: 700;
        }

        .status-chip.new {
            color: #8a3f00;
            background: rgba(255, 122, 47, 0.16);
            border-color: rgba(255, 122, 47, 0.3);
            font-weight: 700;
        }

        .price {
            font-size: 1.12rem;
            font-weight: 800;
            color: #1b4154;
            margin-bottom: 6px;
        }

        .landlord {
            color: var(--muted);
            font-size: 0.92rem;
            margin-bottom: 14px;
        }

        .view-link {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 10px;
            background: #102f45;
            color: #fff;
            font-weight: 700;
            font-size: 0.92rem;
        }

        .view-link:hover {
            background: #0e2435;
        }

        .empty-state {
            border: 1px dashed var(--line);
            border-radius: var(--radius-md);
            padding: 36px;
            text-align: center;
            color: var(--muted);
            background: rgba(255, 255, 255, 0.68);
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-top: 22px;
        }

        .feature {
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 10px 24px rgba(31, 40, 52, 0.06);
        }

        .feature h4 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #102f45;
            margin-bottom: 8px;
        }

        .feature p {
            color: var(--muted);
            line-height: 1.5;
            font-size: 0.95rem;
        }

        footer {
            margin-top: 60px;
            padding: 24px 0 32px;
            border-top: 1px solid var(--line);
            color: #556072;
            font-size: 0.92rem;
            text-align: center;
        }

        footer a {
            color: var(--brand-deep);
            font-weight: 700;
        }

        @keyframes rise {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 900px) {
            .search-panel form {
                grid-template-columns: 1fr 1fr;
            }

            .search-panel button {
                grid-column: span 2;
            }
        }

        @media (max-width: 768px) {
            .menu-toggle {
                display: inline-grid;
                place-items: center;
            }

            nav {
                position: absolute;
                right: 3vw;
                top: 72px;
                width: min(280px, 92vw);
                display: none;
                flex-direction: column;
                align-items: stretch;
                gap: 6px;
                background: #fff;
                border: 1px solid var(--line);
                border-radius: 14px;
                padding: 12px;
                box-shadow: var(--shadow);
            }

            nav.active {
                display: flex;
            }

            .dropdown-content {
                position: static;
                border: none;
                box-shadow: none;
                padding: 4px 0 0;
            }

            .hero-carousel {
                min-height: 46vh;
            }

            .search-panel {
                margin-top: -38px;
            }

            .search-panel form {
                grid-template-columns: 1fr;
            }

            .search-panel button {
                grid-column: auto;
            }

            .section {
                padding-top: 58px;
            }
        }
    </style>
</head>
<body>
<header>
    <div class="container topbar">
        <a href="index.php" class="brand">
            <span class="brand-mark">RC</span>
            <span>RentConnect</span>
        </a>
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

<main>
    <section class="hero-wrap container">
        <div class="hero-carousel" id="heroCarousel">
            <div class="slide active">
                <img src="images/home-banner.png" alt="Find rental homes in Liberia">
                <div class="hero-overlay"></div>
                <div class="caption">
                    <h2>Find Your Next Home With Confidence</h2>
                    <p>Explore trusted rental listings across Liberia with fast search and verified hosts.</p>
                </div>
            </div>
            <div class="slide">
                <img src="images/home-banner-2.png" alt="Modern apartment building">
                <div class="hero-overlay"></div>
                <div class="caption">
                    <h2>Simple Search. Real Listings.</h2>
                    <p>From first visit to move-in, RentConnect helps renters and landlords stay connected.</p>
                </div>
            </div>

            <div class="hero-dots" id="heroDots">
                <button class="hero-dot active" type="button" aria-label="Go to slide 1"></button>
                <button class="hero-dot" type="button" aria-label="Go to slide 2"></button>
            </div>
        </div>

        <div class="search-panel">
            <form method="get" action="">
                <input type="text" name="location" placeholder="Search location" value="<?php echo htmlspecialchars($search_location); ?>">
                <input type="number" name="min_price" placeholder="Minimum price" value="<?php echo $min_price ?: ''; ?>">
                <input type="number" name="max_price" placeholder="Maximum price" value="<?php echo $max_price ?: ''; ?>">
                <button type="submit">Search Homes</button>
            </form>
        </div>
    </section>

    <section class="section container">
        <div class="section-head">
            <div>
                <h3>Latest Properties</h3>
                <p>Available properties appear first, and newest listings are prioritized in every result.</p>
            </div>
        </div>

        <div class="properties">
            <?php if (!empty($properties)): ?>
                <?php foreach ($properties as $property): ?>
                    <?php
                    $status = strtolower((string) ($property['status'] ?? ''));
                    $bookingStatus = strtolower((string) ($property['booking_status'] ?? 'available'));
                    $isAvailable = !in_array($status, ['taken', 'booked', 'inactive', 'rejected'], true)
                        && !in_array($bookingStatus, ['taken', 'booked', 'maintenance'], true);
                    $createdAt = strtotime((string) ($property['created_at'] ?? '')) ?: 0;
                    $isNew = $createdAt > 0 && (time() - $createdAt) <= (7 * 24 * 60 * 60);
                    ?>
                    <article class="property">
                        <div class="carousel" id="carousel-<?php echo $property['id']; ?>">
                            <?php
                            $images = rc_mig_get_property_image_ids($conn, (int) $property['id']);
                            if (!empty($images)) {
                                $first = true;
                                foreach ($images as $row) {
                                    echo '<img src="display_image.php?img_id=' . $row['id'] . '" class="' . ($first ? 'active' : '') . '" alt="Property image">';
                                    $first = false;
                                }
                            } else {
                                echo '<img src="images/no-image.png" class="active" alt="No image available">';
                            }
                            ?>
                            <button class="prev" type="button" aria-label="Previous image">&#10094;</button>
                            <button class="next" type="button" aria-label="Next image">&#10095;</button>
                        </div>

                        <div class="content">
                            <h4><?php echo htmlspecialchars($property['title']); ?></h4>
                            <div class="meta">
                                <?php if ($isNew): ?>
                                    <span class="status-chip new">Just Added</span>
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($property['location']); ?></span>
                                <span class="status-chip <?php echo $isAvailable ? 'available' : 'booked'; ?>">
                                    <?php echo $isAvailable ? 'Available' : 'Booked'; ?>
                                </span>
                                <?php if ($user_role === 'landlord'): ?>
                                    <span>Status: <?php echo ucfirst($property['status']); ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="price">$<?php echo number_format($property['price']); ?></p>
                            <p class="landlord">Landlord: <?php echo htmlspecialchars($property['landlord_name']); ?></p>
                            <a class="view-link" href="login.php">View Details</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">No properties found for your current search. Try adjusting your filters.</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="section container">
        <div class="section-head">
            <div>
                <h3>Why Use RentConnect</h3>
                <p>A practical rental platform made for both house seekers and property owners.</p>
            </div>
        </div>

        <div class="features">
            <div class="feature">
                <h4>For Renters</h4>
                <p>Browse trusted apartments and homes, compare options quickly, and submit requests securely.</p>
            </div>
            <div class="feature">
                <h4>For Landlords</h4>
                <p>Upload listings with images, highlight key property details, and manage demand in one place.</p>
            </div>
            <div class="feature">
                <h4>Secure and Fast</h4>
                <p>Simple account flows, organized dashboards, and a cleaner experience from search to chat.</p>
            </div>
        </div>
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
    const dropdown = event.target.closest(".dropdown");
    dropdown.classList.toggle("active");
}

document.addEventListener("DOMContentLoaded", () => {
    const heroSlides = document.querySelectorAll(".hero-carousel .slide");
    const heroDots = document.querySelectorAll(".hero-dot");
    let heroIndex = 0;

    function showHero(index) {
        heroSlides[heroIndex].classList.remove("active");
        heroDots[heroIndex].classList.remove("active");
        heroIndex = (index + heroSlides.length) % heroSlides.length;
        heroSlides[heroIndex].classList.add("active");
        heroDots[heroIndex].classList.add("active");
    }

    if (heroSlides.length > 1) {
        setInterval(() => showHero(heroIndex + 1), 4500);
        heroDots.forEach((dot, idx) => {
            dot.addEventListener("click", () => showHero(idx));
        });
    }

    document.querySelectorAll(".carousel").forEach((carousel) => {
        const imgs = carousel.querySelectorAll("img");
        if (imgs.length <= 1) {
            return;
        }

        let index = 0;
        function showSlide(newIndex) {
            imgs[index].classList.remove("active");
            index = (newIndex + imgs.length) % imgs.length;
            imgs[index].classList.add("active");
        }

        setInterval(() => showSlide(index + 1), 3000);
        carousel.querySelector(".prev").addEventListener("click", () => showSlide(index - 1));
        carousel.querySelector(".next").addEventListener("click", () => showSlide(index + 1));
    });

    document.addEventListener("click", (event) => {
        if (!event.target.closest(".dropdown")) {
            document.querySelectorAll(".dropdown").forEach((drop) => drop.classList.remove("active"));
        }
    });
});
</script>

<script>
// Register Service Worker for PWA
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js')
            .then((registration) => {
                console.log('[PWA] Service Worker registered:', registration);
                
                // Check for updates periodically
                setInterval(() => {
                    registration.update();
                }, 60000); // Check every minute
            })
            .catch((error) => {
                console.warn('[PWA] Service Worker registration failed:', error);
            });
    });
}

// Handle PWA install prompt
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredPrompt = event;
    console.log('[PWA] Install prompt available');
});

// Optional: Add a custom install button
window.addEventListener('appinstalled', () => {
    console.log('[PWA] App installed successfully');
    deferredPrompt = null;
});
</script>
</body>
</html>
