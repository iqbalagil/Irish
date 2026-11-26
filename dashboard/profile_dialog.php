<dialog id="profile-dialog" class="profile-dialog" aria-labelledby="profile-title">
    <form id="profile-form" method="post" action="adminDashboard.php">
        <input type="hidden" name="csrf_token" value="<?= escape_html(csrf_token()) ?>">
        <h2 id="profile-title">Ubah Profil Admin</h2>
        <label for="admin-name">Username admin</label>
        <input id="admin-name" name="adminName" type="text" maxlength="50" value="<?= escape_html($admin['nama_admin']) ?>" required>
        <label for="admin-email">Email admin</label>
        <input id="admin-email" name="adminEmail" type="email" value="<?= escape_html($admin['email_admin']) ?>" required>
        <p id="profile-message" role="status" aria-live="polite"></p>
        <div class="dialog-actions">
            <button type="button" id="cancel-profile">Batal</button>
            <button type="submit">Simpan</button>
        </div>
    </form>
</dialog>
<script src="../js/dashboard.js" defer></script>
