# Rancangan: pratinjau tautan (tab Tautan + bubble chat)

Permintaan user, 5 Nov 2026, dengan gambar WhatsApp "Semua media › Tautan". Tab Tautan menampilkan kartu pratinjau beserta potongan chat terkait, bukan hanya URL.

## Keputusan user

| Hal | Keputusan |
|---|---|
| Pratinjau | **Diambil otomatis** dari situsnya (og:title, og:site_name, og:image), di antrean, dengan batas waktu dan perlindungan alamat internal. Gagal dibaca: ikon tautan + URL + domain. |
| Gambar | **Langsung dari situs asal** (tidak disalin ke S3). Gambar yang gagal dimuat diganti ikon tautan. |
| Ketuk | **Seperti WhatsApp.** Kartu membuka tautan di tab baru. Baris di bawahnya (URL, potongan chat, ›) melompat ke pesan di chat dan menandainya. |
| Cakupan | **Tab Tautan dan bubble chat.** Pesan bertautan menampilkan kartu pratinjau di dalam bubble. |

## Data

Tabel `link_previews` (satu baris per URL, dipakai semua grup):
- `url_hash` (unik)
- `url`
- `title`
- `site_name`
- `description` (≤ 300)
- `image_url`
- `status` (pending | ok | failed)
- `fetched_at`
- timestamps

**Pengambilan** (`FetchLinkPreviewJob`, antrean):
- dipicu saat pesan bertautan terkirim (maks. 3 tautan pertama per pesan) dan saat tab Tautan menemukan URL tanpa data;
- **Keamanan:**
  - hanya http/https;
  - host di-resolve dan ditolak bila alamat privat, loopback, link-local, atau metadata (SSRF);
  - redirect maks. 3, dicek ulang setiap kali;
  - batas waktu 6 detik;
  - hanya membaca 512 KB pertama HTML;
  - User-Agent generik "NormiChatBot/1.0 (+https://normi.chat)".
- **Isi:**
  - og:title, lalu twitter:title, lalu `<title>`;
  - og:site_name, lalu domain;
  - og:description;
  - og:image (dijadikan URL absolut, hanya https).
- Gagal: `failed`, dicoba lagi paling cepat 24 jam kemudian. Berhasil: diperbarui lagi setelah 7 hari bila dibuka.

**CSP:** `img-src` ditambah `https:` agar thumbnail situs asal bisa tampil. Skrip dan koneksi lain tidak berubah.

## Tab Tautan (Media, tautan, dan dok)

Dikelompokkan per waktu, mengikuti zona waktu user:
- **TERBARU** (7 hari terakhir)
- **MINGGU LALU** (8–14 hari)
- **BULAN LALU** (sampai 30 hari)
- lalu per bulan: "OKTOBER 2026"

Satu entri per tautan (pesan dengan 2 tautan menghasilkan 2 entri), terbaru dulu. Hanya pesan yang boleh dilihat user (batas riwayat anggota baru, pesan disembunyikan, terhapus, sistem tidak ikut).

Setiap entri berisi:
- **Kartu**:
  - thumbnail 88 px (gambar situs, atau ikon tautan bila tidak ada atau gagal dimuat);
  - judul maks. 2 baris (tanpa judul: URL);
  - domain.

  Ketuk kartu membuka tautan.
- **Baris di bawahnya**: URL dipotong satu baris, lalu potongan teks chat selain URL (abu), lalu ›. Ketuk baris ini menutup Info grup dan melompat ke pesan di chat, yang lalu disorot.
  - Pesan yang dimuat (80 terakhir) langsung digulir ke sana.
  - Pesan lebih lama memakai pemuat "pesan di sekitarnya" (`GET /groups/{group}/messages/around/{message}`) dari rancangan cari pesan (docs/rancangan-cari-pesan.md, dikerjakan sesi root-99).
  - Sebelum endpoint itu ada, chat dibuka dengan `?message={id}`, dan server memuat 80 pesan yang berakhir di sekitar pesan itu. Mekanisme ini dipakai bersama dengan root-99.

## Bubble chat

Pesan dengan tautan menampilkan kartu pratinjau di dalam bubble, di atas teks: gambar lebar (bila ada), judul, deskripsi 2 baris, dan domain. Ketuk kartu membuka tautan.
- Pratinjau yang belum siap: kartu belum tampil. Setelah job selesai, event realtime `link.preview.ready` (kanal grup) memasang kartunya tanpa memuat ulang.
- Hanya tautan pertama di pesan yang diberi kartu (seperti WhatsApp).
- Jawaban Normi AI tidak diberi kartu (tautan sumber Deep Research tetap teks).

## Uji

**Feature:**
- penguraian og/twitter/title;
- tolak alamat privat dan redirect ke alamat privat;
- batas ukuran;
- `failed` + coba lagi 24 jam;
- tab Tautan dikelompokkan dan memakai batas riwayat;
- presenter memuat `link_preview`;
- job dipicu saat kirim.

**Browser:**
- kartu dengan thumbnail dan tanpa thumbnail;
- ketuk kartu membuka tab baru;
- ketuk baris melompat dan menyorot pesan;
- kartu muncul di bubble setelah event;
- tidak ada error CSP.
