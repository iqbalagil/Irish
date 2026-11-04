<?php

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

const USERS_FILE = __DIR__ . '/data/users.php';
const USERS_HEADER = "<?php http_response_code(403); exit; ?>\n";

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

function read_users($file): array
{
    rewind($file);
    $contents = stream_get_contents($file);

    if ($contents === false) {
        throw new RuntimeException('Data akun tidak dapat dibaca.');
    }
    if ($contents === '') {
        return [];
    }
    if (!str_starts_with($contents, USERS_HEADER)) {
        throw new RuntimeException('Format data akun tidak sesuai.');
    }

    $users = json_decode(substr($contents, strlen(USERS_HEADER)), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($users)) {
        throw new RuntimeException('Format data akun tidak sesuai.');
    }

    return $users;
}

function find_user(string $email): ?array
{
    if (!is_file(USERS_FILE)) {
        return null;
    }

    $file = @fopen(USERS_FILE, 'rb');
    if ($file === false) {
        throw new RuntimeException('Data akun tidak dapat dibuka.');
    }

    try {
        if (!flock($file, LOCK_SH)) {
            throw new RuntimeException('Data akun sedang digunakan.');
        }
        return read_users($file)[$email] ?? null;
    } finally {
        flock($file, LOCK_UN);
        fclose($file);
    }
}

function register_user(string $username, string $email, string $password): bool
{
    $file = @fopen(USERS_FILE, 'c+');
    if ($file === false) {
        throw new RuntimeException('Data akun tidak dapat disimpan.');
    }

    try {
        if (!flock($file, LOCK_EX)) {
            throw new RuntimeException('Data akun sedang digunakan.');
        }

        $users = read_users($file);
        if (isset($users[$email])) {
            return false;
        }

        $users[$email] = [
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ];
        $contents = USERS_HEADER . json_encode($users, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";

        rewind($file);
        if (!ftruncate($file, 0) || fwrite($file, $contents) !== strlen($contents) || !fflush($file)) {
            throw new RuntimeException('Data akun tidak dapat disimpan.');
        }

        return true;
    } finally {
        flock($file, LOCK_UN);
        fclose($file);
    }
}
