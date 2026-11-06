# SIA Konveksi

Sistem Informasi Akuntansi berbasis web untuk perusahaan konveksi (PDH, jersey, kaos).
Dibangun dengan Laravel, MySQL, dan Bootstrap. Dokumentasi database: `docs/DATABASE.md`.

## Kebutuhan

- PHP 8.2 atau lebih baru, dengan ekstensi: `pdo_mysql`, `mbstring`, `xml`, `gd`, `zip`, `fileinfo`
- Composer
- MySQL atau MariaDB
- Node.js dan npm (hanya untuk membangun tampilan login)
- Disarankan: Laragon (Windows), yang sudah membawa semuanya

## Instalasi Lokal

1. Salin folder proyek ke `C:\laragon\www\sia-konveksi` (tanpa folder `vendor` dan `node_modules`
   juga boleh, keduanya dibuat ulang di langkah 3 dan 4).
2. Jalankan Laragon, klik **Start All**.
3. Di folder proyek, jalankan:
   ```
   composer install
   copy .env.example .env
   php artisan key:generate
   ```
4. Buat database kosong `sia_konveksi` (phpMyAdmin, collation `utf8mb4_unicode_ci`).
5. Edit `.env`:
   ```
   APP_NAME="SIA Konveksi"
   APP_URL=http://sia-konveksi.test
   APP_TIMEZONE=Asia/Jakarta
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=sia_konveksi
   DB_USERNAME=root
   DB_PASSWORD=
   ```
6. Buat tabel dan data awal (akun, satuan, kategori, barang, pengguna):
   ```
   php artisan migrate --seed
   php artisan storage:link
   npm install
   npm run build
   ```
7. (Opsional) Isi data contoh transaksi:
   ```
   php artisan db:seed --class=SampleDataSeeder
   ```
8. Buka `http://sia-konveksi.test`, atau jalankan `php artisan serve` lalu buka `http://127.0.0.1:8000`.

## Akun Bawaan

| Peran | Email | Kata sandi |
|---|---|---|
| Admin | admin@konveksi.test | password |
| Keuangan | keuangan@konveksi.test | password |
| Gudang | gudang@konveksi.test | password |
| Pemilik | pemilik@konveksi.test | password |

**Ganti semua kata sandi sebelum dipakai untuk data sungguhan.**

## Hak Akses

| Menu | Admin | Keuangan | Gudang | Pemilik |
|---|---|---|---|---|
| Dashboard | penuh | penuh | operasional saja | penuh |
| Data master | ya | tidak | tidak | tidak |
| Pembelian, Produksi, Pesanan | ya | ya | lihat, buat draft, mulai produksi | tidak |
| Posting, pembayaran, pengiriman | ya | ya | tidak | tidak |
| Pengeluaran, Modal dan Prive | ya | ya | tidak | tidak |
| Jurnal, Buku Besar, Neraca Saldo, Laporan Keuangan | ya | ya | tidak | lihat |
| Kartu dan Laporan Persediaan | ya | ya | ya | ya |

## Memulai dari Nol (Data Kosong)

Untuk memakai aplikasi dengan data sungguhan, jangan jalankan `SampleDataSeeder`. Urutan awal:
1. Ubah data master (pemasok, pelanggan, barang) sesuai usaha.
2. Catat **Modal awal** di menu Modal dan Prive.
3. Mulai mencatat pembelian, produksi, dan penjualan.

## Cadangan Data

Ekspor database lewat phpMyAdmin (**Export**, format SQL), atau:
```
mysqldump -u root sia_konveksi > cadangan.sql
```
Folder `storage/app/public/bukti` berisi file bukti transaksi yang perlu ikut dicadangkan.

## Pemecahan Masalah

| Gejala | Solusi |
|---|---|
| Halaman putih atau 419 | `php artisan optimize:clear` |
| `Class ... not found` | `composer dump-autoload` |
| `Access denied for user` | Periksa `.env` dan pastikan MySQL berjalan |
| Tampilan tidak berformat | Pastikan koneksi internet (Bootstrap dan Chart.js dari CDN) dan `npm run build` sudah dijalankan |
| Bukti transaksi tidak terbuka | `php artisan storage:link` |

## Catatan Teknis

- Metode persediaan: rata-rata tertimbang.
- Pendapatan diakui saat barang terkirim. Uang muka dicatat sebagai liabilitas (2102).
- Transaksi terposting tidak dihapus. Koreksi memakai jurnal pembalik.
- Belum tersedia: retur penjualan, penyesuaian stok, tutup buku, aset tetap.