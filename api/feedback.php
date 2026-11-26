<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

$pdo = db();

// ── GET: Ambil daftar feedback (opsional filter product_id) ────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $productId = isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0;

        if ($productId > 0) {
            $stmt = $pdo->prepare(
                "SELECT f.id, f.product_id, p.nama_produk, f.nama_pengguna, f.pesan,
                        DATE_FORMAT(f.tanggal, '%d %b %Y') AS tanggal
                 FROM feedback_data f
                 LEFT JOIN product_data p ON p.id = f.product_id
                 WHERE f.product_id = :product_id
                 ORDER BY f.id DESC
                 LIMIT 20"
            );
            $stmt->execute([':product_id' => $productId]);
        } else {
            $stmt = $pdo->query(
                "SELECT f.id, f.product_id, p.nama_produk, f.nama_pengguna, f.pesan,
                        DATE_FORMAT(f.tanggal, '%d %b %Y') AS tanggal
                 FROM feedback_data f
                 LEFT JOIN product_data p ON p.id = f.product_id
                 ORDER BY f.id DESC
                 LIMIT 20"
            );
        }

        $rows = $stmt->fetchAll();
        echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        error_log('api/feedback.php GET error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Gagal memuat feedback.']);
    }
    exit;
}

// ── POST: Simpan feedback baru ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$csrfPost = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '';
if (!hash_equals(csrf_token(), $csrfPost)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token tidak valid. Muat ulang halaman.']);
    exit;
}

$nama      = trim((string) ($_POST['nama_pengguna'] ?? ''));
$pesan     = trim((string) ($_POST['pesan'] ?? ''));
$productId = isset($_POST['product_id']) && $_POST['product_id'] !== '' ? (int) $_POST['product_id'] : null;

if ($nama === '' || mb_strlen($nama, 'UTF-8') > 40) {
    http_response_code(400);
    echo json_encode(['error' => 'Nama wajib diisi (maksimal 40 karakter).']);
    exit;
}
if ($pesan === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Pesan ulasan tidak boleh kosong.']);
    exit;
}

if ($productId !== null) {
    if ($productId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Produk tidak valid.']);
        exit;
    }
    $checkProd = $pdo->prepare('SELECT id FROM product_data WHERE id = :id LIMIT 1');
    $checkProd->execute([':id' => $productId]);
    if (!$checkProd->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Produk tidak ditemukan.']);
        exit;
    }
}

$akunId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

try {
    $stmt = $pdo->prepare(
        'INSERT INTO feedback_data (akun_id, tempat_id, product_id, nama_pengguna, pesan)
         VALUES (:akun_id, NULL, :product_id, :nama, :pesan)'
    );
    $stmt->execute([
        ':akun_id'    => $akunId,
        ':product_id' => $productId,
        ':nama'       => $nama,
        ':pesan'      => $pesan,
    ]);

    echo json_encode(['success' => true], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('api/feedback.php POST error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Gagal menyimpan feedback.']);
}
