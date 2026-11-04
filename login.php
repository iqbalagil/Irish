<?php
require __DIR__ . '/auth.php';

if (!empty($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

$email = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!valid_csrf()) {
        $error = 'Form tidak valid. Muat ulang halaman dan coba lagi.';
    } elseif (!is_string($email) || !is_string($password)) {
        $error = 'Email dan kata sandi wajib diisi.';
        $email = '';
    } else {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $error = 'Isi email dan kata sandi yang benar.';
        } else {
            try {
                $user = find_user($email);
                if ($user && is_string($user['password_hash'] ?? null)
                    && password_verify($password, $user['password_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION['username'] = $user['username'];
                    header('Location: index.php');
                    exit;
                }
                $error = 'Login gagal. Email atau kata sandi tidak sesuai.';
            } catch (Throwable $exception) {
                error_log($exception->getMessage());
                $error = 'Login belum bisa diproses. Coba lagi nanti.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — Irish</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/auth.css">
</head>

<body>

    <main class="auth-page">
        <section class="auth-card">
            <div class="auth-pane">
                <a href="index.php" class="auth-logo">
                    <img src="asset/icon.png" alt="Irish">
                </a>

                <div class="auth-body">
                    <form class="auth-form" id="login-form" action="login.php" method="post">
                        <h1>LOGIN</h1>
                        <p>Enter your email and password to sign in</p>
                        <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">

                        <div class="field">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" placeholder="nama@email.com" value="<?= escape_html($email) ?>" autocomplete="email" required>
                        </div>
                        <div class="field">
                            <label for="password">Kata Sandi</label>
                            <input type="password" id="password" name="password" placeholder="Masukkan kata sandi" autocomplete="current-password"
                                required>
                        </div>
                        <p>Dont have account? <a href="register.php" class="link">Sign Up</a></p>

                        <?php if ($error): ?>
                            <p id="login-message" class="auth-message error" role="alert"><?= escape_html($error) ?></p>
                        <?php elseif (isset($_GET['registered'])): ?>
                            <p id="login-message" class="auth-message success" role="status">Register berhasil! Silakan login.</p>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-primary btn-block auth-submit">Masuk</button>
                    </form>
                </div>
            </div>

            <aside class="auth-visual">
            </aside>
        </section>
    </main>

</body>

</html>
