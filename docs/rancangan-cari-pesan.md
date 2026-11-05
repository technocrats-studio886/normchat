# Rancangan: cari pesan ala Telegram (desktop) dan WhatsApp (HP)

Permintaan user, 5 Nov 2026, dengan gambar 1–3:
- **Masalah sekarang:** pencarian hanya menyaring bubble (pesan lain disembunyikan) dan kata yang dicari tidak ditandai jelas.
- **Gambar 2 (Telegram desktop):** daftar hasil di kolom kiri, "6 messages found", tiap hasil menampilkan foto, nama, tanggal, dan cuplikan dengan kata yang dicari.
- **Gambar 3 (WhatsApp HP):** bilah cari menggantikan header, dengan tombol ↑ ↓, dan kata yang dicari ditandai di dalam bubble.

## Keputusan user

| Hal | Keputusan |
|---|---|
| Cakupan | **Seluruh riwayat grup** yang boleh dilihat user. Batas riwayat anggota baru, pemberitahuan pribadi Normi, dan pesan yang disembunyikan untuk diri sendiri berlaku. Pesan terhapus dan pemberitahuan sistem tidak ikut. |
| Hasil di desktop | **Kolom kiri menggantikan daftar grup** selama pencarian terbuka. Daftar grup kembali saat ditutup. |

## Server

`GET /groups/{group}/messages/search?q=…&before_id=…`
- Minimal 2 karakter. Mencari isi pesan (`ILIKE`), juga nama berkas lampiran, tanpa beda huruf besar/kecil.
- Urutan terbaru dulu, 30 per halaman (`before_id` untuk halaman berikutnya), plus `total`.
- Tiap hasil:
  - `id`
  - `sender_name` ("Kamu" / "Normi AI" / nama)
  - `sender_avatar_url`
  - `sender_type`
  - `created_at`
  - `snippet`: sekitar 90 karakter di sekitar kata pertama yang cocok, dengan penanda `…` di depan/belakang bila dipotong; dibentuk di server, ditandai di browser.

`GET /groups/{group}/messages/around/{message}`
- Potongan feed untuk pesan yang tidak ada di 80 pesan yang dimuat: 30 pesan sebelum dan 30 sesudahnya, dengan bentuk yang sama seperti pesan awal (`MessagePresenter`).
- Aturan yang boleh dilihat sama dengan feed.

## Browser

**Penanda di bubble**
- Semua kemunculan kata ditandai `<mark>` di bubble yang terlihat.
- Penandaan berjalan per simpul teks, sehingga format jawaban Normi (tebal, daftar, tautan) tetap utuh.
- Hasil yang sedang dipilih memakai warna lebih kuat, dan bubble-nya disorot sebentar.
- Pesan tidak lagi disembunyikan.

**HP (< 1024 px), ala WhatsApp**
- Cari membuka bilah di atas, menutupi header chat:
  - ← (tutup)
  - kolom cari
  - "3 dari 12"
  - ↑ (lebih lama) dan ↓ (lebih baru)
- Mengetik (jeda 250 ms) mencari di server, lalu langsung melompat ke hasil terbaru.
- ↑ ↓ berpindah antarhasil, menggulir ke pesannya, dan menandai katanya. Hasil di luar pesan yang dimuat memuat potongan di sekitarnya.

**Desktop (≥ 1024 px), ala Telegram**
- Bilah cari tetap di atas chat (dengan ↑ ↓ dan "3 dari 12").
- Kolom kiri berganti menjadi panel hasil:
  - judul "Cari di grup ini" dengan tombol tutup;
  - "N pesan ditemukan";
  - baris hasil berisi foto, nama, tanggal, dan cuplikan dengan kata ditandai.
- Mengklik hasil melompat ke pesannya. Gulir ke bawah daftar memuat hasil berikutnya.

**Potongan di sekitar pesan lama**
- Feed diganti potongan itu, dengan pita "Menampilkan pesan lama" dan tombol **Ke pesan terbaru** yang memuat ulang 80 pesan terakhir.
- Pesan baru yang masuk selama itu menambah hitungan di tombol ⌄, tanpa menyisip ke potongan lama.

**Tutup pencarian:** penanda hilang, kolom kiri kembali ke daftar grup, dan feed kembali ke pesan terbaru bila sedang menampilkan potongan lama.

## Uji

- **Feature:**
  - hasil hanya pesan yang boleh dilihat (batas riwayat anggota baru, pemberitahuan pribadi, disembunyikan untuk diri sendiri, terhapus, sistem tidak ikut);
  - cuplikan dan urutan;
  - halaman berikutnya;
  - `around` memuat pesan target dan tetangganya, dan menolak pesan di luar batas riwayat.
- **Browser:**
  - penanda `<mark>` di bubble biasa dan jawaban Normi (format tetap);
  - HP ↑ ↓ dan "x dari y";
  - desktop panel kiri dan klik hasil;
  - lompat ke pesan lama di luar 80 lalu Ke pesan terbaru;
  - tutup memulihkan semuanya.
