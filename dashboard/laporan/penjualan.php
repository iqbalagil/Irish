<?php

declare(strict_types=1);

require __DIR__ . '/../../auth.php';
require_login('../../login.php');

require_once __DIR__ . '/../../dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$pdo = db();

$summary = $pdo->query(
    'SELECT total_penjualan, total_pendapatan, penjualan_bulan_ini, pendapatan_hari_ini
     FROM v_ringkasan_penjualan'
)->fetch() ?: [
    'total_penjualan'     => 0,
    'total_pendapatan'    => 0,
    'penjualan_bulan_ini' => 0,
    'pendapatan_hari_ini' => 0,
];

$transaksi = $pdo->query(
    "SELECT kode_transaksi, nama_produk, nama_pembeli, email_pembeli,
            jumlah, harga_satuan, total, metode_pembayaran, status,
            DATE_FORMAT(tanggal, '%d %b %Y %H:%i') AS tanggal_fmt
     FROM transaksi_data
     WHERE status <> 'dibatalkan'
     ORDER BY tanggal DESC"
)->fetchAll();

$tanggalCetak = date('d F Y, H:i');

function rupiah(int|float|string $amount): string
{
    return 'Rp' . number_format((float) $amount, 0, ',', '.');
}

$formattedPendapatan = rupiah($summary['total_pendapatan'] ?? 0);
$formattedHariIni    = rupiah($summary['pendapatan_hari_ini'] ?? 0);
$totalPenjualan      = (int) ($summary['total_penjualan'] ?? 0);
$penjualanBulanIni   = (int) ($summary['penjualan_bulan_ini'] ?? 0);

$rowsHtml = '';
if (empty($transaksi)) {
    $rowsHtml = '<tr><td colspan="10" style="padding:14px; text-align:center; color:#666;">Belum ada data transaksi aktif.</td></tr>';
} else {
    foreach ($transaksi as $i => $trx) {
        $no = $i + 1;
        $bg = ($i % 2 === 0) ? '#fff' : '#fafafa';
        $rowsHtml .= "<tr style=\"background:{$bg}\">
            <td style=\"padding:7px 10px; border-bottom:1px solid #eee;\">{$no}</td>
            <td style=\"padding:7px 10px; border-bottom:1px solid #eee;\">" . htmlspecialchars((string) $trx['kode_transaksi'], ENT_QUOTES, 'UTF-8') . "</td>
            <td style=\"padding:7px 10px; border-bottom:1px solid #eee;\">" . htmlspecialchars((string) $trx['nama_produk'], ENT_QUOTES, 'UTF-8') . "</td>
            <td style=\"padding:7px 10px; border-bottom:1px solid #eee;\">" . htmlspecialchars((string) $trx['nama_pembeli'], ENT_QUOTES, 'UTF-8') . "</td>
            <td style=\"padding:7px 10px; border-bottom:1px solid #eee; text-align:right;\">" . (int) $trx['jumlah'] . "</td>
            <td style=\"padding:7px 10px; border-bottom:1px solid #eee; text-align:right;\">" . rupiah($trx['harga_satuan']) . "</td>
            <td style=\"padding:7px 10px; border-bottom:1px solid #eee; text-align:right; font-weight:600;\">" . rupiah($trx['total']) . "</td>
            <td style=\"padding:7px 10px; border-bottom:1px solid #eee;\">" . htmlspecialchars((string) $trx['metode_pembayaran'], ENT_QUOTES, 'UTF-8') . "</td>
            <td style=\"padding:7px 10px; border-bottom:1px solid #eee;\">" . htmlspecialchars((string) $trx['status'], ENT_QUOTES, 'UTF-8') . "</td>
            <td style=\"padding:7px 10px; border-bottom:1px solid #eee; font-size:11px;\">" . htmlspecialchars((string) $trx['tanggal_fmt'], ENT_QUOTES, 'UTF-8') . "</td>
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
    <h1>IRISH — Laporan Penjualan</h1>
    <p>Dicetak pada: {$tanggalCetak} &nbsp;|&nbsp; Total {$totalPenjualan} transaksi aktif</p>
  </div>

  <table style="margin: 0 24px 20px; width: calc(100% - 48px);">
    <tr>
      <td style="padding:12px 16px; background:#fff9f9; border:1px solid #ddd; width:25%;">
        <strong style="font-size:16px; color:#DB4141;">{$totalPenjualan}</strong><br>
        <span style="font-size:11px; color:#666;">Total Transaksi</span>
      </td>
      <td style="padding:12px 16px; background:#fff9f9; border:1px solid #ddd; width:25%;">
        <strong style="font-size:16px; color:#DB4141;">{$formattedPendapatan}</strong><br>
        <span style="font-size:11px; color:#666;">Total Pendapatan</span>
      </td>
      <td style="padding:12px 16px; background:#fff9f9; border:1px solid #ddd; width:25%;">
        <strong style="font-size:16px; color:#DB4141;">{$penjualanBulanIni}</strong><br>
        <span style="font-size:11px; color:#666;">Penjualan Bulan Ini</span>
      </td>
      <td style="padding:12px 16px; background:#fff9f9; border:1px solid #ddd; width:25%;">
        <strong style="font-size:16px; color:#DB4141;">{$formattedHariIni}</strong><br>
        <span style="font-size:11px; color:#666;">Pendapatan Hari Ini</span>
      </td>
    </tr>
  </table>

  <table style="margin: 0 24px; width: calc(100% - 48px);">
    <thead>
      <tr>
        <th>#</th><th>Kode</th><th>Produk</th><th>Pembeli</th>
        <th style="text-align:right;">Jml</th><th style="text-align:right;">Harga Sat.</th><th style="text-align:right;">Total</th>
        <th>Metode</th><th>Status</th><th>Tanggal</th>
      </tr>
    </thead>
    <tbody>
      {$rowsHtml}
    </tbody>
  </table>

  <div class="footer">Irish Admin &mdash; Laporan Penjualan &mdash; {$tanggalCetak}</div>
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
$dompdf->stream('laporan-penjualan-' . date('Ymd') . '.pdf', ['Attachment' => true]);
exit;
