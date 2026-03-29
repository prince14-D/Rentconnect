<?php
session_start();
include "app_init.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'renter') {
    header("Location: login.php");
    exit;
}

$renter_id = (int) $_SESSION['user_id'];
$message = '';
$message_type = 'success';
$current_page = basename((string) ($_SERVER['PHP_SELF'] ?? 'renter_profile.php'));

$renter_profile = rc_mig_get_user_profile($conn, $renter_id);
if (!$renter_profile) {
    $renter_profile = [
        'name' => (string) ($_SESSION['name'] ?? 'Renter'),
        'email' => '',
        'phone' => '',
        'profile_pic' => '',
        'avatar_url' => '',
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_name = trim((string) ($_POST['name'] ?? ''));
    $new_phone = trim((string) ($_POST['phone'] ?? ''));
    $profile_pic = (string) (($renter_profile['profile_pic'] ?? '') ?: ($renter_profile['avatar_url'] ?? ''));

    if (isset($_FILES['profile_photo']) && is_array($_FILES['profile_photo']) && (int) ($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $upload_error = (int) ($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_OK);
        $tmp_name = (string) ($_FILES['profile_photo']['tmp_name'] ?? '');
        $file_size = (int) ($_FILES['profile_photo']['size'] ?? 0);

        if ($upload_error !== UPLOAD_ERR_OK) {
            $message = 'Could not upload photo. Please try again.';
            $message_type = 'error';
        } elseif ($file_size > 2 * 1024 * 1024) {
            $message = 'Profile photo must be 2MB or smaller.';
            $message_type = 'error';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmp_name) ?: '';
            $allowed_mimes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
            ];

            if (!isset($allowed_mimes[$mime])) {
                $message = 'Only JPG, PNG, WEBP, and GIF images are allowed.';
                $message_type = 'error';
            } else {
                $upload_dir_rel = 'uploads/profile_photos';
                $upload_dir_abs = __DIR__ . '/' . $upload_dir_rel;
                if (!is_dir($upload_dir_abs)) {
                    mkdir($upload_dir_abs, 0755, true);
                }

                $filename = 'renter_' . $renter_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed_mimes[$mime];
                $destination_abs = $upload_dir_abs . '/' . $filename;
                $destination_rel = $upload_dir_rel . '/' . $filename;

                if (move_uploaded_file($tmp_name, $destination_abs)) {
                    $profile_pic = $destination_rel;
                } else {
                    $message = 'Could not save uploaded photo.';
                    $message_type = 'error';
                }
            }
        }
    }

    if ($message === '') {
        $update = rc_mig_update_user_profile($conn, $renter_id, [
            'name' => $new_name,
            'phone' => $new_phone,
            'profile_pic' => $profile_pic,
        ]);

        if (!empty($update['ok'])) {
            $_SESSION['name'] = $new_name;
            $message = 'Profile updated successfully.';
            $message_type = 'success';
            $renter_profile = rc_mig_get_user_profile($conn, $renter_id) ?: $renter_profile;
        } else {
            $message = (string) ($update['error'] ?? 'Failed to update profile.');
            $message_type = 'error';
        }
    }
}

$active_bookings = rc_mig_get_renter_active_bookings($conn, $renter_id);
$requests = rc_mig_get_renter_requests($conn, $renter_id);
$pending_payments = rc_mig_get_renter_pending_payments_count($conn, $renter_id);

$current_profile_photo = (string) (($renter_profile['profile_pic'] ?? '') ?: ($renter_profile['avatar_url'] ?? ''));
if ($current_profile_photo === '') {
    $current_profile_photo = 'images/default-avatar.png';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Manage your renter profile.">
<title>My Profile - RentConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --panel: #ffffff;
    --ink: #1f2836;
    --muted: #627089;
    --brand: #1f8f67;
    --line: #d8e4ed;
}
* { box-sizing: border-box; }
body {
    margin: 0;
    font-family: 'Manrope', sans-serif;
    color: var(--ink);
    background: radial-gradient(circle at top right, #f7fbff 0%, #edf3f8 42%, #e6eef5 100%);
}
.wrap { max-width: 1220px; margin: 0 auto; padding: 20px 14px 34px; }
.hero {
    background: linear-gradient(120deg, #123f50 0%, #176480 64%, #1f8f67 100%);
    color: #fff;
    border-radius: 14px;
    padding: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    box-shadow: 0 10px 24px rgba(13, 31, 43, 0.2);
}
.hero h1 { margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.35rem; }
.hero p { margin: 5px 0 0; opacity: 0.92; font-size: 0.92rem; }
.back-home {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    text-decoration: none;
    color: #133744;
    background: #fff;
    border-radius: 10px;
    padding: 9px 12px;
    font-size: 0.86rem;
    font-weight: 800;
}
.layout { margin-top: 14px; display: flex; gap: 14px; align-items: flex-start; }
.side {
    width: 250px;
    flex-shrink: 0;
    background: linear-gradient(180deg, rgba(19, 64, 78, 0.96), rgba(16, 50, 63, 0.96));
    border-radius: 14px;
    padding: 12px;
    box-shadow: 0 10px 24px rgba(15, 31, 40, 0.14);
    position: sticky;
    top: 14px;
}
.side h3 { margin: 4px 4px 10px; color: #fff; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1rem; }
.menu { display: grid; gap: 7px; }
.menu a {
    text-decoration: none;
    color: #eaf4f6;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 10px;
    padding: 9px 10px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.88rem;
    font-weight: 700;
    transition: all 0.2s ease;
}
.menu a:hover,
.menu a.active {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(255, 255, 255, 0.34);
    transform: translateX(2px);
}
.main { flex: 1; min-width: 0; }
.notice {
    margin: 0 0 12px;
    border-radius: 11px;
    border: 1px solid rgba(31, 143, 103, 0.22);
    background: rgba(31, 143, 103, 0.09);
    color: #1f8f67;
    padding: 9px 11px;
    font-size: 0.88rem;
    font-weight: 700;
}
.notice.error {
    border-color: rgba(217, 83, 79, 0.26);
    background: rgba(217, 83, 79, 0.1);
    color: #a83430;
}
.stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.stat { background: var(--panel); border: 1px solid var(--line); border-radius: 12px; padding: 11px; }
.stat .label { color: var(--muted); font-size: 0.77rem; font-weight: 700; }
.stat .value { margin-top: 4px; font-size: 1.2rem; font-weight: 800; color: var(--ink); }
.panel {
    margin-top: 12px;
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: 13px;
    padding: 14px;
    box-shadow: 0 6px 20px rgba(28, 50, 76, 0.05);
}
.profile-box { display: grid; grid-template-columns: 180px 1fr; gap: 16px; align-items: flex-start; }
.profile-photo {
    width: 160px;
    height: 160px;
    border-radius: 14px;
    object-fit: cover;
    border: 2px solid #d7e6ef;
    background: #f1f6fb;
}
.profile-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
.profile-grid label { display: block; font-size: 0.78rem; font-weight: 700; color: #607089; margin-bottom: 5px; }
.profile-grid input {
    width: 100%;
    border: 1px solid #cad7e2;
    border-radius: 8px;
    padding: 10px;
    font: inherit;
}
.profile-grid .full { grid-column: 1 / -1; }
.profile-grid button {
    border: none;
    border-radius: 9px;
    padding: 10px 13px;
    font-weight: 800;
    color: #fff;
    cursor: pointer;
    background: linear-gradient(140deg, #1f8f67, #15543e);
}
@media (max-width: 980px) {
    .layout { flex-direction: column; }
    .side { width: 100%; position: static; }
    .menu { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .stats { grid-template-columns: 1fr; }
    .profile-box { grid-template-columns: 1fr; }
}
@media (max-width: 560px) {
    .menu, .profile-grid { grid-template-columns: 1fr; }
    .hero { flex-direction: column; align-items: flex-start; }
}
</style>
</head>
<body>
<div class="wrap">
    <header class="hero">
        <div>
            <h1>My Profile</h1>
            <p>Update your renter details and profile photo.</p>
        </div>
        <a class="back-home" href="renter_dashboard.php"><i class="fa-solid fa-arrow-left"></i>Dashboard</a>
    </header>

    <div class="layout">
        <aside class="side">
            <h3>Renter Services</h3>
            <nav class="menu">
                <a href="renter_profile.php" class="<?php echo $current_page === 'renter_profile.php' ? 'active' : ''; ?>"><i class="fa-solid fa-user"></i>Profile</a>
                <a href="browse_properties.php" class="<?php echo $current_page === 'browse_properties.php' ? 'active' : ''; ?>"><i class="fa-solid fa-house"></i>Browse Properties</a>
                <a href="my_requests.php" class="<?php echo $current_page === 'my_requests.php' ? 'active' : ''; ?>"><i class="fa-solid fa-envelope-open-text"></i>My Requests</a>
                <a href="active_bookings.php" class="<?php echo $current_page === 'active_bookings.php' ? 'active' : ''; ?>"><i class="fa-solid fa-calendar-check"></i>Active Bookings</a>
                <a href="rent_reminders.php" class="<?php echo $current_page === 'rent_reminders.php' ? 'active' : ''; ?>"><i class="fa-solid fa-bell"></i>Reminders</a>
                <a href="rent_payments.php" class="<?php echo $current_page === 'rent_payments.php' ? 'active' : ''; ?>"><i class="fa-solid fa-credit-card"></i>Payments</a>
                <a href="chat_list.php" class="<?php echo in_array($current_page, ['chat.php', 'chat_list.php'], true) ? 'active' : ''; ?>"><i class="fa-solid fa-comments"></i>Chat</a>
                <a href="index.php"><i class="fa-solid fa-house-circle-check"></i>Home</a>
            </nav>
        </aside>

        <main class="main">
            <?php if ($message !== ''): ?>
                <p class="notice <?php echo $message_type === 'error' ? 'error' : ''; ?>"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>

            <section class="stats">
                <article class="stat">
                    <div class="label">Active Bookings</div>
                    <div class="value"><?php echo count($active_bookings); ?></div>
                </article>
                <article class="stat">
                    <div class="label">My Requests</div>
                    <div class="value"><?php echo count($requests); ?></div>
                </article>
                <article class="stat">
                    <div class="label">Pending Payments</div>
                    <div class="value"><?php echo (int) $pending_payments; ?></div>
                </article>
            </section>

            <section class="panel">
                <form method="post" enctype="multipart/form-data" class="profile-box">
                    <div>
                        <img src="<?php echo htmlspecialchars($current_profile_photo); ?>" alt="Profile photo" class="profile-photo">
                    </div>
                    <div class="profile-grid">
                        <div>
                            <label for="profile_name">Full Name</label>
                            <input type="text" id="profile_name" name="name" required value="<?php echo htmlspecialchars((string) ($renter_profile['name'] ?? '')); ?>">
                        </div>
                        <div>
                            <label for="profile_email">Email Address</label>
                            <input type="email" id="profile_email" value="<?php echo htmlspecialchars((string) ($renter_profile['email'] ?? '')); ?>" readonly>
                        </div>
                        <div>
                            <label for="profile_phone">Phone Number</label>
                            <input type="text" id="profile_phone" name="phone" placeholder="e.g. 231xxxxxxxxx" value="<?php echo htmlspecialchars((string) ($renter_profile['phone'] ?? '')); ?>">
                        </div>
                        <div>
                            <label for="profile_photo">Profile Photo (max 2MB)</label>
                            <input type="file" id="profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif">
                        </div>
                        <div class="full">
                            <button type="submit" name="update_profile" value="1">Save Profile Changes</button>
                        </div>
                    </div>
                </form>
            </section>
        </main>
    </div>
</div>
</body>
</html>
