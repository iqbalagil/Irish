<?php

declare(strict_types=1);

require __DIR__ . '/../../auth.php';
require_login('../../login.php');

require_once __DIR__ . '/../../dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$pdo = db();

$products = $pdo->query(
    "SELECT id, nama_produk, kategori, deskripsi, harga, stok, status_pengajuan,
            DATE_FORMAT(created_at, '%d %b %Y') AS created_fmt
     FROM product_data
     ORDER BY created_at DESC"
)->fetchAll();

$statsRow = $pdo->query(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN status_pengajuan = 'disetujui' THEN 1 ELSE 0 END), 0) AS aktif,
            COALESCE(SUM(CASE WHEN status_pengajuan = 'menunggu'  THEN 1 ELSE 0 END), 0) AS menunggu,
            COALESCE(SUM(CASE WHEN status_pengajuan = 'ditolak'   THEN 1 ELSE 0 END), 0) AS ditolak
     FROM product_data"
)->fetch() ?: ['total' => 0, 'aktif' => 0, 'menunggu' => 0, 'ditolak' => 0];

$tanggalCetak = date('d F Y, H:i');

function rupiah_prod(int|float|string $amount): string
{
    return 'Rp' . number_format((float) $amount, 0, ',', '.');
}

function status_color(string $s): string
{
    return match ($s) {
        'disetujui' => '#2e9e5b',
        'menunggu'  => '#c98a12',
        'ditolak'   => '#DB4141',
        default     => '#888',
    };
}

$totalProd    = (int) ($statsRow['total'] ?? 0);
$aktifProd    = (int) ($statsRow['aktif'] ?? 0);
$menungguProd = (int) ($statsRow['menunggu'] ?? 0);
$ditolakProd  = (int) ($statsRow['ditolak'] ?? 0);

$rowsHtml = '';
if (empty($products)) {
    $rowsHtml = '<tr><td colspan="7" style="padding:14px; text-align:center; color:#666;">Belum ada data produk.</td></tr>';
} else {
    foreach ($products as $i => $p) {
        $no  = $i + 1;
        $bg  = ($i % 2 === 0) ? '#fff' : '#fafafa';
        $col = status_color((string) $p['status_pengajuan']);
        $rowsHtml .= "<tr style=\"background:{$bg}\">
            <td style=\"padding:7px 10px; border-bottom:1px solid #eee;\">{$no}</td>
            <td style=\"padding:7px 10px; border-bottom:1px solid #eee;\">" . htmlspecialchars((string) $p['nama_produk'], ENT_QUOTES, 'UTF-8') . "</td>
            <td style=\"padding:7px 10px; border-bottom:1px solid #eee;\">" . htmlspecialchars((string) $p['kategori'], ENT_QUOTES, 'UTF-8') . "</td>
            <td style=\"padding:7px 10px; border-bottom:1px solid #eee; text-align:right;\">" . rupiah_prod($p['harga']) . "</td>
            <td style=\"padding:7px 10px; border-bottom:1px solid #eee; text-align:right;\">" . (int) $p['stok'] . "</td>
            <td style=\"padding:7px 10px; border-bottom:1px solid #eee;\">
                <span style=\"color:{$col}; font-weight:600;\">" . ucfirst(htmlspecialchars((string) $p['status_pengajuan'], ENT_QUOTES, 'UTF-8')) . "</span>
            </td>
            <td style=\"padding:7px 10px; border-bottom:1px solid #eee; font-size:11px;\">" . htmlspecialchars((string) $p['created_fmt'], ENT_QUOTES, 'UTF-8') . "</td>
        </tr>";
    }
}

$html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; margin: 0; padding: 0; }
  .header { background: #DB4141; color: #fff; padding: 18px 24px; margin-bottom: 20px; }
  .header h1 { font-size: 20px; margin: 0; letter-spacing: 1px; }
  .header p  { font-size: 11px; margin: 4px 0 0; opacity: 0.85; }
  table { border-collapse: collapse; }
  thead th { background: #DB4141; color: #fff; padding: 9px 10px; text-align: left; font-size: 11px; letter-spacing: 0.5px; }
  .footer { text-align: right; font-size: 10px; color: #999; padding: 16px 24px; }
</style>
</head>
<body>
  <div class="header">
    <h1>IRISH — Laporan Produk Preloved</h1>
    <p>Dicetak pada: {$tanggalCetak} &nbsp;|&nbsp; Total {$totalProd} produk terdaftar</p>
  </div>

  <table style="margin: 0 24px 20px; width: calc(100% - 48px);">
    <tr>
      <td style="padding:12px 16px; background:#fff9f9; border:1px solid #ddd; width:25%;">
        <strong style="font-size:16px; color:#DB4141;">{$totalProd}</strong><br>
        <span style="font-size:11px; color:#666;">Total Produk</span>
      </td>
      <td style="padding:12px 16px; background:#fff9f9; border:1px solid #ddd; width:25%;">
        <strong style="font-size:16px; color:#2e9e5b;">{$aktifProd}</strong><br>
        <span style="font-size:11px; color:#666;">Produk Aktif</span>
      </td>
      <td style="padding:12px 16px; background:#fff9f9; border:1px solid #ddd; width:25%;">
        <strong style="font-size:16px; color:#c98a12;">{$menungguProd}</strong><br>
        <span style="font-size:11px; color:#666;">Menunggu Persetujuan</span>
      </td>
      <td style="padding:12px 16px; background:#fff9f9; border:1px solid #ddd; width:25%;">
        <strong style="font-size:16px; color:#DB4141;">{$ditolakProd}</strong><br>
        <span style="font-size:11px; color:#666;">Ditolak / Nonaktif</span>
      </td>
    </tr>
  </table>

  <table style="margin: 0 24px; width: calc(100% - 48px);">
    <thead>
      <tr>
        <th>#</th><th>Nama Produk</th><th>Kategori</th>
        <th style="text-align:right;">Harga</th><th style="text-align:right;">Stok</th>
        <th>Status</th><th>Dibuat</th>
      </tr>
    </thead>
    <tbody>
      {$rowsHtml}
    </tbody>
  </table>

  <div class="footer">Irish Admin &mdash; Laporan Produk Preloved &mdash; {$tanggalCetak}</div>
</body>
</html>
HTML;

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('laporan-produk-' . date('Ymd') . '.pdf', ['Attachment' => true]);
exit;
