# Rancangan: Info grup (gabungan gaya Telegram dan WhatsApp)

Ditulis 4 Nov 2026 dari permintaan user dan 6 gambar acuan: Telegram desktop (info grup, menu More, Edit group) serta WhatsApp HP (info grup, Tautan grup, lembar bagikan).

## Tujuan

Mengetuk bagian atas chat (foto + nama grup) membuka **Info grup** yang rapi.

- **Desktop:** jendela melayang di atas chat, seperti Telegram.
- **HP:** halaman penuh, seperti WhatsApp.

Isinya tetap fitur yang sudah ada. Fitur baru hanya dua: **ubah nama grup** di tempat yang mudah dijangkau dan **foto grup**.

## Inventaris fitur yang dipindahkan

Semua fitur halaman Pengaturan grup (`settings/show.blade.php`) sekarang dan menu titik tiga di chat tetap ada. Tempat barunya:

| Fitur | Siapa | Tempat baru |
|---|---|---|
| Saldo grup + Isi Normkredit | semua lihat, tombol untuk yang boleh isi | Kartu saldo tepat di bawah foto, nama, deskripsi |
| Sisa batas harianku | anggota berbatas | Kecil di kartu saldo |
| Grup gratis, slot terpakai | semua | Kecil di kartu saldo |
| Batas pemakaian harian per anggota | pemilik | **Pengaturan** |
| Nama, deskripsi, kata sandi | pemilik/admin | **Pengaturan → Edit grup** |
| Foto grup (baru) | pemilik/admin | **Pengaturan → Edit grup**, juga ketuk foto besar |
| Kode grup, salin, bagikan | semua | Ikon **Bagikan** membuka lembar "Tautan grup" |
| Anggota, jadikan admin, keluarkan | semua lihat, aksi untuk pengelola | Daftar **Anggota**, aksi lewat menu per anggota |
| Media, tautan, berkas | semua | Baris "Media, tautan, dan berkas" lalu panel bertab |
| Riwayat patungan | semua | Kartu saldo ("Riwayat") dan Pengaturan |
| Unduh riwayat chat + cadangan | sesuai izin sekarang | Pengaturan + menu Lainnya |
| Normi AI (model & kepribadian) | ubah: yang berizin, lihat: semua | Pengaturan |
| Cari pesan | semua | Tombol **Cari**: menutup info dan membuka bilah cari di chat |
| Bersihkan chat di perangkat ini | semua | Menu Lainnya |
| Keluar grup / Hapus grup | anggota / pemilik | Paling bawah Pengaturan (merah) |

Halaman lain (Normi AI, Riwayat & cadangan, Riwayat patungan) tidak berubah. Tombol kembalinya mengarah ke Info grup.

## Keputusan user (4 Nov 2026)

| Hal | Keputusan |
|---|---|
| Tombol bulat HP | Cari · Bagikan · Normi AI · Lainnya. Isi Normkredit cukup lewat tombol di kartu saldo. |
| Bisukan grup | Tidak ditambahkan sekarang. |
| Desktop | Dua-duanya. Mengetuk header membuka **jendela melayang**. **Ikon buka/tutup** di kanan header membuka **panel samping kanan** berisi Info grup yang sama, seperti sidebar kiri. Status panel diingat per perangkat. |
| Foto di /join | Ditampilkan. Foto bisa dibuka dengan kode grup. |
| Penyimpanan foto | Disk `normchat_avatars` (S3 produksi, folder `avatars/groups/{id}/`). |

## Desktop (lebar ≥ 1024 px): jendela melayang ala Telegram + panel kanan

**Panel kanan:** ikon panel di header chat (di antara Cari dan ⋮) membuka kolom 360 px di kanan. Chat menyempit, persis seperti sidebar kiri. Isinya tampilan utama Info grup tanpa tombol X. Kelola, Media, dan Edit grup bergeser di dalam panel. Mengetuk header saat panel terbuka tidak membuka jendela; panelnya yang disorot.

Mengetuk header chat **tidak berpindah halaman**. Jendela 420 px muncul di tengah atas chat, latar redup, tutup dengan X, Esc, atau klik di luar. Isinya dimuat dari `GET /groups/{id}/settings?panel=1`, yaitu potongan HTML yang sama dengan halaman HP. Jadi hanya ada satu sumber.

```
┌──────────────────────────────────────┐
│                                   ✕  │
│              ( FOTO )                │
│            Fasil Dicoding            │
│              3 anggota               │
│     Deskripsi grup (bila ada)        │
│ ┌──────────────────────────────────┐ │
│ │ Saldo grup                       │ │
│ │ 245,50 NK        [Isi Normkredit]│ │
│ │ Sisa batas harianmu 10 NK · Riwayat│
│ └──────────────────────────────────┘ │
│ [ Cari ] [Bagikan] [Kelola] [Lainnya]│
├──────────────────────────────────────┤
│ ▣  33 media · 4 tautan · 2 berkas  › │
├──────────────────────────────────────┤
│ 👥 3 ANGGOTA                     +👤 │
│ (MS) Memes            Pemilik        │
│ (SA) Sari             Admin       ⋮  │
│ (BU) Budi                         ⋮  │
└──────────────────────────────────────┘
```

- **Kelola** (khusus pemilik/admin; anggota biasa melihat **Pengaturan**) menggeser isi jendela ke tampilan "Edit grup" seperti gambar 3. Ada tombol ‹ untuk kembali.
- **Lainnya** membuka menu kecil seperti gambar 2:
  - Unduh riwayat chat
  - Cadangan (moderator)
  - Normi AI
  - Riwayat patungan
  - Bersihkan chat di perangkat ini
- **+👤** di judul Anggota membuka lembar **Tautan grup**.
- **⋮** per anggota (untuk pengelola) membuka menu Jadikan admin/anggota dan Keluarkan.
- Media membuka panel bertab Media | Tautan | Berkas di dalam jendela, dengan tombol ‹.

**Edit grup (desktop dan HP sama):**

```
‹  Edit grup
( 📷 )  Nama grup  [Fasil Dicoding     ]
        Deskripsi  [opsional           ]
────────────────────────────────────────
🔒 Kata sandi grup            Tidak ada ›
⏱ Batas harian per anggota  Tanpa batas ›   (pemilik)
✦ Normi AI                       Astra ›
⇩ Riwayat & cadangan                    ›
🧾 Riwayat patungan                     ›
────────────────────────────────────────
Hapus grup / Keluar grup (merah)
                      [Batal] [Simpan]
```

- Foto, nama, deskripsi, dan kata sandi disimpan sekaligus lewat satu form (`settings.profile.update`, sekarang multipart).
- Batas harian punya form sendiri (`groups.credits.limit`) yang terbuka di baris itu.

Setelah simpan, halaman chat dimuat ulang dan jendela Info grup terbuka lagi di tampilan yang sama (flash `group_info`), supaya pesan "Tersimpan" terlihat di tempatnya.

## HP (< 1024 px): halaman penuh ala WhatsApp

Mengetuk header chat membuka `settings.show` seperti sekarang, dengan tata letak baru:

```
←                              [⤴] [⋮]
              (  FOTO  )
          LOLOS SELEKSI AUDISI
          Grup · 130 anggota
     Deskripsi / "Tambah deskripsi grup"
┌────────────────────────────────────┐
│ Saldo grup   245,50 NK  [Isi NK]   │
└────────────────────────────────────┘
   (Cari)  (Bagikan) (Normi AI) (Lainnya)
────────────────────────────────────────
 Media, tautan, dan berkas    33   ›
────────────────────────────────────────
      Anggota        Pengaturan
      ═══════
 130 anggota                        🔍
 (+) Tambah anggota
 (A) Anda                    Pemilik
 (G) grace                  Admin grup
```

- **⤴** di kanan atas membuka lembar Tautan grup.
- **⋮** membuka menu Lainnya yang sama dengan desktop.
- Tab **Pengaturan** berisi daftar yang sama dengan "Edit grup" di atas. Untuk anggota biasa, baris yang tidak boleh diubah tampil sebagai teks.
- 🔍 di tab Anggota menyaring daftar anggota (muncul bila anggota > 8).
- "Tambah deskripsi grup" hanya untuk pengelola dan langsung membuka Edit grup.

## Lembar "Tautan grup" (gambar 5–6)

Dipakai oleh ikon Bagikan, +👤, dan kartu ajakan grup gratis. Desktop berupa jendela kecil, HP berupa lembar dari bawah.

```
( FOTO )  Fasil Dicoding
          https://normi.chat/join/ABC123
──────────────────────────────────
⧉  Salin tautan
#  Salin kode            ABC123
⤴  Bagikan tautan
▦  Kode QR
```

- **Bagikan tautan** memakai `navigator.share` sehingga muncul pilihan aplikasi bawaan HP (WhatsApp, Telegram, Gmail, dan lainnya seperti gambar 6). Bila browser tidak mendukung (umumnya desktop Firefox/Chrome Linux), tautan disalin dan muncul toast "Tautan disalin".
- **Kode QR** menampilkan QR tautan undangan, dibuat di browser (pustaka npm `qrcode`, MIT), lengkap dengan tombol "Simpan gambar" (PNG).
- Teks bagikan: "Gabung ke grup {nama} di Normi Chat" + tautan. Bila grup bersandi, sandinya tidak ikut. Lembar menampilkan catatan "Grup ini memakai kata sandi. Beri tahu sandinya ke temanmu."

## Foto grup (baru)

- Kolom `groups.avatar_path` (nullable) dan `groups.avatar_version` (int, untuk memecah cache).
- Unggah JPG/PNG/WebP maksimal 5 MB. Server memotong jadi persegi 512 px WebP dengan GD, lalu menyimpannya di disk `normchat` (S3 di produksi) pada `groups/{id}/avatar-{versi}.webp`. Berkas lama dihapus.
- Ada pilihan **Hapus foto**, yang kembali ke inisial.
- Disajikan lewat `GET /groups/{group}/avatar?v={versi}` untuk anggota (policy `view`) dan `GET /join/{kode}/avatar?v={versi}` untuk calon anggota yang memegang kode grup. Header cache privat 30 hari.
- Tampil di header chat, daftar grup (HP dan sidebar desktop), Info grup, lembar Tautan grup, dan halaman gabung `/join/{kode}`.
- Dicatat AuditLog `settings.update_group_profile` dengan `avatar_updated`/`avatar_removed`.

## Cari pesan

Tombol Cari menutup Info grup (desktop) atau kembali ke chat (HP lewat `chat.show?search=1`), lalu membuka bilah "Cari pesan" yang sudah ada. Bilah itu menyaring pesan grup. Menu titik tiga di header chat tetap ada, dan "Pengaturan grup" di dalamnya diganti "Info grup".

## Tidak berubah

- Izin (policy `editGroupProfile`, `manageMembers`, `manageBilling`, `manageAiPersona`)
- Rute POST
- Validasi
- Halaman Normi AI, Riwayat & cadangan, Riwayat patungan

Grup gratis, slot, dan kartu ajakan di chat tetap berjalan. Tombol "Ajak teman" di kartu ajakan kini membuka lembar Tautan grup.

## Uji

1. **Feature test:**
   - unggah/hapus foto (izin, ukuran, tipe, berkas lama terhapus)
   - rute avatar hanya untuk anggota
   - `?panel=1` mengembalikan potongan tanpa layout
   - flash `group_info` membuka ulang jendela
   - anggota biasa tidak melihat form edit
2. **Browser** (desktop 1280, HP 390/360):
   - ketuk header → jendela/halaman
   - Kelola → Edit grup → simpan nama + foto → header chat berubah
   - lembar Tautan grup: salin tautan, salin kode, QR tampil, Bagikan memanggil `navigator.share` (di-stub)
   - Cari membuka bilah cari
   - tanpa geser horizontal, tanpa error JS/CSP
3. Audit kontras 4 tema untuk halaman Info grup, Edit grup, lembar Tautan grup, dan QR.
4. Suite lengkap.

## Alur gabung lewat tautan atau kode (permintaan user 4 Nov 2026)

1. Pemilik (atau anggota) membagikan tautan `/join/{kode}` atau kodenya lewat lembar Tautan grup.
2. **Profil grup.** Calon anggota membuka tautan dan melihat foto, nama, deskripsi, jumlah anggota, serta tombol **Bergabung**.
3. **Kata sandi.** Setelah Bergabung diketuk, calon anggota diminta kata sandi grup (`POST /join/{kode}/sandi`). Sandi benar disimpan di sesi sebagai `join_verified.{group_id}` selama 30 menit. Grup tanpa sandi melewati langkah ini.
4. **Tagihan patungan.** Muncul tagihan (paket patungan, minimal 1 paket) dengan tombol Bayar QRIS. Keanggotaan baru aktif setelah pembayaran berhasil (`PaymentFulfillment`). Sebelum itu chat tetap tertutup, walau sandinya benar.
5. Grup gratis yang masih punya slot anggota gratis: langkah 4 diganti **Masuk gratis** tanpa tagihan (keputusan user: tetap gratis). Anggota ke-3 dan seterusnya membayar.

Server memeriksa ulang di setiap langkah:
- `POST /join/{kode}` (bayar/masuk gratis) ditolak tanpa `join_verified` yang masih berlaku bila grup bersandi, jadi langkah sandi tidak bisa dilompati dengan mengirim form langsung.
- Grup harus `active`.
- Anggota aktif langsung diarahkan ke chat.

## Catatan implementasi (4 Nov 2026)

- **Isi panel:** `resources/views/groups/info/panel.blade.php`, datanya dari `App\Services\Groups\GroupInfo`.
- **Lembar Tautan grup:** `groups/info/share-sheet.blade.php`, dengan skrip `resources/js/ui/share-sheet.js` (QR dari npm `qrcode` 1.5.4, MIT, dimuat saat dibutuhkan).
- **Perilaku jendela dan panel:** `resources/js/ui/group-info.js`.
  - Panel kanan memakai `--nc-info-w`. Kelas `nc-info-open` di `<html>` dipasang oleh skrip head dari `localStorage['nc-info-side']`.
  - Esc berlapis. Menu, lembar, dan dialog memanggil `preventDefault`, sehingga jendela Info grup baru tertutup pada Esc berikutnya.
- **Foto grup:** `App\Services\Groups\GroupAvatars`, disk `normchat_avatars`, path `groups/{id}-{acak}.webp`, 512 px.
- **Logo:** `components/brand-mark` memakai `logo-mark-white.svg` di mode gelap. Halaman login tamu selalu terang.
- **Test:** `GroupInfoTest`, plus `FreeGroupsTest` untuk alur gabung bertahap dan konsistensi harga.
- **Harness:** `uji-info.mjs`.

## Revisi 2 (4 Nov 2026, permintaan user dari gambar WhatsApp)

- **Edit di bagian atas.** Foto, nama, dan deskripsi diubah langsung di profil atas. Mengetuknya (pengelola saja) mengganti profil dengan form edit: foto + kamera, nama, deskripsi, Batal/Simpan. Pengaturan tidak lagi berisi form edit.
- **Tombol di bawah saldo:** Cari · Bagikan · Lainnya, sama di HP dan desktop. Normi AI pindah ke menu Lainnya dan tab Pengaturan. Tombol isi saldo bertuliskan **Tambah**.
- **Tab Anggota | Media | Pengaturan**, di HP dan desktop (jendela dan panel kanan). Isi tab tampil langsung di bawah tab.
  - **Media:** baris "Media, tautan, dan dok · N ›" membuka tampilan lengkap Media | Tautan | Berkas. Di bawahnya deretan gambar yang digeser kiri-kanan sampai gambar pertama/terakhir. Mengetuk gambar membuka penampil layar penuh yang bisa digeser (sentuh, tombol ‹ ›, panah keyboard), dengan penanda "3 / 12".
  - **Pengaturan:** kata sandi (form sendiri), batas harian, Normi AI, riwayat & cadangan, riwayat patungan, slot grup gratis, Keluar/Hapus grup.
- Kata sandi disimpan lewat `settings.profile.update` dengan field parsial. Nama dan deskripsi hanya diubah bila dikirim.
