# Changelog

Semua perubahan penting pada MTI Radio Management, yang terbaru di atas. Formatnya mengikuti
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), nomor versinya [Semantic Versioning](https://semver.org/):
kapan MAJOR, MINOR atau PATCH naik ada di README bagian [Versi](README.md#versi).

Setiap versi ditutup baris **Deploy**: yang perlu dilakukan di server setelah memperbarui kodenya. Dengan Docker:
`git pull && docker compose up -d --build`, lalu `docker compose exec app php artisan migrate` bila versi itu membawa
migrasi. Migrasi cukup dijalankan sekali per database, walau database itu dipakai beberapa instalasi.

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
