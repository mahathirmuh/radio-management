# MTI Radio Management

Aplikasi register radio HT & rig (MOTOTRBO), pengganti sheet **Form** di
`Radio Register 07-08-25.xlsx`. Dibangun di atas **Base-Apps-Merdeka**
(Laravel 12, tema Tabler, modul di `modules/`, RBAC menu/role/permission).

## Isi aplikasi

| Menu | Fungsi |
| --- | --- |
| Dashboard | Ringkasan radio aktif, kondisi, hasil cek, per company/tipe, ID bebas berikutnya |
| Register Radio | Daftar radio (1 baris = 1 radio fisik / SN), filter, tambah/edit, arsip/pulihkan, cetak form, export Excel |
| Detail Radio | Data lengkap, hasil cek, riwayat, data asli Excel |
| Riwayat Radio | Log serah terima, status, program, verifikasi, arsip (otomatis + manual) |
| Master Data Radio | Tipe Radio, Perusahaan, Department, Template Channel, dan **E-Sign Master** (tanda tangan tim ICT, lihat [E-Sign Master](#e-sign-master)) |
| Import Excel | Impor register lama (sheet Form + to Konawe) |
| COF Scan | Isi folder SharePoint berisi scan IT Equipment Checkout Form (lihat [Dokumen SharePoint](#dokumen-sharepoint)) |
| ICT Masterlist | Isi workbook Excel "ICT Masterlist" di SharePoint sebagai tabel, satu tab per sheet; sel bisa diajukan untuk diubah (lihat [Dokumen SharePoint](#dokumen-sharepoint)) |
| Persetujuan Edit | Pengajuan perubahan sel workbook, dan persetujuannya; baru setelah disetujui orang lain workbook di SharePoint berubah (lihat [Mengubah isi workbook](#mengubah-isi-workbook-pengajuan-dan-persetujuan)) |

Aturan utama:

- **Radio ID** unik di antara radio aktif. **MDC Code** adalah Radio ID dalam heksadesimal
  (1001 → 3E9). ID yang MDC Code-nya mengandung huruf **F** tidak bisa dipakai (wildcard MDC):
  daftar "ID bebas" melewatinya, dan bila diketik, form menjelaskan alasannya serta menawarkan
  ID bebas terdekat (751 = 2EF → 768 = 300). ID terakhir yang diketahui dari radio tanpa ID
  ikut dicadangkan.
- **SN** unik. **MDC Code**, **kategori** dan **daya** dihitung otomatis.
- **Owner** (kolom di Register Radio, filter, detail dan Export) berisi **MTI** atau **Non MTI**. Tidak diketik:
  mengikuti **Pemilik Aset** radio itu (MTI bila kode perusahaannya `MTI`, lihat `owner_mti_code` di
  `modules/RadioManagement/Config/radio.php`; Non MTI untuk perusahaan lain). Radio yang Pemilik Aset-nya belum diisi
  tampil "-" dan tidak ikut di kedua pilihan filter: isi Pemilik Aset-nya lewat Edit.
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
- Tombol **Cetak** (ikon printer di kolom Aksi) membuka pop-up berisi **IT Equipment Checkout Form**
  (MTI-HRM-ICT-FRM-001 Rev.000) untuk radio itu, siap dicetak di satu halaman A4. Yang diisi dari
  register: Radio ID (No. ID), tipe (Merk & Type), Serial Number, nama pengguna (Employee Name di
  REQUESTOR dan RESPONSIBLE USER), department, dan tanggal cetak (Date di PROCESSED BY). Pilihan
  **HT / RIG** yang bukan jenis radio itu dicoret (klik tulisannya untuk mencoret atau membatalkan).
  Isian lain dikosongkan untuk diisi tangan, dan semua isian bisa diubah di pop-up sebelum dicetak;
  perubahan itu hanya untuk cetakan itu dan **tidak disimpan**. Siapa pun yang boleh membuka Register
  Radio (Read) boleh mencetak, juga untuk radio yang sudah diarsipkan (Radio ID-nya sudah dilepas,
  jadi kotak "No. ID" dibiarkan kosong). Logo di form adalah `public/assets/img/branding/mti-logo.png`.
  - **Tanda tangan di layar** (Responsible User): kotak tanda tangan di blok RESPONSIBLE USER diklik,
    lalu muncul kanvas besar untuk menggambar tanda tangan dengan pena, jari, atau mouse. Tekanan pena
    dipakai (makin keras makin tebal) bila pena dikenali browser sebagai pena, yaitu lewat Windows Ink;
    pena yang tidak melaporkan tekanan digambar setebal mouse. Selama pena terdeteksi (sampai 2,5 detik
    setelah gerakan pena terakhir, termasuk pena yang hanya melayang), sentuhan jari diabaikan supaya
    telapak tangan yang bersandar di layar pena tidak ikut mencoret; tombol samping dan ujung penghapus
    pena tidak menggambar. Coretan sepanjang kurang dari 6% lebar kanvas (ketukan, titik) tidak dianggap
    tanda tangan. **Pakai tanda tangan** memasukkannya ke kotak sebagai gambar yang ikut tercetak atau
    tersimpan ke PDF, dan mengisi "Date:" dengan hari itu (isian yang sudah ada tidak ditimpa). Tombol ×
    di kotak menghapusnya. Tanda tangan **tidak disimpan** dan tidak dikirim ke mana pun: hilang saat
    pop-up ditutup. Kotak PROCESSED BY (ICT) tetap ditandatangani di kertas. Tanpa tanda tangan,
    kotaknya kosong seperti biasa.
  - Selagi kanvas masih terbuka, **Cetak** (dan Ctrl+P) tidak mencetak dan bilang apa yang harus
    dilakukan: coretan di kanvas belum ada di form dan akan tertinggal dari kertas. Menutup pop-up
    dengan tanda tangan yang belum dicetak (atau coretan yang belum dipakai) bertanya dulu.
  - Dicetak **tanpa header dan footer browser** (judul dan alamat halaman): halaman form tidak punya
    margin sendiri, margin 12 mm di atas dan bawah adalah padding isinya, dan lembarnya selebar 160 mm
    di tengah halaman. Bila isian membuat form lebih dari satu halaman A4, muncul peringatan merah di
    layar, dan saat dicetak form **diperkecil otomatis** (tulisannya ikut mengecil; peringatan
    menyebut persennya) supaya tetap satu halaman. Ctrl+P di pop-up mencetak form, bukan halaman register.
  - Pop-up tidak tertutup oleh klik di luarnya atau tombol Esc (yang diketik dan ditandatangani akan
    hilang); tutup dengan **Tutup**. Setiap form memakai frame baru, supaya tombol Back browser tidak
    penuh dengan riwayat form. Bila yang dimuat bukan form (sesi berakhir, radio sudah dihapus, atau
    tidak ada izin), pop-up bilang begitu dan tidak menampilkan halaman itu.

## Instalasi

Folder `vendor/` tidak ikut repo: setelah clone jalankan `composer install` lebih dulu (di komputer yang
folder itu sudah ada, tidak perlu).

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

4. Impor register Excel. File register tidak ikut repo (isinya nama pemegang radio): beri path-nya,
   atau taruh sebagai `Radio Register 07-08-25.xlsx` di root proyek (default perintah ini):

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
  e-mail + password tidak bisa dipakai untuk akun tersebut, kecuali:
  - akun yang punya **Directory username** (metode sign-in **LDAP** di User Management): form itu memeriksa
    password Active Directory-nya, lihat [Halaman Users](#halaman-users);
  - akun yang diberi password lewat tombol kunci di User Management.
- Akun yang dinonaktifkan di User Management ditolak di semua cara login, dan sesi yang masih terbuka berakhir
  pada permintaan berikutnya.
- Login SSO tidak memakai "remember me": akses berakhir bersama sesi (`SESSION_LIFETIME`).
- Setelah login, user masuk ke halaman awal role-nya (atau menu pertama yang boleh dibukanya).
- Form e-mail + password dibatasi 10 percobaan per menit.

## Role

`RadioMenuSeeder` membuat dua role:

- **Admin Radio**: akses penuh (tambah/ubah/hapus) ke semua menu radio.
- **Viewer Radio**: hanya baca.

`DocumentEditAccessSeeder` membuat role ketiga, untuk dokumen SharePoint:

- **Document Controller**: membaca COF Scan dan ICT Masterlist, mengajukan perubahan sel ICT Masterlist, dan
  **menyetujui atau menolak** pengajuan di menu Persetujuan Edit. Tidak punya akses ke menu radio.

Super Admin (role 1) otomatis mendapat semua menu. Atur user di **User Management**.

Form tambah/edit user menanyakan **Position** (Manager, Superintendent, Supervisor, IT Field, IT System Support, IT Assistant; daftarnya di
`User::POSITIONS`), bukan lagi Company. Akun baru otomatis masuk ke perusahaan orang yang menambahkannya, dan
perusahaan akun yang sudah ada tidak berubah saat diedit. Akun lama belum punya Position sampai diedit. Kolom
`users.position` ditambahkan migrasi `2026_10_12_140000_add_position_to_users_table`; sebelum migrasi dijalankan,
menyimpan user ditolak dengan keterangan itu.

### Halaman Users

Daftar user (`/user-management/user-registered`) menampilkan Name (dengan Position di bawahnya), Email, Role,
**Status**, **Sign-In** dan **Last Sign-In**. Tombol per baris: edit, nonaktifkan/aktifkan, atur password, hapus;
baris akun sendiri hanya punya edit.

**Sign-In** tidak disimpan, tetapi dibaca dari dua hal (`User::signInMethod()`): apakah akun punya Directory
username (`users.ldap_username`), dan apakah akun pernah login lewat Microsoft (`users.microsoft_linked_at`).

| Sign-In | Artinya |
| --- | --- |
| LDAP | Punya Directory username; belum pernah login lewat Microsoft |
| Microsoft | Login lewat Microsoft saja |
| Both | Punya Directory username dan pernah login lewat Microsoft |
| Pending | Belum pernah dipakai login. Bila terus begini, periksa apakah e-mailnya sama dengan alamat di Microsoft |

Akun tanpa keduanya yang pernah masuk dengan password buatan admin tampil sebagai "Password". Tombol kunci
(atur password) tidak ada pada akun LDAP: password akun itu ada di Active Directory, bukan di sini.

Form tambah/edit user:

- **Find employee**: mencari karyawan di database HR menurut nama atau e-mail kantor; memilih satu mengisi Name
  dan Email. Hanya alamat di domain `EMPLOYEE_DIRECTORY_DOMAINS` yang ditawarkan (alamat pribadi atau isian
  seperti "no need" tidak bisa login di sini). Tampil hanya bila `HR_DB_HOST` dkk. diisi di `.env`
  (`config/employee_directory.php`, koneksi `hr` di `config/database.php`), dan hanya untuk yang boleh menambah
  atau mengubah user. Yang dibaca tiga kolom saja (nomor karyawan, nama, e-mail kantor): nama tabel (lebih baik
  sebuah view berisi tiga kolom itu saja) dan nama kolomnya disetel lewat `EMPLOYEE_DIRECTORY_TABLE` dan
  `EMPLOYEE_DIRECTORY_*_COLUMN` di `.env`.
  Database HR tidak terjangkau = pencarian kosong (dicatat di log), form tetap bisa diisi dengan mengetik.
- **Sign-in method** (LDAP / Microsoft) dan **Directory username**: tampil hanya bila Active Directory disetel
  (`LDAP_URL` dan `LDAP_BIND_DN` di `.env`, `config/ldap.php`). LDAP mencatat Directory username; dikosongkan
  berarti diambil dari bagian e-mail sebelum `@`. Microsoft tidak mencatat apa pun. Satu akun direktori hanya
  untuk satu akun di sini.
- Login LDAP: di form e-mail + password, password diperiksa dengan bind ke Active Directory sebagai orang itu
  (`App\Services\Directory\LdapAuthenticator`); tidak ada password yang disimpan. Password kosong ditolak sebelum
  sampai ke direktori, direktori yang tidak bisa dihubungi dilaporkan sebagai gangguan (bukan "password salah"),
  dan tanpa "remember me". **Perlu ekstensi PHP `ldap`**; selama ekstensi itu tidak dimuat, form user memberi
  catatan dan akun LDAP tetap bisa masuk lewat Microsoft.
- `LDAP_TLS_VERIFY` sebaiknya `true`. `false` hanya selama sertifikat domain controller berasal dari CA internal
  yang belum dipercaya server ini: tanpa pemeriksaan, password yang diketik bisa diterima pihak yang menyadap.

Kolomnya ditambahkan migrasi `2026_10_13_100000_add_sign_in_to_users_table` (login terakhir) dan
`2026_10_13_110000_add_directory_sign_in_to_users_table` (Directory username). Sebelum migrasi dijalankan halaman
tetap jalan: Sign-In tampil "Pending", dan pilihan LDAP belum ada. Tes tidak pernah menghubungi database HR atau
Active Directory (`phpunit.xml` mengosongkan setelannya; penggantinya di `tests/Concerns/FakesDirectory.php`).

Untuk role buatan sendiri (halaman Role & Permission), izin per menu berarti:

| Izin | Register Radio | Master Data | Import Excel |
| --- | --- | --- | --- |
| Read | daftar, detail, export, cetak form | daftar | - |
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

Semua role bawaan aplikasi ini bertipe ALL, jadi aturan ini baru berlaku bila ada role terbatas. Halaman
**Role & Permission** tidak lagi menampilkan atau menanyakan tipe dan daftar perusahaan: role baru selalu bertipe ALL,
dan mengedit role tidak mengubah tipenya. Role terbatas hanya bisa dibuat langsung di database (atau lewat permintaan
yang menyebut `role_type`), dan aturan di atas tetap berlaku untuknya.

### Menu Approval Management

Modul approval bawaan base app tidak berfungsi di aplikasi ini, jadi cabang menu **Approval Management**
tidak ditampilkan: menunya tetap ada, hanya hak aksesnya yang dicabut (instalasi baru oleh `DatabaseSeeder`,
database yang sudah dipakai oleh migrasi `2026_10_03_120000_hide_approval_menus`). Untuk menampilkannya lagi,
buka **Role & Permission**, edit role-nya, lalu centang halaman di bawah **Approval Management**.

Di form role, grup dan halamannya saling mengikuti: mencentang sebuah halaman ikut mencentang grupnya
(halaman hanya tampil di sidebar bila grupnya juga diberikan), dan melepas centang grup melepas semua
halaman di bawahnya.

## E-Sign Master

Menu **E-Sign Master** (di grup Master Data Radio) adalah gudang tanda tangan tim ICT. Tanda tangan itu mengisi kotak
**PROCESSED BY (ICT Person)** di IT Equipment Checkout Form (tombol **Cetak** di Register Radio), yang sebelumnya
selalu ditandatangani di kertas.

- **Yang mengelola: Super Admin.** Ia mendaftarkan tanda tangan untuk sebuah **akun**: pilih akunnya, lalu pemilik
  tanda tangan menandatangani di kotaknya (pena Wacom, jari, atau mouse; pad yang sama dengan pad di form). Satu akun
  satu tanda tangan. Bisa diganti, dinonaktifkan, atau dihapus. Menu ini tidak diberikan ke Admin Radio; role lain
  diberi lewat Role & Permission.
- **Di form, yang muncul adalah tanda tangan akun yang sedang membuka form itu**, beserta nama tercetaknya, dan tidak
  pernah tanda tangan orang lain: Super Admin yang mendaftarkan tanda tangan seseorang tidak mendapatkannya di formnya
  sendiri. Jadi tanda tangan di PROCESSED BY menyatakan siapa yang memproses. Akun tanpa tanda tangan (atau yang
  dinonaktifkan) mendapat kotak kosong, untuk ditandatangani di kertas seperti dulu.
- Tanda tangan bisa **dilepas dari satu form** sebelum dicetak (tombol × di kotaknya); yang di E-Sign Master tetap.
- **Setiap pemakaian dicatat** (siapa, radio mana, kapan) dan bisa dilihat lewat tombol riwayat. Dicatat saat form
  dibuka dengan tanda tangan di dalamnya, bukan saat dicetak (aplikasi tidak tahu apakah dialog cetak diselesaikan).
- **Penyimpanan**: gambar disimpan di database, terenkripsi dengan kunci aplikasi (`APP_KEY`), tidak pernah sebagai file
  dan tidak pernah di folder publik. Yang disimpan adalah PNG yang digambar ulang server dari piksel kiriman browser
  (isi lain yang menumpang di file tidak ikut). **Bila `APP_KEY` diganti, tanda tangan yang tersimpan tidak bisa dibaca
  lagi** dan harus didaftarkan ulang.
- Ini gambar tanda tangan, bukan tanda tangan digital bersertifikat: kekuatannya sebagai bukti ada pada catatan
  pemakaian dan pada aturan bahwa hanya pemilik akun yang bisa memunculkannya.
- Tabel dan menunya ditambahkan migrasi `2026_10_12_120000_create_it_signatures_tables` dan
  `2026_10_12_130000_add_e_sign_menu` (instalasi baru: `DatabaseSeeder`). Sebelum migrasi dijalankan, form dicetak
  tanpa tanda tangan dan halaman E-Sign Master bilang tabelnya belum ada. Selama baris menunya tidak ada, halaman ini
  tertutup untuk semua orang kecuali Super Admin.
- Belum diuji: pena Wacom sungguhan di halaman pendaftaran (pad-nya sama dengan pad form), dan tampilan di browser
  selain lewat pemeriksa skrip.

## Dokumen SharePoint

Bagian "Dokumen" di sidebar menampilkan isi SharePoint di dalam aplikasi: sebuah folder (**COF Scan**)
dan sebuah workbook Excel (**ICT Masterlist**). Keduanya dibaca aplikasi lewat Microsoft Graph dengan
identitasnya sendiri, yaitu app Entra yang sama dengan SSO (`MS_GRAPH_*`). App itu perlu izin
**aplikasi** `Sites.Read.All` yang sudah diberi *admin consent*. Aplikasi hanya membaca.

### COF Scan (folder)

Menu **COF Scan** menampilkan isi sebuah folder SharePoint: cari berdasarkan nomor atau nama, urutkan
(termasuk per **Tipe** file), dan lihat file-nya.

- **Daftar** berisi semua file dan subfolder di folder itu, yang terbaru di atas.
- **Lihat** (tombol mata): file ditampilkan di jendela pop-up di dalam aplikasi, dengan tombol
  sebelumnya/berikutnya, buka di tab baru, dan buka di SharePoint. Isi file diambil aplikasi dari
  SharePoint dengan identitasnya sendiri, jadi **siapa pun yang diberi menu ini (Read) bisa melihat isi
  file-nya, walau tidak punya akses ke folder itu di SharePoint**. Karena itu menu ini hanya diberikan
  ke **Super Admin** dan **Admin Radio**; role lain dicentang sendiri di **Role & Permission** (cukup
  Read). Setiap file yang ditampilkan dicatat di log aplikasi (user dan nama file).
  - Yang ditampilkan di sini hanya PDF dan gambar (png, jpg, gif, webp) sampai 10 MB
    (`preview_max_mb` di `modules/SharePointDocs/Config/sharepoint-docs.php`). Jenis lain dan file
    yang lebih besar mendapat tombol "buka di SharePoint" sebagai gantinya.
  - Menampilkan satu file menunggu paling lama 25 detik (`preview_time_limit`). File yang tidak
    sempat tiba dalam waktu itu (koneksi lambat) dilaporkan sebagai "terlalu lama diambil", bukan
    sebagai gangguan SharePoint.
  - File tidak disimpan di server dan tidak ditinggalkan di cache browser.
  - Browser yang diatur untuk mengunduh PDF (bukan menampilkannya) tidak menampilkan apa pun di
    pop-up; setelah beberapa detik pop-up menunjuk ke tombol "tab baru" dan "SharePoint".
  - Bila sesi login sudah berakhir, pop-up meminta halaman dimuat ulang.
- **Klik nama file** membuka file di SharePoint, dengan akun Microsoft orang yang mengklik.
- Alamat foldernya diisi di `.env`: buka foldernya di browser, lalu salin alamat dari *address bar*:

  ```ini
  SHAREPOINT_COF_SCAN_URL="https://<tenant>.sharepoint.com/sites/<situs>/Shared%20Documents/Forms/AllItems.aspx?id=..."
  ```

  Jangan memakai tautan hasil **Copy link / Share**: tautan seperti itu memberi akses kepada siapa pun
  yang memegangnya. (Bila tetap dipakai, foldernya ditemukan, tetapi tautannya tidak ditampilkan di
  halaman; tombol "Buka di SharePoint" memakai alamat folder yang biasa.)

  Folder dikenali dari alamat itu, lalu diingat sehari. Alamat yang diganti langsung berlaku (bila
  konfigurasi di-cache: `php artisan config:clear`). Bila foldernya diganti nama atau dipindahkan,
  alamat di `.env` harus ikut diganti.
- Daftar disimpan sementara `SHAREPOINT_DOCS_CACHE_MINUTES` menit (bawaan 10), jadi file yang baru
  diunggah muncul paling lambat setelah itu; tombol **Muat ulang** mengambilnya saat itu juga (paling
  cepat 15 detik setelah pengambilan terakhir).
- Bila Microsoft sedang tidak bisa dihubungi (jaringan putus, server sibuk), daftar terakhir tetap
  ditampilkan dengan keterangan, paling lama sehari, dan Microsoft baru dihubungi lagi semenit kemudian
  (hal yang sama berlaku per subfolder dan per file yang gagal diambil). Satu kali memuat daftar
  menunggu Microsoft paling lama 20 detik (`time_limit`; bila halaman ini juga membaca workbook untuk
  kolom Masterlist, lihat "Waktu" di bagian COF Scan dan ICT Masterlist saling dicek). Jawaban yang pasti dari Microsoft (izin
  dicabut, folder tidak ada, client secret ditolak) langsung ditampilkan sebagai pesan, tanpa daftar
  lama.
- Nama file berpola `ICTCOF123 - Nama.pdf` dipecah menjadi kolom **No. COF** dan **Nama**; file dengan
  nama lain tetap tampil apa adanya. Subfolder bisa dibuka dari daftar.
- Menu ditambahkan migrasi `2026_10_07_100000_add_cof_scan_menu` (instalasi baru: `DatabaseSeeder`).
  Selama baris menunya tidak ada, halaman ini tertutup untuk semua orang kecuali Super Admin.
- Folder lain: tambahkan entri di `modules/SharePointDocs/Config/sharepoint-docs.php` dan satu baris
  menu dengan route `documents.<kunci>.index` (contoh: `CofScanMenuSeeder`).

### ICT Masterlist (workbook Excel)

Menu **ICT Masterlist** menampilkan isi sebuah workbook Excel (`.xlsx`) di SharePoint sebagai tabel:
satu tab per sheet, bisa dicari, diurutkan dan dibuka halaman per halaman. Halaman ini
**menampilkan**; sel bisa diajukan untuk diubah oleh role yang berhak, tetapi baru berubah di SharePoint
setelah disetujui orang lain (lihat [Mengubah isi workbook](#mengubah-isi-workbook-pengajuan-dan-persetujuan)).
Selain itu mengubah data dilakukan di Excel (tombol **Buka di Excel**).

- **Tab**: setiap sheet yang terlihat di Excel menjadi satu tab, dengan jumlah barisnya. Sheet yang
  disembunyikan di Excel tidak ditampilkan. Bila perlu, daftar sheet ditentukan sendiri (urutannya,
  termasuk sheet tersembunyi) lewat `sheets` di `modules/SharePointDocs/Config/sharepoint-docs.php`;
  perubahannya langsung berlaku (isi yang tersimpan dibuat per pilihan sheet).
  **Hanya sheet yang tersembunyi yang tidak ditampilkan**: kolom dan baris yang disembunyikan di Excel,
  atau baris yang tersaring oleh filter, tetap tampil dan ikut dicari.
- **Judul kolom** adalah baris judul sheet: baris pertama, atau baris di bawah judul besar. Kolom yang
  berisi tetapi tidak berjudul tampil sebagai "Kolom A", "Kolom AG", dan seterusnya.
- **Baris**: kolom "Baris" adalah nomor baris di Excel. Urutan awal: baris terakhir di atas, yaitu
  isian terbaru. Register di workbook ini diberi nomor lebih dulu, jadi baris yang baru berisi nomornya
  saja tidak ditampilkan: baris yang paling banyak punya satu sel yang diketik (sel hasil rumus tidak
  dihitung). Pengaturannya `skip_unused_rows`; `false` menampilkan semua baris.
- **Isi sel** ditampilkan seperti yang terlihat di Excel: tanggal sebagai `dd-mm-yyyy`, angka menurut
  format selnya (ribuan dengan titik, desimal dengan koma; lama waktu `[h]:mm` sebagai jam), rumus
  sebagai hasil hitungan Excel saat workbook terakhir disimpan (aplikasi tidak menghitung ulang rumus).
  Sel yang isinya alamat `https://…`, misalnya tautan ke scan, bisa diklik.
- **Cari** mencari di semua kolom dan semua sheet sekaligus (kecuali kolom **File scan** di sheet COF, lihat
  bagian COF Scan dan ICT Masterlist saling dicek): setiap tab menunjukkan jumlah baris yang
  cocok di sheet-nya. Bila beberapa kata diketik, semuanya harus ada di baris itu.
- **Klik judul kolom** mengurutkan menurut kolom itu: angka dan tanggal menurut nilainya, teks menurut
  abjad dengan angka di dalamnya dibaca sebagai angka (COF2 sebelum COF10). Sel kosong selalu di bawah.
- Sama seperti COF Scan, isi workbook dibaca aplikasi dengan identitasnya sendiri: **siapa pun yang
  diberi menu ini (Read) melihat seluruh isi sheet yang ditampilkan, walau tidak punya akses ke
  workbook itu di SharePoint**. Menu ini diberikan ke **Super Admin**, **Admin Radio** dan **Document Controller**.
- Alamat workbook diisi di `.env`: buka workbook-nya di browser (Excel untuk web), lalu salin alamat
  dari *address bar*:

  ```ini
  SHAREPOINT_ICT_MASTERLIST_URL="https://<tenant>.sharepoint.com/:x:/r/sites/<situs>/_layouts/15/Doc.aspx?sourcedoc=..."
  ```

  Alamat ini hanya dipakai untuk menemukan workbook dan tidak pernah ditampilkan di halaman. Bila
  workbook-nya diganti dengan file lain, alamat di `.env` harus ikut diganti.
- Isi workbook disimpan sementara dengan cara yang sama seperti daftar folder
  (`SHAREPOINT_DOCS_CACHE_MINUTES`, tombol **Muat ulang**, isi terakhir tetap tampil dengan keterangan
  selama Microsoft tidak bisa dihubungi atau file-nya tidak tiba tepat waktu). Workbook yang belum
  disimpan ulang sejak pembacaan terakhir tidak diunduh lagi. Bila yang diterima bukan workbook sama
  sekali (halaman sebuah filter web, misalnya), atau file yang tiba rusak, itu dianggap Microsoft tidak
  bisa dihubungi: isi terakhir tetap tampil dan file-nya dicoba lagi. Workbook yang dibuka tetapi tidak
  bisa dibaca atau terlalu besar tidak diunduh dan dibaca lagi sebelum disimpan ulang, sebelum batas
  yang menentukannya diubah (`workbook_max_cells`, `workbook_max_mb`), atau setelah
  `SHAREPOINT_DOCS_CACHE_MINUTES` menit (bawaan 10); isi yang tersimpan dibuang.
- Batas: workbook sampai 15 MB (`workbook_max_mb`) dan 200.000 sel yang berisi nilai di semua sheet
  (`workbook_max_cells`; sel yang hanya berformat atau di luar kolom ke-80 tidak dihitung). Workbook
  yang lebih besar **ditolak seluruhnya** ("terlalu besar"), bukan dipotong. Itu juga yang terjadi pada
  workbook dengan lebih dari 500 sheet, lebih dari 32 MB teks (teks bersama dihitung sekali untuk setiap
  sel yang memakainya), lebih dari 5.000.000 elemen sel (sel yang hanya berformat ikut dihitung),
  file yang mengembang di atas 256 MB, atau yang butuh lebih dari separuh `memory_limit` PHP; file yang
  memakai *document type declaration* ditolak, dan workbook dengan password tidak bisa dibaca. Per sheet
  dibaca 20.000 baris dan 80 kolom; sheet yang lebih panjang atau lebar dipotong dan diberi keterangan
  hanya bila yang terpotong memang berisi. Pembacaan (setelah unduhan) diberi sisa waktu
  `preview_time_limit`, tidak kurang dari 5 dan tidak lebih dari 15 detik; bila waktunya habis,
  halaman bilang "terlalu lama" (bukan "terlalu besar"), isi terakhir tetap tampil, dan baru bila
  pembacaan itu sudah diberi seluruh 15 detik, versi file itu tidak dicoba lagi sebelum waktunya.
  Format angka yang lebih panjang dari 255 karakter (batas Excel) diabaikan: angkanya tampil apa
  adanya. Yang dibaca hanya `.xlsx`, bukan `.xls` atau `.xlsb`.
- Alamat unduhan sekali pakai yang dipakai aplikasi untuk mengambil file memuat izin baca file itu,
  jadi tidak boleh sampai ke log atau ke layanan lain: pesan galat dicatat tanpa query-nya, dan
  breadcrumb serta span permintaan HTTP di `config/sentry.php` dimatikan.
- Menu ditambahkan migrasi `2026_10_07_110000_add_ict_masterlist_menu` (instalasi baru:
  `DatabaseSeeder`), di bawah COF Scan; menu User Management dan Approval Management bergeser satu
  urutan ke bawah. Selama baris menunya tidak ada, halaman ini tertutup untuk semua orang kecuali
  Super Admin.
- Workbook lain: tambahkan entri di bagian `workbooks` pada config yang sama dan satu baris menu dengan
  route `documents.<kunci>.index` (contoh: `IctMasterlistMenuSeeder`).

### Mengubah isi workbook (pengajuan dan persetujuan)

Sel di ICT Masterlist bisa diubah dari web, dengan dua langkah dan dua orang. **Tidak ada yang langsung
tertulis ke SharePoint.**

```text
Pengaju (Super Admin / Admin Radio / Document Controller)
  klik ikon pensil di sebuah sel -> isi nilai baru -> "Ajukan perubahan"
        |   disimpan di tabel sharepoint_edit_requests, status Menunggu
        v
Penyetuju (Document Controller; cadangan Super Admin) di menu "Persetujuan Edit"
  melihat nilai lama dan baru -> Tolak (dengan alasan)   atau   Setujui
        |   Setujui
        v
Aplikasi membaca workbook yang SEKARANG di SharePoint, dan menulis hanya bila:
  - sel itu masih berisi nilai lama, dan sel pengenal barisnya (mis. COF No) masih sama
  - sel itu bukan rumus
  - tidak ada yang menyimpan workbook di antara pembacaan dan penulisan
  ya    -> Diterapkan          sel/baris sudah berubah -> Konflik (tidak ditulis; ajukan lagi)
                               SharePoint tidak bisa ditulis -> Gagal (tidak ditulis; bisa disetujui lagi)
```

Siapa boleh apa ditentukan **menu**, seperti halaman lain (Role & Permission):

| Hak | Artinya |
| --- | --- |
| Update di **ICT Masterlist** | boleh mengajukan perubahan sel (ikon pensil muncul). Bawaan: Super Admin, Admin Radio, Document Controller |
| Read di **Persetujuan Edit** | boleh melihat daftar pengajuan. Bawaan: Super Admin, Admin Radio, Document Controller |
| Update di **Persetujuan Edit** | boleh menyetujui dan menolak. Bawaan: Super Admin, Document Controller |

Aturan yang tidak bisa dinyatakan menu:

- **Tidak ada yang menyetujui pengajuannya sendiri**, Super Admin pun tidak. Pengajuan Document Controller
  disetujui Super Admin (cadangan), dan pengajuan Super Admin disetujui Document Controller.
- **Sel berisi rumus tidak bisa diubah** (ikon gembok; ubah di Excel), juga sel yang di Excel sudah menjadi
  rumus setelah pengajuan dibuat. Kolom **File scan** bukan sel workbook, jadi tidak bisa diubah.
- Satu sel hanya punya **satu pengajuan yang menunggu** pada satu waktu.
- **Nilai**: sel yang berisi angka diisi angka (`1500` atau `1.500,50`, paling banyak 15 digit seperti Excel),
  sel tanggal diisi `hh-bb-tttt` (`15-03-2023`, atau `15-03-2023 14:30` bila ada jamnya), sel lain diisi teks
  dan **tetap teks** (`000123` tidak berubah menjadi 123). Sel kosong yang diisi angka biasa menjadi angka,
  seperti di Excel. Dikosongkan = tidak mengetik apa-apa. Teks yang diketik tidak pernah dibaca sebagai rumus.
- Hanya **isi sel** yang bisa diubah; menambah atau menghapus baris dan kolom belum.
- Setiap pengajuan tersimpan **selamanya** dengan nama pengaju, penyetuju, waktu, nilai lama dan baru. Itu
  catatan audit: di riwayat versi SharePoint semua perubahan ini tercatat atas nama **aplikasi**, bukan orangnya.

Cara menulisnya, dan akibatnya:

- **Excel REST API Microsoft Graph tidak bisa dipakai** untuk ini: Microsoft tidak menyediakannya bagi aplikasi
  yang masuk atas nama dirinya sendiri (hanya bagi user yang sedang masuk). Maka aplikasi mengunduh file `.xlsx`,
  mengubah **hanya satu sel** di XML sheet-nya (`XlsxPatcher`; bagian file lain, termasuk grafik, tabel pivot,
  format dan sheet lain, dibawa apa adanya), lalu menyimpannya kembali dengan header `If-Match` berisi versi file
  yang dibaca: SharePoint menolak bila ada yang menyimpan di antaranya (status Konflik, tidak ada yang tertimpa).
- Setiap penulisan menjadi **versi baru** file di SharePoint (Riwayat versi), yang bisa dipulihkan di sana.
- Rumus yang bergantung pada sel itu baru dihitung ulang saat workbook dibuka di Excel (aplikasi meminta Excel
  menghitung ulang semuanya saat dibuka, jadi Excel mungkin menawarkan menyimpan saat ditutup). Halaman ini
  menampilkan hasil hitung yang tersimpan, jadi hasil rumus yang bergantung pada sel yang baru diubah tetap
  lama sampai workbook dibuka dan disimpan di Excel.
- **File yang sedang dibuka orang lain di Excel desktop terkunci**: penulisan gagal dengan keterangan itu dan bisa
  disetujui lagi nanti. Workbook yang menghitung tanggal dari tahun 1904 belum bisa diubah untuk sel angka atau tanggal.
- Menulis butuh izin **aplikasi** untuk **menulis** ke site workbook itu, dengan admin consent: `Sites.Selected`
  dengan peran *write* hanya untuk site ICT (disarankan, karena hanya site itu yang bisa ditulis aplikasi), atau
  `Sites.ReadWrite.All`. Tanpa itu setiap persetujuan berakhir **Gagal**, dan tidak ada yang berubah. (Izin baca
  `Sites.Read.All` saja tidak cukup.) Ini belum bisa dicoba terhadap SharePoint sungguhan oleh test: test
  memakai Microsoft tiruan. **Cobalah dulu pada salinan workbook** (ganti `SHAREPOINT_ICT_MASTERLIST_URL`).
- Pengaturan di entri workbook (`modules/SharePointDocs/Config/sharepoint-docs.php`, bagian `edit`):
  `enabled` (`SHAREPOINT_ICT_MASTERLIST_EDIT=false` mematikan pengajuan untuk semua orang) dan `anchors` (judul
  kolom pengenal baris per sheet; sheet yang tidak disebut memakai kolom pertamanya, sheet COF memakai `COF No`).
- Menu dan role ditambahkan migrasi `2026_10_12_110000_add_edit_approvals_menu` (instalasi baru: `DatabaseSeeder`):
  menu **Persetujuan Edit** di bawah ICT Masterlist (User Management dan Approval Management bergeser satu urutan),
  role Document Controller, dan hak Update di ICT Masterlist untuk Admin Radio dan Document Controller. Tabelnya
  `2026_10_12_100000_create_sharepoint_edit_requests_table`. Migrasi ini dijalankan sekali; hak yang kemudian
  dicabut di Role & Permission tidak dikembalikan, kecuali `php artisan db:seed --class=DocumentEditAccessSeeder`
  dijalankan lagi.

### COF Scan dan ICT Masterlist saling dicek

Tab **COF** di ICT Masterlist dan folder COF Scan membicarakan hal yang sama: tiap baris COF punya link ke
file scan-nya. Aplikasi mencocokkan keduanya dan menampilkannya di dua halaman itu, tanpa menu baru dan tanpa
menulis apa pun ke SharePoint atau ke Excel. Pencocokan dihitung tiap halaman dibuka dari data yang sudah
dibaca (daftar folder dan isi workbook, masing-masing disimpan `SHAREPOINT_DOCS_CACHE_MINUTES` menit), jadi file
yang baru diunggah muncul paling lambat setelah itu, atau langsung lewat **Muat ulang** (di kedua halaman; itu
membaca ulang kedua sumber, paling cepat 15 detik setelah pembacaan terakhir).

- **ICT Masterlist, tab COF**: kolom terakhir **File scan** dan, di atas tabel, lima kartu yang menghitung baris
  sheet itu: **Total baris**, **Ada**, **Belum ada**, **Nama beda**, **Subfolder**. Klik sebuah kartu untuk hanya
  melihat barisnya, klik lagi (atau kartu Total) untuk semuanya; kartu yang kosong tidak bisa diklik (kecuali yang
  sedang terpilih). Pencarian tetap berlaku dan angka di kartu selalu untuk seluruh sheet. Kolom File scan **tidak
  ikut dicari** (kata "ada" akan menemukan hampir semua baris; untuk itu ada kartu), tetapi bisa diurutkan. Hanya
  sheet yang sedang tampil yang dicek: tab lain tidak membaca folder.
  - **Ada**: file yang disebut link di kolom link ada di folder, dengan nama yang sama (huruf besar atau kecil
    tidak dibedakan, seperti SharePoint). **Spasi dihitung**: link dengan satu spasi tidak menemukan file yang
    namanya memakai dua spasi, dan sebaliknya. (Karena itu pembaca workbook meringkas spasi di sel biasa tetapi
    tidak di sel yang berisi alamat web.) Link yang disalin dari penampil SharePoint, yang berekor `?web=1` atau
    `#...`, dicocokkan juga tanpa ekor itu. Alamat yang disalin dari *address bar* library
    (`.../Forms/AllItems.aspx?id=...&parent=...`) dan dari **Copy link** (`.../:b:/r/sites/...`) dibaca path
    file-nya (dari `id=`, atau setelah awalan `/:b:/r`); link berbentuk token (`/:b:/s/...`) tidak memuat nama
    file dan tidak bisa dicocokkan.
  - **Nama beda**: tidak ada file dengan nama yang disebut link (link-nya kosong atau bukan alamat file, menyebut
    nama lain, atau mengeja namanya lain: spasi lebih atau kurang), tetapi ada file dengan **nomor** yang sama
    (`ICT/COF/123` di kolom COF No sama dengan `ICTCOF123` di nama file; tanda baca dan huruf besar atau kecil
    tidak dibedakan). Nomor sebuah file adalah bagian namanya **sebelum ` - `** (spasi, tanda hubung, spasi,
    sesuai pengaturan `name_split` folder itu): `ICTCOF123-Nama.pdf` atau `ICTCOF123 – Nama.pdf` tidak punya
    nomor selain seluruh namanya, jadi baris dan filenya sama-sama dilaporkan sebagai tidak ketemu. Isi kolom COF No
    yang tidak mengandung huruf atau angka (`-`, `/`) dianggap kosong, lalu nomor diambil dari link. Yang perlu
    diperbaiki di Excel adalah link-nya, bukan filenya.
  - **Subfolder**: link menunjuk ke subfolder di bawah folder COF Scan, biasanya karena nama pemohon yang diketik
    mengandung tanda "/" (tanda itu tidak boleh ada di nama file, jadi link-nya tidak akan menemukan filenya, yang
    biasanya ada di folder utama dengan nama lain).
  - **Belum ada**: tidak ada file dengan nama maupun nomor itu.

  Status **Ada**, **Nama beda**, dan **Subfolder** (bila filenya ada) punya jalan ke file di halaman COF Scan, yang
  lalu hanya menampilkan file bernomor itu (**Tampilkan semua file** untuk kembali).
- **COF Scan**: dua kolom baru, **Masterlist** dan **Catatan**. Masterlist: nomor baris Excel yang menyebut file itu
  (**Baris N**, dan **+M** bila M baris lain menyebutnya juga), dengan jalan ke barisnya di ICT Masterlist, yang lalu
  hanya menampilkan baris itu (**Tampilkan semua baris** untuk kembali); **Nama beda · baris N** bila baris itu
  hanya punya nomornya, link-nya kosong, atau file ini salinan ganda dari file yang sudah disebut baris itu;
  **Tidak ada baris** bila tidak ada baris sama sekali. Catatan: isi kolom Note dari baris pertama yang punya
  catatan, dipotong pada 500 karakter (dengan "…"), empat baris kelihatan dan selebihnya ada di tooltip. Kolom
  Masterlist bisa diurutkan (file tanpa baris di atas); Catatan hanya dari Excel dan tidak bisa diubah di sini.
  Kedua kolom itu tidak ikut pencarian halaman, yang mencari nama file.
- Jumlah file dan jumlah baris tidak harus sama: dua file dengan nomor yang sama tampil dengan baris yang sama
  (yang kedua "Nama beda": itu salinan ganda, hapus salah satunya, bukan diganti namanya), dan baris Subfolder
  punya file di folder utama dengan nama lain.
- Dibuka dari halaman lain: ICT Masterlist menerima `?sheet=COF&line=2,9` (hanya baris Excel itu, sampai 20 nomor)
  dan COF Scan menerima `?code=ICTCOF123` (hanya file bernomor itu); keduanya juga menerima `?search=...` seperti
  pencarian biasa. `sheet` boleh nama sheet (huruf besar atau kecil tidak dibedakan; sheet yang namanya angka
  dicari sebagai nama lebih dulu) atau nomor urutnya, **dihitung dari 0** (`sheet=1` tanpa sheet bernama "1" adalah
  sheet kedua). Tab di halaman itu sendiri meminta sheet menurut urutannya (`tab=1`), jadi sheet yang namanya "1"
  atau "2" tidak membingungkan tab. `code` bisa sampai 400 karakter: file yang namanya tanpa ` - ` bernomor seluruh
  namanya. Tombol **Reset** menghapus pilihan kartu, baris, dan nomor itu juga.
- Tiap halaman menampilkan tambahan dari sumber lain **hanya untuk yang boleh membuka halaman sumber itu**: menunya
  ada untuk salah satu rolenya **dan** Read-nya dicentang (Super Admin selalu). Pembaca ICT Masterlist tanpa menu
  COF Scan, atau tanpa Read-nya, tidak mendapat kolom File scan dan folder itu tidak dibaca sama sekali untuknya;
  begitu pula sebaliknya. Bila alamat salah satu sumber di `.env` kosong, atau entri `scans` tidak lengkap (tanpa
  `folder` atau `link`), tidak ada yang tampil dan tidak ada yang dikatakan.
- Halaman tidak bergantung pada sumber lainnya: bila folder atau workbook yang satunya tidak bisa dibaca (Microsoft
  tidak terjangkau, sheet atau kolom link-nya tidak ada, isi folder terlalu banyak atau sheet terlalu panjang
  untuk dibaca seluruhnya, waktu permintaan habis), halaman tampil seperti biasa dan menyebut kenapa kolom
  tambahannya kosong. Di COF Scan kedua kolomnya tetap ada (berisi tanda "-"); di ICT Masterlist kolom File scan
  dan kartunya tidak tampil. Judul kolom nomor (`COF No`) atau catatan (`Note`) yang berubah di Excel tidak
  disebut: nomor lalu diambil dari link, dan catatan kosong.
- Waktu: menunggu Microsoft untuk dua sumber sekaligus dibatasi 27 detik per permintaan (dihitung sejak
  permintaan mulai; waktu sebelum controller berjalan ikut dihitung sampai 10 detik). Sumber pertama dibaca dengan
  batas waktunya sendiri (`time_limit` untuk folder, `preview_time_limit` untuk workbook); sumber kedua dengan sisa
  waktunya, tidak lebih dari batas waktunya sendiri, dan workbook yang menjadi sumber kedua disisakan 5 detik untuk
  dibaca setelah diunduh. Sumber kedua tidak ditanya bila sisa waktunya kurang dari 3 detik, atau bila sumber
  pertama hanya bisa diberikan dari data tersimpan karena Microsoft tidak terjangkau; data yang masih segar di
  cache dipakai tanpa syarat itu.
  Sumber kedua yang gagal dibaca karena Microsoft tidak terjangkau atau terlalu lambat tidak ditanya lagi selama
  semenit, **oleh kedua halaman**: itu tanda yang sama dengan yang dipakai halaman sumber itu sendiri. Jawaban
  yang pasti (izin dicabut, tidak ada) tidak ditahan: ditanya lagi di permintaan berikutnya. Pengecualiannya
  waktu yang dipotong sumber pertama sampai kurang dari separuh yang paling banyak bisa didapat sumber kedua
  (dengan pengaturan bawaan: 11 detik untuk workbook, 10 untuk folder): kehabisan waktu seperti itu bukan tanda Microsoft bermasalah,
  jadi sumber itu tidak ditahan dan halamannya sendiri tidak ikut terhenti. Batas 27 detik itu tidak ikut naik
  bila `time_limit` atau `preview_time_limit` dinaikkan (angkanya ada di `ScanLinks`). Workbook yang menjadi
  sumber pertama tetap bisa memakan `preview_time_limit` ditambah 5 detik membaca (dan waktu sebelum controller),
  jadi di server yang `max_execution_time`-nya 30 detik dan menghitung waktu sebenarnya (seperti Windows),
  turunkan `preview_time_limit` atau naikkan `max_execution_time`.
- Yang dicocokkan diatur di `scans` pada entri workbook di `modules/SharePointDocs/Config/sharepoint-docs.php`:
  sheet **COF** dihubungkan ke folder `cof-scan`, dengan judul kolom link (`Column4`), kolom nomor dokumen
  (`COF No`) dan kolom catatan (`Note`) seperti tertulis di workbook (huruf besar atau kecil dan spasi di tepi
  judul tidak dibedakan). Bila judul kolom link diubah di Excel, halaman bilang kolomnya tidak ditemukan dan kolom
  tambahan tidak tampil sampai pengaturannya disesuaikan; sebuah tes menjaga isi pengaturan itu.

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
  Resources/views/          radio/ (termasuk checkout-form = form cetak, checkout-signature = kanvas
                            tanda tangan, print-modal = pop-up-nya), master/, history/, import/, partials/
  Routes/web/radio-management.php
database/migrations/2026_10_03_100000_create_radio_management_tables.php
database/seeders/RadioMasterSeeder.php, RadioMenuSeeder.php, ESignMenuSeeder.php
modules/RadioManagement/ (E-Sign Master)
  Models/ItSignature.php, ItSignatureUse.php    tanda tangan per akun (gambar terenkripsi) dan catatan pemakaiannya
  Services/SignatureImage.php                   memeriksa gambar kiriman dan menggambarnya ulang sebagai PNG
  Http/Controllers/ESignController.php          halaman, daftar, pad, simpan/ubah/hapus, riwayat
  Resources/views/e-sign/                       index (daftar + dialog), pad (halaman pad di dalam frame dialog)
  Resources/views/radio/checkout-signature-css  gaya pad, dipakai form dan halaman pad
modules/Dashboard/          diganti menjadi dashboard radio
modules/SharePointDocs/     folder dan workbook SharePoint sebagai halaman (menu COF Scan, ICT Masterlist, Persetujuan Edit)
  Config/sharepoint-docs.php        folder dan workbook yang ditampilkan, batas ukuran dan waktu
  Services/                 GraphClient (token aplikasi, baca Graph, ambil isi file), SharePointFolder
                            (cari folder, daftar isi, simpan sementara, file untuk ditampilkan),
                            SharePointWorkbook (cari workbook, unduh bila berubah, simpan sementara),
                            WorkbookReader (isi .xlsx menjadi tabel teks), XlsxReader (sel-sel dari XML
                            di dalam .xlsx), GraphException (alasan gagal + pesannya), ScanRegister
                            (pencocokan baris COF dengan file folder), ScanLinks (dua sumber sekaligus,
                            waktu, izin), MenuAccess (boleh membuka halaman lain?), CellEdits (mengajukan,
                            menyetujui, menolak, menulis perubahan sel), XlsxPatcher (membaca dan menulis
                            satu sel di dalam .xlsx), EditException, SheetNotFound
  Models/EditRequest.php    satu pengajuan perubahan sel, dan nasibnya (tabel sharepoint_edit_requests)
  Http/                     FolderController (halaman, daftar, file), WorkbookController (halaman,
                            baris: cari/urut/halaman, ajukan perubahan), EditApprovalController
                            (daftar pengajuan, setujui, tolak), Middleware/RequireMenu
  Resources/views/          index (folder + pop-up penampil file), file-unavailable (pesan di pop-up),
                            workbook (tab per sheet + tabel + pop-up pengajuan), edit-approvals
database/seeders/DocumentMenuSeeder.php (dasar), CofScanMenuSeeder.php, IctMasterlistMenuSeeder.php,
                 EditApprovalsMenuSeeder.php, DocumentEditAccessSeeder.php (role Document Controller + hak ubah)
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

Test tidak pernah menghubungi Microsoft: `phpunit.xml` mengosongkan client secret serta alamat folder
dan workbook SharePoint, permintaan HTTP yang tidak dipalsukan menggagalkan test-nya, dan
`SharePointDocsTest` serta `SharePointWorkbookTest` memakai Microsoft tiruan dengan nama, alamat dan
workbook karangan. `WorkbookReaderTest` (di `tests/Unit`) membaca workbook yang ditulis tangan di
test itu sendiri: bentuk-bentuk sel yang dipakai Excel, format angka, serta file yang terlalu besar,
terlalu lama dibaca, rusak, atau memakai XML untuk membesarkan diri atau membaca file di server.
File yang rusak di tengah diuji dengan memutus aliran isinya (pembungkus `zip://` pengganti di
`tests/Concerns/CutsAPartShort.php`), dan salinan sementara workbook harus hilang juga bila skrip
berhenti karena kehabisan memori.

JavaScript di halaman tidak dijalankan oleh PHPUnit sendiri; beberapa test memakai Node.js untuk itu
(semuanya dilewati bila Node.js tidak terpasang):

- `BladeScriptSyntaxTest` memeriksa sintaks setiap `<script>` di semua view.
- `SharePointPageScriptTest` menjalankan skrip halaman COF Scan dan ICT Masterlist dengan pengganti
  jQuery (`tests/tools/page-sandbox.mjs`, dipakai juga oleh checker tombol Cetak di bawah) dan
  memeriksa perilakunya: teks dari SharePoint selalu di-escape, permintaan yang dikirim saat
  mencari/mengurutkan/berpindah halaman atau tab, fokus papan ketik, bentuk markup yang ditulis skrip
  (tag dikenal dan tertutup), dan pop-up penampil file
  (`sharepoint-page-behaviour.mjs`, `sharepoint-workbook-behaviour.mjs`).
- `RadioCheckoutFormTest` menjalankan empat checker untuk tombol Cetak, di `tests/tools/`:
  `radio-index-behaviour.mjs` (tombol di baris daftar membawa alamat form dan label radionya ke pop-up),
  `radio-print-behaviour.mjs` (frame baru untuk tiap form, pencetakan hanya ditawarkan untuk form, bukan
  halaman login atau galat, Cetak dan Ctrl+P ditolak selagi kanvas terbuka, tanya sebelum menutup dengan
  tanda tangan yang belum dicetak, tombol tutup dan fokusnya), `radio-form-behaviour.mjs` (isian form
  bisa diketik dan ditempel sebagai teks biasa, klik pada HT/RIG mencoret atau membatalkan coretan,
  peringatan dan ukuran cetak bila form lebih dari satu halaman, Ctrl+P) dan
  `radio-signature-behaviour.mjs` (ukuran kanvas, apa yang digambar pena, jari, dan mouse dan apa yang
  tidak, jari di dekat pena, goresan yang dibatalkan sistem atau yang akhirnya tidak datang, berapa
  panjang coretan yang dianggap tanda tangan, letak dan tebal tinta di gambar hasilnya, tanggal, hapus).
  Test yang sama juga memeriksa CSS cetak (tanpa margin halaman, padding 12 mm, tombol dan kanvas tidak
  ikut tercetak), bahwa halaman form hanya memuat logo dari luar dirinya, dan bahwa tidak ada skrip di
  dalamnya yang mengirim atau menyimpan tanda tangan (fetch, localStorage, dan sebagainya).
- `SharePointScansTest` (kedua sumber dimainkan Microsoft tiruan) dan `ScanRegisterTest` (pencocokan murni)
  memeriksa pencocokan COF dengan folder: status tiap baris dan file, kartu, filter, loncat ke baris atau nomor,
  urutan, pencarian, izin menu (menu dan Read, masing-masing), waktu yang tersisa di kedua arah, penyegaran,
  dan halaman yang tetap tampil bila sumber lain gagal. Skrip kedua halamannya
  diperiksa checker `sharepoint-*-behaviour.mjs` seperti di atas.
- `SharePointEditTest` memeriksa pengajuan dan persetujuan dengan Microsoft tiruan yang menyimpan workbook
  yang ditulis ke sana: siapa boleh mengajukan dan menyetujui (menu), tidak ada yang menyetujui pengajuannya
  sendiri, apa yang diketik menjadi isi sel apa, sel tertulis dan hanya sel itu (versi file dikirim sebagai
  `If-Match`), konflik (sel berubah, baris bergeser, rumus, sheet hilang, file disimpan orang lain saat
  menulis), penulisan yang sebenarnya berhasil walau jawabannya hilang, file terkunci / tanpa izin tulis /
  tidak terjangkau, daftar dan hitungannya, menu dan role, serta migrasinya. `XlsxPatcherTest` (di `tests/Unit`)
  memeriksa penulis selnya: teks tetap teks, angka dan gayanya, sel dan baris yang belum ada, dan bahwa bagian file
  lain sama persis. `sharepoint-approvals-behaviour.mjs` menjalankan skrip halaman Persetujuan Edit, dan
  `sharepoint-workbook-behaviour.mjs` juga memeriksa ikon pensil dan pop-up pengajuan.
- `ESignTest` memeriksa E-Sign Master: siapa yang boleh mengelola (menu; tertutup tanpa baris menu), gambar yang
  diterima dan ditolak (bukan PNG, kosong, terlalu besar, isi yang menumpang di belakang gambar), penyimpanan
  terenkripsi, ganti/nonaktif/hapus, bahwa form hanya membawa tanda tangan akun yang membukanya, catatan pemakaian,
  bahwa form yang bertanda tangan tetap tidak mengambil apa pun dari luar dan tidak mengirim apa pun, halaman pad,
  dan keadaan sebelum migrasi. `esign-index-behaviour.mjs` menjalankan skrip halaman master.
- `PageSandboxTest` memeriksa pengganti jQuery itu sendiri (`page-sandbox-selftest.mjs`: cara jQuery menulis
  permintaan dan gagal pada setengah karakter, apa yang di-`trigger`, pemeriksa bentuk markup), karena checker
  hanya sebaik pengganti yang dipakainya. Checker memerlukan Node.js 18 atau lebih baru.

Yang **tidak** bisa dibuktikan test di atas, dan apa yang sudah dan belum dicek sungguhan:

- Sudah dicek di Microsoft Edge (headless, lewat DevTools) pada server percobaan dengan data karangan:
  pena simulasi dengan tekanan, sentuhan jari, mouse, tombol samping pena, kanvas pada layar 1366 x 768 di
  125%, pop-up beserta konfirmasi tutupnya, dan hasil **PDF** (`Page.printToPDF`): satu halaman A4,
  logo dan tanda tangan sebagai gambar, tanpa header/footer browser (dengan kontrol: bila halaman diberi
  margin sendiri, header/footer memang tercetak), lembar 25 mm dari kiri/kanan dan 12 mm dari atas, dan
  form panjang yang diperkecil tetap satu halaman.
- **Belum** dicek: perangkat Wacom sungguhan (tekanan, tombol, dan penolakan telapak tangan lewat driver
  dan Windows Ink), Firefox dan Safari, dialog cetak sungguhan dan printer. Tanpa header/footer itu
  dibuktikan di Chromium/Edge dengan pengaturan Margins = Default di dialog cetak; dialog yang diubah
  ke "None" atau "Custom" mengikuti pengaturannya sendiri.
- Bila tekanan pena tidak terasa (garis selalu setebal mouse), nyalakan **Windows Ink** di driver Wacom
  (biasanya di Wacom Tablet Properties, tab Mapping, "Use Windows Ink"): tanpa itu browser hanya melihat mouse.
