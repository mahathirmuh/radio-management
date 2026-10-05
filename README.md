# MTI Radio Management

Aplikasi register radio HT & rig (MOTOTRBO), pengganti sheet **Form** di
`Radio Register 07-08-25.xlsx`. Dibangun di atas **Base-Apps-Merdeka**
(Laravel 12, tema Tabler, modul di `modules/`, RBAC menu/role/permission).

## Isi aplikasi

| Menu | Fungsi |
| --- | --- |
| Dashboard | Ringkasan radio aktif, kondisi, hasil cek, per company/tipe, ID bebas berikutnya |
| Register Radio | Daftar radio (1 baris = 1 radio fisik / SN), filter, tambah/edit, arsip/pulihkan, export Excel |
| Detail Radio | Data lengkap, hasil cek, riwayat, data asli Excel |
| Riwayat Radio | Log serah terima, status, program, verifikasi, arsip (otomatis + manual) |
| Master Data Radio | Tipe Radio, Perusahaan, Department, Template Channel |
| Import Excel | Impor register lama (sheet Form + to Konawe) |

Aturan utama:

- **Radio ID** unik di antara radio aktif. **MDC Code** adalah Radio ID dalam heksadesimal
  (1001 → 3E9). ID yang MDC Code-nya mengandung huruf **F** tidak bisa dipakai (wildcard MDC):
  daftar "ID bebas" melewatinya, dan bila diketik, form menjelaskan alasannya serta menawarkan
  ID bebas terdekat (751 = 2EF → 768 = 300). ID terakhir yang diketahui dari radio tanpa ID
  ikut dicadangkan.
- **SN** unik. **MDC Code**, **kategori** dan **daya** dihitung otomatis.
- Aturan ID/SN diperiksa saat nilainya **diisi atau diubah**. Radio hasil impor yang ID/SN-nya
  sudah bermasalah (dobel, mengandung F) tetap bisa diedit; masalahnya tetap tampil di kolom **Cek**.
- **Status** lama dipecah jadi **Posisi** (Di User / Stok IT / Service / Hilang) dan
  **Kondisi** (Layak / Perlu Service / Rusak / Belum dicek).
- Radio yang keluar permanen **diarsipkan** (Pindah Site / Afkir / Hilang (BA selesai)),
  Radio ID-nya dilepas. Bila dipulihkan, ID lamanya kembali kecuali sudah dipakai atau
  dicadangkan radio lain.
- Kolom **Cek** menandai: SN/ID dobel, ID mengandung F, SN/ID/tipe kosong, hilang tanpa BA,
  perlu program ulang (tgl program < tgl berlaku template), verifikasi kosong / > 12 bulan,
  dan **Perlu dicek** (data Excel yang bertentangan; tidak ditebak).
- Dua orang menyimpan bersamaan: penyimpanan diproses satu per satu, jadi satu Radio ID tidak
  bisa terpakai dua kali. Form edit yang dibuka sebelum orang lain menyimpan radio yang sama
  ditolak dengan pesan "baru saja diubah" (buka ulang form, lalu ulangi). Selama impor Excel
  berjalan, penyimpanan lain dijawab "Register sedang sibuk". Bila impor terputus di tengah jalan
  (server mati, batas waktu), jawaban itu hilang sendiri paling lama 10 menit kemudian;
  `php artisan radio:unlock` melepasnya saat itu juga.
- Master data yang **dinonaktifkan** tidak bisa dipilih lagi untuk radio lain; radio yang sudah
  memakainya tidak berubah. Master data hanya bisa dihapus bila tidak dipakai radio mana pun.

## Instalasi

Folder `vendor/` sudah disalin dari base app, jadi `composer install` tidak wajib.

1. Buat database di SQL Server (nama boleh mengandung spasi):

   ```sql
   CREATE DATABASE [MTI Radio Management];
   ```

2. Sesuaikan `.env` (lihat `.env.example`):

   ```ini
   DB_CONNECTION=sqlsrv
   DB_HOST=<host SQL Server>
   DB_PORT=1433
   DB_DATABASE="MTI Radio Management"
   DB_USERNAME=sa
   DB_PASSWORD=...
   DB_ENCRYPT=yes
   DB_TRUST_SERVER_CERTIFICATE=true
   ```

   Untuk instance bernama, isi `DB_HOST=SERVER\NAMAINSTANCE` dan kosongkan `DB_PORT`.

3. Tabel + data awal (role, menu, master radio). **Tidak ada akun yang dibuat** di langkah ini:

   ```bash
   php artisan migrate --seed
   ```

   Seeder bawaan base app hanya berjalan di database kosong. Di database yang sudah berisi,
   `--seed` hanya menyegarkan master radio dan menu radio.

4. Impor register Excel (default: file di root proyek):

   ```bash
   php artisan radio:import
   php artisan radio:import "D:\path\file.xlsx" --fresh   # impor ulang, hapus data radio lama
   ```

   Bisa juga dari menu **Import Excel**.

5. Daftarkan akun Microsoft pertama (SSO hanya menerima e-mail yang sudah ada di tabel `users`):

   ```bash
   php artisan user:register nama@merdekabattery.com --role="Super Admin"
   ```

   Perintah yang sama dipakai bila admin terkunci dari aplikasi (tulis `--role`-nya). Untuk akun
   yang sudah ada, perintah ini selalu mengaktifkan akunnya (akun yang sudah dihapus dipulihkan)
   dan mengganti password-nya dengan nilai acak. Role dan company hanya berubah bila diketik:
   `--role` menambah role, `--role=... --replace` menggantinya, tanpa `--role` role tidak disentuh.
   Role bawaan "Super Admin" hanya berlaku untuk akun yang benar-benar baru. Akun tanpa role
   tidak bisa login, dan perintah akan mengingatkannya.

6. Jalankan `php artisan serve` (port 8020 dari `SERVER_PORT` di `.env`), buka
   <http://localhost:8020> dan klik tombol Microsoft.

Database yang sudah ada (misalnya dari base app) cukup ditambah menu radio:
`php artisan migrate` lalu `php artisan db:seed --class=RadioMasterSeeder` dan
`php artisan db:seed --class=RadioMenuSeeder`.

## Login

- **Microsoft SSO** (`MS_GRAPH_TENANT_ID`, `MS_GRAPH_CLIENT_ID`, `MS_GRAPH_CLIENT_SECRET`, `MS_SSO_REDIRECT_URI` di `.env`).
  E-mail yang dicocokkan adalah `userPrincipalName` dari Microsoft. Akun harus sudah terdaftar dan punya role.
- Buka aplikasi lewat host yang sama dengan `MS_SSO_REDIRECT_URI` (`localhost`). Banner `php artisan serve`
  menulis `127.0.0.1`; dari sana tombol Microsoft otomatis pindah ke `localhost` dulu.
- Pendaftaran mandiri (`/register`) dan reset password dimatikan. User dibuat lewat **User Management** atau
  `php artisan user:register <email> --role="Admin Radio" --company=MTI`. Password akun diisi acak, jadi form
  e-mail + password tidak bisa dipakai untuk akun tersebut.
- Login SSO tidak memakai "remember me": akses berakhir bersama sesi (`SESSION_LIFETIME`).
- Setelah login, user masuk ke halaman awal role-nya (atau menu pertama yang boleh dibukanya).
- Form e-mail + password dibatasi 10 percobaan per menit.

## Role

`RadioMenuSeeder` membuat dua role:

- **Admin Radio**: akses penuh (tambah/ubah/hapus) ke semua menu radio.
- **Viewer Radio**: hanya baca.

Super Admin (role 1) otomatis mendapat semua menu. Atur user di **User Management**.

Untuk role buatan sendiri (halaman Role & Permission), izin per menu berarti:

| Izin | Register Radio | Master Data | Import Excel |
| --- | --- | --- | --- |
| Read | daftar, detail, export | daftar | - |
| Create | tambah radio | tambah | impor ke register kosong |
| Update | edit, arsip/pulihkan, tambah riwayat | edit | - |
| Delete | hapus radio | hapus | - |

Impor dengan **"Hapus semua data radio & riwayat"** membutuhkan Create pada Import Excel **dan**
Delete pada Register Radio. Role Super Admin hanya bisa diberikan atau dicabut oleh Super Admin,
dan tidak ada yang bisa menghapus akunnya sendiri.

User dengan role yang dibatasi ke perusahaan tertentu (tipe HO / SITE / COMPANY):

- hanya melihat dan mengelola user perusahaan dalam cakupannya, walau juga memegang role bertipe ALL;
- hanya bisa memberi role sejenis yang cakupannya tidak lebih luas dari miliknya. Role lain yang
  sudah dimiliki seorang user tidak ditampilkan di form-nya dan tidak ikut berubah;
- tidak bisa melepas role terbatas miliknya sendiri, dan tidak bisa mengganti e-mail user yang
  memegang role di luar kelolaannya (login memakai e-mail, jadi itu sama dengan mengambil alih akun).

Semua role bawaan aplikasi ini bertipe ALL, jadi aturan ini baru berlaku bila Anda membuat role terbatas.

### Menu Approval Management

Modul approval bawaan base app tidak berfungsi di aplikasi ini, jadi cabang menu **Approval Management**
tidak ditampilkan: menunya tetap ada, hanya hak aksesnya yang dicabut (instalasi baru oleh `DatabaseSeeder`,
database yang sudah dipakai oleh migrasi `2026_10_03_120000_hide_approval_menus`). Untuk menampilkannya lagi,
buka **Role & Permission**, edit role-nya, lalu centang halaman di bawah **Approval Management**.

Di form role, grup dan halamannya saling mengikuti: mencentang sebuah halaman ikut mencentang grupnya
(halaman hanya tampil di sidebar bila grupnya juga diberikan), dan melepas centang grup melepas semua
halaman di bawahnya.

## Struktur kode

```text
modules/RadioManagement/
  Config/radio.php          aturan ID, verifikasi, alasan arsip, jenis riwayat
  Enums/                    Placement (Posisi), Condition (Kondisi)
  Models/                   Radio, RadioHistory, RadioType, RadioCompany, RadioDepartment, ChannelTemplate
  Services/                 RadioService (simpan + riwayat), RadioIdService, RadioConflicts (ID/SN bebas?),
                            RegisterLock (satu penyimpanan pada satu waktu), RadioCheckService,
                            RadioFilter, Like (pencarian teks apa adanya), RadioImportService
  Http/Controllers/         Radio, MasterData (4 master), RadioHistory, RadioImport
  Http/Requests/RadioRequest.php
  Exports/RadioExport.php
  Console/ImportRadioRegister.php   (php artisan radio:import)
  Resources/views/          radio/, master/, history/, import/, partials/
  Routes/web/radio-management.php
database/migrations/2026_10_03_100000_create_radio_management_tables.php
database/seeders/RadioMasterSeeder.php, RadioMenuSeeder.php
modules/Dashboard/          diganti menjadi dashboard radio
```

## Catatan SQL Server

Penyesuaian terhadap base app supaya berjalan di SQL Server:

- Foreign key `CASCADE`/`SET NULL` yang membentuk jalur ganda (contractor, approval, menu
  parent) memakai `NO ACTION` di SQL Server.
- Seeder bawaan yang mengisi ID eksplisit dibungkus `SET IDENTITY_INSERT` (`DatabaseSeeder`).
- Daftar radio mengurutkan kolom ID dulu, lalu mengambil baris lengkap. SQL Server lambat
  mengurutkan baris yang membawa kolom `nvarchar(max)`.
- Pencarian memakai `LIKE ... ESCAPE '!'`: di SQL Server `[`, `%` dan `_` adalah wildcard,
  jadi teks yang diketik di-escape dulu.
- Nilai Excel yang tidak muat di kolom (SN > 50 karakter, Radio ID di atas batas) tidak
  menggagalkan impor: nilainya dipotong/dikosongkan dan radionya ditandai **Perlu dicek**.

## Zona waktu

`APP_TIMEZONE` di `.env` (default `Asia/Jakarta`). Komputer pengguna MTI memakai WITA (UTC+8),
jadi dipakai `Asia/Makassar`, supaya jam yang tampil dan "hari ini" pada validasi tanggal sama
dengan jam di komputer.

## Test

```bash
php vendor/bin/phpunit
```

Test **menghapus isi database-nya**. `phpunit.xml` mengarah ke database terpisah
`MTI Radio Management Test` di SQL Server LocalDB `(localdb)\MSSQLLocalDB`, bukan database
di `.env`. Test menolak berjalan bila nama database-nya tidak berakhiran `Test`.

JavaScript di halaman tidak dijalankan oleh PHPUnit. `BladeScriptSyntaxTest` memeriksa sintaks
setiap `<script>` di view dengan Node.js (dilewati bila Node.js tidak terpasang).
