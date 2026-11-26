const profileDialog = document.querySelector('#profile-dialog');
const profileForm = document.querySelector('#profile-form');
const salesRows = document.querySelector('#sales-rows');
const editProfileBtn = document.querySelector('#edit-profile');
const cancelProfileBtn = document.querySelector('#cancel-profile');
const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });

if (editProfileBtn && profileDialog && profileForm) {
  editProfileBtn.addEventListener('click', () => {
    const namaEl = document.querySelector('#nama_admin');
    const emailEl = document.querySelector('#email_admin');
    if (namaEl && profileForm.elements.adminName) {
      profileForm.elements.adminName.value = namaEl.textContent.trim();
    }
    if (emailEl && profileForm.elements.adminEmail) {
      profileForm.elements.adminEmail.value = emailEl.textContent.trim();
    }
    const msg = document.querySelector('#profile-message');
    if (msg) msg.textContent = '';
    profileDialog.showModal();
  });
}

if (cancelProfileBtn && profileDialog) {
  cancelProfileBtn.addEventListener('click', () => profileDialog.close());
}

if (salesRows) {
  salesRows.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-transaction-id]');
    if (!button || !salesRows.contains(button)) return;
    const { productName, quantity, buyerName, buyerEmail, paymentMethod, total, status } = button.dataset;
    window.alert(
      `Detail Transaksi (${status})\n${productName} × ${quantity}\nPembeli: ${buyerName} (${buyerEmail})\nMetode: ${paymentMethod}\nTotal: ${rupiah.format(Number(total || 0))}`
    );
  });
}
