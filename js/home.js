const productList = document.querySelector('#product-list');
const productsStatus = document.querySelector('#products-status');
const dialog = document.querySelector('#transaction-dialog');
const form = document.querySelector('#transaction-form');
const quantityInput = document.querySelector('#quantity');
const payButton = document.querySelector('#pay-button');
const transactionMessage = document.querySelector('#transaction-message');
const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
const catalogSearch = document.querySelector('#catalog-search');
const catalogCategory = document.querySelector('#catalog-category');
let products = [];
let selectedProduct = null;

function renderProducts(list) {
  productList.replaceChildren();
  for (const product of list) {
    const card = document.createElement('article');
    card.className = 'product-card';
    const img = document.createElement('img');
    img.src = product.image;
    img.alt = product.name;
    img.width = 223;
    img.height = 223;
    img.style.imageRendering = 'auto';
    img.style.objectFit = 'cover';
    const body = document.createElement('div');
    body.className = 'product-body';
    const title = document.createElement('h3');
    title.textContent = product.name;
    const category = document.createElement('span');
    category.className = 'product-category';
    category.textContent = product.category;
    const description = document.createElement('p');
    description.textContent = product.description;
    const price = document.createElement('strong');
    price.textContent = rupiah.format(product.price);
    const stock = document.createElement('span');
    stock.className = 'product-stock';
    stock.textContent = `Stok: ${product.stock}`;
    const button = document.createElement('button');
    button.type = 'button';
    button.dataset.productId = String(product.id);
    button.textContent = 'Beli produk';
    const feedbackBtn = document.createElement('button');
    feedbackBtn.type = 'button';
    feedbackBtn.className = 'product-feedback-btn';
    feedbackBtn.dataset.feedbackProductId = String(product.id);
    feedbackBtn.textContent = 'Ulasan produk';
    const actions = document.createElement('div');
    actions.className = 'product-card-actions';
    actions.append(button, feedbackBtn);
    body.append(title, category, description, price, stock, actions);
    card.append(img, body);
    productList.append(card);
  }
}

function applyProductFilters() {
  const q = (catalogSearch?.value || '').trim().toLowerCase();
  const cat = catalogCategory?.value || '';
  const filtered = products.filter((p) => {
    const matchQ = !q || p.name.toLowerCase().includes(q) || p.description.toLowerCase().includes(q) || p.category.toLowerCase().includes(q);
    const matchCat = !cat || p.category === cat;
    return matchQ && matchCat;
  });
  renderProducts(filtered);
  productsStatus.textContent = `${filtered.length} outfit tersedia. Pilih outfit untuk membeli.`;
}

async function loadProducts() {
  if (!productList) return;
  try {
    const response = await fetch('api/products.php');
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const data = await response.json();
    if (!Array.isArray(data)) throw new Error('Format produk tidak sesuai.');
    products = data;

    if (catalogCategory) {
      const current = catalogCategory.value;
      const categories = [...new Set(products.map((p) => p.category).filter(Boolean))];
      catalogCategory.innerHTML = '<option value="">Semua Kategori</option>';
      for (const c of categories) {
        const opt = document.createElement('option');
        opt.value = c;
        opt.textContent = c;
        catalogCategory.append(opt);
      }
      catalogCategory.value = current;
    }

    applyProductFilters();
  } catch (error) {
    productsStatus.textContent = 'Outfit gagal dimuat dari database. Muat ulang halaman.';
    console.error('Gagal memuat produk:', error);
  }
}

catalogSearch?.addEventListener('input', applyProductFilters);
catalogCategory?.addEventListener('change', applyProductFilters);

function updateTotal() {
  if (!selectedProduct) return;
  const quantity = Number(quantityInput.value);
  document.querySelector('#transaction-total').textContent =
    Number.isInteger(quantity) && quantity > 0 && quantity <= 20
      ? `Total: ${rupiah.format(selectedProduct.price * quantity)}`
      : 'Masukkan jumlah 1 sampai 20.';
}

productList.addEventListener('mousedown', (event) => {
  if (event.button !== 0) return;
  const button = event.target.closest('button[data-product-id]');
  if (!button || !productList.contains(button)) return;
  button.classList.add('is-pressed');
  window.setTimeout(() => button.classList.remove('is-pressed'), 180);
});

productList.addEventListener('click', (event) => {
  const button = event.target.closest('button[data-product-id]');
  if (!button || !productList.contains(button)) return;
  selectedProduct = products.find((product) => String(product.id) === button.dataset.productId);
  if (!selectedProduct) return;
  form.reset();
  quantityInput.value = '1';
  transactionMessage.textContent = '';
  document.querySelector('#selected-product').textContent = `${selectedProduct.name} — ${rupiah.format(selectedProduct.price)} / unit`;
  updateTotal();
  dialog.showModal();
  document.querySelector('#buyer-name').focus();
});

quantityInput.addEventListener('input', updateTotal);
document.querySelector('#close-transaction').addEventListener('click', () => dialog.close());
dialog.addEventListener('click', (event) => {
  if (event.target === dialog) dialog.close();
});

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (!selectedProduct || !form.reportValidity()) return;
  const quantity = Number(quantityInput.value);
  if (!Number.isInteger(quantity) || quantity < 1 || quantity > 20) return;

  payButton.disabled = true;
  transactionMessage.textContent = 'Memproses transaksi...';
  try {
    const response = await fetch('api/checkout.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        productId: selectedProduct.id,
        buyerName: form.elements.buyerName.value.trim(),
        buyerEmail: form.elements.buyerEmail.value.trim(),
        quantity,
        paymentMethod: form.elements.paymentMethod.value,
      }),
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.error || `HTTP ${response.status}`);
    }

    form.dispatchEvent(new CustomEvent('transaction:paid', { bubbles: true, detail: result }));
    dialog.close();
    window.alert(`${result.productName} berhasil dibeli (${result.id}). Pembayaran masuk.`);
    await loadProducts();
  } catch (error) {
    transactionMessage.textContent = `Transaksi gagal: ${error.message}`;
  } finally {
    payButton.disabled = false;
  }
});

document.querySelector('.style').addEventListener('transaction:paid', (event) => {
  productsStatus.textContent = `Pembayaran ${event.detail.id} berhasil disimpan ke database.`;
});

window.addEventListener('load', () => {
  loadProducts();
});

// ── Burger Sidebar Navigation ─────────────────────────────────
const navToggle   = document.querySelector('#nav-toggle');
const navSidebar  = document.querySelector('#nav-sidebar');
const navBackdrop = document.querySelector('#nav-backdrop');
const navClose    = document.querySelector('#nav-close');

function setSidebarOpen(open) {
  if (!navSidebar || !navBackdrop || !navToggle) return;
  navSidebar.classList.toggle('is-open', open);
  navBackdrop.classList.toggle('is-open', open);
  navSidebar.setAttribute('aria-hidden', String(!open));
  navToggle.setAttribute('aria-expanded', String(open));
}

navToggle?.addEventListener('click', () => setSidebarOpen(true));
navClose?.addEventListener('click', () => setSidebarOpen(false));
navBackdrop?.addEventListener('click', () => setSidebarOpen(false));
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && navSidebar?.classList.contains('is-open')) {
    setSidebarOpen(false);
  }
});

function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

// ── Per-Product Feedback Dialog ───────────────────────────────
const pfDialog  = document.querySelector('#product-feedback-dialog');
const pfForm    = document.querySelector('#product-feedback-form');
const pfList    = document.querySelector('#pf-list');
const pfMessage = document.querySelector('#pf-message');
const pfProdId  = document.querySelector('#pf-product-id');
const pfProdName = document.querySelector('#pf-product-name');

async function loadProductFeedbacks(productId) {
  if (!pfList) return;
  pfList.innerHTML = '<p class="shop-status">Memuat ulasan...</p>';
  try {
    const res = await fetch(`api/feedback.php?product_id=${encodeURIComponent(productId)}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    if (!Array.isArray(data) || data.length === 0) {
      pfList.innerHTML = '<p class="shop-status">Belum ada ulasan untuk produk ini.</p>';
      return;
    }
    pfList.replaceChildren();
    for (const fb of data) {
      const item = document.createElement('div');
      item.className = 'feedback-item';
      item.innerHTML = `
        <p class="feedback-item-name">${escHtml(fb.nama_pengguna)}</p>
        <p class="feedback-item-date">${escHtml(fb.tanggal)}</p>
        <p class="feedback-item-pesan">${escHtml(fb.pesan)}</p>
      `;
      pfList.append(item);
    }
  } catch (err) {
    pfList.innerHTML = '<p class="shop-status">Gagal memuat ulasan produk.</p>';
  }
}

if (productList && pfDialog) {
  productList.addEventListener('click', (event) => {
    const btn = event.target.closest('button[data-feedback-product-id]');
    if (!btn || !productList.contains(btn)) return;
    const product = products.find((p) => String(p.id) === btn.dataset.feedbackProductId);
    if (!product) return;

    pfForm.reset();
    pfProdId.value = String(product.id);
    pfProdName.textContent = `${product.name} — ${product.category}`;
    pfMessage.textContent = '';
    pfDialog.showModal();
    loadProductFeedbacks(product.id);
  });

  document.querySelector('#close-product-feedback')?.addEventListener('click', () => pfDialog.close());
  pfDialog.addEventListener('click', (event) => {
    if (event.target === pfDialog) pfDialog.close();
  });
}

if (pfForm) {
  pfForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!pfForm.reportValidity()) return;
    const submitBtn = pfForm.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    pfMessage.textContent = 'Mengirim...';
    pfMessage.style.color = '';

    try {
      const body = new FormData(pfForm);
      const res  = await fetch('api/feedback.php', { method: 'POST', body });
      const data = await res.json();
      if (!res.ok || !data.success) throw new Error(data.error || `HTTP ${res.status}`);

      const currentId = pfProdId.value;
      pfForm.reset();
      pfProdId.value = currentId;
      pfMessage.textContent = 'Ulasan produk berhasil dikirim!';
      pfMessage.style.color = '#2e9e5b';
      await loadProductFeedbacks(currentId);
    } catch (err) {
      pfMessage.textContent = `Gagal: ${err.message}`;
      pfMessage.style.color = '#DB4141';
    } finally {
      submitBtn.disabled = false;
    }
  });
}
