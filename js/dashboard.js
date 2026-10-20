const profileDialog = document.querySelector('#profile-dialog');
const profileForm = document.querySelector('#profile-form');
const salesRows = document.querySelector('#sales-rows');
const salesMessage = document.querySelector('#sales-message');
const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
const dateFormatter = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

function readTransactions() {
  try {
    const data = JSON.parse(localStorage.getItem('irishTransactions') || '[]');
    return Array.isArray(data) ? data : [];
  } catch {
    salesMessage.textContent = 'Data transaksi di browser tidak dapat dibaca.';
    return [];
  }
}

function renderSales() {
  const transactions = readTransactions();
  salesRows.replaceChildren();
  if (transactions.length === 0) {
    const row = document.createElement('tr');
    const cell = document.createElement('td');
    cell.colSpan = 8;
    cell.textContent = 'Belum ada transaksi. Pilih produk dari halaman utama.';
    row.append(cell);
    salesRows.append(row);
  }
  for (const transaction of transactions) {
    const row = document.createElement('tr');
    const fields = [
      transaction.id, transaction.productName, transaction.buyerName,
      dateFormatter.format(new Date(transaction.paidAt)), transaction.quantity,
      rupiah.format(transaction.total), transaction.status
    ];
    for (const field of fields) {
      const cell = document.createElement('td');
      cell.textContent = field;
      row.append(cell);
    }
    const actionCell = document.createElement('td');
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'receipt-button';
    button.dataset.transactionId = transaction.id;
    button.textContent = 'Lihat detail';
    actionCell.append(button);
    row.append(actionCell);
    salesRows.append(row);
  }

  const now = new Date();
  const thisMonth = transactions.filter((item) => {
    const date = new Date(item.paidAt);
    return date.getFullYear() === now.getFullYear() && date.getMonth() === now.getMonth();
  });
  const today = transactions.filter((item) => new Date(item.paidAt).toDateString() === now.toDateString());
  document.querySelector('#total-sales').textContent = transactions.length;
  document.querySelector('#total-revenue').textContent = rupiah.format(transactions.reduce((sum, item) => sum + Number(item.total || 0), 0));
  document.querySelector('#monthly-sales').textContent = thisMonth.length;
  document.querySelector('#today-revenue').textContent = rupiah.format(today.reduce((sum, item) => sum + Number(item.total || 0), 0));
}

function renderProfile() {
  try {
    const profile = JSON.parse(localStorage.getItem('irishAdminProfile') || 'null');
    document.querySelector('#nama_admin').textContent = profile?.name || 'Admin Irish';
    document.querySelector('#email_admin').textContent = profile?.email || 'admin@irish.id';
  } catch {
    document.querySelector('#nama_admin').textContent = 'Admin Irish';
    document.querySelector('#email_admin').textContent = 'admin@irish.id';
  }
}

document.querySelector('#edit-profile').addEventListener('click', () => {
  profileForm.elements.adminName.value = document.querySelector('#nama_admin').textContent;
  profileForm.elements.adminEmail.value = document.querySelector('#email_admin').textContent;
  document.querySelector('#profile-message').textContent = '';
  profileDialog.showModal();
});
document.querySelector('#cancel-profile').addEventListener('click', () => profileDialog.close());
profileForm.addEventListener('submit', (event) => {
  event.preventDefault();
  if (!profileForm.reportValidity()) return;
  const name = profileForm.elements.adminName.value.trim();
  const email = profileForm.elements.adminEmail.value.trim().toLowerCase();
  if (!name) {
    document.querySelector('#profile-message').textContent = 'Username wajib diisi.';
    return;
  }
  try {
    localStorage.setItem('irishAdminProfile', JSON.stringify({ name, email }));
    renderProfile();
    profileDialog.close();
    salesMessage.textContent = 'Profil admin berhasil disimpan di browser ini.';
  } catch {
    document.querySelector('#profile-message').textContent = 'Profil gagal disimpan di browser ini.';
  }
});

// Delegation 2: satu listener untuk tombol detail pada baris transaksi yang dibuat dinamis.
salesRows.addEventListener('click', (event) => {
  const button = event.target.closest('button[data-transaction-id]');
  if (!button || !salesRows.contains(button)) return;
  const transaction = readTransactions().find((item) => item.id === button.dataset.transactionId);
  if (!transaction) return;
  window.alert(`Pembayaran masuk (simulasi)\n${transaction.productName} × ${transaction.quantity}\nPembeli: ${transaction.buyerName} (${transaction.buyerEmail})\nMetode: ${transaction.paymentMethod}\nTotal: ${rupiah.format(transaction.total)}`);
});

window.addEventListener('storage', (event) => {
  if (event.key === 'irishTransactions') renderSales();
  if (event.key === 'irishAdminProfile') renderProfile();
});

renderProfile();
renderSales();
