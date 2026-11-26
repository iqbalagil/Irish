<?php

declare(strict_types=1);

require_once __DIR__ . '/../koneksi.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $stmt = $pdo->query(
        "SELECT id, nama_produk, kategori, deskripsi, harga, stok, foto
         FROM product_data
         WHERE status_pengajuan = 'disetujui' AND stok > 0
         ORDER BY id ASC"
    );
    $rows = $stmt->fetchAll();

    $products = array_map(static function (array $row): array {
        return [
            'id'          => (int) $row['id'],
            'name'        => $row['nama_produk'],
            'category'    => $row['kategori'],
            'description' => $row['deskripsi'],
            'price'       => (int) $row['harga'],
            'stock'       => (int) $row['stok'],
            'image'       => $row['foto'],
        ];
    }, $rows);

    echo json_encode($products, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('api/products.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Gagal memuat produk dari database.']);
}
