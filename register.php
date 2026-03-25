<?php
session_start();
include "db.php";
require_once "user_registration_helper.php";

$error = "";
$name = "";
$email = "";
$role = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rawPassword = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    $result = rc_register_user($conn, $name, $email, $rawPassword, $role);
    if (!$result['ok']) {
        $error = $result['error'];
    } else {
        $_SESSION['user_id'] = $result['user_id'];
        $_SESSION['name'] = $result['name'];
        $_SESSION['role'] = $result['role'];
        header("Location: index.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Register a new RentConnect account quickly and easily.">
    <meta name="theme-color" content="#1f8f67">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="manifest" href="/manifest.json">
    <title>Register - RentConnect</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #1f2430;
            --muted: #5c6477;
            --brand: #1f8f67;
            --brand-deep: #15543e;
            --accent: #ff7a2f;
            --line: rgba(31, 36, 48, 0.13);
            --shadow: 0 20px 42px rgba(26, 39, 34, 0.16);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 18px;
            font-family: 'Manrope', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 8% 8%, rgba(255, 122, 47, 0.24), transparent 36%),
                radial-gradient(circle at 92% 12%, rgba(31, 143, 103, 0.2), transparent 34%),
                linear-gradient(160deg, #f9f6ef, #f2f7f8 58%, #fffdfa);
        }

        .auth-wrap {
            width: min(980px, 96vw);
            display: grid;
            grid-template-columns: 1fr 1.08fr;
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid rgba(255, 255, 255, 0.88);
            border-radius: 22px;
            box-shadow: var(--shadow);
            overflow: hidden;
            backdrop-filter: blur(8px);
        }

        .panel { padding: clamp(24px, 4vw, 42px); }

        .brand-panel {
            color: #fff;
            background:
                linear-gradient(145deg, rgba(15, 74, 57, 0.94), rgba(21, 120, 88, 0.86)),
                url('images/home-banner-2.png') center/cover no-repeat;
            display: grid;
            align-content: end;
            min-height: 420px;
        }

        .brand-badge {
            display: inline-block;
            width: fit-content;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 800;
            letter-spacing: -0.02em;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            margin-bottom: 14px;
        }

        .brand-panel h1 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: clamp(1.6rem, 3vw, 2.2rem);
            margin-bottom: 10px;
            letter-spacing: -0.03em;
        }

        .brand-panel p {
            max-width: 38ch;
            color: rgba(255, 255, 255, 0.92);
            line-height: 1.5;
        }

        .form-panel h2 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.7rem;
            letter-spacing: -0.02em;
            margin-bottom: 8px;
        }

        .subtitle {
            color: var(--muted);
            margin-bottom: 22px;
        }

        .alert {
            border-radius: 12px;
            padding: 11px 13px;
            margin-bottom: 14px;
            font-size: 0.94rem;
            border: 1px solid rgba(190, 43, 43, 0.35);
            background: rgba(236, 68, 68, 0.09);
            color: #8f1f1f;
        }

        form { display: grid; gap: 12px; }

        label {
            font-size: 0.92rem;
            font-weight: 700;
        }

        input,
        select {
            width: 100%;
            padding: 12px 13px;
            border: 1px solid var(--line);
            border-radius: 10px;
            font: inherit;
            background: #fff;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: rgba(31, 143, 103, 0.56);
            box-shadow: 0 0 0 3px rgba(31, 143, 103, 0.14);
        }

        .submit-btn {
            margin-top: 6px;
            border: none;
            border-radius: 11px;
            padding: 12px 14px;
            color: #fff;
            font-weight: 700;
            font-size: 0.98rem;
            background: linear-gradient(140deg, var(--brand), var(--brand-deep));
            cursor: pointer;
            box-shadow: 0 12px 22px rgba(21, 84, 62, 0.28);
        }

        .links {
            margin-top: 16px;
            display: grid;
            gap: 10px;
            font-size: 0.94rem;
        }

        .links a {
            color: #0f4f96;
            text-decoration: none;
            font-weight: 700;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .home-btn {
            display: inline-block;
            margin-top: 10px;
            width: fit-content;
            padding: 10px 14px;
            border-radius: 10px;
            text-decoration: none;
            color: #fff;
            background: linear-gradient(135deg, var(--accent), #f15f13);
            font-weight: 700;
        }

        @media (max-width: 820px) {
            .auth-wrap {
                grid-template-columns: 1fr;
            }

            .brand-panel {
                min-height: 240px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-wrap">
        <section class="panel brand-panel">
            <span class="brand-badge">RentConnect</span>
            <h1>Create Your Account</h1>
            <p>Join as a renter or landlord and start your rental journey with a faster, cleaner platform.</p>
        </section>

        <section class="panel form-panel">
            <h2>Register</h2>
            <p class="subtitle">Fill in your details to continue.</p>

            <?php if (!empty($error)): ?>
                <div class="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <label for="name">Full Name</label>
                <input id="name" type="text" name="name" required value="<?php echo htmlspecialchars($name); ?>">

                <label for="email">Email Address</label>
                <input id="email" type="email" name="email" required value="<?php echo htmlspecialchars($email); ?>">

                <label for="password">Password</label>
                <input id="password" type="password" name="password" required minlength="6">

                <label for="role">Role</label>
                <select id="role" name="role" required>
                    <option value="">Select Role</option>
                    <option value="landlord" <?php echo $role === 'landlord' ? 'selected' : ''; ?>>Landlord</option>
                    <option value="renter" <?php echo $role === 'renter' ? 'selected' : ''; ?>>Renter</option>
                </select>

                <button class="submit-btn" type="submit">Create Account</button>
            </form>

            <div class="links">
                <p>Already have an account? <a href="login.php">Login</a></p>
            </div>

            <a class="home-btn" href="index.php">Back to Home</a>
        </section>
    </div>

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
