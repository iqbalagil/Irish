const productList = document.querySelector('#product-list');
const productsStatus = document.querySelector('#products-status');
const dialog = document.querySelector('#transaction-dialog');
const form = document.querySelector('#transaction-form');
const quantityInput = document.querySelector('#quantity');
const payButton = document.querySelector('#pay-button');
const transactionMessage = document.querySelector('#transaction-message');
const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
let products = [];
let selectedProduct = null;

async function loadProducts() {
  try {
    const response = await fetch('data/products.json');
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const data = await response.json();
    if (!Array.isArray(data)) throw new Error('Format produk tidak sesuai.');
    products = data;
    productList.replaceChildren();
    for (const product of products) {
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
      const description = document.createElement('p');
      description.textContent = product.description;
      const price = document.createElement('strong');
      price.textContent = rupiah.format(product.price);
      const button = document.createElement('button');
      button.type = 'button';
      button.dataset.productId = product.id;
      button.textContent = 'Beli produk';
      body.append(title, description, price, button);
      card.append(img, body);
      productList.append(card);
    }
    productsStatus.textContent = `${products.length} outfit tersedia. Pilih outfit untuk membeli (simulasi).`;
  } catch (error) {
    productsStatus.textContent = 'Outfit gagal dimuat. Jalankan situs melalui server lokal lalu muat ulang halaman.';
    console.error('Gagal memuat produk:', error);
  }
}

function updateTotal() {
  if (!selectedProduct) return;
  const quantity = Number(quantityInput.value);
  document.querySelector('#transaction-total').textContent =
    Number.isInteger(quantity) && quantity > 0 && quantity <= 20
      ? `Total: ${rupiah.format(selectedProduct.price * quantity)}`
      : 'Masukkan jumlah 1 sampai 20.';
}

// Delegation 1: satu listener melayani semua tombol produk hasil fetch.
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
  selectedProduct = products.find((product) => product.id === button.dataset.productId);
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

function wait(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (!selectedProduct || !form.reportValidity()) return;
  const quantity = Number(quantityInput.value);
  if (!Number.isInteger(quantity) || quantity < 1 || quantity > 20) return;

  payButton.disabled = true;
  transactionMessage.textContent = 'Memproses pembayaran simulasi...';
  try {
    await wait(600);
    const transaction = {
      id: `PJ-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`,
      productId: selectedProduct.id,
      productName: selectedProduct.name,
      buyerName: form.elements.buyerName.value.trim(),
      buyerEmail: form.elements.buyerEmail.value.trim(),
      quantity,
      total: selectedProduct.price * quantity,
      paymentMethod: form.elements.paymentMethod.value,
      paidAt: new Date().toISOString(),
      status: 'Pembayaran masuk (simulasi)'
    };
    if (!transaction.buyerName) throw new Error('Nama pembeli wajib diisi.');
    const saved = JSON.parse(localStorage.getItem('irishTransactions') || '[]');
    const transactions = Array.isArray(saved) ? saved : [];
    transactions.unshift(transaction);
    localStorage.setItem('irishTransactions', JSON.stringify(transactions));

    // Propagation: event berasal dari form dan bubble ke pembungkus halaman.
    form.dispatchEvent(new CustomEvent('transaction:paid', { bubbles: true, detail: transaction }));
    dialog.close();
    window.alert(`${transaction.productName} berhasil dibeli (simulasi). Pembayaran masuk.`);
  } catch (error) {
    transactionMessage.textContent = `Transaksi gagal: ${error.message}`;
  } finally {
    payButton.disabled = false;
  }
});

document.querySelector('.style').addEventListener('transaction:paid', (event) => {
  productsStatus.textContent = `Pembayaran ${event.detail.id} masuk (simulasi).`;
});

window.addEventListener('load', () => {
  loadProducts();
});
