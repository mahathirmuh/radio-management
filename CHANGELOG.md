# Changelog

Semua perubahan penting pada MTI Radio Management, yang terbaru di atas. Formatnya mengikuti
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), nomor versinya [Semantic Versioning](https://semver.org/):
kapan MAJOR, MINOR atau PATCH naik ada di README bagian [Versi](README.md#versi).

Setiap versi ditutup baris **Deploy**: yang perlu dilakukan di server setelah memperbarui kodenya. Dengan Docker:
`git pull && docker compose up -d --build`, lalu `docker compose exec app php artisan migrate` bila versi itu membawa
migrasi. Migrasi cukup dijalankan sekali per database, walau database itu dipakai beberapa instalasi.

## [Unreleased]

Tampilan baru (design refresh), dikerjakan per fase di branch `design-refresh`; rencana dan keputusannya di
[docs/PLAN.md](docs/PLAN.md). Bagian ini menjadi satu rilis saat branch itu digabung ke `main`.

### Ditambahkan

- Fase 6, kemampuan baru:
  - pemilih Radio ID di form radio: begitu ID diketik, form bilang apakah ID itu bisa dipakai. Bila tidak, form
    menyebut alasannya (MDC Code mengandung F, dipakai radio lain, atau dicadangkan radio tanpa ID) beserta pemegangnya,
    dan menawarkan ID pengganti ("Pakai 768"). Panelnya menampilkan ID di sekitarnya (bebas / dipakai / ada F) dan
    ID bebas berikutnya. Endpoint baru: `GET radio-management/radio/id-check`.
  - **Serah terima** di detail radio (dengan izin Update):
    - dialog 900px: penerima (dicari di direktori karyawan atau diketik), No. BA (wajib), No. PO, aksesori, kondisi,
      lokasi sesudahnya, department/company penerima, dan pad tanda tangan penerima. Tombol konfirmasi baru aktif
      setelah ada tanda tangan;
    - radio menjadi milik penerima, dengan satu baris riwayat "Serah Terima" (BA sebagai nomor dokumen) dan audit
      `radio.handed_over`;
    - tanda tangan hanya untuk cetak: form checkout terbuka dengan tanda tangan itu di kotak Responsible User, dan tidak
      disimpan di mana pun (juga tidak di audit);
    - endpoint baru: `PUT radio-management/radio/{radio}/handover` dan `GET radio-management/radio/employees`.

### Diubah

- Fase 1, kerangka halaman:
  - lapisan gaya baru `public/assets/css/radio-ui.css` (warna desain, bayangan, gerak, resep komponen), dimuat setelah
    `custom.css` di semua halaman dan di halaman login;
  - sidebar 248px dengan label seksi, grup yang terlipat dengan animasi, dan angka pengajuan yang menunggu di
    **Persetujuan Edit**. Menu tetap tertandai di halaman turunannya (detail radio menandai **Register Radio**);
  - rail ikon 80px dengan logo kecil, garis antar-seksi, titik penanda jumlah, dan nama menu di samping ikon. Pilihan
    lebarnya diingat dan dipasang sebelum halaman tergambar, jadi tidak berkedip;
  - top bar 56px: tombol menu, judul halaman, nama perusahaan, lonceng dengan angka merah, serta nama dan role user
    yang membuka menu **Notifikasi** dan **Keluar**;
  - warna utama biru `#2563eb`, latar halaman `#f8fafc`, footer selalu di dasar jendela;
  - halaman login memakai font Inter (sebelumnya menautkan file font yang tidak ada); tautan Google Fonts yang tidak
    terpakai dibuang.
- Fase 2, Register Radio, Detail dan Riwayat Radio:
  - pola halaman baru: judul dan subjudul di atas satu kartu. Kartu berisi toolbar, filter, chip **Diterapkan** untuk
    setiap filter yang aktif, bulk bar, dan tabel bergaris dengan footer **Menampilkan… / Baris per halaman** dan nomor
    halaman. Footer ini dipakai semua halaman daftar;
  - Register:
    - pencarian langsung saat mengetik;
    - lingkup Aktif / Arsip / Semua berupa segmented, dan scope Arsip punya kolom sendiri (user terakhir, tanggal arsip,
      alasan);
    - klik baris membuka detail radio;
    - tombol aksi baris tetap terlihat walau tabel digulir ke samping;
  - tombol yang tidak boleh dipakai sebuah role tidak lagi ditampilkan: Viewer tidak melihat Tambah, Edit, Arsipkan dan
    Hapus. Server tetap memeriksa;
  - dialog baru:
    - arsip dengan pilihan alasan berupa kartu;
    - pulihkan, verifikasi massal, dan arsip massal;
    - hapus, yang kini meminta Radio ID diketik;
    - form tambah/edit radio 760px, dengan Posisi berupa segmented dan ID bebas berupa chip;
  - setelah menyimpan, mengarsipkan atau memulihkan radio, hasilnya muncul sebagai toast di bawah layar; hasil aksi
    massal tetap berupa popup;
  - detail radio: breadcrumb, kartu kepala dengan tombol **Cetak form**, kartu Hasil cek, tiga kartu info, timeline
    riwayat dengan perubahan lama → baru, dan Data asli Excel;
  - Riwayat Radio: baris filter dengan pilihan jenis (centang banyak, dengan jumlahnya) dan kalender untuk tanggal.
    Daftarnya langsung mengikuti filter.

- Fase 3, Dashboard, Master Data Radio, E-Sign, Import Excel, COF Scan, ICT Masterlist, Persetujuan Edit:
  - Dashboard:
    - enam KPI baru (ubin ikon berwarna, angka besar), tetap berupa tautan ke daftar yang tersaring;
    - kartu Kondisi dengan bar bertumpuk dan legenda yang bisa diklik, lalu Radio ID bebas berikutnya berupa chip;
    - daftar **Perlu tindakan** dengan tautan **Lihat**, bar per perusahaan dan per tipe, dan tabel aktivitas terbaru
      dengan badge aksi;
  - Master Data Radio: tab garis per jenis master dengan tombol Tambah di kanan. Form tambah/ubah ada di kartu di samping
    tabel, bukan modal lagi. Pencarian langsung saat mengetik, dan hasil simpan/hapus berupa toast;
  - E-Sign: tampilan Grid (kartu dengan inisial, pratinjau tanda tangan, tanggal diperbarui) atau List; pilihannya
    diingat;
  - Import Excel:
    - dua kartu, unggah dan aturan;
    - kontrol file dan kotak centang baru, merah untuk "hapus data lama";
    - konfirmasi oranye bila data lama akan dihapus;
    - hasil impor berupa ubin angka;
  - COF Scan dan ICT Masterlist: judul dan kartu, toolbar dengan kolom cari berikon, dan tabel bergaris. ICT Masterlist
    juga punya:
    - banner info;
    - kartu status File scan dengan angka berwarna, yang dipilih bercincin biru;
    - tab sheet bergaris dengan jumlah barisnya;
  - Persetujuan Edit:
    - status berupa tab bergaris dengan jumlahnya. Diproses, Konflik dan Gagal hanya muncul bila ada pengajuannya;
    - nilai lama merah dicoret, nilai baru hijau;
    - dialog Setujui/Tolak menampilkan nilai sekarang dan nilai baru berdampingan;
    - hasil yang berhasil muncul sebagai toast;
    - warna status mengikuti desain: Menunggu kuning, Ditolak merah;
  - badge dan kotak info/peringatan Tabler lama di halaman lain ikut memakai warna desain.

- Fase 4, Audit Log, API Tokens, Users, Role & Permission, Notifikasi, Login:
  - Audit Log:
    - pilihan Table / Log activity berupa segmented;
    - aksi berupa badge berwarna (misalnya Created hijau, Updated oranye, Deleted merah), kuncinya tetap di tooltip;
    - klik baris (atau entri Log activity) membuka kartu **Event details** di samping tabel: waktu sampai detik,
      user, kunci aksi, IP, dan perubahan baris per baris. Di bawah 1440px kartunya pindah ke bawah tabel;
    - Log activity: lingkaran ikon berwarna per aksi, kalimat "siapa melakukan apa", kotak perubahan, baris waktu;
  - API Tokens: satu kartu dengan seksi Tokens dan API clients. Token yang tidak berlaku lagi tampil pudar. Dialog
    baru untuk membuat token (token tampil sekali dengan contoh curl) dan client. Cabut dan hapus memakai dialog
    desain, hasilnya berupa toast;
  - Users:
    - filter Role (centang banyak) dan Status langsung di toolbar dan berlaku saat diubah; kartu Filter terpisah
      dihapus;
    - role tampil sebagai badge berwarna;
    - Last sign-in ditulis "2 hours ago", tanggal lengkapnya di tooltip;
    - tombol baris mengikuti izin role (yang tidak boleh tidak ditampilkan);
    - form user 760px dengan label di kolom 190px dan metode sign-in di urutan pertama;
    - konfirmasi "Is the data correct?" berupa dialog di tengah;
    - simpan, aktif/nonaktif, password dan hapus dikabarkan dengan toast. Tidak ada lagi popup "Canceled!";
  - Role & Permission:
    - cari langsung saat mengetik;
    - badge jumlah user;
    - View di semua role, Edit dan Delete tidak ada di Super Admin;
    - dialog 800px dengan tabel izin yang header-nya tetap saat digulir;
  - Notifikasi:
    - panel lonceng 400px: lingkaran ikon berwarna per jenis, titik biru untuk yang belum dibaca;
    - halaman maksimal 960px, dikelompokkan Hari ini / Kemarin / Sebelumnya;
    - **Tandai semua dibaca** di halaman berjalan tanpa memuat ulang;
  - Login:
    - kartu 1180px dengan empat foto yang berganti perlahan (cross-fade 24 detik), menggantikan carousel;
    - tombol Microsoft 48px dan tombol Login dengan status sibuk;
    - panduan login 440px.

- Fase 5, gerak:
  - halaman muncul dengan fade; dialog dan popup muncul (pop-in) dan hilang lebih cepat, latarnya memudar;
  - menu (user, lonceng, pilihan, baris per halaman) turun dari sisi tempatnya dibuka;
  - toast naik dari bawah dan turun lagi;
  - baris tabel memudar masuk setiap kali filter, halaman atau tampilan berganti;
  - bulk bar, chip filter dan panel filter turun saat muncul; kotak yang dicentang "pop";
  - Dashboard: angka KPI menghitung naik, bar kondisi terbuka dari kiri, bar per perusahaan/tipe tumbuh;
  - Log activity: entri naik satu per satu;
  - garis bawah tab Persetujuan Edit dan sheet ICT Masterlist bergeser ke tab yang dipilih;
  - kartu tanda tangan yang baru disimpan berdenyut;
  - semua gerak mati bila sistem operasi meminta "kurangi gerak" (prefers-reduced-motion): angka langsung tampil,
    login hanya menampilkan foto pertama.

### Diperbaiki

- Role & Permission: di mode View, kotak izin bisa dicentang setelah role selesai dimuat. Sekarang tetap terkunci.
- Halaman login tidak lagi memuat gambar yang tidak ada: thumbnail carousel lama, dan ikon `manifest.json` peninggalan
  template. Tautan manifest dihapus dari halaman login; file-nya masih ada di `public/`.

**Deploy:** fase 1 sampai 5 tanpa migrasi dan tanpa setting baru.

## [1.9.0] - 2026-10-19

### Ditambahkan

- Sesi login punya dua batas waktu yang dijaga server, mengikuti OWASP dan NIST SP 800-63B-4: **30 menit tanpa
  aktivitas** dan **8 jam sejak login** walau terus aktif (`SESSION_IDLE_TIMEOUT`, `SESSION_ABSOLUTE_TIMEOUT`). Setiap
  halaman memberi peringatan dengan hitung mundur 2 menit sebelum batas tidak aktif (**Tetap masuk** / **Keluar**) dan
  5 menit sebelum batas 8 jam (**Login ulang sekarang** / **Nanti**).
- Sesi yang berakhir membawa halaman ke login, yang menyebut alasannya; tercatat di Audit Log sebagai
  `auth.session_expired`. Form yang dikirim dari halaman yang sesinya sudah habis juga berakhir di halaman login,
  bukan di "Page Expired".

### Keamanan

- Login password tidak lagi memberi cookie "remember me" walau diminta lewat form, dan cookie seperti itu dari
  sebelumnya tidak menghidupkan sesi lagi.
- Tombol **Microsoft** selalu menampilkan pilihan akun: browser yang masih login Microsoft sebagai orang lain tidak
  langsung masuk sebagai orang itu.

**Deploy:** tanpa migrasi. Setting baru opsional: `SESSION_IDLE_TIMEOUT` dan `SESSION_ABSOLUTE_TIMEOUT` (menit, default
30 dan 480); `SESSION_DRIVER` tetap `file`. Sesi yang sedang terbuka mulai dihitung pada permintaan berikutnya.

## [1.8.4] - 2026-10-19

### Diubah

- Halaman login: gambar di samping form dan latar halamannya kembali seperti sebelum 1.8.3 (foto-foto Merdeka
  Copper Gold dan slide-nya). Gambar 1.8.3 dihapus dari aplikasi.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.8.3] - 2026-10-19

### Diubah

- Halaman login: gambar di samping form kini lukisan pabrik pengolahan Merdeka Battery Materials (dari foto udara milik
  perusahaan sendiri), dan latar halamannya lukisan lanskap lembah industri (dibuat AI, bukan fasilitas sebenarnya).
  Keduanya menggantikan gambar Merdeka Copper Gold; slide empat gambar menjadi satu gambar, dan halaman login kini
  memuat sekitar 0,8 MB gambar, bukan sekitar 3,5 MB. Di layar sempit gambarnya tampil sebagai pita di atas form.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.8.2] - 2026-10-19

### Diubah

- Halaman login: tulisan di bawah form kini **© Merdeka Group**, bukan "© PT Merdeka Copper Gold Tbk", sama dengan
  footer halaman lain.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.8.1] - 2026-10-19

### Dihapus

- Popup **Welcome!** yang muncul saat membuka halaman login (dan halaman lain). Itu sisa template yang dipasang di
  setiap request; popup yang memang menyampaikan sesuatu (login gagal, logout) tetap muncul.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.8.0] - 2026-10-19

### Ditambahkan

- Users: kolom **No**, dan **pilih banyak** (kolom centang per baris dan per halaman, pilihan tetap saat pindah
  halaman) dengan bar **Activate**, **Deactivate** dan **Delete** (konfirmasi dengan mengetik jumlahnya); tombolnya
  mengikuti izin role. Setiap akun diproses seperti aksi satuannya; akun sendiri dan akun yang tidak boleh dikelola
  tidak bisa dicentang. Paling banyak 100 akun sekaligus.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.7.0] - 2026-10-19

### Ditambahkan

- Register Radio: **pilih banyak**. Kolom centang per radio dan per halaman, pilihan yang tetap saat pindah halaman
  atau ganti filter, dan **Pilih semua N radio hasil filter**. Bar di atas tabel (tombolnya mengikuti izin role):
  - **Verifikasi hari ini**: riwayat "Verifikasi" untuk setiap radio, tanggal verifikasinya ikut maju; bisa untuk
    semua hasil filter (stock opname), paling banyak 1.000 sekaligus;
  - **Arsipkan**: satu alasan, tanggal dan catatan untuk radio yang dicentang;
  - **Hapus**: untuk salah input, dengan mengetik jumlahnya sebagai konfirmasi;
  - **Export terpilih**: Excel berisi radio yang dicentang saja.

  Arsipkan dan Hapus hanya untuk radio yang dicentang satu per satu (paling banyak 100). Radio yang tidak bisa
  diproses disebutkan beserta alasannya.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.6.1] - 2026-10-18

### Diubah

- Docker: folder `certs/` di samping `docker-compose.yml` selalu dipasang ke container (hanya baca), tempat sertifikat
  root CA domain controller (`certs/ca.pem`) untuk login LDAP yang memeriksa sertifikat. Sebelumnya baris itu harus
  dibuka sendiri di `docker-compose.yml`, yang bentrok dengan `git pull` berikutnya.
- README dan `.env.example`: cara menyalakan `LDAP_TLS_VERIFY`: sertifikat root CA, dan `LDAP_URL` dengan nama DNS
  server AD (bukan IP), karena pemeriksaan mencocokkan nama itu dengan sertifikatnya.

**Deploy:** tanpa migrasi. Untuk menyalakan pemeriksaan sertifikat LDAP di production: salin sertifikat root CA ke
`certs/ca.pem` di server, lalu di `.env` server isi `LDAP_URL` dengan nama DNS server AD, `LDAP_TLS_VERIFY=true` dan
`LDAP_CA_CERT=/var/www/html/certs/ca.pem`; `docker compose up -d --build`.

## [1.6.0] - 2026-10-18

### Ditambahkan

- **Notifikasi** di lonceng header: angka yang belum dibaca (diperbarui tiap menit), 8 notifikasi terbaru, tandai
  dibaca, dan halaman **Lihat semua notifikasi**. Isinya:
  - pengajuan perubahan sel ICT Masterlist, untuk yang boleh menyetujuinya; hasilnya (diterapkan, ditolak, gagal,
    konflik) untuk pengajunya;
  - token API yang berakhir dalam 7 hari, untuk pemiliknya (token API client: untuk Super Admin);
  - ringkasan mingguan "Perlu tindakan" register, untuk yang boleh mengubah Register Radio.
- Halaman Persetujuan Edit bisa dibuka langsung tersaring lewat alamatnya (`?status=pending&search=...`): tautan
  notifikasi memakainya.

**Deploy:** migrasi `2026_10_18_100000_create_user_notifications_table`. Sebelum dijalankan lonceng tetap kosong dan
tidak ada yang gagal. Tidak ada setting baru.

## [1.5.4] - 2026-10-18

### Dihapus

- Dashboard: tombol **Register Radio** di samping judul. Register Radio sudah ada di sidebar, dan angka-angka di
  dashboard tetap membuka daftarnya.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.5.3] - 2026-10-17

### Diperbaiki

- Persetujuan Edit: workbook yang sudah diubah sesekali gagal disimpan sementara di server Windows (sekitar 1 dari 200
  kali: file barunya sedang dipegang proses lain, misalnya antivirus), dan persetujuannya berakhir "Gagal" tanpa alasan
  yang jelas. Penyimpanan itu sekarang diulang otomatis, dan workbook yang akan dikirim ke SharePoint dibaca ulang
  dulu untuk memastikan perubahannya ada. Bila tetap gagal, pesannya menyebut bahwa tidak ada yang ditulis ke
  SharePoint dan pengajuan bisa disetujui lagi.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.5.2] - 2026-10-17

### Diperbaiki

- Add User: nama yang memakai tanda kurung, seperti nama tampilan di Active Directory ("Budi Santoso [MTI]"), ditolak
  saat Submit ("The name may only have letters, ..."). Sejak 1.5.0 Find employee untuk LDAP mengisi nama dari Active
  Directory, sehingga hampir semua orang yang ditemukan di sana tidak bisa disimpan. Nama sekarang boleh memuat
  `( ) [ ] { }`; tanda lain seperti `;`, kutip dan `< >` tetap ditolak.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.5.1] - 2026-10-17

### Dihapus

- Login: kartu "Merdeka Copper Gold Group, @merdekacoppergold.com, access via Google SSO" di **Access Login Guide**.
  Aplikasi ini tidak punya login Google: login Google bawaan template (tombol yang tersembunyi selama tidak disetel,
  alamat `auth/google/*`) belum pernah disetel dan ikut dihapus. Masuk tetap lewat Microsoft atau akun Merdeka
  (password / Active Directory).

### Diubah

- Login: kartu Microsoft di Access Login Guide hanya menyebut `@merdekabattery.com` (tanpa `@scmnickel.com`).

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.5.0] - 2026-10-17

### Ditambahkan

- Nomor versi aplikasi: file `VERSION`, tampil di footer setiap halaman, di dokumentasi API (`/docs/api`) dan di
  `php artisan about`; changelog ini; tag git `vX.Y.Z` untuk setiap versi.
- Add User: dengan metode **LDAP**, Find employee mencari di **Active Directory** menurut nama, username atau e-mail.
  Memilih seseorang mengisi Name, Email dan Directory username persis seperti di direktori. Akun yang dinonaktifkan
  tidak ditawarkan.
- Directory username diperiksa ke Active Directory saat akun disimpan: username yang tidak ada, atau akun yang
  dinonaktifkan, ditolak. Selama Active Directory tidak terjangkau, akun LDAP tidak bisa disimpan.

### Diubah

- Add User: **Sign-in method** sekarang paling atas, karena metode itulah yang menentukan di mana Find employee
  mencari. Dengan Microsoft, Find employee tetap mencari di database HR.

**Deploy:** tanpa migrasi. Setting baru yang opsional: `LDAP_PEOPLE_SEARCH_FILTER` (filter pencarian orang di Active
Directory; sudah ada nilai bawaannya).

## [1.4.0] - 2026-10-17

### Ditambahkan

- API fase 3:
  - COF Scan: daftar file di folder scan beserta keterangannya dari sheet COF, dan unduhan satu file (PDF atau
    gambar);
  - ICT Masterlist: workbook, sheet dan baris (cari, urut, halaman; di sheet COF juga kolom "File scan" dan
    hitungannya);
  - pengajuan ubah sel, daftar pengajuan, setujui dan tolak: hanya dengan token pribadi, tidak pernah dengan token
    API client, dan tidak ada yang menyetujui pengajuannya sendiri;
  - E-Sign: daftar tanda tangan dan pemakaiannya (tanpa gambarnya), dan menonaktifkan tanda tangan.
- Batas: yang dibaca dari SharePoint 30 permintaan per menit per token, menyetujui pengajuan 10 per menit.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.3.0] - 2026-10-17

### Ditambahkan

- API fase 2:
  - export Excel register, dengan filter yang sama seperti daftarnya;
  - isi form Cetak sebagai data, tanpa tanda tangan;
  - Import Excel: laporan terakhir dan impor workbook (`fresh=1` mengganti seluruh register dan butuh izin Delete di
    Register Radio);
  - users, roles dan audit log, hanya baca.
- Import dan export lewat API: 10 permintaan per menit per token.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.2.0] - 2026-10-17

### Ditambahkan

- REST API `/api/v1` untuk aplikasi lain, dengan dokumentasinya di `/docs/api`.
- Halaman **Settings → API Tokens**: token untuk API client (akun sebuah aplikasi) atau token pribadi; baca saja atau
  baca dan tulis; berlaku 30 sampai 365 hari; ditampilkan sekali saja.
- API fase 1: me, referensi, dashboard, radio (daftar, detail, tambah, ubah, arsip, pulihkan, riwayat, hapus, ID
  bebas, cek ID dan SN), riwayat semua radio, dan empat tabel master data.
- Setiap endpoint mengikuti izin menu halamannya di Role & Permission; 60 permintaan per menit per token; perubahan
  lewat API tercatat di Audit Log beserta token yang membuatnya.

**Deploy:** migrasi `2026_10_17_120000_create_personal_access_tokens_table`,
`2026_10_17_130000_add_api_client_to_users_table`, `2026_10_17_140000_add_via_to_audit_logs_table` dan
`2026_10_17_150000_add_api_tokens_menu`. Setting baru yang opsional: `CORS_ALLOWED_ORIGINS` (situs yang boleh
memanggil API dari browser pengunjungnya; kosong berarti tidak ada) dan `SANCTUM_TOKEN_PREFIX`.

## [1.1.1] - 2026-10-17

### Diperbaiki

- Audit Log menyebut halaman awal sebuah role dengan nama halamannya, bukan nomor menunya.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.1.0] - 2026-10-17

### Ditambahkan

- Menu **Audit Log** (Settings, khusus Super Admin): siapa login (juga yang ditolak, beserta alasannya) dan logout,
  serta siapa mengubah apa (akun, role, radio, master data, E-Sign, import Excel, pengajuan ubah ICT Masterlist),
  dengan nilai sebelum dan sesudahnya. Tampil sebagai tabel atau timeline, dengan filter user dan aksi. Password,
  token dan gambar tanda tangan tidak pernah dicatat.

### Keamanan

- Log permintaan (`activity_logs`, `error_logs`) tidak lagi menyimpan gambar tanda tangan E-Sign.

**Deploy:** migrasi `2026_10_17_100000_create_audit_logs_table` dan `2026_10_17_110000_add_audit_log_menu`. Sebelum
keduanya dijalankan belum ada yang dicatat.

## [1.0.0] - 2026-10-17

Versi pertama yang berjalan di production. Isinya:

- **Radio**: Register Radio (satu baris untuk satu radio fisik menurut SN; Radio ID dan kode MDC, dan Radio ID yang
  kode MDC-nya mengandung F tidak bisa dipakai; posisi dan kondisi; arsip; tanda "Perlu dicek"; kolom Owner MTI /
  Non MTI), Riwayat Radio, Master Data Radio, Import dan Export Excel.
- **Cetak**: IT Equipment Checkout Form untuk satu radio, dengan tanda tangan di layar untuk RESPONSIBLE USER dan
  tanda tangan IT dari **E-Sign Master** untuk PROCESSED BY.
- **Dokumen** (SharePoint): COF Scan (isi folder scan), ICT Masterlist (workbook sebagai tabel), keduanya saling
  dicek, dan perubahan sel ICT Masterlist lewat pengajuan dan **Persetujuan Edit**.
- **User**: Users (status, cara login, login terakhir, nonaktifkan, atur password; Add User dengan Find employee dari
  database HR, Sign-in method LDAP / Microsoft dan Directory username) dan Role & Permission. Login dengan Microsoft
  atau dengan password Active Directory.
- **Docker**: `Dockerfile` dan `docker-compose.yml` (port 26102), siap di belakang reverse proxy HTTPS.

**Deploy:** instalasi pertama, lihat README bagian Instalasi dan Docker.

[1.9.0]: https://github.com/mahathirmuh/radio-management/compare/v1.8.4...v1.9.0
[1.8.4]: https://github.com/mahathirmuh/radio-management/compare/v1.8.3...v1.8.4
[1.8.3]: https://github.com/mahathirmuh/radio-management/compare/v1.8.2...v1.8.3
[1.8.2]: https://github.com/mahathirmuh/radio-management/compare/v1.8.1...v1.8.2
[1.8.1]: https://github.com/mahathirmuh/radio-management/compare/v1.8.0...v1.8.1
[1.8.0]: https://github.com/mahathirmuh/radio-management/compare/v1.7.0...v1.8.0
[1.7.0]: https://github.com/mahathirmuh/radio-management/compare/v1.6.1...v1.7.0
[1.6.1]: https://github.com/mahathirmuh/radio-management/compare/v1.6.0...v1.6.1
[1.6.0]: https://github.com/mahathirmuh/radio-management/compare/v1.5.4...v1.6.0
[1.5.4]: https://github.com/mahathirmuh/radio-management/compare/v1.5.3...v1.5.4
[1.5.3]: https://github.com/mahathirmuh/radio-management/compare/v1.5.2...v1.5.3
[1.5.2]: https://github.com/mahathirmuh/radio-management/compare/v1.5.1...v1.5.2
[1.5.1]: https://github.com/mahathirmuh/radio-management/compare/v1.5.0...v1.5.1
[1.5.0]: https://github.com/mahathirmuh/radio-management/compare/v1.4.0...v1.5.0
[1.4.0]: https://github.com/mahathirmuh/radio-management/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/mahathirmuh/radio-management/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/mahathirmuh/radio-management/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/mahathirmuh/radio-management/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/mahathirmuh/radio-management/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/mahathirmuh/radio-management/releases/tag/v1.0.0
