<?php
session_start();
include "app_init.php";

$message = "";

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = trim((string) ($_POST['password'] ?? ''));

    $user = rc_mig_get_user_auth_by_email($conn, $email);

    if ($user) {
        $hashedPassword = (string) ($user['password'] ?? '');
        if ($hashedPassword !== '' && password_verify($password, $hashedPassword)) {
            $id = (int) ($user['id'] ?? 0);
            $name = (string) ($user['name'] ?? '');
            $role = (string) ($user['role'] ?? 'renter');

            $_SESSION['user_id'] = $id;
            $_SESSION['name'] = $name;
            $_SESSION['role'] = $role;

            // Redirect based on role
            if ($role === 'super_admin') {
                header("Location: super_admin_dashboard.php");
            } elseif ($role === 'landlord') {
                header("Location: landlord_dashboard.php");
            } elseif ($role === 'renter') {
                header("Location: renter_dashboard.php");
            }
            exit();
        } else {
            $message = "Incorrect password.";
        }
    } else {
        $message = "No account found with this email.";
    }
}

// Get prefilled email from signup
$prefill_email = $_SESSION['prefill_email'] ?? '';
unset($_SESSION['prefill_email']);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login to RentConnect and access your rental account.">
    <meta name="theme-color" content="#1f8f67">
    <link rel="apple-touch-icon" href="/favicon.svg" />
    <link rel="icon" href="/favicon.svg" />
    <link rel="manifest" href="/manifest.json">
    <title>Login - RentConnect</title>
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
            width: min(940px, 96vw);
            display: grid;
            grid-template-columns: 1fr 1.05fr;
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
                linear-gradient(145deg, rgba(16, 74, 57, 0.92), rgba(31, 143, 103, 0.85)),
                url('images/home-banner.png') center/cover no-repeat;
            display: grid;
            align-content: end;
            min-height: 400px;
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
            max-width: 36ch;
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
            border: 1px solid;
        }

        .alert.error {
            border-color: rgba(190, 43, 43, 0.35);
            background: rgba(236, 68, 68, 0.09);
            color: #8f1f1f;
        }

        .alert.success {
            border-color: rgba(39, 139, 90, 0.35);
            background: rgba(51, 170, 112, 0.11);
            color: #145734;
        }

        form { display: grid; gap: 12px; }

        label {
            font-size: 0.92rem;
            font-weight: 700;
        }

        input {
            width: 100%;
            padding: 12px 13px;
            border: 1px solid var(--line);
            border-radius: 10px;
            font: inherit;
            background: #fff;
        }

        input:focus {
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

        .auth-note {
            margin-top: 8px;
            color: var(--muted);
            font-size: 0.86rem;
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
            <h1>Welcome Back</h1>
            <p>Sign in to continue browsing homes, managing requests, and chatting with landlords.</p>
        </section>

        <section class="panel form-panel">
            <h2>Login</h2>
            <p class="subtitle">Use your account details to continue.</p>

            <?php if (isset($_SESSION['success_message'])): ?>
                <p class="alert success"><?php echo htmlspecialchars($_SESSION['success_message']); ?></p>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if ($message): ?>
                <p class="alert error"><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>

            <form method="post" action="">
                <label for="email">Email Address</label>
                <input id="email" type="email" name="email" required value="<?php echo htmlspecialchars($prefill_email); ?>">

                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>

                <button class="submit-btn" type="submit">Sign In</button>
            </form>

            <div class="divider">or</div>
            <button type="button" id="googleLoginBtn" class="google-btn">Sign in with Google</button>
            <p class="auth-note">Google sign-in uses Firebase Authentication.</p>

            <div class="links">
                <a href="forgot-password.php">Forgot Password?</a>
                <p>Don't have an account? <a href="signup.php">Create one</a></p>
            </div>

            <a class="home-btn" href="index.php">Back to Home</a>
        </section>
    </div>

    <script>
        const firebaseConfig = <?php echo json_encode($firebase_config, JSON_UNESCAPED_SLASHES); ?>;
        let firebaseAuthRuntimePromise = null;

        function firebaseErrorMessage(err, fallback) {
            const code = String(err?.code || '');
            if (code === 'auth/operation-not-allowed') {
                return "Google sign-in is disabled in Firebase. Enable it in Firebase Console > Authentication > Sign-in method > Google, and add this domain to Authorized domains.";
            }
            if (code === 'auth/unauthorized-domain') {
                return "This domain is not authorized for Firebase Auth. Add it in Firebase Console > Authentication > Settings > Authorized domains.";
            }
            if (code === 'auth/popup-blocked' || code === 'auth/popup-closed-by-user') {
                return "Popup sign-in was blocked. We will switch to full-page Google redirect.";
            }
            return err?.message || fallback;
        }

        function isPopupFailure(err) {
            const code = String(err?.code || '');
            return code === 'auth/popup-blocked'
                || code === 'auth/popup-closed-by-user'
                || code === 'auth/cancelled-popup-request'
                || code === 'auth/operation-not-supported-in-this-environment';
        }

        window.onload = function () {
            const emailField = document.getElementById("email");
            const passwordField = document.getElementById("password");
            if (emailField.value.trim() !== "") {
                passwordField.focus();
            } else {
                emailField.focus();
            }
        };

        async function getFirebaseAuthRuntime() {
            if (firebaseAuthRuntimePromise) {
                return firebaseAuthRuntimePromise;
            }

            firebaseAuthRuntimePromise = (async () => {
                const [{ initializeApp }, {
                    getAuth,
                    GoogleAuthProvider,
                    signInWithPopup,
                    signInWithRedirect,
                    getRedirectResult,
                    setPersistence,
                    browserLocalPersistence
                }] = await Promise.all([
                    import("https://www.gstatic.com/firebasejs/10.12.5/firebase-app.js"),
                    import("https://www.gstatic.com/firebasejs/10.12.5/firebase-auth.js")
                ]);

                const app = initializeApp(firebaseConfig, "rentconnect-login");
                const auth = getAuth(app);
                await setPersistence(auth, browserLocalPersistence);

                return {
                    auth,
                    provider: new GoogleAuthProvider(),
                    signInWithPopup,
                    signInWithRedirect,
                    getRedirectResult
                };
            })();

            return firebaseAuthRuntimePromise;
        }

        async function sendFirebaseTokenToBackend(idToken) {
            const response = await fetch("firebase_auth.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    idToken: idToken,
                    role: "renter"
                })
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.error || "Firebase login failed");
            }

            window.location.href = data.redirect;
        }

        async function handleGoogleRedirectResult() {
            if (!firebaseConfig.apiKey || !firebaseConfig.projectId) {
                return;
            }

            const runtime = await getFirebaseAuthRuntime();
            const redirectResult = await runtime.getRedirectResult(runtime.auth);
            if (redirectResult?.user) {
                const idToken = await redirectResult.user.getIdToken();
                await sendFirebaseTokenToBackend(idToken);
            }
        }

        async function signInWithGoogle() {
            if (!firebaseConfig.apiKey || !firebaseConfig.projectId) {
                alert("Firebase config is missing. Set FIREBASE_* environment variables first.");
                return;
            }

            const runtime = await getFirebaseAuthRuntime();
            try {
                const result = await runtime.signInWithPopup(runtime.auth, runtime.provider);
                const idToken = await result.user.getIdToken();
                await sendFirebaseTokenToBackend(idToken);
            } catch (err) {
                if (isPopupFailure(err)) {
                    await runtime.signInWithRedirect(runtime.auth, runtime.provider);
                    return;
                }
                throw err;
            }
        }

        document.getElementById("googleLoginBtn").addEventListener("click", async function () {
            try {
                await signInWithGoogle();
            } catch (err) {
                alert(firebaseErrorMessage(err, "Google login failed."));
            }
        });

        handleGoogleRedirectResult().catch((err) => {
            alert(firebaseErrorMessage(err, "Google login failed after redirect."));
        });
    </script>
</body>
</html>
