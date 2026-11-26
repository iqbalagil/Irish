<?php
require __DIR__ . '/../auth.php';
require_login('../login.php');

$pdo = db();
$adminProfile = get_admin_profile();

$feedbacks = $pdo->query(
    'SELECT f.nama_pengguna, f.pesan, f.tanggal, t.nama_tempat
     FROM feedback_data f
     LEFT JOIN tempat_booking t ON t.id = f.tempat_id
     ORDER BY f.tanggal DESC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Pengguna — Irish</title>
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
                <a href="bookingDashboard.php">
                    <img src="../asset/dashboard/home-icon.png" alt="" width="19" height="19">
                    Data Booking
                </a>
                <a href="feedbackDashboard.php" class="active">
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
                <h1>Feedback Pengguna</h1>
                <div class="topbar-profile">
                    <div>
                        <span id="nama_admin"><?= escape_html($adminProfile['nama_admin']) ?></span>
                        <small id="email_admin"><?= escape_html($adminProfile['email_admin']) ?></small>
                    </div>
                    <a href="adminDashboard.php" class="profile-edit">Ubah profil</a>
                </div>
            </div>

            <section class="panel">
                <div class="panel-head">
                    <h2>Daftar Feedback</h2>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Pengguna</th>
                                <th>Tempat</th>
                                <th>Pesan</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($feedbacks)): ?>
                                <tr>
                                    <td colspan="4">Belum ada feedback pengguna.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($feedbacks as $fb): ?>
                                    <tr>
                                        <td><?= escape_html($fb['nama_pengguna']) ?></td>
                                        <td><?= escape_html($fb['nama_tempat'] ?? '-') ?></td>
                                        <td><?= escape_html($fb['pesan']) ?></td>
                                        <td><?= escape_html(date('d M Y', strtotime($fb['tanggal']))) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

</body>
</html>
