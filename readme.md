# Irish
<img width="1280" height="120" alt="Group 17(1)" src="https://github.com/user-attachments/assets/afb1b3af-f952-44a2-bf3f-da88bed0e2e7" />


Aplikasi Web yang berfungsi dalam jual beli serta penyewaan Studio

Tech stack: PHP 8 (dengan ekstensi mbstring), CSS, dan JavaScript.

Jalankan dari folder proyek dengan `php -S localhost:8000`, lalu buka
`http://localhost:8000/index.php`. Daftar akun melalui `register.php`, masuk
melalui `login.php`, dan gunakan tombol Logout untuk mengakhiri session.

Akun disimpan di `data/users.php` saat pendaftaran pertama. File ini tidak
masuk Git dan password disimpan sebagai hash. Akun lama yang hanya ada di
`localStorage` browser perlu didaftarkan ulang.
