# PLAN: design refresh MTI Radio Management

Rencana kerja untuk menerapkan desain baru ke aplikasi ini: tampilan baru untuk semua halaman yang ada (fase 1–5) dan
lima kemampuan baru yang butuh backend (fase 6).

- **Branch:** `design-refresh`, dibuat dari `main` di `673b878`. Jangan bekerja di `main`.
- **Spesifikasi:** `docs/design_handoff_radio_management/README.md` dan `design_files/*.dc.html` (folder lokal, lihat K9).
  Ukuran, warna dan state diambil dari atribut `style="…"`. Tag `<dc-import>`, `<sc-for>`, `<sc-if>` dan `{{ }}` hanya
  kerangka prototipe: komponen, perulangan, kondisi, variabel.
- **Status:** langkah 1 selesai. Rencana ini sudah ditulis dan menunggu jawaban keputusan K1–K13. Lihat "Progres".

## Cara melanjutkan di sesi baru

1. `git checkout design-refresh && git pull`.
2. Baca "Aturan kerja", "Keputusan" dan "Asumsi", lalu "Progres" untuk tahu posisi terakhir.
3. Kerjakan butir pertama yang belum dicentang di fase yang sedang berjalan.

## Aturan kerja

- Fase 1–6 dikerjakan berurutan, tanpa berhenti di antara fase.
- Bangun dengan Blade, Tabler dan helper jQuery yang sudah ada (`renderTableWithFeatures`, `renderTable`,
  `renderPagination`, `debounce`, `Swal`). Tidak ada React, Tailwind atau langkah build.
- Controller dan endpoint tidak diubah, kecuali perubahan backend yang disebut README. Semuanya dikerjakan di fase 6.
- Setelah tiap fase:
  1. jalankan semua tes (`php artisan test`, LocalDB) dan perbaiki yang gagal;
  2. centang file ini;
  3. commit `fase N: …` sebagai mahathirmuh;
  4. push branch.
- Berhenti dan tanya pemilik bila:
  - ada keputusan yang tidak ada di README atau di file ini;
  - tes gagal dan tidak bisa diperbaiki;
  - perlu menjalankan migration.
- Migration fase 6 hanya ditulis. Jangan dijalankan sebelum pemilik memeriksanya (untuk database tes, lihat K13j).
- Setelah tiap fase, periksa di browser:
  - pakai salinan preview dengan LocalDB dan data rekaan, Microsoft/SharePoint/LDAP dimatikan, `SESSION_DRIVER=file`;
  - bandingkan dengan prototipe di 1366, 1440 dan 1920 px, dan di 390 px untuk ponsel.
- Repo publik: jangan commit IP internal, hostname, nama orang sungguhan, `.env*` atau gambar tanda tangan.
- Bila konteks panjang, catat posisi kerja di "Progres" sebelum lanjut.

## Keputusan

K1–K5 berasal dari README ("Decisions to confirm"). K6–K13 muncul saat desain dibandingkan dengan kode. Jawaban pemilik
diisi di baris **Jawaban**.

**K1. Bahasa.** Desain berbahasa Inggris. Bahasa aplikasi sekarang:
- Indonesia: halaman Radio dan Dokumen.
- Inggris: User Management, Audit Log, API dan login.
- Indonesia, dari server: status, hasil cek, jenis riwayat dan notifikasi. Label ini ikut ke API, export dan audit log.

Pilihan:
- a. Pertahankan bahasa yang ada; teks baru mengikuti bahasa modulnya. Menu pengguna di top bar disatukan ke Bahasa
  Indonesia (Notifikasi, Keluar), seperti panel notifikasi dan peringatan sesi.
- b. Semua Inggris seperti desain. Judul menu di database diganti lewat migration data, dan label dari server ikut
  berubah.
- c. Semua Indonesia.

Rekomendasi: a. **Jawaban:** —

**K2. Font.** README mengira aplikasi memakai Public Sans. Faktanya:
- aplikasi sudah memakai Inter (self-hosted, `public/assets/css/fonts/fonts.css`);
- tautan Google Fonts Public Sans di `main/index.blade.php` dimuat tapi tidak dipakai;
- halaman login menautkan `googleApis-publicSans.css`, file yang tidak ada, sehingga login tampil dengan font sistem.

Rekomendasi:
- Inter di semua halaman, termasuk login; kedua tautan Public Sans dibuang.
- Monospace untuk Radio ID, SN, MDC, nomor dokumen dan IP: `ui-monospace, "IBM Plex Mono", Menlo, monospace`, tanpa
  memuat font baru.

**Jawaban:** —

**K3. Tambahan desain di luar repo** (README menyebut boleh dibuang):

| Butir | Bagian | Rekomendasi |
| --- | --- | --- |
| a | Audit log: filter Entity dan Date range (kalender rentang). Butuh perubahan kecil di controller; query-nya sudah mendukung `from`, `to` dan `entityType`, yang dipakai API. | Ambil, di fase 6. |
| b | Audit log: kartu "Event details" 360px. Isinya bisa dari data yang sudah dikirim (waktu, user, aksi, entity, perubahan, IP). | Ambil di fase 4. Baris Browser menyusul di fase 6 (user agent sudah tersimpan, tapi belum dikirim). Role dan Request id dibuang. |
| c | Register: chip "Applied:" dan teks bulk bar. Keduanya sudah ada (1.7.0). | Restyle saja; teks tetap bahasa modul. |
| d | Dashboard alternatif (`DashboardAlt`). | Buang. |
| e | Navigasi di top bar (varian tanpa sidebar). | Buang. |
| f | Detail radio sebagai drawer. | Buang; tetap halaman detail. |

**Jawaban:** —

**K4. Sidebar diciutkan.** `Shell.dc.html` memakai rail ikon 80px, sementara README bagian Decisions menyebut sidebar
disembunyikan. Rekomendasi: rail 80px seperti sekarang, ditambah tooltip nama menu dan titik penanda jumlah.
**Jawaban:** —

**K5. Pop-up cetak.** Rekomendasi: tetap iframe seperti sekarang; yang diubah hanya kepala dan tombol pop-up.
**Jawaban:** —

**K6. README lawan aturan desain lokal (`CLAUDE.local.md`).**

| | README | Aturan lokal |
| --- | --- | --- |
| Tombol | 36px, radius 8 | `rounded-2` |
| Tombol aksi baris | netral berbingkai | `btn-detail` berwarna |
| Badge | `.rm-badge` | `bg-*-lt` |
| Teks tabel | 13px | 0.75rem |
| Hasil aksi | toast | SweetAlert |

Warna desain slate (abu-abu), emerald, rose, amber dan sky tidak ada di skala `--mdka-*`.

Pilihan:
- a. README berlaku di semua halaman. Warna desain jadi token `--rm-*` di `radio-ui.css`, persis desain. Aturan lokal
  diperbarui di fase 1.
- b. README berlaku, tapi warnanya memakai `--mdka-*` terdekat (beda tipis, tanpa token baru).

Rekomendasi: a. **Jawaban:** —

**K7. Umpan balik setelah aksi.** Desain memakai toast di bawah tengah setelah simpan, arsip atau verifikasi; aplikasi
memakai popup SweetAlert "Success!". Rekomendasi:
- toast seperti desain untuk hasil sukses sederhana, dibuat dengan mode toast SweetAlert2;
- konfirmasi, error dan hasil bulk tetap popup SweetAlert.

**Jawaban:** —

**K8. Versi dan rilis di branch.** Aturan biasa: setiap commit kode adalah rilis SemVer (VERSION, CHANGELOG, tag).
Rekomendasi:
- selama di branch, tidak ada nomor versi baru atau tag;
- tiap fase menambah catatan di bagian `## [Unreleased]` CHANGELOG;
- saat digabung ke `main`, semuanya jadi satu rilis MINOR (1.10.0), dengan baris Deploy yang memuat migration fase 6.

**Jawaban:** —

**K9. Folder handoff dan zip.** README menyarankan folder handoff di-commit, padahal:
- repo ini publik;
- isi folder memuat contoh alamat IP privat dan nama orang (katanya rekaan, tapi tidak bisa dipastikan);
- zip di root isinya sama persis dengan folder itu.

Rekomendasi:
- folder tetap lokal: tidak di-commit, dan dimasukkan ke `.git/info/exclude`;
- zip dihapus, hanya bila pemilik bilang "hapus".

**Jawaban:** —

**K10. "My profile" di menu pengguna.** Aplikasi belum punya halaman profil.
- a. Hilangkan; menu pengguna hanya berisi Notifikasi dan Keluar.
- b. Jendela kecil baca-saja (nama, email, role, perusahaan, metode sign-in), tanpa endpoint baru.

Rekomendasi: a. **Jawaban:** —

**K11. Menutup dialog.** README: klik di luar dan Esc menutup semua dialog, kecuali pop-up cetak. Rekomendasi:
- dialog berisi isian (tambah/edit radio, user, set password, role, import) tetap statis, supaya ketikan tidak hilang;
  alasannya sama dengan pop-up cetak;
- dialog konfirmasi, dialog lihat dan panduan login tertutup dengan klik di luar atau Esc.

**Jawaban:** —

**K12. Bagian desain yang butuh backend baru.** Yang diambil dikerjakan di fase 6.

| Halaman | Bagian | Rekomendasi |
| --- | --- | --- |
| Register | No. BA saat arsip dan kolomnya; filter arsip (alasan, tanggal, BA); restore dengan ID/lokasi pilihan | Buang. Arsip tetap alasan, tanggal, catatan. |
| Detail | Kartu Documents (radio ↔ file SharePoint) | Buang. |
| Riwayat Radio | Export Excel, jam kejadian, cari MDC | Buang. |
| Master data | "Active radios" tanpa arsip, deskripsi tipe, jumlah channel, terakhir diubah | Buang; tampilkan kolom yang ada. |
| E-sign | "Default signer", baris role, Draw/Upload terpisah | Buang. |
| COF scan | Tiga sel statistik, banner, filter All/Linked/Unmatched (butuh hitungan dan filter status di endpoint list) | Ambil (hanya membaca). |
| COF scan | Upload scan (butuh izin tulis ke SharePoint) | Buang. |
| ICT masterlist | "Diubah oleh" | Buang. |
| Edit approvals | Alasan, komentar saat menyetujui, beberapa sel per permintaan | Buang; tab per status cukup tanpa backend. |
| Audit log | Pilihan User, Export CSV | Buang; pencarian sudah ada. |
| API tokens | Masa berlaku "Custom…" dengan kalender | Buang. |
| Notifikasi | Tab Radio/Dokumen/Sistem dan hitungannya; "tandai dibaca" per baris | Buang; tab Semua dan Belum dibaca saja. |

**Jawaban:** —

**K13. Fase 6 (kemampuan baru).**

Rekomendasi per butir:
- **a. Scan di ponsel (stock opname).** Radio belum berlabel, dan belum ada library scanner. Rekomendasi:
  - kolom ketik ID/SN, yang juga menerima scanner genggam;
  - kamera lewat `BarcodeDetector` bawaan browser bila ada (Chrome Android), tanpa library baru;
  - iPhone baru bisa memakai kamera bila library dimasukkan ke `public/assets/libs`.
- **b. Tanda tangan serah terima.** Bisa disimpan (terenkripsi) atau hanya dicetak, seperti form checkout sekarang.
  Rekomendasi: hanya dicetak. Yang dicatat adalah riwayat "Serah Terima" dengan nomor dokumen, penerima dan waktu. Form
  bertanda tangan tetap diarsip lewat scan COF.
- **c. Nomor BA atau dokumen serah terima.** Belum ada seri. Rekomendasi: diketik, wajib dan unik, tanpa nomor otomatis.
- **d. Tiket servis.**
  - Nomor `SRV-0001` dibuat otomatis.
  - Admin Radio dan Super Admin membuat dan memindah tiket; Viewer hanya melihat.
  - Pusat servis berupa teks bebas, dengan saran dari isian sebelumnya.
- **e. Perlu perhatian.**
  - Opsi perbaikan per jenis cek, sesuai tabel di 6.4.
  - "Abaikan" wajib beralasan, dan item muncul lagi bila data radionya berubah.
  - Item bisa ditugaskan ke user yang boleh mengubah radio.
- **f. Stock opname.**
  - Per departemen, dengan filter perusahaan opsional (radio tidak punya site).
  - Hanya online, tanpa foto.
  - Yang boleh submit: pemilik izin Update di menu opname.
  - Submit = verifikasi hari ini per radio, ditambah satu catatan audit untuk sesinya.
- **g. Bahasa halaman baru.** Indonesia, seperti halaman Radio.
- **h. Edit langsung (tanpa persetujuan) di ICT masterlist.**
  - Pilihan 1: berdasarkan izin. Syaratnya boleh menyetujui edit, punya Update di ICT masterlist, dan akun orang (bukan
    API client). Praktisnya: Document Controller dan Super Admin.
  - Pilihan 2: hanya role bernama "Document Controller".
  - Rekomendasi: berdasarkan izin.
  - Catatan: bagi mereka aturan empat-mata hilang, dan SharePoint mencatat aplikasi sebagai pengubahnya.
- **i. Menu baru.** "Perlu Perhatian", "Servis & Perbaikan" dan "Stock Opname", di bawah Register Radio:
  - Super Admin dan Admin Radio: penuh.
  - Viewer Radio: baca.
  - Dipasang lewat migration, yang dijalankan pemilik.
- **j. Database tes.** Tes otomatis memakai LocalDB yang dibuat ulang setiap tes, jadi migration fase 6 ikut terpasang di
  database tes dan database preview. Database live tidak disentuh. Rekomendasi: boleh.

**Jawaban:** —

## Asumsi

Dipakai kecuali pemilik bilang lain.

- A1. Lebar konten tetap maksimal 1440px; desain digambar di 1440.
- A2. Warna utama tombol dan tautan adalah brand-600 `#2563eb` (README). Warna ini menggantikan biru Tabler `#206bc4` lewat
  `--tblr-primary`.
- A3. Chip versi di footer membaca file VERSION, bukan "v1.8.0" seperti di desain. Tahun copyright otomatis.
- A4. Top bar: nama perusahaan diambil dari perusahaan user, seperti sekarang; di bawah nama user ditampilkan role-nya.
- A5. Tombol tampil sibuk selama permintaan berjalan, tanpa jeda buatan 700–900 ms.
- A6. Tombol yang tidak boleh dipakai sebuah role disembunyikan dengan `@permission`; server tetap memeriksa.
- A7. Kolom No yang sudah ada (Register, Users, Roles) tetap ada, walau desain Register tidak memilikinya.
- A8. Kalender (RangeCal) dibuat sekali sebagai helper bersama. Riwayat Radio memakai mode tanggal tunggal; Audit log
  memakai mode rentang (fase 6).
- A9. Di bawah 992px sidebar tetap bisa dilipat seperti sekarang, termasuk entri keluar.
- A10. `renderPagination` dan `renderTable` di-restyle di fase 2, sehingga semua halaman daftar ikut berubah.
- A11. Label dari server (status, kondisi, hasil cek, jenis riwayat, notifikasi) tidak diubah. Peta warna badge dibuat
  di browser.
- A12. Judul Dashboard tetap "Dashboard Radio". Ukuran KPI mengikuti prototipe: label 13px, angka 28/36.
- A13. Angka di badge menu:
  - Edit approvals = jumlah permintaan yang menunggu, dari query baca di view sidebar, tanpa endpoint baru;
  - Perlu perhatian menyusul di fase 6.
- A14. Master data:
  - tab berupa tautan ke empat halaman yang ada, sehingga izin per menu tetap berlaku;
  - form tambah menjadi kartu 340px di samping tabel, dan disembunyikan untuk role baca-saja.
- A15. Halaman setelah login tetap Home Page milik role, bukan aturan tetap seperti di desain.

## Fakta repo yang berbeda dari README

- **Tampilan dasar**
  - Font sudah Inter (lihat K2). Versi aplikasi 1.9.0.
  - Sidebar Tabler lebarnya 240px; desain 248px. Penciutan ke 80px dilakukan JavaScript yang menulis style inline.
- **Register Radio**
  - Sudah punya bulk bar (Verifikasi hari ini, Arsipkan, Hapus, Export terpilih, pilih semua di filter), chip filter
    cek, tiga scope, kolom No, dan urutan di 4 kolom.
  - Endpoint ID bebas sudah ada: `radio-management/radio/next-id`, plus API `/radios/free-ids` dan `/radios/check`.
    Endpoint ini juga punya status "reserved", yang tidak ada di desain.
  - Hapus radio adalah soft delete; riwayatnya tetap ada.
  - Arsip wajib bertanggal; alasannya dipilih dari 4 nilai config.
- **Data radio**
  - Hasil cek dihitung langsung di setiap permintaan. Tidak ada scheduler atau pekerjaan malam.
  - Radio tidak punya kolom site, dan tanggal riwayat tidak berjam.
- **Dokumen SharePoint**
  - Edit approvals punya 6 status. Satu permintaan berisi satu sel, tanpa alasan.
  - Role Document Controller sudah ada: COF baca, ICT baca+ubah, Edit approvals baca+ubah. Home page-nya Dashboard.
  - Cara aplikasi menulis ke SharePoint (app-only): unduh file, ubah sel, lalu ganti file dengan If-Match.
- **Halaman lain**
  - E-sign: satu tanda tangan per akun, tanpa "default signer".
  - Users sudah punya filter Role (multi) dan Status, kolom Last sign-in, dan dialog View role.
  - Notifikasi hanya punya 4 jenis: `register_weekly`, `edit_requested`, `edit_decided`, `token_expiring`.
  - Tanda tangan di form checkout terdokumentasi dan teruji tidak pernah disimpan.

## Fase 1: lapisan CSS dan shell

Prototipe: `Shell.dc.html`. Berkas utama:
- `resources/views/main/index.blade.php`
- `main/components/style/style.blade.php`
- `main/sidebar/*`
- `main/navbar/*`
- `main/footer/footer.blade.php`
- `auth/components/style/style.blade.php`

- [ ] `public/assets/css/radio-ui.css`, ditautkan setelah `custom.css` di layout utama dan layout login. Isinya:
  - token: `--rm-ring`, `--rm-shadow-lg`, `--rm-shadow-xl`, `--rm-ease`, palet sesuai K6, dan `--tblr-primary` sesuai A2;
  - semua keyframe README, yang baru dipakai di fase 5, beserta aturan `prefers-reduced-motion`;
  - resep: `.rm-title`, `.rm-sub`, `.rm-card`, `.rm-toolbar`, `.rm-end`, `.rm-box`, `.rm-table` (compact, hover,
    `is-selected`), `.rm-foot`, `.rm-seg`, `.rm-tabs`, `.btn-rm` (sm, primary, secondary, success, destructive, disabled,
    `is-busy`), `.rm-field`, `.rm-badge`.
- [ ] Font sesuai K2:
  - buang tautan Public Sans di layout utama;
  - login: ganti tautan file yang tidak ada dengan `fonts.css`, lalu tambahkan Tabler Icons dan `radio-ui.css`.
- [ ] Sidebar:
  - 248px, putih, garis kanan `inset -1px 0 0 gray-200`;
  - kepala 56px berisi logo MCG dan MBM (tinggi 28, jarak 10, padding `0 16px`, garis bawah);
  - nav: padding `8px 12px 16px`, jarak 2px;
  - item: padding `8px 10px`, radius 8, teks 14/20, ikon 16, jarak 10;
  - hover `rgba(15,23,42,.045)`, ditekan `.09`; aktif brand-50 / brand-700 / 600;
  - label seksi: 11/16, 600, `.06em`, huruf besar, gray-400, padding `16px 10px 6px`.
- [ ] Grup Master Data:
  - chevron 14px gray-400 berputar 180° dalam .22s;
  - anak: padding `7px 10px 7px 36px`;
  - buka/tutup dengan `grid-template-rows 1fr ↔ 0fr` dalam .22s;
  - ganti dropdown Tabler (`data-bs-toggle="dropdown"`) dengan toggle milik sendiri.
- [ ] `sidebar-background.png`: lebar 248px, menempel di bawah, di belakang nav (z 0), tidak bisa diklik, disembunyikan
  di rail.
- [ ] Rail 80px:
  - logo `mbm-logo-small.png` tinggi 28; hanya ikon, rata tengah (padding `8px 0`);
  - label seksi jadi garis 1px (`margin:10px 8px 6px`);
  - badge jumlah jadi titik 8px (top 5, right 9, cincin putih 2px);
  - ikon grup membuka anak pertama;
  - tooltip di kanan ikon: gray-900, teks 12/16 500, radius 6, padding `6px 10px`, shadow-lg, fade .12s. Pakai tooltip
    Bootstrap yang sudah diinisialisasi global.
- [ ] Toggle:
  - transisi lebar .24s `var(--rm-ease)`;
  - status tetap disimpan di `localStorage.sidebarState`;
  - JavaScript yang menulis style inline diganti satu kelas di `<body>` plus CSS;
  - margin halaman 248px ↔ 80px.
- [ ] Badge jumlah di menu Edit approvals, sesuai A13.
- [ ] Top bar:
  - 56px, putih, garis bawah, padding `0 24px`, sticky;
  - tombol toggle 32px: cincin, radius 8, `ti-layout-sidebar` 16px, gray-500, hover gray-50, ditekan gray-100;
  - judul halaman 16/24 600, dari `@yield('page-title')`.
- [ ] Sisi kanan top bar (jarak 16):
  - nama perusahaan 13px gray-500;
  - lonceng 32px radius 8 (`ti-bell` 18px) dengan pil merah (error-600, minimal 16px, teks 10/16 600). Id `notifBell`,
    `notifCount`, `notifReadAll` dan `notifList` tetap;
  - blok user: avatar 32 bulat, nama 13/600, role 12 gray-500.
- [ ] Dropdown user:
  - lebar 228px, cincin + shadow-lg;
  - kepala berisi nama dan email;
  - item: (My profile sesuai K10), Notifikasi, Keluar (error-600);
  - `#logout-form` tetap ada (dipakai timer sesi).
- [ ] Main: latar gray-50, padding 24px, lebar sesuai A1.
- [ ] Footer:
  - garis atas, padding `14px 24px`, rata tengah, teks 12px gray-500;
  - teks "Copyright © {tahun}, Development by Merdeka Group" dan chip versi (gray-700, putih, 11px, radius 4, padding
    `1px 6px`);
  - `VersionTest` harus tetap lulus.
- [ ] Di bawah 992px, perilaku sekarang tetap (A9).
- [ ] Tes:
  - perbarui `NotificationsTest` dan `VersionTest` bila perlu;
  - tes baru `ShellTest`: `radio-ui.css` tertaut di dua layout, tautan Public Sans hilang, seksi dan item aktif, badge
    Edit approvals;
  - checker baru `tests/tools/sidebar-behaviour.mjs` lewat `page-sandbox.mjs`: toggle, `localStorage`, grup, tooltip
    rail. Tambahkan stand-in `localStorage` dan `Swal.mixin` di sandbox bila perlu.
- [ ] Browser:
  - lebar 1366, 1440, 1920, rail, dan 390;
  - bandingkan dengan `Shell.dc.html`.
- [ ] Perbarui aturan desain di `CLAUDE.local.md` (file lokal) sesuai K6.

## Fase 2: content card, Register, Riwayat Radio, Detail

Prototipe: `Register`, `Detail`, `RadioForm`, `IdPicker` (sisi tampilan), `Dialogs`, `ActionDialog` (jenis radio),
`History`, `RangeCal`, `CheckoutForm`, plus bagian bersama (`DataTable`, `Tb`, `Btn`, `Fld`, `Sel`, `Bd`, `Cd`).

Bagian bersama (`resources/views/main/components/js/js.blade.php` dan CSS):
- [ ] Blok judul `.rm-title` + `.rm-sub` di atas kartu, menggantikan `<h2 class="mb-3">`. Kartu `.rm-card` dengan
  toolbar (aksi di ujung kanan) dan `.rm-box` untuk tabel.
- [ ] `renderTable` dan `renderTableWithFeatures`:
  - header 12/16, 600, gray-500 di atas gray-50;
  - sel `11px 12px`, teks 13px;
  - hover, `is-selected` (brand-50), teks kosong.
- [ ] `renderPagination`:
  - "Showing a–b of N" (sesuai bahasa halaman);
  - baris per halaman 10/25/50/100;
  - tombol 28px radius 6;
  - `onPageChange` dan `onLimitChange` tetap.
- [ ] Helper:
  - toast (sesuai K7): bawah tengah, `#0f172a`, putih 14px, radius 10, ikon centang;
  - tombol sibuk (spinner 16px);
  - kulit SweetAlert: 520px, radius 12, ikon 44px;
  - kulit modal Bootstrap: radius 12, shadow-xl, judul 18/28;
  - multi-select bergaya desain (Select all, Clear, pil jumlah);
  - kalender (A8).

Register Radio (`modules/RadioManagement/Resources/views/radio/index.blade.php` dan partial `filter`, `bulk-bar`, `bulk`,
`modal`, `archive-modal`, `print-modal`, `partials/scripts`):
- [ ] Judul, plus subjudul hidup ("N dari T radio aktif").
- [ ] Toolbar:
  - cari 320px dengan ikon, langsung saat mengetik (debounce);
  - Filter (chevron) dan Reset;
  - segmented Aktif / Arsip / Semua;
  - Export Excel dan Tambah radio (`@permission`).
- [ ] Kartu filter di dalam content card: grid 4 kolom, select2 34px, Clear dan Apply. Chip "Applied" untuk setiap
  filter aktif.
- [ ] Restyle bulk bar: brand-50, cincin brand-200, tombol 28px. Aksi dan id tetap.
- [ ] Kolom tabel:
  - kotak centang; Radio ID (+ MDC mono); SN / tipe; User / departemen; Perusahaan;
  - badge Owner: MTI biru, Non MTI oranye;
  - Template;
  - badge Lokasi: biru, hijau, oranye, merah;
  - badge Kondisi: hijau, kuning, merah, abu;
  - Diverifikasi; Cek (badge, atau centang hijau);
  - aksi ikon 28px berbingkai: Lihat, Cetak, Edit, Arsip/Restore.
- [ ] Baris dan scope:
  - klik baris membuka detail (`detail_url`); kotak centang tidak ikut membuka;
  - kolom scope Arsip: ID/MDC lama, user terakhir, tanggal arsip, alasan, aksi Restore;
  - Viewer tidak melihat tombol ubah (A6).
- [ ] Dialog 520px radius 12:
  - form tambah/edit 760px: 4 seksi, Lokasi berupa segmented, badge Owner, footer gray-50, Simpan sibuk lalu toast. "ID
    bebas" yang sudah ada tampil dengan gaya picker (ok / mengandung F / dipakai, lewat `next-id`);
  - arsip: kartu alasan, tanggal, catatan;
  - restore: menunjukkan ID lama;
  - verifikasi bulk: daftar;
  - hapus: ketik Radio ID untuk konfirmasi.

Riwayat Radio (`modules/RadioManagement/Resources/views/history/index.blade.php`):
- [ ] Judul dan kartu.
- [ ] Baris filter: cari, Tipe multi-select dengan pil jumlah, Dari/Sampai dengan kalender tanggal tunggal, Tampilkan,
  Reset.
- [ ] Tabel: Tanggal, Radio (tautan + SN · user), badge Tipe berwarna, Keterangan, No. dokumen (mono), Oleh.

Detail radio (`modules/RadioManagement/Resources/views/radio/detail.blade.php`):
- [ ] Breadcrumb dan banner Viewer.
- [ ] Kartu kepala:
  - ubin 64px, ID 30/40, MDC mono, badge lokasi dan kondisi;
  - tombol Cetak form, Tambah riwayat, Arsip/Restore, Hapus (cincin merah), Edit, semuanya dengan `@permission`. Serah
    terima menyusul di fase 6.
- [ ] Kartu Cek (callout peringatan), tiga kartu info (Identitas unit, User, Program & verifikasi), timeline riwayat
  dengan tabel lama → baru, kartu Catatan, dan Data Excel asli.
- [ ] Pop-up cetak (K5): hanya kepala dan tombol Tutup/Cetak; backdrop statis dan iframe tetap.

Tes yang terdampak:
- `RadioManagementTest`, `RadioRegisterRulesTest`, `RadioCheckoutFormTest`, `RadioBulkTest`;
- `tests/tools/radio-index-behaviour.mjs`, `radio-bulk-behaviour.mjs`, `radio-print-behaviour.mjs`;
- checker SharePoint yang mengunci `btn-pagination`, karena pagination bersama berubah.

## Fase 3: Dashboard, Master data, E-sign, Import, COF scan, ICT masterlist, Edit approvals

Dashboard (`modules/Dashboard/Resources/views/index.blade.php`, `components/global/summary-card.blade.php`):
- [ ] Judul dan subjudul, lalu satu content card.
- [ ] 6 KPI (restyle `summary-card`): border 1px, padding 16, ubin ikon 44px c-100/c-600, label 13px, angka 28/36 600.
  Tautan KPI tetap.
- [ ] Baris 5fr/7fr:
  - kiri: bar Kondisi bertumpuk (20px, celah 2px), legenda berupa tautan `?condition=`, lalu chip "ID bebas berikutnya";
  - kanan: tabel Perlu perhatian (badge, jumlah, tautan `?issue=`).
- [ ] Bar per perusahaan dan per tipe: label 96px, bar 14px brand-600.
- [ ] Tabel Aktivitas terbaru, dengan header dan warna aksi dari `historyColors`. Tautan "Buka inbox" baru muncul di
  fase 6.

Master data (`modules/RadioManagement/Resources/views/master/index.blade.php`, empat rute):
- [ ] Judul per tab. Tab garis berupa tautan (A14), tombol Tambah di kanan.
- [ ] Tabel: nama tebal + baris kedua, badge, jumlah, aksi 28px.
- [ ] Kartu form 340px menggantikan `#masterModal`, memakai `masterForm` dan id `field_*`. `@permission` untuk tambah,
  ubah dan hapus.

E-sign (`modules/RadioManagement/Resources/views/e-sign/index.blade.php`):
- [ ] Segmented Grid/List; Grid jadi default dan diingat.
- [ ] Kartu: avatar inisial, nama, akun, pratinjau 112px, Diperbarui, Ganti/Hapus; ditambah ubin tambah bergaris putus.
- [ ] List: `#esignTable` di-restyle. Banner aturan memakai aturan repo. Halaman pad tidak diubah.

Import (`modules/RadioManagement/Resources/views/import/index.blade.php`):
- [ ] Dua kartu 5fr/7fr; kontrol file dan kotak centang khusus.
- [ ] Hasil: 4 ubin (radius 12, angka 26/34), kotak baris yang dilewati, tabel compact.
- [ ] Dialog konfirmasi 440px, merah bila menghapus data. `#importFresh` tetap di dalam `@permission` Delete.

COF scan (`modules/SharePointDocs/Resources/views/index.blade.php`):
- [ ] Judul dan kartu. Toolbar: cari 360px, Reset, Muat ulang (sibuk), Buka di SharePoint.
- [ ] Tabel: No, No. COF (mono), Nama, badge Masterlist (hijau/oranye/merah), Catatan, Tipe, Diubah, Oleh, Ukuran,
  Lihat. Header bisa diurutkan.
- [ ] Restyle modal penampil. Id `#docs*` tetap. Bagian K12 menyusul di fase 6.

ICT masterlist (`modules/SharePointDocs/Resources/views/workbook.blade.php`):
- [ ] Banner info sesuai role, kepala file (nama, diubah, jumlah baris).
- [ ] Toolbar: cari 420px, Reset, Muat ulang, Buka di Excel.
- [ ] Lima kartu (yang dipilih: brand-50 + cincin 2px), tab garis dengan jumlah, tabel yang bisa diurutkan (Baris).
- [ ] Restyle dialog permintaan per sel. Id `#wb*` tetap.

Edit approvals (`modules/SharePointDocs/Resources/views/edit-approvals.blade.php`):
- [ ] Tab garis Menunggu / Disetujui / Ditolak, ditambah Konflik / Gagal yang hanya tampil bila jumlahnya > 0. Hitungan
  dari `counts`.
- [ ] Kartu permintaan yang bisa dibuka/tutup: avatar 36, siapa, di mana · kapan, badge, chevron.
- [ ] Diff Kolom / Sekarang / Usulan (160px / 1fr / 1fr): sekarang di error-50 dicoret, usulan di success-50.
- [ ] Setujui/Tolak dengan kulit SweetAlert. Pencarian tetap ada (tautan notifikasi memakai `?search=`).

Tes yang terdampak:
- `RadioManagementTest` (dashboard, import), `RadioRegisterRulesTest` (`importFresh`);
- `ESignTest` dan `tests/tools/esign-index-behaviour.mjs`;
- `SharePointDocsTest`, `SharePointWorkbookTest`, `SharePointEditTest`, `SharePointScansTest`;
- `tests/tools/sharepoint-page-behaviour.mjs`, `sharepoint-workbook-behaviour.mjs`, `sharepoint-approvals-behaviour.mjs`.

## Fase 4: Audit log, API tokens, Users, Roles, Notifikasi, Login

Audit log (`modules/AuditLog/Resources/views/index.blade.php`):
- [ ] Judul dan kartu; segmented Table / Log activity (`data-audit-view` dan `?view=` tetap); filter yang ada di-restyle.
- [ ] Tabel: Time, User, badge Action (warna dari kunci aksi), Entity, Summary, IP.
- [ ] Klik baris membuka kartu Event details 360px (K3b), yang turun ke bawah tabel di bawah 1440px.
- [ ] Timeline: ikon bulat 32px berwarna per aksi, kalimat "siapa melakukan apa • waktu relatif", kotak perubahan mono,
  baris stempel.

API tokens (`modules/Api/Resources/views/tokens/index.blade.php`):
- [ ] Satu content card dengan dua seksi: Tokens, dan API clients (khusus Super Admin).
- [ ] Owner dengan tag "You"; badge akses hijau/oranye; baris redup bila kedaluwarsa atau pemiliknya tidak aktif.
- [ ] Dialog 520px radius 12 dengan lingkaran ikon 44px: New token, Copy token + curl, New/Edit client, Revoke, Delete.

Users (`modules/UserManagement/Resources/views/user-registered/*`):
- [ ] Judul dan subjudul. Toolbar: cari 360px, Role multi-select 210px, Status 170px (langsung di toolbar, berlaku saat
  diubah), Tambah user.
- [ ] Tabel:
  - kolom No, Name (+ You), Email, Role (badge berwarna), Status;
  - badge Sign-in: LDAP abu, Microsoft biru, Both ungu, Pending kuning;
  - Last sign-in relatif, waktu lengkap di tooltip;
  - aksi 28px; Hapus merah.
- [ ] Restyle bulk bar. Footer berisi jumlah hasil.
- [ ] Dialog 760px (kolom label 190px), metode sign-in di urutan pertama, Find employee. Dialog Set password.
- [ ] Konfirmasi "Is the data correct?", lalu hasil sesuai K7.

Roles (`modules/UserManagement/Resources/views/role-permission/*`):
- [ ] Judul dan subjudul; cari langsung 320px; Reset sebagai tombol teks; Add Role.
- [ ] Tabel: No, Name, Total user (badge pudar), Action. View ada di semua role; Edit dan Delete tidak ada di Super Admin.
- [ ] Dialog 800px. Tabel izin: 80px + 4×72px, header sticky, baris grup gray-50, anak menjorok 36px, kotak nonaktif.
- [ ] Perbaiki bug: di mode View, kotak izin bisa diklik setelah role termuat.

Notifikasi (`resources/views/main/navbar/notifications.blade.php`, `modules/Notifications/Resources/views/index.blade.php`):
- [ ] Panel lonceng 400px:
  - ikon bulat 36px berwarna per jenis, waktu singkat di kanan;
  - baris belum dibaca brand-50 dengan titik 8px;
  - Tandai semua dibaca.
- [ ] Halaman, lebar maksimal 960px:
  - subjudul berisi jumlah belum dibaca;
  - tab Semua / Belum dibaca dengan jumlah;
  - grup Hari ini / Kemarin / Sebelumnya;
  - Tandai semua dibaca lewat AJAX.

Login (`resources/views/auth/login.blade.php`):
- [ ] Latar dan kartu:
  - latar `mdka-primary-bg.webp`, cover, brightness 50%;
  - kartu 1180px, radius 20, bayangan `0 4px 20px rgba(0,0,0,.55)`, grid 7fr/5fr.
- [ ] Kiri: empat foto yang sama berganti dengan cross-fade (`rm-xfade`, siklus 24s) dan empat bar 44×6, menggantikan
  carousel.
- [ ] Kanan (padding `36px 44px 16px`):
  - logo MCG 36px dan MBM 40px; judul 28/36 600;
  - Microsoft 48px; "Or"; Email/username + Password; Login 44px brand-600 dengan status sibuk;
  - chip "Click here" membuka panduan 440px dengan tombol X;
  - footer "© Merdeka Group {tahun}".
- [ ] Yang dipertahankan:
  - tanpa popup Welcome;
  - pesan sesi berakhir tetap tampil;
  - `type="text"` tetap atribut pertama di field username.

Tes yang terdampak:
- `AuditLogTest`, `Api/ApiTokensPageTest`;
- `UserManagementTest` dan `tests/tools/user-bulk-behaviour.mjs`;
- `NotificationsTest`, `SsoLoginTest`, `DirectoryLoginTest`, `SessionTimeoutTest`.

## Fase 5: gerak (motion)

- [ ] Main fade-in .22s.
- [ ] Modal:
  - scrim: masuk .18s, keluar .15s;
  - dialog: pop-in .22s, pop-out .15s;
  - berlaku untuk modal Bootstrap dan SweetAlert.
- [ ] Dropdown, popover, menu user dan panel lonceng: drop-in .16s, titik asal sesuai sisi.
- [ ] Toast: masuk .28s; hilang setelah sekitar 2,6s, keluar .2s.
- [ ] Body tabel: fade .2s setiap kali filter, halaman atau tampilan berganti. Keyframe `rm-fade-in` dan `rm-fade-in-b`
  dipakai bergantian supaya animasinya berulang.
- [ ] Bulk bar, chip Applied dan kartu filter: drop-in atau rise .22s.
- [ ] Centang kotak: `rm-check` .18s.
- [ ] Dashboard:
  - KPI menghitung naik 800ms (ease-out cubic `1-(1-k)^3`, tick 32ms, failsafe 1,5s);
  - bar bertumpuk `rm-reveal` .8s;
  - bar horizontal `rm-grow`.
- [ ] Timeline Log activity: `rm-rise` .26s, jeda 26ms × indeks.
- [ ] Tab: garis bawah bergeser. Edit approvals: daftar muncul bertahap, kartu buka/tutup lewat `grid-template-rows` .28s.
- [ ] Tanda tangan baru: `rm-pulse 1.3s ease-out .4s 2`.
- [ ] Foto login: `rm-xfade 24s`, jeda 0/6/12/18s.
- [ ] Spinner tombol sibuk: `rm-spin .7s`.
- [ ] `prefers-reduced-motion`: semua animasi mati; login hanya menampilkan foto pertama.
- [ ] Checker untuk hitung naik (failsafe) dan pergantian keyframe tabel.

## Fase 6: kemampuan baru (backend)

Prinsip:
- Migration ditulis di `database/migrations` dan tidak dijalankan sebelum diperiksa pemilik.
- Tanpa foreign key ke `radios`, karena import "fresh" menghapus semua radio. Label radio disalin ke baris baru.
- Import fresh ditolak selama masih ada tiket atau sesi opname yang terbuka.
- Rute baru dibungkus menu dan izin (`RequireMenu`).
- Kode baru tetap jalan walau tabel belum ada (pola `ready()`).
- Baris Deploy di CHANGELOG menyebut migration-nya dan cara menghapus cache peta menu di setiap instalasi.

### 6.1 Radio ID picker
- [ ] GET `radio-management.radio.id-check` (Read Register Radio). Mengembalikan:
  - status: ok, hex_f, taken atau reserved;
  - pemegang ID;
  - ID yang disarankan;
  - strip ID sekitar;
  - ID bebas berikutnya.

  Untuk ini ditambahkan `RadioConflicts::holderOf()`.
- [ ] Picker di form tambah/edit, popover 440px: MDC hex tampil langsung, huruf F merah, saran "Pakai 768".
- [ ] Tes:
  - 751 → 768;
  - taken, reserved, dan ID milik radio itu sendiri;
  - radio yang diarsip melepas ID-nya;
  - checker JS untuk modal.

### 6.2 Serah terima dengan tanda tangan
- [ ] Dialog 900px berisi:
  - ringkasan radio;
  - penerima (dari direktori karyawan);
  - nomor dokumen (K13c), PO, aksesori, kondisi, lokasi sesudahnya;
  - pad tanda tangan 180px.

  Konfirmasi terkunci sampai pad berisi tinta.
- [ ] Simpan dan cetak:
  - `RadioService::handover()` mencatat riwayat "Serah Terima" dengan `document_no` dan audit `radio.handed_over`;
  - PUT `radio-management.radio.handover` (Update);
  - lalu form checkout terbuka dengan tanda tangan (K13b).
- [ ] Tombol Serah terima di Detail dengan `@permission`.
- [ ] Tes:
  - Viewer mendapat 403;
  - tanda tangan kosong atau bukan PNG ditolak;
  - tanda tangan tidak masuk audit;
  - radio arsip ditolak.

### 6.3 Servis dan perbaikan
- [ ] Migration `create_service_tickets_tables` (`service_tickets`, `service_ticket_events`) dan `add_service_menu`.
- [ ] `ServiceTicketController`: index, list, show, export, store, status, note.
  - Nomor `SRV-0001` dibuat di dalam `RegisterLock`.
  - Satu tiket terbuka per radio; radio arsip tidak bisa diberi tiket.
- [ ] Efek status pada radio:
  - Dikirim: lokasi radio jadi Servis, plus riwayat "Service" bernomor SRV;
  - Kembali: lokasi dan kondisi dipilih, plus riwayat;
  - audit `service.*`.
- [ ] Halaman:
  - 4 sel statistik; Board/List; cari; filter pusat servis; Export; Tiket baru;
  - kolom board: abu, biru, oranye, hijau, dengan garis atas 3px;
  - kartu dengan chip jumlah hari, merah bila lebih dari 14 hari;
  - drawer 460px berisi stepper, fakta, aktivitas, catatan.
- [ ] Tes: izin per role, riwayat dan audit, export, `RouteSmokeTest`.

### 6.4 Perlu perhatian (inbox)
- [ ] Migration `create_radio_check_states_table` (penugasan, alasan abaikan, pertama terlihat) dan `add_attention_menu`.
- [ ] Endpoint index, list, show, assign, dismiss, fix (menu `radio-management.attention`). Cek tetap dihitung langsung
  oleh `RadioCheckService`.
- [ ] Opsi perbaikan lewat `RadioService`, yang menulis riwayat dan audit:

  | Cek | Opsi perbaikan |
  | --- | --- |
  | `sn_duplicate` | betulkan SN / arsipkan duplikat / tandai radio berbeda |
  | `id_duplicate` | ID bebas berikutnya / arsipkan |
  | `id_hex_f` | ID berikutnya yang bisa dipakai (radio harus diprogram ulang) |
  | `sn_empty`, `sn_format` | isi SN (dengan saran bentuk yang benar) |
  | `id_empty` | ID terakhir bila masih bebas / ID bebas berikutnya |
  | `type_empty` | pilih tipe |
  | `lost_no_ba` | isi nomor BA / arsipkan sebagai hilang |
  | `reprogram` | tambah riwayat Program |
  | `verify_empty`, `verify_stale` | tambah riwayat Verifikasi |
  | `needs_review` | tandai sudah ditinjau |
- [ ] Halaman tiga panel (250px cek, 340px item, detail perbandingan). Badge merah di sidebar dan tautan "Buka inbox" di
  Dashboard.
- [ ] Tes per jenis cek dan per izin.

### 6.5 Stock opname di ponsel
- [ ] Migration `create_radio_opname_tables` (sesi dan item) dan `add_opname_menu`.
- [ ] Layout ponsel tanpa sidebar, empat layar:
  - Mulai: departemen, jumlah yang diharapkan, sesi terakhir;
  - Scan: K13a;
  - Verifikasi: pemegang, kondisi, catatan;
  - Ringkasan + Submit.
- [ ] Setiap scan langsung disimpan, karena sesi berakhir setelah 30 menit tanpa aktivitas. Submit:
  - menjalankan `RadioBulk::verify`;
  - mencatat audit `radio.opname_submitted`;
  - "Laporkan masalah" membuat tiket servis.
- [ ] Tes: alur sesi, izin, submit.

### 6.6 Edit langsung ICT masterlist
- [ ] Rute baru dan `CellEdits::writeRow`:
  - unduh sekali;
  - cek semua sel dan anchor baris;
  - patch;
  - unggah sekali dengan If-Match.
- [ ] Pencatatan: `EditRequest` disimpan sebagai disetujui sendiri, dengan penanda "langsung" (kolom baru lewat
  migration); audit tetap tercatat.
- [ ] Respons `rows` membawa flag "boleh tulis" (aturan K13h).
- [ ] Dialog "Edit baris" berisi kolom sheet; sel rumus dan "File scan" hanya bisa dibaca.
- [ ] Bila ada permintaan menunggu di sel yang sama, edit langsung ditolak dengan pesan.
- [ ] Tes: izin, konflik 412 dan 423, API client ditolak.

### 6.7 Tambahan dari K3 dan K12 yang diambil
- [ ] Audit log:
  - filter Entity dan Date range dengan kalender rentang; `list()` meneruskan `from`, `to` dan `entityType`;
  - baris Browser di Event details.
- [ ] COF scan: hitungan Linked/Unmatched dan filter status di endpoint list; sel statistik, banner, segmented.
- [ ] Butir K12 lain yang diambil pemilik.

### 6.8 Menu dan urutan
- [ ] Seeder menu baru mengikuti pola `ESignMenuSeeder`, dipanggil migration dan `DatabaseSeeder`.
- [ ] Ubah urutan di `RadioMenuSeeder` dalam commit yang sama, lalu hapus cache `rbac_menu_route_key_map`.
- [ ] Bagian `[Unreleased]` di CHANGELOG: baris Deploy dengan daftar migration dan langkah cache.

## Progres

- 2026-10-20: branch `design-refresh` dibuat dari `main` (`673b878`). Kelima perbandingan desain dengan kode selesai
  (baca-saja), dan PLAN.md ini ditulis. Menunggu jawaban K1–K13 sebelum fase 1.
