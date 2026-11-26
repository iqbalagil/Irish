<?php
require __DIR__ . '/../auth.php';
require_login('../login.php');

$pdo = db();
$flashMessage = '';
$errorMessage = '';

function save_booking_image(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Gagal mengunggah gambar tempat.');
    }

    $tmpPath = $file['tmp_name'] ?? '';
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpPath);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Format gambar harus JPG, PNG, WEBP, atau GIF.');
    }

    $uploadDir = __DIR__ . '/../asset/uploads/booking';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Direktori upload tidak dapat dibuat.');
    }

    $filename = 'room-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($tmpPath, $destination)) {
        throw new RuntimeException('Gagal menyimpan file gambar.');
    }

    return 'asset/uploads/booking/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!valid_csrf()) {
        $errorMessage = 'Form tidak valid. Muat ulang halaman dan coba lagi.';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'create') {
                $nama = trim((string) ($_POST['nama'] ?? ''));
                $deskripsi = trim((string) ($_POST['deskripsi'] ?? ''));
                $waktuRaw = trim((string) ($_POST['waktu'] ?? ''));
                $harga = (int) ($_POST['harga'] ?? -1);

                $waktuDt = DateTime::createFromFormat('Y-m-d\TH:i', $waktuRaw);
                if ($nama === '' || mb_strlen($nama, 'UTF-8') > 100) {
                    throw new RuntimeException('Nama tempat wajib diisi (maksimal 100 karakter).');
                }
                if ($deskripsi === '') {
                    throw new RuntimeException('Deskripsi wajib diisi.');
                }
                if (!$waktuDt) {
                    throw new RuntimeException('Format waktu booking tidak valid.');
                }
                if ($harga < 0) {
                    throw new RuntimeException('Harga sewa tidak boleh negatif.');
                }
                if (empty($_FILES['gambar']['name'])) {
                    throw new RuntimeException('Gambar tempat wajib diunggah.');
                }

                $gambarPath = save_booking_image($_FILES['gambar']);
                $stmt = $pdo->prepare(
                    'INSERT INTO tempat_booking (nama_tempat, deskripsi, waktu_booking, harga_sewa, gambar, aktif)
                     VALUES (:nama, :deskripsi, :waktu, :harga, :gambar, 1)'
                );
                $stmt->execute([
                    ':nama'      => $nama,
                    ':deskripsi' => $deskripsi,
                    ':waktu'     => $waktuDt->format('Y-m-d H:i:s'),
                    ':harga'     => $harga,
                    ':gambar'    => $gambarPath,
                ]);

                header('Location: bookingDashboard.php?status=created');
                exit;
            }

            if ($action === 'update') {
                $id = (int) ($_POST['id'] ?? 0);
                $nama = trim((string) ($_POST['nama'] ?? ''));
                $deskripsi = trim((string) ($_POST['deskripsi'] ?? ''));
                $waktuRaw = trim((string) ($_POST['waktu'] ?? ''));
                $harga = (int) ($_POST['harga'] ?? -1);

                $waktuDt = DateTime::createFromFormat('Y-m-d\TH:i', $waktuRaw);
                if ($id <= 0) {
                    throw new RuntimeException('ID tempat tidak valid.');
                }
                if ($nama === '' || mb_strlen($nama, 'UTF-8') > 100) {
                    throw new RuntimeException('Nama tempat wajib diisi (maksimal 100 karakter).');
                }
                if ($deskripsi === '') {
                    throw new RuntimeException('Deskripsi wajib diisi.');
                }
                if (!$waktuDt) {
                    throw new RuntimeException('Format waktu booking tidak valid.');
                }
                if ($harga < 0) {
                    throw new RuntimeException('Harga sewa tidak boleh negatif.');
                }

                $existing = $pdo->prepare('SELECT gambar FROM tempat_booking WHERE id = :id LIMIT 1');
                $existing->execute([':id' => $id]);
                $row = $existing->fetch();
                if (!$row) {
                    throw new RuntimeException('Data tempat tidak ditemukan.');
                }

                $gambarPath = $row['gambar'];
                if (!empty($_FILES['gambar']['name'])) {
                    $gambarPath = save_booking_image($_FILES['gambar']);
                }

                $stmt = $pdo->prepare(
                    'UPDATE tempat_booking
                     SET nama_tempat = :nama, deskripsi = :deskripsi, waktu_booking = :waktu, harga_sewa = :harga, gambar = :gambar
                     WHERE id = :id'
                );
                $stmt->execute([
                    ':nama'      => $nama,
                    ':deskripsi' => $deskripsi,
                    ':waktu'     => $waktuDt->format('Y-m-d H:i:s'),
                    ':harga'     => $harga,
                    ':gambar'    => $gambarPath,
                    ':id'        => $id,
                ]);

                header('Location: bookingDashboard.php?status=updated');
                exit;
            }

            if ($action === 'delete') {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new RuntimeException('ID tempat tidak valid.');
                }

                $stmt = $pdo->prepare('UPDATE tempat_booking SET aktif = 0 WHERE id = :id');
                $stmt->execute([':id' => $id]);

                header('Location: bookingDashboard.php?status=deleted');
                exit;
            }

            if ($action === 'booking_status') {
                $bookingId = (int) ($_POST['booking_id'] ?? 0);
                $newStatus = trim((string) ($_POST['booking_status'] ?? ''));
                $allowedStatuses = ['menunggu', 'dikonfirmasi', 'ditolak', 'selesai', 'dibatalkan'];

                if ($bookingId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
                    throw new RuntimeException('Status reservasi tidak valid.');
                }

                $stmt = $pdo->prepare('UPDATE booking_data SET status = :status WHERE id = :id');
                $stmt->execute([
                    ':status' => $newStatus,
                    ':id'     => $bookingId,
                ]);

                header('Location: bookingDashboard.php?status=booking_updated');
                exit;
            }
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
        }
    }
}

$statusParam = $_GET['status'] ?? '';
if ($statusParam === 'created') {
    $flashMessage = 'Tempat booking berhasil ditambahkan ke database.';
} elseif ($statusParam === 'updated') {
    $flashMessage = 'Tempat booking berhasil diperbarui.';
} elseif ($statusParam === 'deleted') {
    $flashMessage = 'Tempat booking berhasil dinonaktifkan.';
} elseif ($statusParam === 'booking_updated') {
    $flashMessage = 'Status reservasi pelanggan berhasil diperbarui.';
}

$adminProfile = get_admin_profile();

$places = $pdo->query(
    'SELECT id, nama_tempat, deskripsi, waktu_booking, harga_sewa, gambar, created_at
     FROM tempat_booking
     WHERE aktif = 1
     ORDER BY created_at DESC'
)->fetchAll();

$reservations = $pdo->query(
    'SELECT b.id, b.nama_pemesan, b.nomor_hp, b.tanggal, b.jam_mulai, b.jam_selesai, b.keperluan, b.total_biaya, b.status,
            t.nama_tempat
     FROM booking_data b
     INNER JOIN tempat_booking t ON t.id = b.tempat_id
     ORDER BY b.tanggal DESC, b.jam_mulai DESC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Booking — Irish</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>

    <div class="dashboard">
        <aside class="sidebar">
            <a href="../index.php" class="sidebar-brand">
                <img src="../asset/icon-dashboard.png" alt="Irish">
            </a>

            <nav class="sidebar-nav">
                <a href="adminDashboard.php">
                    <img src="../asset/dashboard/barChart-icon.png" alt="" width="19" height="19">
                    Data Penjualan
                </a>
                <a href="bookingDashboard.php" class="active">
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
                <h1>Data Booking</h1>
                <div class="topbar-profile">
                    <div>
                        <span id="nama_admin"><?= escape_html($adminProfile['nama_admin']) ?></span>
                        <small id="email_admin"><?= escape_html($adminProfile['email_admin']) ?></small>
                    </div>
                    <a href="adminDashboard.php" class="profile-edit">Ubah profil</a>
                </div>
            </div>

            <?php if ($flashMessage !== ''): ?>
                <p class="sales-message" role="status" aria-live="polite" style="margin-bottom: 16px; color: #2e9e5b; font-weight: 600;"><?= escape_html($flashMessage) ?></p>
            <?php endif; ?>
            <?php if ($errorMessage !== ''): ?>
                <p class="sales-message" role="alert" style="margin-bottom: 16px; color: var(--red); font-weight: 600;"><?= escape_html($errorMessage) ?></p>
            <?php endif; ?>

            <section class="panel">
                <div class="panel-head">
                    <h2>Tempat Booking</h2>
                </div>
                <div class="booking-grid">
                    <?php if (empty($places)): ?>
                        <p>Belum ada tempat booking aktif. Tambahkan melalui formulir di bawah.</p>
                    <?php else: ?>
                        <?php foreach ($places as $place): ?>
                            <article class="booking-card">
                                <img src="../<?= escape_html($place['gambar']) ?>" alt="<?= escape_html($place['nama_tempat']) ?>">
                                <div class="booking-body">
                                    <h3><?= escape_html($place['nama_tempat']) ?></h3>
                                    <p><?= escape_html($place['deskripsi']) ?></p>
                                    <small style="color: var(--gray); font-size: 12px;">Jadwal: <?= escape_html(date('d M Y H:i', strtotime($place['waktu_booking']))) ?></small>
                                    <span class="booking-price">Rp<?= number_format((int) $place['harga_sewa'], 0, ',', '.') ?>/jam</span>
                                    <div style="display: flex; gap: 8px; margin-top: 4px;">
                                        <button
                                            type="button"
                                            class="profile-edit js-edit-place"
                                            data-id="<?= (int) $place['id'] ?>"
                                            data-nama="<?= escape_html($place['nama_tempat']) ?>"
                                            data-deskripsi="<?= escape_html($place['deskripsi']) ?>"
                                            data-waktu="<?= escape_html(date('Y-m-d\TH:i', strtotime($place['waktu_booking']))) ?>"
                                            data-harga="<?= (int) $place['harga_sewa'] ?>"
                                        >Edit</button>
                                        <form action="bookingDashboard.php" method="post" onsubmit="return confirm('Nonaktifkan tempat booking ini?');">
                                            <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $place['id'] ?>">
                                            <button type="submit" class="btn-delete" style="margin-top: 0;">Hapus</button>
                                        </form>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <h2>Tambah Data Booking</h2>
                </div>
                <form class="booking-form" action="bookingDashboard.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create">
                    <div class="form-group">
                        <label for="nama">Nama Tempat</label>
                        <input type="text" id="nama" name="nama" placeholder="Contoh: Studio Musik" maxlength="100" required>
                    </div>
                    <div class="form-group">
                        <label for="deskripsi">Deskripsi</label>
                        <textarea id="deskripsi" name="deskripsi" rows="3" placeholder="Deskripsi tempat" required></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="waktu">Waktu Booking</label>
                            <input type="datetime-local" id="waktu" name="waktu" required>
                        </div>
                        <div class="form-group">
                            <label for="harga">Harga</label>
                            <input type="number" id="harga" name="harga" placeholder="150000" min="0" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="gambar">Gambar Tempat</label>
                        <input type="file" id="gambar" name="gambar" accept="image/*" required>
                    </div>
                    <button type="submit" class="btn-submit">Tambah Booking</button>
                </form>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <h2>Daftar Reservasi Pelanggan</h2>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Pemesan</th>
                                <th>Tempat</th>
                                <th>Tanggal &amp; Jam</th>
                                <th>Keperluan</th>
                                <th>Total Biaya</th>
                                <th>Status</th>
                                <th>Ubah Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reservations)): ?>
                                <tr>
                                    <td colspan="7">Belum ada data reservasi pelanggan.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($reservations as $res): ?>
                                    <tr>
                                        <td>
                                            <?= escape_html($res['nama_pemesan']) ?><br>
                                            <small style="color: var(--gray);"><?= escape_html($res['nomor_hp']) ?></small>
                                        </td>
                                        <td><?= escape_html($res['nama_tempat']) ?></td>
                                        <td>
                                            <?= escape_html(date('d M Y', strtotime($res['tanggal']))) ?><br>
                                            <small style="color: var(--gray);"><?= escape_html(substr($res['jam_mulai'], 0, 5)) ?> - <?= escape_html(substr($res['jam_selesai'], 0, 5)) ?></small>
                                        </td>
                                        <td><?= escape_html($res['keperluan']) ?></td>
                                        <td>Rp<?= number_format((int) $res['total_biaya'], 0, ',', '.') ?></td>
                                        <td><?= escape_html($res['status']) ?></td>
                                        <td>
                                            <form action="bookingDashboard.php" method="post" style="display: flex; gap: 6px; align-items: center;">
                                                <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                                                <input type="hidden" name="action" value="booking_status">
                                                <input type="hidden" name="booking_id" value="<?= (int) $res['id'] ?>">
                                                <select name="booking_status" style="padding: 6px 8px; border: 1px solid var(--line); border-radius: 6px;">
                                                    <?php foreach (['menunggu', 'dikonfirmasi', 'ditolak', 'selesai', 'dibatalkan'] as $st): ?>
                                                        <option value="<?= $st ?>" <?= $res['status'] === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="receipt-button">Simpan</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <dialog id="edit-place-dialog" class="profile-dialog" aria-labelledby="edit-place-title">
        <form id="edit-place-form" action="bookingDashboard.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" id="edit-place-id" name="id" value="">
            <h2 id="edit-place-title">Ubah Tempat Booking</h2>
            <label for="edit-place-nama">Nama Tempat</label>
            <input id="edit-place-nama" name="nama" type="text" maxlength="100" required>
            <label for="edit-place-deskripsi">Deskripsi</label>
            <textarea id="edit-place-deskripsi" name="deskripsi" rows="3" style="width:100%; border:1px solid var(--line); border-radius:8px; padding:10px; font:inherit;" required></textarea>
            <label for="edit-place-waktu">Waktu Booking</label>
            <input id="edit-place-waktu" name="waktu" type="datetime-local" required>
            <label for="edit-place-harga">Harga</label>
            <input id="edit-place-harga" name="harga" type="number" min="0" required>
            <label for="edit-place-gambar">Gambar Baru (opsional)</label>
            <input id="edit-place-gambar" name="gambar" type="file" accept="image/*">
            <div class="dialog-actions">
                <button type="button" id="cancel-edit-place">Batal</button>
                <button type="submit">Simpan</button>
            </div>
        </form>
    </dialog>

    <script>
        const editDialog = document.querySelector('#edit-place-dialog');
        const cancelEditBtn = document.querySelector('#cancel-edit-place');
        document.querySelectorAll('.js-edit-place').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.querySelector('#edit-place-id').value = btn.dataset.id;
                document.querySelector('#edit-place-nama').value = btn.dataset.nama;
                document.querySelector('#edit-place-deskripsi').value = btn.dataset.deskripsi;
                document.querySelector('#edit-place-waktu').value = btn.dataset.waktu;
                document.querySelector('#edit-place-harga').value = btn.dataset.harga;
                editDialog.showModal();
            });
        });
        cancelEditBtn.addEventListener('click', () => editDialog.close());
    </script>

</body>
</html>
