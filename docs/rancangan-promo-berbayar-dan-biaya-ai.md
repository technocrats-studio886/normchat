# Promo berbayar dan audit biaya AI — 17 November 2026

Keputusan pemilik produk dalam percakapan ini:
- Semua pembuatan grup baru wajib dibayar; login tidak memberikan saldo/grup otomatis.
- Harga dasar tetap Rp30.000 untuk 250 NC (mengikuti pengaturan harga).
- Bonus awal +50 NC untuk setiap grup yang dibayar selama promo. Pilihan bonus nominal atau persen.
- Dua teman pertama di setiap grup baru bergabung gratis; slot tidak kembali setelah keluar.
- Promo awal nonaktif. Periode diatur pemilik melalui superadmin setelah perubahan diuji.
- Grup, saldo, dan hak anggota gratis yang sudah ada dipertahankan.
- Perbaikan AI berfokus pada pemborosan teknis; pilihan model, kualitas, konteks, dan batas jawaban tidak diturunkan.
- Pemilik meminta implementasi langsung di /root/normchat, melalui Docker.

## Pembayaran dan pemasaran

Promo dikunci pada checkout, lalu base NC dan bonus dicatat terpisah setelah nominal pembayaran diverifikasi.
Webhook berulang tidak menggandakan bonus. Checkout yang dimulai dalam periode promo tetap mendapat tawarannya
saat lunas setelah periode berakhir, mengikuti pola penawaran pembayaran sebelumnya. Top-up dan patungan tidak
mendapat bonus promo pembuatan grup. Biaya QRIS tetap ditampilkan terpisah di Midtrans.

Superadmin mengatur saklar, nominal/persen, nilai bonus, mulai/akhir (WITA), dan slot anggota gratis untuk grup
berikutnya. Tampilan login, marketing, dan form pembuatan grup membaca penawaran dari sumber yang sama.
Grup berbayar yang punya slot anggota gratis tidak diberi label grup gratis.

## Audit awal (query agregat, tanpa isi chat, 17 November 2026)

Data lokal yang tersedia sejak 22 Oktober: 280 catatan API. Distribusi biaya internal:

| Jenis | Panggilan | USD | Persen |
|---|---:|---:|---:|
| Deep Research | 14 | 1,033142 | 35,53% |
| Canvas | 9 | 0,925072 | 31,81% |
| Chat | 89 | 0,615901 | 21,18% |
| Gambar | 16 | 0,270996 | 9,32% |
| File | 4 | 0,057464 | 1,98% |
| Guided Learning | 1 | 0,004480 | 0,15% |
| Embedding | 146 | 0,000501 | 0,02% |

Ada 32 setoran saldo gratis (1.970 NC) dan satu setoran pembuatan grup berbayar (Rp30.210).
Ini merupakan data pemasukan dalam database ini, bukan laporan laba/rugi lengkap.

Untuk rentang yang sudah direkonsiliasi, hitungan internal US$2,356729 berbanding catatan Costs API
US$12,431759. Pada 11 November: US$0,291441 berbanding US$8,494348; 12 November tidak ada biaya lokal
namun Costs API mencatat US$3,660451. Pemilik mengonfirmasi project OpenAI juga dipakai pengujian/aplikasi lain.
Karena itu selisih tersebut tidak boleh dianggap sebagai konsumsi pengguna produksi atau kesalahan tarif semata.
Pisahkan project/key/budget produksi dan pengujian melalui dashboard OpenAI; tidak ada key yang dibuat/dipindahkan
tanpa instruksi. Tidak ada panggilan model berbayar untuk audit ini.

## Temuan teknis

- Riset dan Canvas mendominasi biaya yang tercatat; embedding bukan sasaran penghematan utama.
- Parser usage hanya menyimpan input, cached input, output. Reasoning dan cache-write tidak terukur terpisah.
- Tarif input/output katalog cocok dengan dokumentasi yang diperiksa, tetapi komponen cache-write belum dihitung.
- Reservasi riset menahan USD alat web, tetapi NC alat belum dimasukkan saat menghitung anggaran/output.
- Estimasi gambar melewati jalur pembatas NC per permintaan; pengaman perlu diterapkan sebelum panggilan API.
- Batas output Canvas 24.000 dan file 16.000 adalah plafon, bukan token yang otomatis dibayar. Tidak diturunkan.
- Anggaran OpenAI bulanan belum diisi, cadangan USD nol. Nilai anggaran membutuhkan keputusan pemilik;
  tidak ditentukan diam-diam. Anggaran aplikasi hanya melindungi panggilan yang melewati aplikasi ini.

Sumber API diperiksa 17 November 2026:
- https://developers.openai.com/api/docs/guides/prompt-caching
- https://developers.openai.com/api/docs/guides/token-counting
- https://developers.openai.com/api/docs/models/gpt-6.1-sol
- https://developers.openai.com/api/docs/models/gpt-6-astra
- https://developers.openai.com/api/docs/models/gpt-6-luna

## Verifikasi

Pengujian menggunakan kode yang sama dengan database Docker terisolasi, API palsu, tanpa kunci OpenAI produksi.
Wajib memeriksa pembayaran gagal/kurang, webhook ulang, jadwal promo, perubahan setelan setelah checkout,
slot gratis, akun baru tanpa grant otomatis, copy marketing, serta reservasi dan accounting AI.

Hasil implementasi dan rilis:
- 413 pengujian diperiksa, 408 lulus, 5 tes S3 dilewati karena NORMCHAT_TEST_S3_ENDPOINT tidak disediakan;
  2.419 assertions. Seluruh permintaan HTTP tes diblokir kecuali dipalsukan secara eksplisit.
- PostgreSQL tes memakai container tersendiri dan sudah dihapus; tidak menggunakan database produksi untuk tes.
- Build Vite produksi berhasil; config, route, dan Blade cache dibangun ulang; app/queue/scheduler dimuat ulang.
- Versi harga 5 mencatat perubahan. Promo masih nonaktif; nilai dasar 250 NC/Rp30.000 dan 2 slot teman gratis.
- HTTP /up, /, /home, /pricing berhasil; halaman admin mengalihkan tamu ke login.
- Cadangan DB tersedia di storage/app/deploy-backups/promo-20261117-before-release.sql sebelum aktivasi layanan.
- 35 grup dan 34 catatan kontribusi yang sudah ada tetap tersedia. Saldo/pembayaran lama tidak ditulis ulang.
- Accounting sekarang memisahkan reasoning, cache-write 30m/12h, dan cache-read. Metadata lama tidak direka ulang.
- Gambar identik pada lampiran dan kutipan balasan dikirim ke model satu kali; konteks teks tetap dipertahankan.
- Reservasi biaya web search ikut dalam batas NC; gambar mengikuti batas per permintaan sebelum API dipanggil.
- Anggaran bulanan dan cadangan USD belum diubah karena nominalnya belum diputuskan pemilik.
