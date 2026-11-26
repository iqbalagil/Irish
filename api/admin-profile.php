<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../koneksi.php';
require_login('../login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metode tidak didukung.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!valid_csrf()) {
    http_response_code(403);
    echo json_encode(['error' => 'Form tidak valid. Muat ulang halaman dan coba lagi.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawInput = file_get_contents('php://input');
$data = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')
    ? json_decode($rawInput ?: '[]', true, 512, JSON_THROW_ON_ERROR)
    : $_POST;
if (!is_array($data)) {
    $data = [];
}

$nama = trim((string) ($data['nama_admin'] ?? ''));
$email = strtolower(trim((string) ($data['email_admin'] ?? '')));

if ($nama === '' || mb_strlen($nama, 'UTF-8') > 50 || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['error' => 'Nama dan email admin wajib diisi dengan format yang benar.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id FROM admin_data ORDER BY id LIMIT 1');
    $stmt->execute();
    $adminId = $stmt->fetchColumn();

    if ($adminId === false) {
        $stmt = $pdo->prepare('INSERT INTO admin_data (nama_admin, email_admin) VALUES (:nama, :email)');
        $stmt->execute(['nama' => $nama, 'email' => $email]);
        $adminId = (int) $pdo->lastInsertId();
    } else {
        $stmt = $pdo->prepare('UPDATE admin_data SET nama_admin = :nama, email_admin = :email WHERE id = :id');
        $stmt->execute(['nama' => $nama, 'email' => $email, 'id' => (int) $adminId]);
    }

    echo json_encode([
        'id' => (int) $adminId,
        'nama_admin' => $nama,
        'email_admin' => $email,
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Profil admin gagal disimpan.'], JSON_UNESCAPED_UNICODE);
}
