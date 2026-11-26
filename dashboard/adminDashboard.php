<?php
require __DIR__ . '/../auth.php';
require_login('../login.php');

$pdo = db();
$salesMessage = '';
$profileError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    if (!valid_csrf()) {
        $profileError = 'Form tidak valid. Muat ulang halaman dan coba lagi.';
    } else {
        $adminId = (int) ($_POST['adminId'] ?? 0);
        $adminName = trim((string) ($_POST['adminName'] ?? ''));
        $adminEmail = strtolower(trim((string) ($_POST['adminEmail'] ?? '')));

        if ($adminName === '' || mb_strlen($adminName, 'UTF-8') > 50) {
            $profileError = 'Nama admin wajib diisi (maksimal 50 karakter).';
        } elseif ($adminEmail === '' || strlen($adminEmail) > 254 || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $profileError = 'Format email admin tidak valid.';
        } else {
            try {
                update_admin_profile($adminId, $adminName, $adminEmail);
                header('Location: adminDashboard.php?profile=updated');
                exit;
            } catch (Throwable $e) {
                error_log('Update admin profile error: ' . $e->getMessage());
                $profileError = 'Gagal menyimpan profil admin.';
            }
        }
    }
}

if (isset($_GET['profile']) && $_GET['profile'] === 'updated') {
    $salesMessage = 'Profil admin berhasil disimpan ke database.';
}
if ($profileError !== '') {
    $salesMessage = $profileError;
}

$adminProfile = get_admin_profile();

$summary = $pdo->query('SELECT total_penjualan, total_pendapatan, penjualan_bulan_ini, pendapatan_hari_ini FROM v_ringkasan_penjualan')->fetch() ?: [
    'total_penjualan'     => 0,
    'total_pendapatan'    => 0,
    'penjualan_bulan_ini' => 0,
    'pendapatan_hari_ini' => 0,
];

// Widget: Jumlah Data Keseluruhan
$widgetStats = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM product_data WHERE status_pengajuan = 'disetujui') AS total_produk_aktif,
        (SELECT COUNT(*) FROM product_data WHERE status_pengajuan = 'menunggu')  AS produk_menunggu,
        (SELECT COUNT(*) FROM booking_data WHERE status = 'menunggu')            AS booking_menunggu,
        (SELECT COUNT(*) FROM booking_data WHERE status = 'dikonfirmasi')        AS booking_dikonfirmasi,
        (SELECT COUNT(*) FROM akun_data)                                          AS total_pengguna,
        (SELECT COUNT(*) FROM feedback_data)                                      AS total_feedback
")->fetch() ?: [
    'total_produk_aktif'   => 0,
    'produk_menunggu'      => 0,
    'booking_menunggu'     => 0,
    'booking_dikonfirmasi' => 0,
    'total_pengguna'       => 0,
    'total_feedback'       => 0,
];

$transactions = $pdo->query(
    'SELECT kode_transaksi, nama_produk, nama_pembeli, email_pembeli, jumlah, total, metode_pembayaran, status, tanggal
     FROM transaksi_data
     ORDER BY tanggal DESC'
)->fetchAll();

function format_rupiah(int|float|string $amount): string
{
    return 'Rp' . number_format((float) $amount, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Penjualan — Irish</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <script src="../js/dashboard.js" defer></script>
</head>
<body>

    <div class="dashboard">
        <aside class="sidebar">
            <a href="../index.php" class="sidebar-brand">
                <img src="../asset/icon-dashboard.png" width="32" height="32" alt="Irish">
            </a>

            <nav class="sidebar-nav">
                <a href="adminDashboard.php" class="active">
                    <img src="../asset/dashboard/barChart-icon.png" alt="" width="19" height="19">
                    Data Penjualan
                </a>
                <a href="bookingDashboard.php">
                    <img src="../asset/dashboard/home-icon.png" alt="" width="19" height="19">
                    Data Booking
                </a>
                <a href="productDashboard.php">
                    <img src="../asset/dashboard/product-icon.png" alt="" width="19" height="19">
                    Data Produk
                </a>
                <a href="feedbackDashboard.php">
                    <img src="../asset/dashboard/speechBubble-icon.png" alt="" width="19" height="19">
                    Feedback Pengguna
                </a>
            </nav>

            <form class="sidebar-logout" action="../logout.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                <button type="submit">Keluar</button>
            </form>
        </aside>

        <main class="main">
            <div class="topbar">
                <h1>Data Penjualan</h1>
                <div class="topbar-profile">
                    <div>
                        <span id="nama_admin"><?= escape_html($adminProfile['nama_admin']) ?></span>
                        <small id="email_admin"><?= escape_html($adminProfile['email_admin']) ?></small>
                    </div>
                    <button type="button" id="edit-profile" class="profile-edit">Ubah profil</button>
                </div>
            </div>

            <section class="stats stats-sales">
                <div class="stat-card">
                    <div>
                        <h3 id="total-sales"><?= (int) $summary['total_penjualan'] ?></h3>
                        <p>Total Penjualan</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div>
                        <h3 id="total-revenue"><?= escape_html(format_rupiah($summary['total_pendapatan'] ?? 0)) ?></h3>
                        <p>Total Pendapatan</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div>
                        <h3 id="monthly-sales"><?= (int) $summary['penjualan_bulan_ini'] ?></h3>
                        <p>Penjualan Bulan Ini</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div>
                        <h3 id="today-revenue"><?= escape_html(format_rupiah($summary['pendapatan_hari_ini'] ?? 0)) ?></h3>
                        <p>Penjualan Hari Ini</p>
                    </div>
                </div>
            </section>

            <h2 class="section-title">Ringkasan Data</h2>
            <section class="stats stats-overview">
                <div class="stat-card">
                    <div>
                        <h3><?= (int) $widgetStats['total_produk_aktif'] ?></h3>
                        <p>Produk Aktif</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div>
                        <h3><?= (int) $widgetStats['produk_menunggu'] ?></h3>
                        <p>Produk Menunggu</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div>
                        <h3><?= (int) $widgetStats['booking_menunggu'] ?></h3>
                        <p>Booking Menunggu</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div>
                        <h3><?= (int) $widgetStats['booking_dikonfirmasi'] ?></h3>
                        <p>Booking Dikonfirmasi</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div>
                        <h3><?= (int) $widgetStats['total_pengguna'] ?></h3>
                        <p>Total Pengguna</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div>
                        <h3><?= (int) $widgetStats['total_feedback'] ?></h3>
                        <p>Total Feedback</p>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <h2>Rincian Penjualan</h2>
                    <a href="laporan/penjualan.php" class="profile-edit" target="_blank">&#11015; Download Laporan PDF</a>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Produk</th>
                                <th>Pembeli</th>
                                <th>Tanggal</th>
                                <th>Jumlah</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="sales-rows">
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="8">Belum ada transaksi. Pilih produk dari halaman utama.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $trx): ?>
                                    <tr>
                                        <td><?= escape_html($trx['kode_transaksi']) ?></td>
                                        <td><?= escape_html($trx['nama_produk']) ?></td>
                                        <td><?= escape_html($trx['nama_pembeli']) ?></td>
                                        <td><?= escape_html(date('d M Y, H:i', strtotime($trx['tanggal']))) ?></td>
                                        <td><?= (int) $trx['jumlah'] ?></td>
                                        <td><?= escape_html(format_rupiah($trx['total'])) ?></td>
                                        <td><?= escape_html($trx['status']) ?></td>
                                        <td>
                                            <button
                                                type="button"
                                                class="receipt-button"
                                                data-transaction-id="<?= escape_html($trx['kode_transaksi']) ?>"
                                                data-product-name="<?= escape_html($trx['nama_produk']) ?>"
                                                data-quantity="<?= (int) $trx['jumlah'] ?>"
                                                data-buyer-name="<?= escape_html($trx['nama_pembeli']) ?>"
                                                data-buyer-email="<?= escape_html($trx['email_pembeli']) ?>"
                                                data-payment-method="<?= escape_html($trx['metode_pembayaran']) ?>"
                                                data-total="<?= (int) $trx['total'] ?>"
                                                data-status="<?= escape_html($trx['status']) ?>"
                                            >Lihat detail</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <p id="sales-message" class="sales-message" role="status" aria-live="polite"><?= escape_html($salesMessage) ?></p>
        </main>
    </div>

    <dialog id="profile-dialog" class="profile-dialog" aria-labelledby="profile-title">
        <form id="profile-form" action="adminDashboard.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
            <input type="hidden" name="action" value="update_profile">
            <input type="hidden" name="adminId" value="<?= (int) $adminProfile['id'] ?>">
            <h2 id="profile-title">Ubah Profil Admin</h2>
            <label for="admin-name">Username admin</label>
            <input id="admin-name" name="adminName" type="text" maxlength="50" value="<?= escape_html($adminProfile['nama_admin']) ?>" required>
            <label for="admin-email">Email admin</label>
            <input id="admin-email" name="adminEmail" type="email" maxlength="254" value="<?= escape_html($adminProfile['email_admin']) ?>" required>
            <p id="profile-message" role="status" aria-live="polite"></p>
            <div class="dialog-actions">
                <button type="button" id="cancel-profile">Batal</button>
                <button type="submit">Simpan</button>
            </div>
        </form>
    </dialog>

</body>
</html>
