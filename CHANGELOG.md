# Changelog

Semua perubahan penting pada MTI Radio Management, yang terbaru di atas. Formatnya mengikuti
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), nomor versinya [Semantic Versioning](https://semver.org/):
kapan MAJOR, MINOR atau PATCH naik ada di README bagian [Versi](README.md#versi).

Setiap versi ditutup baris **Deploy**: yang perlu dilakukan di server setelah memperbarui kodenya. Dengan Docker:
`git pull && docker compose up -d --build`, lalu `docker compose exec app php artisan migrate` bila versi itu membawa
migrasi. Migrasi cukup dijalankan sekali per database, walau database itu dipakai beberapa instalasi.

## [1.20.1] - 2026-10-21

### Dihapus

- Sisa template Base-Apps-Merdeka yang tidak pernah dipakai aplikasi ini. Tampilan dan fungsi aplikasi tidak berubah.
  - Semua komponen Blade `x-global` kecuali `btn-detail` dan `summary-card` (18 file).
  - Library `tinymce`, `bootstrap` dan `list.js` di `public/assets/libs/` (sekitar 9 MB).
  - Model `mSafe` dan `orangeHR`, beserta koneksi database `orangeHr` dan `mSafe` di `config/database.php`. Setting
    `DB_*_ORANGEHR` dan `DB_*_MSAFE` tidak dibaca lagi; keduanya memang tidak pernah ada di `.env.example`.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.20.0] - 2026-10-21

### Diubah

- Dialog **Riwayat versi** (nomor versi di footer) kini mengikuti desain handoff: garis waktu, yang terbaru di atas.
  - Setiap seri (`1.20`, `1.19`, …) satu simpul berlabel nomor serinya, di rilis minornya (`.0`); rilis patch-nya
    bercabang di bawahnya dengan titik kecil.
  - Setiap versi berwarna menurut jenis perubahannya yang terkuat: Dihapus merah, Keamanan ungu, Ditambahkan hijau,
    Diperbaiki oranye, Diubah biru, Ditinggalkan abu-abu. Setiap bagian catatannya diawali label jenisnya dalam warna
    yang sama.
  - Legenda di atas: rilis minor, rilis patch, dan jenis perubahan yang ada.
  - Pita **Versi 1** di tempat sebuah versi mayor dimulai, dan label **Rilis mayor** di versi `N.0.0`-nya.
  - Versi yang sedang berjalan bertanda **Versi ini**, dan simpulnya berdenyut pelan (kecuali bila sistem meminta
    animasi dikurangi).

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.19.1] - 2026-10-21

### Diperbaiki

- Menu **Baris per halaman** di bawah tabel (10, 25, 50, 100): pilihannya tidak lagi melebar keluar dari kotaknya dan
  menutupi isi tabel. Tema memberi setiap pilihan menu lebar minimal 176 px, padahal menu ini hanya 84 px.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.19.0] - 2026-10-21

### Diubah

- `GET /api/v1/health` kini mengecek apakah website jalan normal sampai ke database-nya, juga tanpa token:
  - `web`: halaman login dibuka seperti oleh browser, lewat routing, sesi, bahasa, dan template, lalu dipastikan tampil
    dengan form-nya;
  - `database`: database menjawab, dan tabel aplikasi (`users`, `menu`, `radios`) bisa dibaca;
  - `storage`;
  - `session`: sesi bisa disimpan dan dibaca.

  Status keempatnya ikut ditampilkan di jawaban tanpa token, dan satu saja yang gagal berarti `down` (HTTP 503). Dengan
  token, tiap cek tampil lengkap seperti sebelumnya.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.18.0] - 2026-10-21

### Ditambahkan

- Health check:
  - **`GET /up`**: aplikasi menjawab, tanpa database, sesi, atau token. Untuk healthcheck container dan pemantau uptime.
  - **`GET /api/v1/health`** tanpa token: status aplikasi (database dan storage) dan versinya, HTTP 503 bila tidak
    jalan.
  - **`GET /api/v1/health`** dengan token API: detail per layanan, yaitu database, storage, cache, migrasi yang belum
    dijalankan (dengan namanya), Snipe-IT, Microsoft, Active Directory, dan database HR. Tiap cek berstatus `ok`,
    `warn`, `fail`, atau `skipped`, disertai waktu dan alasannya. Status keseluruhan `degraded` bila layanan pendukung
    bermasalah. Nama server dan pesan error dari server tidak pernah ditampilkan.

### Diubah

- Docker: healthcheck container kini memanggil `/up`, bukan lagi halaman login.

**Deploy:** tanpa migrasi, tanpa setting baru. `git pull && docker compose up -d --build` memakai healthcheck yang baru.

## [1.17.0] - 2026-10-21

### Ditambahkan

- Snipe-IT, Aset: gambar tiap aset di samping asset tag (gambar aset atau modelnya dari Snipe-IT; tanpa gambar, ikon
  kategorinya), dengan nama aset di bawah asset tag.
- Snipe-IT, Aset: tombol khusus **Lihat detail** (ikon mata) di ujung tiap baris untuk membuka detail aset.
- Detail aset: panel **Gambar** yang besar; klik gambarnya untuk membuka ukuran penuh.

### Diubah

- Snipe-IT, Aset: kolom Nama digabung ke kolom Asset tag (sebagai baris kedua), dan asset tag tidak lagi berupa link:
  detail dibuka lewat tombolnya.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.16.0] - 2026-10-21

### Ditambahkan

- Snipe-IT: **detail aset**. Asset tag di daftar Aset (juga aset di Log aktivitas dan Maintenance) membuka halaman
  detailnya di aplikasi ini, berisi:
  - data aset, pemegang dan lokasi;
  - pembelian dan garansi (harga beli, nilai buku, sisa garansi, umur);
  - custom field (misalnya Radio ID dan MDC Code), catatan;
  - riwayat lengkap di Snipe-IT (checkout, checkin, perubahan lama → baru);
  - maintenance, lisensi, aset yang di-checkout ke aset ini, dan tanggal audit.

  Tombol **Buka di Snipe-IT** tetap ada untuk komponen dan file, yang tidak diberikan API Snipe-IT 6.2.

### Diubah

- Snipe-IT, Log aktivitas: kolom Perubahan memakai nama field dan nilai yang mudah dibaca ("Status: Ready to Deploy →
  Deployed", "Lokasi: Plant A"), bukan `status_id: 2 → 4`.

### Keamanan

- Custom field yang namanya menandakan rahasia (password, PIN, BitLocker, kunci) tidak ditampilkan, begitu juga nilai
  password di riwayat. Kunci lisensi tetap tidak pernah dikirim ke browser.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.15.0] - 2026-10-21

### Ditambahkan

- Menu baru **Snipe-IT** di sidebar (bagian "Aset IT"), untuk melihat isi Snipe-IT tanpa membukanya, dengan submenu:
  - **Ringkasan**: jumlah setiap daftar di Snipe-IT, aset per status, dan aset per kategori;
  - **Aset**: semua aset (asset tag, SN, model, kategori, status, pemegang, lokasi), dengan filter kategori dan status;
  - **Katalog**: model, kategori, manufaktur, status, supplier, custom field, fieldset, depresiasi;
  - **Orang & Organisasi**: user, departemen, lokasi, company, grup;
  - **Lisensi & Stok**: lisensi, aksesori, consumable, komponen, kit;
  - **Riwayat**: log aktivitas (checkout, checkin, perubahan) dan maintenance.

  Setiap daftar bisa dicari, diurutkan, dan dibuka per halaman; tiap baris punya link ke halamannya di Snipe-IT.
  Menu ini hanya untuk Super Admin; role lain bisa diberi di Role & Permission.

### Keamanan

- Menu Snipe-IT hanya membaca: aplikasi tidak pernah mengirim apa pun selain GET ke Snipe-IT, dan hanya kolom
  daftar yang sampai ke browser (kunci lisensi, telepon, alamat, dan harga tidak). Token Snipe-IT tidak pernah keluar
  dari server. Disarankan memakai token dari akun Snipe-IT yang hanya boleh melihat.

**Deploy:** jalankan migrasi `2026_10_21_110000_add_snipe_it_menu`. Setting baru di `.env`: `SNIPEIT_URL` (alamat API
Snipe-IT, berakhiran `/api/v1`), `SNIPEIT_TOKEN`, dan opsional `SNIPEIT_CACHE_MINUTES` (bawaan 5).

## [1.14.5] - 2026-10-21

### Diubah

- Audit Log: pilihan filter Aksi kini memakai nama yang mudah dibaca ("Gagal masuk", "Status akun diubah", "Token
  API dicabut") dan dikelompokkan per bagian aplikasi (Masuk & keluar, Akun, Radio, Dokumen, API, …), bukan kode
  seperti `auth.login_failed`. Kodenya tetap dipakai di alamat halaman (`?action=auth.login_failed`).

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.14.4] - 2026-10-21

### Diperbaiki

- Dashboard: topbar kini bertuliskan "Dashboard", dan "Selamat datang di Radio Management System" ada di bawahnya
  (di 1.14.3 keduanya tertukar). Breadcrumb Dashboard, yang hanya berisi dirinya sendiri, tidak ditampilkan.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.14.3] - 2026-10-21

### Diubah

- Judul halaman kini di topbar (di sebelah tombol sidebar), dan breadcrumb pindah ke bawah topbar, di atas isi
  halaman ("Dashboard › Register Radio"). Judul besar di atas kartu dihapus, jadi judul tidak tampil dua kali. Di HP,
  yang tidak menampilkan topbar, judul tetap ada di bawah breadcrumb. Di Dashboard judulnya tetap "Selamat datang di
  Radio Management System".
- Dashboard: kedua kolom baris atas kini berakhir sejajar; panel Radio ID bebas berikutnya memanjang sampai bawah
  panel Perlu tindakan.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.14.2] - 2026-10-21

### Diubah

- Dashboard: panel **Radio aktif per company pengguna** dan **Radio aktif per tipe** kini sama tinggi; batang per tipe
  mengisi tinggi panelnya (paling sedikit 220 px, juga di HP), dengan satu garis dasar yang rata.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.14.1] - 2026-10-21

### Diubah

- Dashboard:
  - **Radio aktif per company pengguna** kini donut dengan total radio di tengah, di samping daftarnya (jumlah dan
    persen): lima company terbesar masing-masing berwarna sendiri, radio tanpa company abu-abu muda, dan company
    lainnya digabung menjadi "Lainnya" (namanya ditulis di bawahnya);
  - **Radio aktif per tipe** kini grafik batang tegak: angka di atas tiap batang, nama tipe di bawahnya;
  - pie **Kondisi radio aktif** tanpa angka persen di dalam irisan (persennya tetap di daftar).

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.14.0] - 2026-10-21

### Ditambahkan

- Halaman Notifikasi mengikuti desain handoff (NotificationsPage), tanpa subjudul:
  - tab Semua, Belum dibaca, Radio, Dokumen dan Sistem, masing-masing dengan jumlahnya (Radio: ringkasan mingguan
    register; Dokumen: pengajuan dan hasil perubahan sel ICT Masterlist; Sistem: token API yang hampir berakhir);
  - **Tandai dibaca** di setiap baris yang belum dibaca, tanpa membuka notifikasinya (juga jalan tanpa JavaScript);
  - di samping judul tampil kategorinya; waktunya "12 menit yang lalu" untuk hari ini, "Kemarin, 16:40", dan
    "17 Okt, 08:00" untuk yang lebih lama (tanggal dan jam lengkap di tooltip).

### Diubah

- Judul Dashboard kini "Selamat datang di Radio Management System" (English: "Welcome to Radio Management System").

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.13.3] - 2026-10-21

### Diubah

- Baris kecil di bawah judul halaman dihapus di semua halaman (Register Radio, Perlu Perhatian, Servis & Perbaikan,
  Riwayat Radio, Master Data, E-Sign Master, COF Scan, ICT Masterlist, Persetujuan Edit, Users, Role & Permission,
  Audit Log, API Tokens, Notifikasi): halaman kini hanya berjudul, seperti Dashboard.
- Register Radio:
  - tombol Reset diganti tombol simbol refresh (Muat ulang), yang memuat ulang daftar tanpa menghapus pencarian dan
    filter; filter tetap bisa dikosongkan lewat "Hapus filter" di panel Filter;
  - garis vertikal di samping kolom tombol di ujung kanan tabel dihapus.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.13.2] - 2026-10-21

### Diubah

- Latar halaman (di belakang kartu, sampai footer) kini putih, bukan abu-abu muda; sidebar tetap abu-abu seperti
  production, jadi keduanya terlihat terpisah. Halaman login, Stock Opname di HP dan form cetak tidak berubah.

**Deploy:** tanpa migrasi baru, tanpa setting baru.

## [1.13.1] - 2026-10-21

### Diubah

- Tombol ganti bahasa kini **ID | EN** di topbar, di sebelah kiri lonceng notifikasi, jadi terlihat tanpa membuka menu
  akun (di 1.13.0 ada di dalam menu akun). Di HP tetap di sidebar, dan di halaman login tetap "ID | EN" di bawah form.

**Deploy:** tanpa migrasi baru, tanpa setting baru (migrasi bahasa dari 1.13.0 tetap perlu dijalankan bila belum).

## [1.13.0] - 2026-10-21

### Ditambahkan

- **Bahasa Indonesia / English.** Ganti bahasa lewat menu akun (kanan atas), di sidebar versi HP, atau "ID | EN" di
  halaman login. Semua halaman ikut: menu, judul, tombol, tabel, dialog, konfirmasi, pesan server dan validasi, halaman
  error, nama bulan. Bahasa bawaan Bahasa Indonesia; pilihan disimpan per akun (dan di browser), jadi ikut ke perangkat
  lain. Notifikasi baru dikirim dalam bahasa penerimanya.
- Data tetap seperti tersimpan, dalam Bahasa Indonesia: riwayat radio, catatan, Audit Log, isi SharePoint, file Excel,
  form checkout yang dicetak dan label di REST API. Posisi, kondisi, alasan arsip, aksesori dan jenis riwayat hanya
  ditampilkan dengan nama dalam bahasa halaman.
- Halaman yang tadinya hanya English (Users, Role & Permission, Audit Log, API Tokens) kini juga dalam Bahasa
  Indonesia, dan bagian yang dulu campur (loader unggah/unduh, beberapa label) mengikuti bahasa halaman.

### Diubah

- REST API (`/api/v1`): pesannya kini dalam English, juga yang dulu dalam Bahasa Indonesia (penolakan aturan,
  validasi, SharePoint); klien yang mengirim `Accept-Language: id` mendapat Bahasa Indonesia. Data (label posisi,
  kondisi, hasil cek) tidak berubah.
- Nama menu sidebar dalam Bahasa Indonesia: "Settings" menjadi "Pengaturan", "User Registered" menjadi "User
  Terdaftar", "Role & Permission" menjadi "Role & Hak Akses".

**Deploy:** satu migrasi baru, `2026_10_21_100000_add_locale_to_users` (kolom `users.locale`):
`docker compose exec app php artisan migrate`. Tanpa setting baru. Sebelum migrasi dijalankan aplikasi tetap jalan;
pilihan bahasa hanya tersimpan di browser.

## [1.12.3] - 2026-10-21

### Diubah

- Dialog role di Role & Permission (Add Role, Edit dan lihat) kini dalam ukuran production: lebar 720 px di tengah
  layar, judul 16 px, nama isian .7rem, isian 29 px dengan teks .65rem, tabel menu dengan judul kolom 10 px dan baris
  27 px (kolom Access sampai Delete masing-masing 90 px, jadi nama menu yang panjang turun ke baris kedua seperti di
  production), tombol 26 px. Warna dan bentuk desain baru tetap.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.12.2] - 2026-10-21

### Diubah

- Latar sidebar sekarang abu-abu sangat muda (`#f8fafc`), sama seperti di production, bukan putih lagi. Berlaku untuk
  sidebar lebar, sidebar ikon dan menu di HP; lingkaran di sekeliling titik penanda pada sidebar ikon ikut warna
  latarnya.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.12.1] - 2026-10-21

### Diubah

- Dashboard:
  - panel baris atas (Kondisi radio aktif dan Radio ID bebas di kiri, Perlu tindakan di kanan) sekarang sama lebar
    dengan panel baris bawah (per company dan per tipe), dua kolom sama besar, jadi tepi panelnya sejajar;
  - di layar 1366 px pie chart kondisi kini muat di samping daftarnya;
  - teks "Register radio HT & rig · data per …" di bawah judul Dashboard Radio dihapus.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.12.0] - 2026-10-21

### Ditambahkan

- Breadcrumb di topbar, di tempat judul halaman, misalnya "Dashboard › Master Data Radio › Tipe Radio" atau
  "Dashboard › Register Radio › Radio 1053":
  - dibuat otomatis dari menu sidebar halaman itu, jadi setiap halaman punya tanpa diatur satu per satu;
  - "Dashboard" dan menu di atas halaman detail bisa diklik untuk kembali; nama grup (Master Data Radio, User
    Management) hanya teks;
  - "Dashboard" tidak ditampilkan bagi role yang tidak punya akses ke Dashboard;
  - halaman di luar menu (Notifikasi) tampil sebagai "Dashboard › Notifikasi".

  Di HP topbar tidak tampil; halaman detail radio tetap punya jalur kembali ke Register Radio di atas isinya.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.11.2] - 2026-10-21

### Diubah

- Dashboard: kondisi radio aktif ditampilkan sebagai pie chart di samping daftarnya (di bawah daftar bila kartunya
  sempit), menggantikan batang bertumpuk.
  - Persentase tertulis di irisan yang cukup besar.
  - Irisan bisa diklik dan menuju Register Radio dengan filter kondisinya, sama seperti barisnya.
  - Saat kursor diarahkan ke irisan atau barisnya, keduanya menyala dan yang lain meredup.
  - Kondisi yang sangat kecil (misalnya 2 dari 1.011 radio) tetap terlihat sebagai irisan tipis; angka persisnya ada
    di daftar.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.11.1] - 2026-10-21

### Diubah

- Halaman login menampilkan logo Merdeka Tsingshan Indonesia di antara logo Merdeka Copper Gold dan Merdeka Battery
  Materials: setinggi 28 px di layar lebar, 24 px di layar 1366 px supaya ketiganya tetap berjarak.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.11.0] - 2026-10-21

### Ditambahkan

- Nomor versi di footer bisa diklik dan membuka dialog **Riwayat versi**: apa yang berubah di tiap versi, yang terbaru di
  atas, dengan tanda "Versi ini" pada versi yang sedang berjalan. Isinya diambil dari CHANGELOG.md, tanpa baris Deploy
  (catatan untuk server), dan bisa dibuka semua akun yang login.

### Diperbaiki

- Dialog yang isinya digulir (Riwayat versi, Riwayat pemakaian tanda tangan) tidak lagi terpotong di bawah layar.

**Deploy:** tanpa migrasi, tanpa setting baru. Image Docker sekarang ikut membawa CHANGELOG.md, jadi build ulang seperti
biasa (`docker compose up -d --build`).

## [1.10.13] - 2026-10-21

### Diubah

- Menu di sidebar diletakkan seperti production:
  - sidebar 240 px (sebelumnya 248); baris menu setinggi 36 px setiap 40 px, mulai 18 px di bawah logo; ikon 20 px dan
    nama menu 13 px di posisi yang sama dengan production;
  - judul seksi (RADIO, DOKUMEN, USER, SETTINGS) 10 px dan sedikit lebih gelap, 18 px di bawah baris di atasnya;
  - sub-menu (misalnya isi Master Data Radio) 0,8rem dengan jarak dan indentasi seperti production;
  - sidebar disembunyikan (deretan ikon): ikon 22 px setiap 48 px, dan dua tanda logo sejajar di tengahnya.

  Warnanya (latar putih, menu aktif biru muda) tetap dari desain baru.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.10.12] - 2026-10-21

### Diubah

- Role & Permission: Super Admin bisa mengedit role Super Admin sendiri lagi (nama, home page, menu dan izinnya), seperti
  di production. Baris itu tetap tanpa tombol Delete, karena role Super Admin tidak bisa dihapus. Pengelola role yang
  bukan Super Admin tetap tidak melihat dan tidak bisa mengubah role ini.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.10.11] - 2026-10-21

### Diubah

- Dialog "Daftarkan tanda tangan" / "Edit tanda tangan" di E-Sign Master berukuran seperti production: lebar 720 px di
  tengah layar, judul 16 px, field setinggi 29 px, tombol Batal dan Simpan 28 px dengan ikonnya. Kotak tanda tangan
  kembali berbingkai, dan kalimat di bawah judul tidak ada lagi, sama seperti production.
- Tanda * pada field wajib berjarak 4 px dari namanya, seperti production. Sebelumnya ada spasi tambahan, jadi
  jaraknya hampir dua kali lipat.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.10.10] - 2026-10-21

### Diubah

- Baris ringkasan di bawah tabel memberi spasi di kiri dan kanan tanda pisah: "Showing 1 – 1 of 1", "Menampilkan
  1 – 25 dari 247 radio", juga di halaman Notifikasi. Rentang tanggal di Audit Log ikut: "08 – 12 Oct 2026".

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.10.9] - 2026-10-21

### Diubah

- Tidak ada lagi tautan yang bergaris bawah saat kursor diarahkan, di halaman mana pun. Contohnya baris Kondisi radio
  aktif di Dashboard, "Lihat", "Buka inbox", breadcrumb, dan "Pilih semua" / "Kosongkan". Tautan teks berubah warna
  sedikit lebih gelap sebagai gantinya.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.10.8] - 2026-10-21

### Diubah

- Logo di atas sidebar seperti production:
  - sidebar terbuka: logo Merdeka Copper Gold dan Merdeka Battery Materials setinggi 32 px (sebelumnya 28 px),
    berdampingan di tengah;
  - sidebar disembunyikan (deretan ikon): dua tanda kecil berdampingan, Merdeka Copper Gold 20 px dan Merdeka Battery
    Materials 24 px. Sebelumnya hanya tanda Merdeka Battery Materials.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.10.7] - 2026-10-21

### Diubah

- Halaman login: judulnya "MTI Radio Monitoring", 24 px di semua lebar layar seperti production. Judul ini cukup pendek,
  jadi ukuran 23 px untuk layar di bawah 1400 px dari 1.10.6 tidak diperlukan lagi.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.10.6] - 2026-10-21

### Diubah

- Halaman login: font, ukuran teks, logo dan jarak di panel masuk sama dengan production (diukur per elemen di sana):
  - font sistem, seperti production (halaman login production tidak memuat Inter);
  - logo Merdeka Copper Gold setinggi 38 px dan logo Merdeka Battery Materials bertumpuk setinggi 56 px;
  - tombol Microsoft 120 × 58 px dengan logo gambar 24 px;
  - judul 24 px (23 px di layar di bawah 1400 px, supaya tetap satu baris), teks 15, 14 dan 12 px;
  - isian dan tombol Login setinggi 36 px.

  Warna dan bentuk tombol tetap memakai gaya baru.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.10.5] - 2026-10-21

### Diubah

- Halaman login: warna judul "Radio Management System" sama dengan production, yaitu abu-abu `text-secondary` dari
  Tabler (#667382).

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.10.4] - 2026-10-21

### Diubah

- Halaman login: ukuran kartunya kini sama dengan production (1.9.x) di setiap lebar layar, menggantikan ukuran tetap
  di 1.10.3. Seperti grid Bootstrap lama, lebarnya 1070,66 px dari layar 1400 px, 920,66 px dari 1200 px, dan 928 px di
  bawahnya. Foto mengambil 7/12, form 5/12, dan tingginya 687,98 px di dalam border 1 px. Judulnya sedikit lebih kecil
  supaya muat satu baris di layar lebar.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.10.3] - 2026-10-21

### Diubah

- Halaman login:
  - kartunya kembali seukuran sebelum tampilan baru: foto 538,88 × 687,98 px dan panel masuk 382,77 × 687,98 px. Di
    jendela yang lebih sempit keduanya mengecil dengan perbandingan yang sama;
  - judulnya "Radio Management System". Judul tab browser dan halaman lain tetap memakai nama aplikasi.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.10.2] - 2026-10-21

### Diubah

- Halaman login: empat garis penanda di bawah foto dihapus. Fotonya tetap bergantian seperti sebelumnya.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.10.1] - 2026-10-21

### Diperbaiki

- Tautan yang tampil sebagai kartu, baris, badge atau tombol ikon bergaris bawah saat kursor diarahkan (aturan `a:hover`
  dari Tabler): kartu angka di Dashboard, baris notifikasi dan tautan bawah di lonceng, badge versi di footer, tombol
  ikon di Register Radio dan Stock Opname, dan tombol Microsoft di halaman login. Tautan berupa teks tetap bergaris
  bawah.

**Deploy:** tanpa migrasi, tanpa setting baru.

## [1.10.0] - 2026-10-21

Tampilan baru (design refresh) untuk semua halaman, dan kemampuan baru: Perlu Perhatian, Servis & Perbaikan, Stock
Opname, serah terima dengan tanda tangan, pemilih Radio ID, dan edit baris langsung di ICT Masterlist. Dikerjakan per
fase di branch `design-refresh`; rencana dan keputusannya di [docs/PLAN.md](docs/PLAN.md).

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
  - menu baru **Perlu Perhatian**, tepat di bawah Register Radio, dengan angka merah item yang terbuka; Dashboard punya
    tautan **Buka inbox**. Hasil cek kolom "Cek" menjadi item yang dikerjakan:
    - satu item per radio, atau satu per SN / Radio ID yang dipakai beberapa radio (radio-radionya dibandingkan, kolom
      yang berbeda ditandai);
    - perbaikan per jenis cek lewat RadioService (riwayat dan audit tercatat): betulkan SN, arsipkan duplikat, ID bebas
      berikutnya, ID terakhir, pilih tipe, isi No. BA (dan arsipkan sebagai hilang), catat program atau verifikasi hari
      ini, tandai sudah ditinjau. Memperbaiki butuh izin Update di Register Radio juga;
    - **Abaikan** wajib beralasan; item muncul lagi bila data radionya berubah;
    - item bisa ditugaskan ke akun yang boleh mengubah data radio; filter Semua / Tugas saya / Belum ditugaskan;
    - audit `attention.assigned` dan `attention.dismissed`;
    - endpoint baru di `radio-management/attention`: `list`, `item`, `PUT assign`, `PUT dismiss`, `PUT fix`.
  - menu baru **Stock Opname**, di bawah Servis & Perbaikan: halaman untuk ponsel, tanpa sidebar:
    - pilih department (dan company bila perlu), lihat jumlah radio yang diharapkan dan opname terakhir, mulai sesi
      (sesi yang masih terbuka di sana dilanjutkan);
    - pindai: kamera membaca barcode SN bila browser-nya bisa (Chrome Android, `BarcodeDetector`), selain itu Radio ID
      atau SN diketik atau dikirim scanner genggam;
    - cek radio: siapa yang memegang (sesuai register, orang lain, tidak ada), kondisi, catatan; setiap pindaian langsung
      tersimpan. **Laporkan masalah** membuka tiket servis;
    - ringkasan: terverifikasi, tidak ditemukan, pemegang lain, department lain. **Kirim ke register** memverifikasi
      setiap radio yang ditemukan hari ini (dengan baris riwayat), memasukkan kondisi yang berubah, dan mencatat audit
      `radio.opname_submitted`;
    - impor Excel yang menghapus data lama ditolak selama masih ada sesi opname terbuka;
    - endpoint baru di `radio-management/opname`: `outlook`, `POST /`, `{opname}`, `{opname}/lookup`, `PUT {opname}/scan`,
      `PUT {opname}/report`, `PUT {opname}/submit`.
  - **Edit baris** langsung di ICT Masterlist (K13h): akun orang (bukan API client) yang boleh mengajukan sekaligus
    menyetujui perubahan (praktisnya Document Controller dan Super Admin) mendapat pensil di nomor baris. Dialognya berisi
    semua kolom sheet; sel rumus, sel yang punya pengajuan menunggu, dan kolom File scan hanya bisa dibaca:
    - workbook diunduh sekali, setiap sel dan kolom pengenal baris dicek, lalu diunggah sekali dengan versinya
      (If-Match). Tidak ada yang ditulis bila baris sudah berubah (409), file disimpan orang lain (412), atau dikunci
      karena dibuka di Excel (423);
    - setiap sel tercatat sebagai pengajuan yang diterapkan oleh pembuatnya, bertanda "langsung" (halaman Persetujuan
      Edit menulis "Ditulis langsung oleh ..."), dan di Audit log sebagai `document.edited_directly`;
    - bagi mereka aturan empat-mata tidak berlaku; SharePoint mencatat aplikasi sebagai yang menyimpan;
    - endpoint baru: `PATCH documents/ict-masterlist/row`; jawaban `rows` membawa `direct`.
  - menu baru **Servis & Perbaikan**, di bawah Perlu Perhatian (entri di bawahnya turun satu tempat). Yang memegang
    Register Radio mendapat izin yang sama di menu ini: Admin Radio dan Super Admin penuh, Viewer Radio baca:
    - tiket servis bernomor `SRV-0001` untuk radio yang bermasalah, satu tiket terbuka per radio, tidak untuk radio arsip;
    - langkahnya Dilaporkan → Dikirim ke servis → Dalam perbaikan → Siap diambil → Kembali. Saat dikirim, lokasi radio
      menjadi Service; saat kembali, lokasi dan kondisinya dipilih. Keduanya menulis baris riwayat "Service" (nomor
      SRV di keterangannya, No. BA sebagai nomor dokumen);
    - halaman: empat angka (tiket terbuka, rata-rata lama servis 90 hari terakhir, terlambat lebih dari 14 hari, biaya
      kuartal ini), Board / List, cari, filter pusat servis, Export Excel (juga tiket yang sudah selesai), drawer tiket
      dengan langkah, fakta, aktivitas dan catatan;
    - audit `service.created`, `service.status_changed`, `service.note_added`;
    - impor Excel yang menghapus data lama ditolak selama masih ada tiket servis terbuka;
    - endpoint baru di `radio-management/service`: `list`, `radios`, `export`, `{ticket}`, `POST /`,
      `PUT {ticket}/status`, `PUT {ticket}/note`.
  - **COF Scan** (bila sheet ICT Masterlist boleh dibuka): tiga sel di atas daftar (Sinkron terakhir, Tertaut ke baris,
    Tidak tertaut), peringatan "N file tidak cocok dengan baris di ICT Masterlist" dengan tombol **Tinjau**, dan filter
    Semua / Tertaut / Tidak tertaut dengan jumlahnya. Hitungannya untuk seluruh folder, apa pun yang dicari. Endpoint
    `documents/cof-scan/list` menerima `status` (`linked`, `unmatched`) dan menjawab `register.linked`,
    `register.unmatched`, `folder.synced` dan `folder.status`.
  - **Audit log**:
    - filter **Entity** (jenis catatan yang ada di audit, dengan namanya: Account, Radio, Radio type, …) dan **Date range**:
      Today, Yesterday, Last 7 days, Last 30 days, All time, atau **Custom range…** dengan kalender rentang (klik hari
      pertama dan terakhir, lalu Apply);
    - alamat halaman menyimpan pilihannya (`?entity=Radio&range=7d`, atau `?from=…&to=…`);
    - Event details menampilkan **Browser** ("Chrome 141 on Windows"), dibaca dari user agent yang sudah tersimpan;
    - `audit-log/list` menerima `entity`, `from` dan `to`.

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
- Pesan toast (mis. "Tiket SRV-0001 dibuat.") tampil putih tanpa teks: gaya SweetAlert yang dimuat belakangan menimpa
  latar gelapnya.
- Halaman login tidak lagi memuat gambar yang tidak ada: thumbnail carousel lama, dan ikon `manifest.json` peninggalan
  template. Tautan manifest dihapus dari halaman login; file-nya masih ada di `public/`.
- `php artisan db:seed` di database yang sudah dipakai (atau `--class=RadioMenuSeeder`) menaruh Approval Management di
  nomor urut yang sama dengan Audit Log, sehingga urutan keduanya di sidebar tidak tentu. Sekarang Audit Log dan API
  Tokens tetap di bawah User Management, Approval Management sesudahnya.

**Deploy:** tanpa setting baru. Membawa tujuh migrasi: `php artisan migrate`, sekali per database. Aplikasi tetap jalan
sebelum migrasinya dijalankan (halaman barunya bilang belum aktif). Migrasinya:
- `2026_10_20_100000_create_service_tickets_tables` (tabel `service_tickets` dan `service_ticket_events`);
- `2026_10_20_110000_add_service_menu` (menu Servis & Perbaikan);
- `2026_10_20_120000_create_radio_check_states_table` (tabel `radio_check_states`: pertama terlihat, penugasan, abaikan);
- `2026_10_20_130000_add_attention_menu` (menu Perlu Perhatian);
- `2026_10_20_140000_create_radio_opname_tables` (tabel `radio_opname_sessions` dan `radio_opname_items`);
- `2026_10_20_150000_add_opname_menu` (menu Stock Opname);
- `2026_10_20_160000_add_direct_to_sharepoint_edit_requests` (kolom `direct` di `sharepoint_edit_requests`).

Migrasi menu menghapus sendiri cache peta menu (`rbac_menu_route_key_map`). Instalasi yang tidak berbagi cache dengan
tempat migrasi dijalankan: `php artisan cache:forget rbac_menu_route_key_map` di instalasi itu.

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

[1.20.1]: https://github.com/mahathirmuh/radio-management/compare/v1.20.0...v1.20.1
[1.20.0]: https://github.com/mahathirmuh/radio-management/compare/v1.19.1...v1.20.0
[1.19.1]: https://github.com/mahathirmuh/radio-management/compare/v1.19.0...v1.19.1
[1.19.0]: https://github.com/mahathirmuh/radio-management/compare/v1.18.0...v1.19.0
[1.18.0]: https://github.com/mahathirmuh/radio-management/compare/v1.17.0...v1.18.0
[1.17.0]: https://github.com/mahathirmuh/radio-management/compare/v1.16.0...v1.17.0
[1.16.0]: https://github.com/mahathirmuh/radio-management/compare/v1.15.0...v1.16.0
[1.15.0]: https://github.com/mahathirmuh/radio-management/compare/v1.14.5...v1.15.0
[1.14.5]: https://github.com/mahathirmuh/radio-management/compare/v1.14.4...v1.14.5
[1.14.4]: https://github.com/mahathirmuh/radio-management/compare/v1.14.3...v1.14.4
[1.14.3]: https://github.com/mahathirmuh/radio-management/compare/v1.14.2...v1.14.3
[1.14.2]: https://github.com/mahathirmuh/radio-management/compare/v1.14.1...v1.14.2
[1.14.1]: https://github.com/mahathirmuh/radio-management/compare/v1.14.0...v1.14.1
[1.14.0]: https://github.com/mahathirmuh/radio-management/compare/v1.13.3...v1.14.0
[1.13.3]: https://github.com/mahathirmuh/radio-management/compare/v1.13.2...v1.13.3
[1.13.2]: https://github.com/mahathirmuh/radio-management/compare/v1.13.1...v1.13.2
[1.13.1]: https://github.com/mahathirmuh/radio-management/compare/v1.13.0...v1.13.1
[1.13.0]: https://github.com/mahathirmuh/radio-management/compare/v1.12.3...v1.13.0
[1.12.3]: https://github.com/mahathirmuh/radio-management/compare/v1.12.2...v1.12.3
[1.12.2]: https://github.com/mahathirmuh/radio-management/compare/v1.12.1...v1.12.2
[1.12.1]: https://github.com/mahathirmuh/radio-management/compare/v1.12.0...v1.12.1
[1.12.0]: https://github.com/mahathirmuh/radio-management/compare/v1.11.2...v1.12.0
[1.11.2]: https://github.com/mahathirmuh/radio-management/compare/v1.11.1...v1.11.2
[1.11.1]: https://github.com/mahathirmuh/radio-management/compare/v1.11.0...v1.11.1
[1.11.0]: https://github.com/mahathirmuh/radio-management/compare/v1.10.13...v1.11.0
[1.10.13]: https://github.com/mahathirmuh/radio-management/compare/v1.10.12...v1.10.13
[1.10.12]: https://github.com/mahathirmuh/radio-management/compare/v1.10.11...v1.10.12
[1.10.11]: https://github.com/mahathirmuh/radio-management/compare/v1.10.10...v1.10.11
[1.10.10]: https://github.com/mahathirmuh/radio-management/compare/v1.10.9...v1.10.10
[1.10.9]: https://github.com/mahathirmuh/radio-management/compare/v1.10.8...v1.10.9
[1.10.8]: https://github.com/mahathirmuh/radio-management/compare/v1.10.7...v1.10.8
[1.10.7]: https://github.com/mahathirmuh/radio-management/compare/v1.10.6...v1.10.7
[1.10.6]: https://github.com/mahathirmuh/radio-management/compare/v1.10.5...v1.10.6
[1.10.5]: https://github.com/mahathirmuh/radio-management/compare/v1.10.4...v1.10.5
[1.10.4]: https://github.com/mahathirmuh/radio-management/compare/v1.10.3...v1.10.4
[1.10.3]: https://github.com/mahathirmuh/radio-management/compare/v1.10.2...v1.10.3
[1.10.2]: https://github.com/mahathirmuh/radio-management/compare/v1.10.1...v1.10.2
[1.10.1]: https://github.com/mahathirmuh/radio-management/compare/v1.10.0...v1.10.1
[1.10.0]: https://github.com/mahathirmuh/radio-management/compare/v1.9.0...v1.10.0
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
