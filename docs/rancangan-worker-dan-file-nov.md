# Rancangan worker, orkestrator antrean, dan pembuatan file (23 Nov 2026)

Permintaan user 23 Nov 2026 setelah audit:

1. Perbaiki kebocoran riwayat tertutup dan pesan privat (selesai, lihat bagian A).
2. Pastikan alur worker berjalan baik dan punya orkestrator.
3. Pastikan pembuatan PDF, Word, Excel, PowerPoint, dan file lain tidak lagi salah diartikan model.

Isi chat tetap tidak dienkripsi (keputusan 27 Okt 2026). Perbaikan nomor 1 murni pemeriksaan hak akses.

## A. Batas visibilitas pesan

`Message::isVisibleTo($user)` sekarang dipakai setiap endpoint yang menerima id pesan: versi pesan, teruskan,
canvas, edit, hapus untuk semua, polling (pilih dan statistik), balasan saat kirim pesan, dan draf. Pesan yang
tidak boleh dilihat diperlakukan seperti tidak ada (404), karena id pesan berurutan dan mudah ditebak.
Tes: `tests/Feature/MessageVisibilityBoundaryTest.php`.

## B. Worker dan orkestrator

### Masalah sebelumnya

- Satu proses `queue:work` untuk semua pekerjaan: jawaban Normi semua grup, ekspor, cadangan 03.00, notifikasi
  push, pratinjau tautan, indeks dokumen, embedding. Satu Deep Research 10 menit menahan semuanya.
- `ProcessGroupChatQueueJob` memproses seluruh antrean satu grup dalam satu job. Batas 600 detik berlaku untuk
  semua permintaan sekaligus, jadi permintaan kedua bisa terputus di tengah jalan.
- Job yang gagal mendapat kunci grup memakai `release(2)` dengan 3 percobaan. Begitu worker lebih dari satu,
  job seperti ini akan gagal permanen hanya karena grupnya sedang sibuk.
- `docker compose up --force-recreate queue` mematikan worker 10 detik setelah SIGTERM, jadi jawaban yang
  sedang disusun ikut terputus.
- Tidak ada yang memantau apakah worker hidup atau antrean menumpuk.

### Keputusan

**Orkestrator = Laravel Horizon** (`laravel/horizon` 5.52, tambahan paket `laravel/sentinel` dan
`symfony/polyfill-php83`). Horizon menjalankan satu master yang mengelola beberapa supervisor. Tiap supervisor
memegang satu kelompok antrean dengan jumlah proses yang naik turun sendiri menurut beban (`balance=auto`).
Horizon juga mencatat metrik, job gagal, dan waktu tunggu per antrean.

Kelompok antrean (`App\Support\Queues`):

| Antrean | Isi | Proses | Batas waktu |
|---|---|---|---|
| `ai` | `ProcessGroupChatQueueJob` (jawaban Normi) | 1–4 | 600 dtk |
| `notify` | push, pratinjau tautan, email peringatan admin | 1–2 | 60 dtk |
| `documents` | ekspor riwayat, cadangan, indeks dokumen, rekap mingguan | 1–2 | 600 dtk |
| `maintenance` | embedding dan ringkasan konteks | 1 | 600 dtk |
| `default` | sisa job lama di Redis saat deploy, job tanpa antrean | 1 | 600 dtk |

Mesin ini 6 CPU dan 8 GB, dipakai bersama Unocoffee produksi, jadi batas atas seluruhnya 10 proses PHP.
Tiap proses dibatasi 192 MB (Horizon me-restart proses yang melewatinya) dengan `memory_limit` PHP 384 MB.

**Satu permintaan per job.** `ProcessGroupChatQueueJob` mengambil satu item, memprosesnya, melepas kunci grup,
lalu memeriksa ulang antrean grup itu dan mengirim job berikutnya bila masih ada. Urutan per grup tetap terjaga
oleh kunci `group-chat-queue:{id}`, sementara grup lain dikerjakan proses lain secara paralel. Job yang tidak
mendapat kunci langsung selesai tanpa gagal. Pemegang kunci selalu memeriksa ulang antrean setelah melepas
kunci, jadi tidak ada permintaan yang tertinggal. Jaring terakhirnya `normchat:release-stale-holds` tiap 5 menit.

**Pemantauan.**
- Horizon `waits` memicu `LongWaitDetected` bila antrean `ai` menunggu lebih dari 60 detik (lainnya 300 detik).
  `App\Listeners\AlertLongQueueWait` meneruskannya ke `AdminAlerts` (banner superadmin + email), satu
  peringatan per antrean per jam.
- `normchat:check-workers` (tiap menit lewat scheduler) memeriksa master Horizon. Bila mati lebih dari 2 menit,
  muncul peringatan "Worker antrean berhenti". Peringatan ditutup otomatis setelah worker hidup lagi.
- `horizon:snapshot` tiap 5 menit untuk metrik throughput dan durasi.
- Halaman superadmin **Antrean** (`/superadmin/antrean`) menampilkan status worker, beban tiap antrean (jumlah
  menunggu, perkiraan tunggu, proses), statistik permintaan Normi 24 jam (menunggu, sedang diproses, selesai,
  gagal, median dan p95 lama selesai), dan 20 job gagal terakhir. Dasbor bawaan Horizon tidak dipakai karena
  skrip inline-nya ditolak CSP bernonce; gerbang `viewHorizon` selalu menolak.

**Deploy.**
- Service `queue` menjalankan `php artisan horizon` dengan `stop_grace_period: 3m`, jadi recreate container
  menunggu jawaban yang sedang disusun (hingga 3 menit) sebelum mematikan proses.
- Kode baru dimuat dengan `php artisan horizon:terminate`. Master selesai dengan anggun, container keluar, lalu
  Docker menyalakannya lagi (`restart: unless-stopped`) dengan kode baru dari bind mount.
- `deploy-server.sh` memakai `composer install --no-dev` (paket baru) dan `horizon:terminate` menggantikan
  `queue:restart`.
- Healthcheck container `queue`: `php artisan horizon:status`.

## C. Pembuatan file

### Cara memeriksa

`tests/Eval/NormiFileEvalTest.php` menjalankan alur produksi lengkap (antrean → FeatureRouter → responder →
FileBuilder) dengan model OpenAI sungguhan. Tiap kasus memeriksa format berkas, berkas bisa dibuka, isi memuat
data dari permintaan, tidak ada Markdown/JSON mentah atau placeholder, dan pertanyaan tidak dijawab dengan file.
Hasilnya (berkas jadi + JSON berisi jawaban model) disimpan di `storage/eval/<model>/`. Jalankan dengan
`NORMI_EVAL=1 OPENAI_API_KEY=… NORMI_EVAL_MODEL=gpt-6-luna vendor/bin/phpunit tests/Eval`. Tanpa variabel itu
eval dilewati, jadi suite biasa tidak pernah memanggil OpenAI.

### Temuan putaran 1 (16 kasus, model Cepat dan Smart)

JSON spesifikasi file dari kedua model sudah benar di semua kasus yang sampai ke FileResponder. Tidak ada PDF,
Word, Excel, atau PowerPoint yang gagal dirender. Salah tafsir datang dari aturan di sisi Normi Chat:

1. **"revisi" di isi dokumen dianggap permintaan merevisi file.** Invoice berisi "revisi 2x @Rp500.000"
   dijawab "Balas langsung file yang ingin direvisi" oleh kedua model. Sekarang "revisi" hanya berarti merevisi
   file bila kalimat diawali "revisi"/"tolong revisi", atau diikuti nama berkas ("revisi file", "revisi RAB",
   "revisi proposal"), atau menunjuk file sebelumnya. Membalas file tetap selalu dianggap revisi.
2. **Bentuk kata sehari-hari tidak dikenali.** "kirim filenya" tidak terdeteksi karena regex memakai batas kata,
   lalu chat biasa hanya menampilkan tombol saran fitur. `FileRequest::normalize()` sekarang menyeragamkan
   "filenya/pdf-nya/excelnya" → "file/pdf/excel", "dibikinin/dijadikan/dikirimkan" → "bikinin/jadikan/kirimkan",
   "pdf-in/word-kan" → "jadikan pdf/word", serta mengenali "butuh/mau/minta file …". FeatureRouter memakai
   normalisasi yang sama sebelum memutuskan bertanya ke model router.
3. **Pertanyaan tentang file.** "kenapa pdf yang dikirim tadi rusak?" kini tetap chat walau memuat kata kerja
   ("dikirim"). Kalimat tanya diawali apa/kenapa/bagaimana/jelaskan hanya dianggap permintaan file bila bentuk
   hasilnya disebut jelas ("jelaskan fotosintesis dalam bentuk pdf"). Permintaan cara ("cara bikin ppt",
   "bagaimana cara menyimpan dalam bentuk pdf") selalu chat.
4. **Error sesaat OpenAI tidak dicoba ulang.** Satu balasan 500 membuat permintaan langsung gagal. Chat
   completions kini dicoba ulang dua kali (jeda 2 dan 5 detik) untuk 5xx dan 429. Batas waktu habis tidak diulang
   agar tetap di bawah batas job 600 detik.

### Temuan putaran 2 (30 kasus: kalimat sehari-hari, balasan ke pesan, lampiran)

5. **Judul Word tampil seperti teks biasa.** `addTitle()` PhpWord tanpa `addTitleStyle()` menulis paragraf
   tanpa gaya Heading, jadi judul di DOCX tidak tebal dan tidak besar. Sejak awal fitur ini semua Word buatan
   Normi begitu. Sekarang judul 1–6 punya gaya, huruf bawaan Calibri 11.
6. **Markdown tanpa baris kosong antarblok.** "## Petunjuk" yang langsung diikuti teks tertulis apa adanya di
   Word, begitu juga paragraf yang langsung diikuti daftar atau tabel. Pemecah blok DOCX kini memisahkan per
   jenis baris (judul, daftar, tabel, teks, code fence). Pilihan "a. …" di bawah soal tetap bagian daftar.
7. **"buat invoice …" dijawab chat.** Jenis dokumen yang hampir selalu diminta sebagai berkas (invoice, faktur,
   kuitansi, nota, proposal, CV, RAB, sertifikat, piagam) kini menjadi PDF bila formatnya tidak disebut, sama
   seperti "surat" dan "notulen". Pengecualian permintaan gambar hanya berlaku bila kata gambar/logo muncul
   sebelum jenis dokumennya ("buatkan logo untuk laporan" tetap gambar, "invoice … jasa desain logo" tetap file).

### Hasil eval final (23 Nov 2026, 30 kasus)

- Smart (gpt-6.1-sol) 30/30. Cepat (gpt-6-luna) 29/30. Satu-satunya yang gagal adalah kriteria eval:
  skrip BMI tanpa `def` dianggap salah, padahal berkasnya benar dan sudah dijalankan. Kriterianya dilonggarkan.
- Sebelum perbaikan, kedua model gagal di invoice dan kuis, ditambah satu error 500 OpenAI yang tidak diulang.

### Yang sengaja tidak diubah

- Format JSON spesifikasi file (`response_format: json_object`) tetap. Kedua model menghasilkan bentuk yang
  benar di semua kasus, jadi beralih ke json_schema ketat hanya menambah risiko tanpa bukti manfaat.
- Penanda `[[fitur:file]]` dari chat biasa tetap menjadi tombol saran, tidak otomatis menjalankan file
  (biaya dua kali panggilan). Dengan pendeteksi yang diperbaiki, jalur ini jarang terpakai.

## D. Orkestrator dokumen hemat token (opsi A, keputusan user 24 Nov 2026)

Keputusan user:
1. Setiap dokumen yang terkirim ke grup dikonversi otomatis dan disimpan, baik men-tag @normi maupun tidak,
   supaya bisa menjadi konteks tambahan saat dibutuhkan.
2. Template PDF dan Word tidak memakai identitas brand Normi maupun grup (render tetap lewat FileBuilder).

### Hasil ukur yang mendasari (tokenizer o200k, proposal + RAB 15 baris)

JSON per baris yang dipakai sebelumnya 125% dari Markdown, JSON/YAML bersarang 176–179%, teks bernomor + tabel
TSV 93%. Revisi berupa patch 187 token dibanding tulis ulang 774 token. Cache prompt aktif di gpt-6-luna dan
gpt-6.1-sol (permintaan kedua 1.978 dari 1.981 token tercache) dengan harga input tercache 90–95% lebih murah.

### Alur

1. **Masuk.** `Message::created` untuk lampiran dokumen (pdf, docx, pptx, ppt, xlsx, csv, tsv, txt, md, json, dll.)
   mengirim `ConvertDocumentJob` ke antrean `documents`.
2. **Konversi sekali.** `DocumentConverter` membuat dokumen kanonik JSON (untuk mesin, tidak pernah dikirim apa
   adanya ke model): bagian ber-id, tabel, daftar judul, jumlah halaman, versi (sha256 berkas). PDF memakai lapisan
   teks lebih dulu, lalu Docling (OCR Indonesia + Inggris, struktur tabel) bila teks tipis atau bertabel. Office
   memakai `OfficeSections` (id bagian tetap sama dengan yang dipakai revisi asli), spreadsheet dan teks dibaca
   langsung. Hasilnya disimpan permanen (`message_documents` + berkas JSON di disk lampiran), menggantikan cache
   Redis 12 jam. Setelah itu `DocumentIndex` mengindeks teks kanonik yang sama (tanpa membaca ulang berkas) untuk
   semua grup, bukan hanya grup aktif.
3. **Ke model.** `DocumentProjection` mengubah dokumen kanonik menjadi teks ringkas:
   - tanya jawab: baris bernomor + tabel TSV;
   - revisi: `id| teks` per bagian, sehingga patch `{"id","old","new"}` tetap dicocokkan persis;
   - dokumen ≤ 6.000 token dikirim utuh; lebih besar → daftar judul + potongan relevan (pencarian hibrida yang
     sudah ada) ≤ 3.000 token, dengan catatan bahwa bagian lain bisa diminta per halaman/bab. Pertanyaan umum
     ("ringkas", "jelaskan isi") pada dokumen besar mendapat daftar judul + awal tiap bagian.
4. **Cache.** Blok dokumen yang dirujuk pesan terbaru (lampiran pesan itu atau pesan yang dibalas) diletakkan
   tepat setelah system prompt, sebagai data (bukan instruksi), sehingga pertanyaan lanjutan tentang dokumen yang
   sama memakai input tercache.
5. **Keluar.** Tetap seperti sekarang: model mengirim spesifikasi JSON, FileBuilder merender PDF/Word/Excel/PPT
   tanpa brand. Revisi dokumen yang sudah ada tetap patch per bagian.

Dokumen yang gagal dikonversi tetap bisa dibaca dengan pembaca teks lama (status `failed` tercatat, dicoba ulang
oleh scheduler). Dokumen yang dihapus untuk semua ikut dihapus dari indeks seperti sebelumnya.

### Hasil (24 Nov 2026)

- Eval model Smart, kode lama vs baru, dokumen ±140 bagian: kode lama menjawab "belum ditemukan, dokumen terpotong"
  (5.709 token input), kode baru menjawab benar Rp4.350.000 (3.794 token, −34%). Dokumen kecil setara.
- Cache prompt: permintaan asli yang dikirim ulang tercache 3.791/3.794 dan 3.898/3.901 token. Di eval cache masih
  0 karena pertanyaan kedua terkirim 2–3 detik setelah yang pertama, sebelum entri cache OpenAI siap.
- OCR: pindaian bersih sama antara eng dan ind+eng; pindaian buram jauh lebih baik dengan ind+eng.
- Eval pembuatan file tetap 30/30 (Smart) setelah perubahan jalur dokumen.

## E. Sisa bug audit 23 Nov 2026 (dikerjakan 24 Nov)

- Pembayaran patungan yang lunas belakangan tidak lagi menurunkan peran admin, menutup riwayat yang sudah dibuka,
  atau mengulang pesan "bergabung"; uangnya tetap jadi saldo grup (`group.contribution_after_join`).
- Lampiran diunggah ke S3 sebelum kunci grup; kiriman ulang (client_message_id sama) dan grup sibuk tidak
  meninggalkan berkas yatim.
- Waktu bayar Midtrans dibaca sebagai WIB (sebelumnya tersimpan 7 jam terlalu lambat).
- Permintaan Normi yang gagal dan tidak bisa dicoba ulang otomatis kini memberi petunjuk sesuai sebabnya ("edit atau
  kirim ulang", "grup dibekukan"); "Hubungi admin" hanya bila Normkredit sudah terpotong tanpa jawaban.
- Paket rentan diperbarui (Laravel 13.1 → 13.43, Symfony 8.1, CommonMark 2.10, Guzzle 7.17, phpseclib 3.0.59):
  `composer audit` turun dari 61 advisory (1 kritis, 15 tinggi) menjadi 7 (tanpa kritis/tinggi).
- Lanjutan 24 Nov: dompdf 3.1.5 → 3.1.6 (baca berkas lokal lewat SVG data-URI, DoS gambar raksasa) dan
  sodium_compat 2.6.0. Celah SVG dompdf tidak terjangkau pengguna saat ini (Markdown membuang data-URI lewat
  `allow_unsafe_links: false`, unggahan SVG ditolak menurut isi berkas), jadi ini lapis pertahanan tambahan.
  `composer audit` kini bersih.
