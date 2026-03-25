<?php
session_start();
include "db.php";

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'landlord'], true)) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$message = "";
$message_type = "success";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $theme_color = $_POST['theme_color'] ?? '#2e7d32';

    // Accept only standard hex color values.
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $theme_color)) {
        $message = "Invalid color value.";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("UPDATE users SET theme_color=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("si", $theme_color, $user_id);
            if ($stmt->execute()) {
                $message = "Theme updated successfully.";
            } else {
                $message = "Database error: " . $stmt->error;
                $message_type = "error";
            }
        } else {
            $message = "Prepare failed: " . $conn->error;
            $message_type = "error";
        }
    }
}

$stmt = $conn->prepare("SELECT theme_color FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$current_theme = $res->fetch_assoc()['theme_color'] ?? '#2e7d32';

$dashboard_path = $user_role === 'admin' ? 'admin_dashboard.php' : 'landlord_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings - RentConnect</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --ink: #1f2430;
    --muted: #5d6579;
    --brand: <?php echo htmlspecialchars($current_theme); ?>;
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

.container {
    width: min(900px, 95vw);
    margin: 0 auto;
}

.hero {
    margin-top: 24px;
    border-radius: 20px;
    color: #fff;
    padding: clamp(18px, 3.5vw, 28px);
    background: linear-gradient(140deg, rgba(16, 62, 79, 0.93), rgba(31, 143, 103, 0.86));
    box-shadow: var(--shadow);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.hero h1 {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: clamp(1.4rem, 2.8vw, 2rem);
    letter-spacing: -0.02em;
    margin-bottom: 6px;
}

.hero p { color: rgba(255, 255, 255, 0.92); }

.back {
    text-decoration: none;
    color: #fff;
    font-weight: 700;
    border-radius: 9px;
    padding: 10px 13px;
    background: rgba(255, 255, 255, 0.2);
}

.card {
    margin-top: 14px;
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid rgba(255, 255, 255, 0.9);
    border-radius: 14px;
    box-shadow: 0 10px 24px rgba(15, 31, 40, 0.09);
    padding: 16px;
}

.message {
    border-radius: 10px;
    padding: 10px 12px;
    margin-bottom: 10px;
    font-weight: 700;
}

.message.success {
    color: #15543e;
    background: rgba(39, 165, 106, 0.12);
    border: 1px solid rgba(39, 165, 106, 0.3);
}

.message.error {
    color: #8d1f1f;
    background: rgba(233, 79, 79, 0.12);
    border: 1px solid rgba(233, 79, 79, 0.3);
}

label {
    display: block;
    margin-bottom: 8px;
    font-weight: 700;
    color: #445069;
}

.color-row {
    display: grid;
    grid-template-columns: 140px 1fr;
    gap: 10px;
    align-items: center;
}

input[type=color] {
    width: 100%;
    height: 50px;
    border: 1px solid var(--line);
    border-radius: 10px;
    cursor: pointer;
    background: #fff;
}

.preview {
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 10px;
    font-size: 0.92rem;
    color: var(--muted);
}

button {
    margin-top: 14px;
    border: none;
    border-radius: 10px;
    padding: 11px 14px;
    color: #fff;
    font-weight: 700;
    background: linear-gradient(140deg, var(--brand), #15543e);
    cursor: pointer;
}

@media (max-width: 640px) {
    .color-row {
        grid-template-columns: 1fr;
    }

    button {
        width: 100%;
    }
}
</style>
</head>
<body>
<main class="container">
    <section class="hero">
        <div>
            <h1>Settings</h1>
            <p>Adjust your dashboard accent color.</p>
        </div>
        <a href="<?php echo $dashboard_path; ?>" class="back">Back to Dashboard</a>
    </section>

    <section class="card">
        <?php if ($message): ?>
            <p class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <form method="post">
            <label for="theme_color">Dashboard Theme Color</label>
            <div class="color-row">
                <input type="color" name="theme_color" id="theme_color" value="<?php echo htmlspecialchars($current_theme); ?>" required>
                <div class="preview">Current color: <strong id="colorValue"><?php echo htmlspecialchars($current_theme); ?></strong></div>
            </div>
            <button type="submit">Update Theme</button>
        </form>
    </section>
</main>

<script>
const themeInput = document.getElementById('theme_color');
const colorValue = document.getElementById('colorValue');

themeInput.addEventListener('input', () => {
    colorValue.textContent = themeInput.value;
});
</script>
</body>
</html>
