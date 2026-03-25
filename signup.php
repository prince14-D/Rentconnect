<?php
session_start();
include "app_init.php";
require_once "user_registration_helper.php";

$message = "";
$name = "";
$email = "";
$role = "";

$firebase_config = [
    'apiKey' => '',
    'authDomain' => '',
    'projectId' => '',
    'storageBucket' => '',
    'messagingSenderId' => '',
    'appId' => '',
];
if (function_exists('rc_firebase_config')) {
    $firebase_config = rc_firebase_config();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';

    $result = rc_register_user($conn, $name, $email, $password, $role);
    if (!$result['ok']) {
        $message = $result['error'];
    } else {
        $_SESSION['success_message'] = "Registration successful! Please login with your {$result['role']} account.";
        $_SESSION['prefill_email'] = $result['email'];
        header("Location: login.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Create a RentConnect account to find or list properties.">
    <meta name="theme-color" content="#1f8f67">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="manifest" href="/manifest.json">
    <title>Sign Up - RentConnect</title>
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

        .panel {
            padding: clamp(24px, 4vw, 42px);
        }

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

        .divider {
            margin: 14px 0;
            text-align: center;
            color: var(--muted);
            font-size: 0.85rem;
            position: relative;
        }

        .divider::before,
        .divider::after {
            content: "";
            position: absolute;
            top: 50%;
            width: 36%;
            height: 1px;
            background: var(--line);
        }

        .divider::before { left: 0; }
        .divider::after { right: 0; }

        .google-btn {
            margin-top: 6px;
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 11px;
            padding: 11px 14px;
            background: #fff;
            color: var(--ink);
            font-weight: 700;
            cursor: pointer;
        }

        .google-btn:hover {
            background: #f8fafb;
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
            <p>Join as a renter or landlord and manage your rental journey with a simpler, modern platform.</p>
        </section>

        <section class="panel form-panel">
            <h2>Sign Up</h2>
            <p class="subtitle">Fill out your details to create an account.</p>

            <?php if ($message): ?>
                <p class="alert"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>

            <form method="post" action="">
                <label for="name">Full Name</label>
                <input id="name" type="text" name="name" required>

                <label for="email">Email Address</label>
                <input id="email" type="email" name="email" required>

                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>

                <label for="role">Account Type</label>
                <select id="role" name="role" required>
                    <option value="renter">Renter</option>
                    <option value="landlord">Landlord</option>
                </select>

                <button class="submit-btn" type="submit">Create Account</button>
            </form>

            <div class="divider">or</div>
            <label for="google_role">Google Account Type</label>
            <select id="google_role">
                <option value="renter">Renter</option>
                <option value="landlord">Landlord</option>
            </select>
            <button type="button" id="googleSignupBtn" class="google-btn">Continue with Google</button>

            <div class="links">
                <p>Already have an account? <a href="login.php">Login</a></p>
            </div>

            <a class="home-btn" href="index.php">Back to Home</a>
        </section>
    </div>

    <script>
    const firebaseConfig = <?php echo json_encode($firebase_config, JSON_UNESCAPED_SLASHES); ?>;

    function firebaseErrorMessage(err, fallback) {
        const code = String(err?.code || '');
        if (code === 'auth/operation-not-allowed') {
            return "Google sign-in is disabled in Firebase. Enable it in Firebase Console > Authentication > Sign-in method > Google, and add this domain to Authorized domains.";
        }
        if (code === 'auth/unauthorized-domain') {
            return "This domain is not authorized for Firebase Auth. Add it in Firebase Console > Authentication > Settings > Authorized domains.";
        }
        return err?.message || fallback;
    }

    async function signUpWithGoogle() {
        if (!firebaseConfig.apiKey || !firebaseConfig.projectId) {
            alert("Firebase config is missing. Set FIREBASE_* environment variables first.");
            return;
        }

        const [{ initializeApp }, { getAuth, GoogleAuthProvider, signInWithPopup }] = await Promise.all([
            import("https://www.gstatic.com/firebasejs/10.12.5/firebase-app.js"),
            import("https://www.gstatic.com/firebasejs/10.12.5/firebase-auth.js")
        ]);

        const app = initializeApp(firebaseConfig, "rentconnect-signup");
        const auth = getAuth(app);
        const provider = new GoogleAuthProvider();
        const result = await signInWithPopup(auth, provider);
        const idToken = await result.user.getIdToken();
        const role = document.getElementById("google_role").value;

        const response = await fetch("firebase_auth.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ idToken, role })
        });

        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error || "Google signup failed");
        }

        window.location.href = data.redirect;
    }

    document.getElementById("googleSignupBtn").addEventListener("click", async function () {
        try {
            await signUpWithGoogle();
        } catch (err) {
            alert(firebaseErrorMessage(err, "Google signup failed."));
        }
    });

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
