# Rancangan: perbaikan UX, superadmin baru, pengumuman, bonus buat grup

Branch: `stagging-v1` · Tanggal: 1 Nov 2026 · Status: disetujui 1 Nov 2026; selesai dan lulus uji 4 Nov 2026 (menunggu deploy).

## 0. Ringkasan keputusan

| # | Pertanyaan | Keputusan |
|---|---|---|
| U1 | Sidebar grup di halaman superadmin | Dihapus; superadmin memakai navigasi admin sendiri |
| U2 | "Klik Akun, tidak ada opsi setting" (user biasa, HP) | Halaman Akun mendapat bagian **Pengaturan** |
| U3 | Isi Pengaturan | Tampilan (Tema), Zona waktu, Notifikasi, Perangkat & sesi |
| U4 | Letak avatar pengirim di ruang chat | Gaya WhatsApp: sejajar pesan **pertama** rangkaian, bersama nama |
| A1 | Arah tampilan superadmin | Konsol ringkas: sidebar admin di kiri (desktop), menu admin di HP |
| P1 | Bentuk pengumuman | Superadmin memilih per pengumuman: popup atau banner |
| P2 | Setelah ditutup | Muncul lagi setiap kali user masuk (login) sampai masa tayang habis |
| B1 | Bonus buat grup | Setiap grup baru selama periode aktif; nilai & periode diatur superadmin |

## 1. Temuan uji yang menjadi dasar

- **Superadmin melihat UI grup.** Layout `layouts/app` selalu memasang `<x-desktop-sidebar />`, jadi semua halaman
  superadmin di desktop menampilkan "Cari grup", "+ Buat grup", kolom kode grup + "Gabung". Halaman Akun superadmin
  juga memuat Penyimpanan & Riwayat Pembayaran yang tidak relevan.
- **Tab admin di HP terpotong.** `.nc-admin-tabs` bisa digeser (scrollWidth 667 px di layar 358 px) tetapi tanpa penanda:
  Biaya OpenAI, Log audit, Pengaturan tidak terlihat.
- **Halaman Akun user di HP** berfungsi (semua baris membuka halamannya), tetapi tidak ada bagian Pengaturan seperti di
  WhatsApp/Telegram.
- **Avatar di chat** sekarang di pesan terakhir rangkaian (`is-run-end`), sama dengan Telegram.

- **Info grup error untuk anggota (31 Okt 04.48–04.50).** Log produksi: `Route [groups.leave] not defined` saat
  anggota membuka pengaturan grup. Kode tombol "Keluar grup" sudah terbaca produksi lewat bind-mount sebelum deploy,
  sementara cache rute container masih lama. Sudah pulih sejak deploy 1 Nov 11.57 (cache rute memuat `groups.leave`,
  tidak ada error lagi). Pengaman tambahan: baris kode grup di pengaturan tidak membuat tautan bila `share_id` kosong.

### Riset letak avatar

- **Telegram** (kode sumber Android, `ChatActivity.java`): bila sel pesan `drawPinnedBottom()` (pesan berikutnya dari
  pengirim yang sama), avatar sel itu disembunyikan; avatar hanya digambar di pesan **terakhir** rangkaian dan menempel
  di bawah saat digulir.
- **WhatsApp**: foto profil anggota ditampilkan di samping pesan masuk di grup, bersama nama pengirim di awal rangkaian
  (laporan WABetaInfo/Android Police tentang fitur foto profil di grup). Ini gaya yang dipilih.

## 2. Ruang chat: avatar gaya WhatsApp (U4)

- Pesan **pertama** rangkaian (`is-run-start`): avatar di kiri, sejajar bagian atas gelembung, nama pengirim di dalam
  gelembung (seperti sekarang).
- Pesan berikutnya dari pengirim yang sama: tanpa avatar dan tanpa nama, menjorok sejajar gelembung pertama, jarak
  antar-gelembung rapat.
- Rangkaian putus bila pengirim berganti, ada pemisah hari, atau pemberitahuan sistem (aturan `sameRun` yang ada).
- Pesan sendiri (kanan) tetap tanpa avatar. Jawaban Normi memakai logo Normi di posisi yang sama.
- Berlaku desktop & HP, semua tema; mode edit, pilih pesan, dan menu aksi tetap bekerja.

## 3. Akun > Pengaturan (U2, U3)

Halaman Akun disusun ulang:

```
[Kartu profil: foto, nama, @username]           → Profil
Pengaturan
  Tampilan            Ikuti sistem · Navy        → lembar Tema (yang sudah ada)
  Zona waktu          Otomatis (WIB)             → halaman Zona waktu
  Notifikasi          Saat disebut: aktif        → halaman Notifikasi
  Perangkat & sesi    2 perangkat                → halaman Perangkat & sesi
Data & riwayat
  Penyimpanan, Riwayat masuk, Riwayat pembayaran
Bantuan
  Laporkan masalah
Keluar
```

### 3.1 Zona waktu
- Pilihan **Otomatis** (bawaan, ikut browser seperti sekarang) atau **Pilih manual**: WIB, WITA, WIT di atas, lalu
  daftar semua zona dengan pencarian.
- Kolom baru `users.timezone_mode` (`auto` | `manual`). Saat `manual`, skrip browser tidak lagi menimpa zona waktu.
- Jam pesan, riwayat, ekspor, dan cadangan ikut zona ini (sudah lewat `UserTime`).

### 3.2 Notifikasi
- **Saat disebut (@nama)**: notifikasi "Kamu disebut" di aplikasi (toast yang ada). Bawaan aktif.
- **Bunyi pesan masuk**: bunyi pendek untuk pesan baru di grup yang sedang dibuka saat aplikasi di latar belakang, dan
  saat disebut di grup mana pun (keputusan 1 Nov: tanpa berlangganan semua grup). Bawaan nonaktif.
- Disimpan per akun (`users.notify_mentions`, `users.notify_sound`), berlaku di semua perangkat.
- Push notification ke HP saat aplikasi tertutup **tidak** termasuk (pekerjaan terpisah); halaman menuliskannya terang.

### 3.3 Perangkat & sesi
- Tabel baru `user_sessions`: `user_id`, hash id sesi, perangkat (dari `LoginDevice`), IP, lokasi bila ada,
  `last_active_at`, `created_at`. Diisi saat masuk, diperbarui middleware paling sering tiap 5 menit.
- Daftar: perangkat, lokasi, terakhir aktif, tanda **Perangkat ini**.
- Tombol **Keluar dari semua perangkat lain** (dengan konfirmasi): menaikkan `users.session_version`; sesi lain
  diakhiri oleh middleware pada request berikutnya (pola yang sama dengan `EndBlockedSession`). Bisa juga keluarkan
  satu perangkat.
- Sesi yang berakhir karena idle 5 hari tetap seperti sekarang.

## 4. Superadmin baru (U1, A1)

### 4.1 Kerangka
- Layout khusus `layouts/admin`: superadmin **tidak pernah** memakai sidebar grup atau navigasi bawah user.
- **Desktop**: sidebar admin kiri (lebar tetap): logo + "Admin", daftar menu, akun superadmin di bawah. Konten lebar
  penuh (maks. ±1200 px), judul halaman + tindakan utama di kanan atas.
- **HP**: bilah atas berisi judul halaman + tombol **Menu** yang membuka laci berisi seluruh menu admin; tidak ada tab
  yang terpotong.
- Menu: Ringkasan · Grup · User · Keuangan & AI · Biaya OpenAI · **Pengumuman** · **Promo** · Log audit · Pengaturan
  (harga & model) · Akun.
- Peringatan (anggaran/saldo OpenAI, kurs basi) tampil sebagai satu baris ringkas di atas konten dengan jumlah, bukan
  kartu besar di setiap halaman; detailnya di halaman Biaya OpenAI.

### 4.2 Gaya (menghindari kesan AI slop)
- Tidak ada grid kartu seragam. Angka utama dalam satu baris ringkas (label kecil, angka besar, perubahan kecil).
- Tabel padat: header tipis lengket, angka rata kanan dengan `tabular-nums`, baris bergaris tipis, aksi di ujung baris.
- Satu aksen (warna utama tema), merah hanya untuk hal yang benar-benar butuh tindakan.
- Teks penjelasan pendek dan hanya di tempat yang perlu; tanpa paragraf penjelasan di setiap bagian.
- Status dengan label kecil berwarna lembut (Aktif, Beku, Diblokir, Dijadwalkan, Tayang, Selesai).

### 4.3 Isi per halaman
- **Ringkasan**: baris angka (user, grup aktif, pendapatan 30 hari, biaya OpenAI 30 hari, margin) · "Perlu perhatian"
  (peringatan terbuka, kurs >24 jam, grup beku, permintaan ditolak hari ini, rekonsiliasi selisih) · aktivitas terbaru
  (pembayaran & tindakan admin).
- **Grup / User**: tabel dengan cari + saring di kepala tabel; detail di halaman sendiri dengan aksi di kanan atas.
- **Keuangan & AI**: saring tanggal di atas; bagian Pendapatan, Pemakaian per model, Konteks Normi, Biaya tak ditagih,
  Ditolak, Grup terbesar, Transaksi — tiap bagian satu tabel, dengan navigasi lompat di atas halaman.
- **Biaya OpenAI, Log audit, Pengaturan**: isi sama, tata letak mengikuti kerangka baru.
- **Akun superadmin**: Profil, Pengaturan (Tampilan, Zona waktu, Perangkat & sesi), Keluar. Tanpa Penyimpanan,
  Riwayat Pembayaran, Notifikasi.
- Audit kontras di 4 tema dan uji tampilan HP untuk semua halaman admin.

## 5. Pengumuman (P1, P2)

### 5.1 Data
Tabel `announcements`: `title` (maks. 80), `body` (teks biasa, maks. 1.000, baris baru dipertahankan, tautan
https otomatis jadi link), `display` (`popup` | `banner`), `tone` (`info` | `penting`), `cta_label` + `cta_url`
(opsional, hanya https atau tautan dalam aplikasi), `starts_at`, `ends_at` (opsional), `is_active`, `created_by`,
`updated_by`, timestamps. Setiap buat/ubah/hentikan tercatat di log audit.

### 5.2 Halaman superadmin "Pengumuman"
- Daftar dengan status: Draf (nonaktif), Dijadwalkan, Tayang, Selesai.
- Form buat/ubah dengan pratinjau langsung popup/banner, pilihan tampil, nada, tombol tautan, jadwal (WIB).
- Aksi: Tayangkan sekarang, Hentikan, Duplikat, Hapus.

### 5.3 Tampil ke user
- Untuk semua user yang masuk (bukan superadmin), di semua halaman aplikasi (beranda, chat, pengaturan, akun).
  Tidak tampil di situs marketing dan halaman login.
- **Popup**: dialog di tengah, judul + isi + tombol tautan (opsional) + "Tutup". Bila ada beberapa, tampil satu per
  satu dari yang terbaru.
- **Banner**: strip di atas konten (di bawah kepala halaman), nada info/penting, tombol tutup. Maksimal satu banner
  terlihat; berikutnya muncul setelah yang pertama ditutup.
- **Ditutup → muncul lagi tiap login (P2)**: penutupan dicatat di sesi, jadi tidak muncul lagi selama sesi itu dan
  muncul lagi setelah user masuk ulang, sampai `ends_at` lewat atau dihentikan.
- Pengumuman baru muncul pada halaman berikutnya yang dibuka (tanpa realtime).

## 6. Bonus buat grup (B1)

### 6.1 Pengaturan (halaman superadmin "Promo")
- **Bonus buat grup**: nilai NC (bawaan 20), mulai, selesai (WIB), saklar aktif. Ikut versi harga (`pricing_versions`)
  dan log audit.
- Status di halaman: Tidak aktif / Dijadwalkan / Berjalan sampai … / Selesai, plus jumlah grup & total NC bonus yang
  sudah diberikan.

### 6.2 Aturan
- Berlaku untuk **setiap grup baru** yang checkout-nya dimulai saat promo berjalan. Nilai bonus dikunci di data
  pembayaran saat checkout, jadi user yang membayar sedikit setelah promo selesai tetap dapat yang dijanjikan.
- Saat pembayaran lunas: saldo grup = modal (250) + bonus (20). Ledger mencatat dua baris: `deposit` 250 dan
  **`bonus` 20**; riwayat patungan grup menampilkan "Bonus buat grup +20,00 NK".
- Form buat grup menampilkan "Bonus +20 NK sampai 30 Nov 2026" dan total saldo awal; harga tetap Rp30.000.
- Bonus **bukan pendapatan**: Keuangan menampilkan "NC bonus diberikan" terpisah, margin dihitung dari NC berbayar.
- Ambang buka Advanced (500 NC) memakai saldo grup, jadi bonus ikut terhitung.
- Pembayaran gagal/kedaluwarsa: tidak ada bonus.

## 7. Cara kerja

Diusulkan pindah ke worktree terpisah, tetapi **keputusan user (1 Nov): tetap langsung di `/root/normchat`** di branch
`stagging-v1`. Konsekuensi yang diterima: perubahan yang memakai rute/kolom baru bisa error di produksi sampai deploy;
deploy dijalankan segera setelah semua lulus uji. Branch tidak dipindah di folder ini.

## 8. Urutan pengerjaan dan uji

1. Migrasi: `users.timezone_mode`, `notify_mentions`, `notify_sound`, `session_version`; tabel `user_sessions`,
   `announcements`; jenis ledger `bonus`; pengaturan promo di `app_settings`.
2. Ruang chat: avatar gaya WhatsApp. Uji: E2E chat, tangkapan desktop & HP, audit kontras.
3. Akun > Pengaturan (4 bagian). Uji: zona manual tidak ditimpa browser, notifikasi mati → tanpa toast,
   keluar dari perangkat lain mengakhiri sesi lain.
4. Layout admin + menu HP + perombakan 9 halaman. Uji: superadmin tidak melihat UI grup di mana pun, semua halaman
   200 di desktop & HP tanpa geser horizontal, audit kontras.
5. Pengumuman. Uji: jadwal, popup vs banner, tutup → hilang dalam sesi → muncul lagi setelah login ulang,
   superadmin tidak melihatnya, teks di-escape.
6. Bonus buat grup. Uji: dalam/di luar periode, nilai dikunci saat checkout, ledger `bonus`, Keuangan terpisah,
   form buat grup.
7. Seluruh test, E2E, audit kontras, uji alur di staging pribadi, lalu perintah deploy.
