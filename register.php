<?php
require __DIR__ . '/auth.php';

if (!empty($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

$username = '';
$email = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['nama'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmation = $_POST['password2'] ?? '';

    if (!valid_csrf()) {
        $error = 'Form tidak valid. Muat ulang halaman dan coba lagi.';
    } elseif (!is_string($username) || !is_string($email)
        || !is_string($password) || !is_string($confirmation)) {
        $error = 'Semua kolom wajib diisi.';
        $username = '';
        $email = '';
    } else {
        $username = trim($username);
        $email = strtolower(trim($email));

        if ($username === '' || $email === '' || $password === '' || $confirmation === '') {
            $error = 'Semua kolom wajib diisi.';
        } elseif (!mb_check_encoding($username, 'UTF-8') || mb_strlen($username, 'UTF-8') > 40) {
            $error = 'Username maksimal 40 karakter.';
        } elseif (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email belum benar.';
        } elseif (!mb_check_encoding($password, 'UTF-8')
            || mb_strlen($password, 'UTF-8') < 8 || strlen($password) > 72) {
            $error = 'Kata sandi harus 8 sampai 72 karakter.';
        } elseif ($password !== $confirmation) {
            $error = 'Konfirmasi kata sandi tidak sama.';
        } else {
            try {
                if (register_user($username, $email, $password)) {
                    header('Location: login.php?registered=1');
                    exit;
                }
                $error = 'Email sudah terdaftar.';
            } catch (Throwable $exception) {
                error_log($exception->getMessage());
                $error = 'Pendaftaran belum bisa diproses. Coba lagi nanti.';
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
    <title>Daftar — Irish</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
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
                    <form class="auth-form" id="register-form" action="register.php" method="post">
                        <h1>REGISTER</h1>
                        <p>Enter your information via username, email and password</p>
                        <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">

                        <div class="field">
                            <label for="nama">Username</label>
                            <input type="text" id="nama" name="nama" placeholder="user123" value="<?= escape_html($username) ?>" maxlength="40" autocomplete="username" required>
                        </div>
                        <div class="field">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" placeholder="user@email.com" value="<?= escape_html($email) ?>" autocomplete="email" required>
                        </div>
                        <div class="field-row">
                            <div class="field">
                                <label for="password">Password</label>
                                <input type="password" id="password" name="password" placeholder="Min. 8 character" minlength="8" autocomplete="new-password" required>
                            </div>
                            <div class="field">
                                <label for="password2">Password Confirmation</label>
                                <input type="password" id="password2" name="password2" placeholder="Fill password again" minlength="8" autocomplete="new-password" required>
                            </div>
                        </div>
                        <p>Already have an account <a href="login.php" class="link">Sign In</a></p>

                        <?php if ($error): ?>
                            <p id="register-message" class="auth-message error" role="alert"><?= escape_html($error) ?></p>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-primary btn-block auth-submit">SIGN UP</button>

                    </form>
                </div>
            </div>

            <aside class="auth-visual">
            </aside>
        </section>
    </main>

</body>
</html>
