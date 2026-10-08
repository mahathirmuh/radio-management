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
| Master Data Radio | Tipe Radio, Perusahaan, Department, Template Channel |
| Import Excel | Impor register lama (sheet Form + to Konawe) |
| COF Scan | Isi folder SharePoint berisi scan IT Equipment Checkout Form (lihat [Dokumen SharePoint](#dokumen-sharepoint)) |
| ICT Masterlist | Isi workbook Excel "ICT Masterlist" di SharePoint sebagai tabel, satu tab per sheet (lihat [Dokumen SharePoint](#dokumen-sharepoint)) |

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

Semua role bawaan aplikasi ini bertipe ALL, jadi aturan ini baru berlaku bila Anda membuat role terbatas.

### Menu Approval Management

Modul approval bawaan base app tidak berfungsi di aplikasi ini, jadi cabang menu **Approval Management**
tidak ditampilkan: menunya tetap ada, hanya hak aksesnya yang dicabut (instalasi baru oleh `DatabaseSeeder`,
database yang sudah dipakai oleh migrasi `2026_10_03_120000_hide_approval_menus`). Untuk menampilkannya lagi,
buka **Role & Permission**, edit role-nya, lalu centang halaman di bawah **Approval Management**.

Di form role, grup dan halamannya saling mengikuti: mencentang sebuah halaman ikut mencentang grupnya
(halaman hanya tampil di sidebar bila grupnya juga diberikan), dan melepas centang grup melepas semua
halaman di bawahnya.

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
  menunggu Microsoft paling lama 20 detik (`time_limit`). Jawaban yang pasti dari Microsoft (izin
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
satu tab per sheet, bisa dicari, diurutkan dan dibuka halaman per halaman. Halaman ini **hanya
menampilkan**; mengubah data tetap dilakukan di Excel (tombol **Buka di Excel**).

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
- **Cari** mencari di semua kolom dan semua sheet sekaligus: setiap tab menunjukkan jumlah baris yang
  cocok di sheet-nya. Bila beberapa kata diketik, semuanya harus ada di baris itu.
- **Klik judul kolom** mengurutkan menurut kolom itu: angka dan tanggal menurut nilainya, teks menurut
  abjad dengan angka di dalamnya dibaca sebagai angka (COF2 sebelum COF10). Sel kosong selalu di bawah.
- Sama seperti COF Scan, isi workbook dibaca aplikasi dengan identitasnya sendiri: **siapa pun yang
  diberi menu ini (Read) melihat seluruh isi sheet yang ditampilkan, walau tidak punya akses ke
  workbook itu di SharePoint**. Menu ini hanya diberikan ke **Super Admin** dan **Admin Radio**.
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
database/seeders/RadioMasterSeeder.php, RadioMenuSeeder.php
modules/Dashboard/          diganti menjadi dashboard radio
modules/SharePointDocs/     folder dan workbook SharePoint sebagai halaman (menu COF Scan, ICT Masterlist)
  Config/sharepoint-docs.php        folder dan workbook yang ditampilkan, batas ukuran dan waktu
  Services/                 GraphClient (token aplikasi, baca Graph, ambil isi file), SharePointFolder
                            (cari folder, daftar isi, simpan sementara, file untuk ditampilkan),
                            SharePointWorkbook (cari workbook, unduh bila berubah, simpan sementara),
                            WorkbookReader (isi .xlsx menjadi tabel teks), XlsxReader (sel-sel dari XML
                            di dalam .xlsx), GraphException (alasan gagal + pesannya)
  Http/                     FolderController (halaman, daftar, file), WorkbookController (halaman,
                            baris: cari/urut/halaman), Middleware/RequireMenu
  Resources/views/          index (folder + pop-up penampil file), file-unavailable (pesan di pop-up),
                            workbook (tab per sheet + tabel)
database/seeders/DocumentMenuSeeder.php (dasar), CofScanMenuSeeder.php, IctMasterlistMenuSeeder.php
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
  mencari/mengurutkan/berpindah halaman atau tab, dan pop-up penampil file
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
- `PageSandboxTest` memeriksa pengganti jQuery itu sendiri (`page-sandbox-selftest.mjs`), karena checker
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
