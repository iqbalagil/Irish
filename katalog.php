<?php require __DIR__ . '/auth.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Katalog Produk — IRISH</title>
<link rel="stylesheet" href="css/home.css?v=<?= filemtime(__DIR__ . '/css/home.css') ?>">
<script src="js/home.js?v=<?= filemtime(__DIR__ . '/js/home.js') ?>" defer></script>
</head>
<body>
<div class="wrap">

  <header>
    <button type="button" class="menu" id="nav-toggle" aria-label="Buka menu" aria-expanded="false" aria-controls="nav-sidebar"></button>
    <a class="logo" href="index.php" aria-label="IRISH home">
      <img src="asset/icon.png" alt="IRISH icon">
      IRISH
    </a>
    <div class="account-bar">
      <?php if (!empty($_SESSION['username'])): ?>
      <span>Halo, <strong><?= escape_html($_SESSION['username']) ?></strong></span>
      <form action="logout.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
        <button type="submit">Logout</button>
      </form>
      <?php else: ?>
      <a href="login.php">Masuk</a>
      <?php endif; ?>
      <div class="icons">
        <a href="katalog.php" aria-label="Search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="10" cy="10" r="7"/><path d="M15 15l6 6"/></svg></a>
        <a href="katalog.php" aria-label="Cart"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M1 2h4l1 3h16l-3 9H8L5 4H1z"/><circle cx="9" cy="20" r="2"/><circle cx="18" cy="20" r="2"/></svg><span class="badge">99+</span></a>
      </div>
    </div>
  </header>

  <section class="style catalog-page">
    <h2>KATALOG PRODUK</h2>
    <div class="catalog-controls">
      <input id="catalog-search" type="search" placeholder="Cari produk preloved..." aria-label="Cari produk">
      <select id="catalog-category" aria-label="Filter kategori">
        <option value="">Semua Kategori</option>
      </select>
    </div>
    <p id="products-status" class="shop-status" role="status" aria-live="polite">Memuat produk...</p>
    <div id="product-list" class="style-product-grid"></div>

    <dialog id="transaction-dialog" class="shop-dialog" aria-labelledby="transaction-title">
      <form id="transaction-form">
        <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
        <div class="dialog-heading">
          <h2 id="transaction-title">Buat Transaksi</h2>
          <button type="button" id="close-transaction" class="close-dialog" aria-label="Tutup">&times;</button>
        </div>
        <p id="selected-product" class="selected-product"></p>
        <label for="buyer-name">Nama pembeli</label>
        <input id="buyer-name" name="buyerName" type="text" maxlength="80" required>
        <label for="buyer-email">Email pembeli</label>
        <input id="buyer-email" name="buyerEmail" type="email" required>
        <label for="quantity">Jumlah</label>
        <input id="quantity" name="quantity" type="number" min="1" max="20" value="1" required>
        <label for="payment-method">Metode pembayaran (simulasi)</label>
        <select id="payment-method" name="paymentMethod" required>
          <option value="">Pilih metode</option>
          <option value="Transfer bank">Transfer bank</option>
          <option value="E-wallet">E-wallet</option>
          <option value="Tunai">Tunai</option>
        </select>
        <p id="transaction-total" class="transaction-total"></p>
        <p id="transaction-message" class="shop-status" role="status" aria-live="polite"></p>
        <button id="pay-button" class="pay-button" type="submit">Bayar (simulasi)</button>
      </form>
    </dialog>

    <dialog id="product-feedback-dialog" class="shop-dialog" aria-labelledby="pf-dialog-title">
      <div class="dialog-heading">
        <h2 id="pf-dialog-title">Ulasan Produk</h2>
        <button type="button" id="close-product-feedback" class="close-dialog" aria-label="Tutup">&times;</button>
      </div>
      <p id="pf-product-name" class="selected-product"></p>
      <div id="pf-list" class="feedback-list pf-dialog-list">
        <p class="shop-status">Memuat ulasan...</p>
      </div>
      <form id="product-feedback-form" class="feedback-form" action="api/feedback.php" method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
        <input type="hidden" id="pf-product-id" name="product_id" value="">
        <label for="pf-nama">Nama</label>
        <input id="pf-nama" name="nama_pengguna" type="text" maxlength="40" placeholder="Namamu" required>
        <label for="pf-pesan">Ulasan</label>
        <textarea id="pf-pesan" name="pesan" rows="3" placeholder="Tulis ulasan untuk produk ini..." required></textarea>
        <p id="pf-message" class="shop-status" role="status" aria-live="polite"></p>
        <button type="submit" class="pay-button">Kirim Ulasan</button>
      </form>
    </dialog>
  </section>

</div>

<div id="nav-backdrop" class="nav-backdrop"></div>
<aside id="nav-sidebar" class="nav-sidebar" aria-label="Navigasi Utama" aria-hidden="true">
  <div class="nav-sidebar-head">
    <a class="logo" href="index.php">
      <img src="asset/icon.png" alt="IRISH icon">
      IRISH
    </a>
    <button type="button" id="nav-close" class="close-dialog" aria-label="Tutup menu">&times;</button>
  </div>
  <nav class="nav-sidebar-links">
    <a href="index.php" class="nav-link">Beranda</a>
    <a href="katalog.php" class="nav-link active" aria-current="page">Katalog Produk</a>
  </nav>
</aside>
</body>
</html>
