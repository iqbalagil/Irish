<?php

declare(strict_types=1);

ini_set('session.use_strict_mode', '1');
session_name('irish_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
header('Cache-Control: no-store, private');

require_once __DIR__ . '/koneksi.php';

function db(): PDO
{
    global $pdo;
    return $pdo;
}

function escape_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function valid_csrf(): bool
{
    return isset($_POST['csrf_token'])
        && is_string($_POST['csrf_token'])
        && hash_equals(csrf_token(), $_POST['csrf_token']);
}

function require_login(string $login_url): void
{
    if (empty($_SESSION['username'])) {
        header('Location: ' . $login_url);
        exit;
    }
}

function find_user(string $email): ?array
{
    $stmt = db()->prepare(
        'SELECT id, username, email, password_hash FROM akun_data WHERE email = :email LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function register_user(string $username, string $email, string $password): bool
{
    $pdo = db();

    $check = $pdo->prepare('SELECT id FROM akun_data WHERE email = :email LIMIT 1');
    $check->execute([':email' => $email]);
    if ($check->fetch()) {
        return false;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO akun_data (username, email, password_hash) VALUES (:username, :email, :password_hash)'
    );

    try {
        $stmt->execute([
            ':username'      => $username,
            ':email'         => $email,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        return true;
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return false;
        }
        throw $e;
    }
}

function get_admin_profile(): array
{
    $stmt = db()->query('SELECT id, nama_admin, email_admin FROM admin_data ORDER BY id ASC LIMIT 1');
    $admin = $stmt->fetch();

    if ($admin) {
        return $admin;
    }

    return [
        'id'          => 0,
        'nama_admin'  => 'Admin Irish',
        'email_admin' => 'admin@irish.id',
    ];
}

function update_admin_profile(int $id, string $namaAdmin, string $emailAdmin): bool
{
    $pdo = db();

    if ($id > 0) {
        $stmt = $pdo->prepare(
            'UPDATE admin_data SET nama_admin = :nama_admin, email_admin = :email_admin WHERE id = :id'
        );
        return $stmt->execute([
            ':nama_admin'  => $namaAdmin,
            ':email_admin' => $emailAdmin,
            ':id'          => $id,
        ]);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO admin_data (nama_admin, email_admin, password_hash) VALUES (:nama_admin, :email_admin, :password_hash)'
    );
    return $stmt->execute([
        ':nama_admin'    => $namaAdmin,
        ':email_admin'   => $emailAdmin,
        ':password_hash' => password_hash('admin12345', PASSWORD_DEFAULT),
    ]);
}
