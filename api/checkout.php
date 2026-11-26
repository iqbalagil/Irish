<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$productId = isset($payload['productId']) ? (int) $payload['productId'] : 0;
$buyerName = is_string($payload['buyerName'] ?? null) ? trim($payload['buyerName']) : '';
$buyerEmail = is_string($payload['buyerEmail'] ?? null) ? strtolower(trim($payload['buyerEmail'])) : '';
$quantity = isset($payload['quantity']) ? (int) $payload['quantity'] : 0;
$paymentMethod = is_string($payload['paymentMethod'] ?? null) ? trim($payload['paymentMethod']) : '';

$allowedMethods = ['Transfer bank', 'E-wallet', 'Tunai'];

if ($productId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Produk tidak valid.']);
    exit;
}
if ($buyerName === '' || mb_strlen($buyerName, 'UTF-8') > 80) {
    http_response_code(400);
    echo json_encode(['error' => 'Nama pembeli wajib diisi (maksimal 80 karakter).']);
    exit;
}
if ($buyerEmail === '' || strlen($buyerEmail) > 254 || !filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Format email pembeli tidak valid.']);
    exit;
}
if ($quantity < 1 || $quantity > 20) {
    http_response_code(400);
    echo json_encode(['error' => 'Jumlah pembelian harus 1 sampai 20.']);
    exit;
}
if (!in_array($paymentMethod, $allowedMethods, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Metode pembayaran tidak valid.']);
    exit;
}

$pdo = db();

try {
    $pdo->beginTransaction();

    $stmtProduct = $pdo->prepare(
        "SELECT id, nama_produk, harga, stok
         FROM product_data
         WHERE id = :id AND status_pengajuan = 'disetujui'
         FOR UPDATE"
    );
    $stmtProduct->execute([':id' => $productId]);
    $product = $stmtProduct->fetch();

    if (!$product) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Produk tidak ditemukan.']);
        exit;
    }

    if ((int) $product['stok'] < $quantity) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['error' => 'Stok produk tidak mencukupi.']);
        exit;
    }

    $hargaSatuan = (int) $product['harga'];
    $total = $quantity * $hargaSatuan;
    $kodeTransaksi = 'TRX-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $akunId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

    $stmtInsert = $pdo->prepare(
        "INSERT INTO transaksi_data
            (kode_transaksi, akun_id, product_id, nama_produk, nama_pembeli, email_pembeli, jumlah, harga_satuan, total, metode_pembayaran, status)
         VALUES
            (:kode_transaksi, :akun_id, :product_id, :nama_produk, :nama_pembeli, :email_pembeli, :jumlah, :harga_satuan, :total, :metode_pembayaran, 'dibayar')"
    );
    $stmtInsert->execute([
        ':kode_transaksi'    => $kodeTransaksi,
        ':akun_id'           => $akunId,
        ':product_id'        => (int) $product['id'],
        ':nama_produk'       => $product['nama_produk'],
        ':nama_pembeli'      => $buyerName,
        ':email_pembeli'     => $buyerEmail,
        ':jumlah'            => $quantity,
        ':harga_satuan'      => $hargaSatuan,
        ':total'             => $total,
        ':metode_pembayaran' => $paymentMethod,
    ]);

    $stmtUpdateStock = $pdo->prepare(
        'UPDATE product_data SET stok = stok - :qty WHERE id = :id'
    );
    $stmtUpdateStock->execute([
        ':qty' => $quantity,
        ':id'  => (int) $product['id'],
    ]);

    $pdo->commit();

    echo json_encode([
        'success'       => true,
        'id'            => $kodeTransaksi,
        'productName'   => $product['nama_produk'],
        'buyerName'     => $buyerName,
        'buyerEmail'    => $buyerEmail,
        'quantity'      => $quantity,
        'total'         => $total,
        'paymentMethod' => $paymentMethod,
        'status'        => 'dibayar',
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('api/checkout.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Transaksi gagal diproses.']);
}
