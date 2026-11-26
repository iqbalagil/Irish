<?php
require __DIR__ . '/../auth.php';
require_login('../login.php');

$pdo = db();
$flashMessage = '';
$errorMessage = '';

function save_product_image(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Gagal mengunggah gambar produk.');
    }

    $tmpPath = $file['tmp_name'] ?? '';
    $finfo   = new finfo(FILEINFO_MIME_TYPE);
    $mime    = $finfo->file($tmpPath);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Format gambar harus JPG, PNG, WEBP, atau GIF.');
    }

    $uploadDir = __DIR__ . '/../asset/uploads/products';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Direktori upload tidak dapat dibuat.');
    }

    $filename    = 'product-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($tmpPath, $destination)) {
        throw new RuntimeException('Gagal menyimpan file gambar.');
    }

    return 'asset/uploads/products/' . $filename;
}

function format_rupiah(int|float|string $amount): string
{
    return 'Rp' . number_format((float) $amount, 0, ',', '.');
}

$allowedStatuses = ['menunggu', 'disetujui', 'ditolak'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!valid_csrf()) {
        $errorMessage = 'Form tidak valid. Muat ulang halaman dan coba lagi.';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'create') {
                $nama      = trim((string) ($_POST['nama_produk'] ?? ''));
                $kategori  = trim((string) ($_POST['kategori'] ?? ''));
                $deskripsi = trim((string) ($_POST['deskripsi'] ?? ''));
                $harga     = (int) ($_POST['harga'] ?? 0);
                $stok      = (int) ($_POST['stok'] ?? -1);

                if ($nama === '' || mb_strlen($nama, 'UTF-8') > 100) {
                    throw new RuntimeException('Nama produk wajib diisi (maksimal 100 karakter).');
                }
                if ($kategori === '' || mb_strlen($kategori, 'UTF-8') > 50) {
                    throw new RuntimeException('Kategori wajib diisi (maksimal 50 karakter).');
                }
                if ($deskripsi === '') {
                    throw new RuntimeException('Deskripsi produk wajib diisi.');
                }
                if ($harga <= 0) {
                    throw new RuntimeException('Harga produk harus lebih dari 0.');
                }
                if ($stok < 0) {
                    throw new RuntimeException('Stok produk tidak boleh negatif.');
                }
                if (empty($_FILES['gambar']['name'])) {
                    throw new RuntimeException('Gambar produk wajib diunggah.');
                }

                $fotoPath = save_product_image($_FILES['gambar']);
                $stmt = $pdo->prepare(
                    "INSERT INTO product_data (akun_id, nama_produk, kategori, deskripsi, harga, stok, foto, status_pengajuan)
                     VALUES (NULL, :nama, :kategori, :deskripsi, :harga, :stok, :foto, 'disetujui')"
                );
                $stmt->execute([
                    ':nama'      => $nama,
                    ':kategori'  => $kategori,
                    ':deskripsi' => $deskripsi,
                    ':harga'     => $harga,
                    ':stok'      => $stok,
                    ':foto'      => $fotoPath,
                ]);

                header('Location: productDashboard.php?status=created');
                exit;
            }

            if ($action === 'update') {
                $id        = (int) ($_POST['id'] ?? 0);
                $nama      = trim((string) ($_POST['nama_produk'] ?? ''));
                $kategori  = trim((string) ($_POST['kategori'] ?? ''));
                $deskripsi = trim((string) ($_POST['deskripsi'] ?? ''));
                $harga     = (int) ($_POST['harga'] ?? 0);
                $stok      = (int) ($_POST['stok'] ?? -1);
                $status    = trim((string) ($_POST['status_pengajuan'] ?? ''));

                if ($id <= 0) {
                    throw new RuntimeException('ID produk tidak valid.');
                }
                if ($nama === '' || mb_strlen($nama, 'UTF-8') > 100) {
                    throw new RuntimeException('Nama produk wajib diisi (maksimal 100 karakter).');
                }
                if ($kategori === '' || mb_strlen($kategori, 'UTF-8') > 50) {
                    throw new RuntimeException('Kategori wajib diisi (maksimal 50 karakter).');
                }
                if ($deskripsi === '') {
                    throw new RuntimeException('Deskripsi produk wajib diisi.');
                }
                if ($harga <= 0) {
                    throw new RuntimeException('Harga produk harus lebih dari 0.');
                }
                if ($stok < 0) {
                    throw new RuntimeException('Stok produk tidak boleh negatif.');
                }
                if (!in_array($status, $allowedStatuses, true)) {
                    throw new RuntimeException('Status pengajuan tidak valid.');
                }

                $existingStmt = $pdo->prepare('SELECT foto, harga FROM product_data WHERE id = :id LIMIT 1');
                $existingStmt->execute([':id' => $id]);
                $existing = $existingStmt->fetch();
                if (!$existing) {
                    throw new RuntimeException('Data produk tidak ditemukan.');
                }

                $trxCheck = $pdo->prepare('SELECT COUNT(*) FROM transaksi_data WHERE product_id = :id');
                $trxCheck->execute([':id' => $id]);
                $hasTransactions = ((int) $trxCheck->fetchColumn()) > 0;

                $fotoPath = $existing['foto'];
                if (!empty($_FILES['gambar']['name'])) {
                    $fotoPath = save_product_image($_FILES['gambar']);
                }

                $finalHarga = $hasTransactions ? (int) $existing['harga'] : $harga;

                $stmt = $pdo->prepare(
                    'UPDATE product_data
                     SET nama_produk = :nama, kategori = :kategori, deskripsi = :deskripsi,
                         harga = :harga, stok = :stok, foto = :foto, status_pengajuan = :status
                     WHERE id = :id'
                );
                $stmt->execute([
                    ':nama'      => $nama,
                    ':kategori'  => $kategori,
                    ':deskripsi' => $deskripsi,
                    ':harga'     => $finalHarga,
                    ':stok'      => $stok,
                    ':foto'      => $fotoPath,
                    ':status'    => $status,
                    ':id'        => $id,
                ]);

                $redirectFlag = ($hasTransactions && $harga !== (int) $existing['harga']) ? 'updated_locked_price' : 'updated';
                header('Location: productDashboard.php?status=' . $redirectFlag);
                exit;
            }

            if ($action === 'deactivate') {
                $id = (int) ($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new RuntimeException('ID produk tidak valid.');
                }

                $stmt = $pdo->prepare(
                    "UPDATE product_data SET stok = 0, status_pengajuan = 'ditolak' WHERE id = :id"
                );
                $stmt->execute([':id' => $id]);

                header('Location: productDashboard.php?status=deactivated');
                exit;
            }
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
        }
    }
}

$statusParam = $_GET['status'] ?? '';
if ($statusParam === 'created') {
    $flashMessage = 'Produk preloved baru berhasil ditambahkan.';
} elseif ($statusParam === 'updated') {
    $flashMessage = 'Data produk berhasil diperbarui.';
} elseif ($statusParam === 'updated_locked_price') {
    $flashMessage = 'Data produk diperbarui (harga tidak diubah karena produk sudah memiliki riwayat transaksi).';
} elseif ($statusParam === 'deactivated') {
    $flashMessage = 'Produk berhasil dinonaktifkan (stok = 0, status = ditolak).';
}

$adminProfile = get_admin_profile();

$stats = $pdo->query("
    SELECT
        COUNT(*) AS total_produk,
        COALESCE(SUM(CASE WHEN status_pengajuan = 'disetujui' THEN 1 ELSE 0 END), 0) AS produk_aktif,
        COALESCE(SUM(CASE WHEN status_pengajuan = 'menunggu'  THEN 1 ELSE 0 END), 0) AS produk_menunggu
    FROM product_data
")->fetch() ?: [
    'total_produk'    => 0,
    'produk_aktif'    => 0,
    'produk_menunggu' => 0,
];

$products = $pdo->query(
    'SELECT id, akun_id, nama_produk, kategori, deskripsi, harga, stok, foto, status_pengajuan, created_at
     FROM product_data
     ORDER BY created_at DESC'
)->fetchAll();

function status_badge_class(string $status): string
{
    return match ($status) {
        'disetujui' => 'badge badge-success',
        'menunggu'  => 'badge badge-warning',
        'ditolak'   => 'badge badge-danger',
        default     => 'badge badge-neutral',
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Produk — Irish</title>
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
                <img src="../asset/icon-dashboard.png" width="32" height="32" alt="Irish">
            </a>

            <nav class="sidebar-nav">
                <a href="adminDashboard.php">
                    <img src="../asset/dashboard/barChart-icon.png" alt="" width="19" height="19">
                    Data Penjualan
                </a>
                <a href="bookingDashboard.php">
                    <img src="../asset/dashboard/home-icon.png" alt="" width="19" height="19">
                    Data Booking
                </a>
                <a href="productDashboard.php" class="active">
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
                <h1>Data Produk</h1>
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

            <section class="stats stats-products">
                <div class="stat-card">
                    <div>
                        <h3><?= (int) $stats['total_produk'] ?></h3>
                        <p>Total Produk</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div>
                        <h3><?= (int) $stats['produk_aktif'] ?></h3>
                        <p>Produk Aktif</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div>
                        <h3><?= (int) $stats['produk_menunggu'] ?></h3>
                        <p>Menunggu Persetujuan</p>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <h2>Daftar Produk Preloved</h2>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <a href="laporan/produk.php" class="profile-edit" target="_blank">&#11015; Download Laporan PDF</a>
                        <button type="button" id="open-add-product" class="btn-submit" style="margin-top: 0;">+ Tambah Produk</button>
                    </div>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Foto</th>
                                <th>Nama Produk</th>
                                <th>Kategori</th>
                                <th>Harga</th>
                                <th>Stok</th>
                                <th>Status</th>
                                <th>Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="9">Belum ada produk preloved. Klik "+ Tambah Produk" untuk menambahkan.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $prod): ?>
                                    <tr>
                                        <td><?= (int) $prod['id'] ?></td>
                                        <td>
                                            <img
                                                src="../<?= escape_html($prod['foto']) ?>"
                                                alt="<?= escape_html($prod['nama_produk']) ?>"
                                                class="product-thumb"
                                                width="40"
                                                height="40"
                                            >
                                        </td>
                                        <td><?= escape_html($prod['nama_produk']) ?></td>
                                        <td><?= escape_html($prod['kategori']) ?></td>
                                        <td><?= escape_html(format_rupiah($prod['harga'])) ?></td>
                                        <td><?= (int) $prod['stok'] ?></td>
                                        <td>
                                            <span class="<?= escape_html(status_badge_class($prod['status_pengajuan'])) ?>">
                                                <?= escape_html(ucfirst($prod['status_pengajuan'])) ?>
                                            </span>
                                        </td>
                                        <td><?= escape_html(date('d M Y', strtotime($prod['created_at']))) ?></td>
                                        <td>
                                            <div class="action-group">
                                                <button
                                                    type="button"
                                                    class="receipt-button js-edit-product"
                                                    data-id="<?= (int) $prod['id'] ?>"
                                                    data-nama="<?= escape_html($prod['nama_produk']) ?>"
                                                    data-kategori="<?= escape_html($prod['kategori']) ?>"
                                                    data-deskripsi="<?= escape_html($prod['deskripsi']) ?>"
                                                    data-harga="<?= (int) $prod['harga'] ?>"
                                                    data-stok="<?= (int) $prod['stok'] ?>"
                                                    data-status="<?= escape_html($prod['status_pengajuan']) ?>"
                                                >Edit</button>
                                                <form action="productDashboard.php" method="post" onsubmit="return confirm('Nonaktifkan produk ini? Stok akan diubah ke 0 dan status menjadi ditolak.');">
                                                    <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="deactivate">
                                                    <input type="hidden" name="id" value="<?= (int) $prod['id'] ?>">
                                                    <button type="submit" class="btn-delete" style="margin-top: 0; padding: 8px 12px; font-size: 12px;">Nonaktifkan</button>
                                                </form>
                                            </div>
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

    <dialog id="product-add-dialog" class="profile-dialog" aria-labelledby="add-product-title">
        <form action="productDashboard.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
            <input type="hidden" name="action" value="create">
            <h2 id="add-product-title">Tambah Produk Preloved</h2>

            <label for="add-nama">Nama Produk</label>
            <input id="add-nama" name="nama_produk" type="text" maxlength="100" required>

            <label for="add-kategori">Kategori</label>
            <input id="add-kategori" name="kategori" type="text" maxlength="50" placeholder="Misal: Pakaian, Elektronik, Buku" required>

            <label for="add-deskripsi">Deskripsi</label>
            <textarea id="add-deskripsi" name="deskripsi" rows="3" style="width:100%; border:1px solid var(--line); border-radius:8px; padding:10px; font:inherit;" required></textarea>

            <label for="add-harga">Harga (Rp)</label>
            <input id="add-harga" name="harga" type="number" min="1" required>

            <label for="add-stok">Stok</label>
            <input id="add-stok" name="stok" type="number" min="0" value="1" required>

            <label for="add-gambar">Foto Produk</label>
            <input id="add-gambar" name="gambar" type="file" accept="image/*" required>

            <div class="dialog-actions">
                <button type="button" id="cancel-add-product">Batal</button>
                <button type="submit">Simpan</button>
            </div>
        </form>
    </dialog>

    <dialog id="product-edit-dialog" class="profile-dialog" aria-labelledby="edit-product-title">
        <form action="productDashboard.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" id="edit-product-id" name="id" value="">
            <h2 id="edit-product-title">Edit Produk Preloved</h2>

            <label for="edit-nama">Nama Produk</label>
            <input id="edit-nama" name="nama_produk" type="text" maxlength="100" required>

            <label for="edit-kategori">Kategori</label>
            <input id="edit-kategori" name="kategori" type="text" maxlength="50" required>

            <label for="edit-deskripsi">Deskripsi</label>
            <textarea id="edit-deskripsi" name="deskripsi" rows="3" style="width:100%; border:1px solid var(--line); border-radius:8px; padding:10px; font:inherit;" required></textarea>

            <label for="edit-harga">Harga (Rp)</label>
            <input id="edit-harga" name="harga" type="number" min="1" required>

            <label for="edit-stok">Stok</label>
            <input id="edit-stok" name="stok" type="number" min="0" required>

            <label for="edit-status">Status Pengajuan</label>
            <select id="edit-status" name="status_pengajuan" style="width:100%; border:1px solid var(--line); border-radius:8px; padding:10px; font:inherit; background:var(--white);" required>
                <option value="menunggu">Menunggu</option>
                <option value="disetujui">Disetujui</option>
                <option value="ditolak">Ditolak</option>
            </select>

            <label for="edit-gambar">Foto Baru (opsional)</label>
            <input id="edit-gambar" name="gambar" type="file" accept="image/*">

            <div class="dialog-actions">
                <button type="button" id="cancel-edit-product">Batal</button>
                <button type="submit">Simpan</button>
            </div>
        </form>
    </dialog>

    <script>
        const addDialog = document.querySelector('#product-add-dialog');
        const editDialog = document.querySelector('#product-edit-dialog');

        document.querySelector('#open-add-product').addEventListener('click', () => addDialog.showModal());
        document.querySelector('#cancel-add-product').addEventListener('click', () => addDialog.close());
        document.querySelector('#cancel-edit-product').addEventListener('click', () => editDialog.close());

        document.querySelectorAll('.js-edit-product').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.querySelector('#edit-product-id').value = btn.dataset.id;
                document.querySelector('#edit-nama').value = btn.dataset.nama;
                document.querySelector('#edit-kategori').value = btn.dataset.kategori;
                document.querySelector('#edit-deskripsi').value = btn.dataset.deskripsi;
                document.querySelector('#edit-harga').value = btn.dataset.harga;
                document.querySelector('#edit-stok').value = btn.dataset.stok;
                document.querySelector('#edit-status').value = btn.dataset.status;
                editDialog.showModal();
            });
        });
    </script>

</body>
</html>
