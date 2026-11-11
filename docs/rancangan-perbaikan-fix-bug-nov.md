# Rancangan perbaikan branch fix-bug (11 Nov 2026)

Daftar dari user berisi 13 poin plus satu tambahan soal status aktivitas. Poin 1–8 sudah dikerjakan di commit
`73b90b8 update 8 bug`, tetapi commit itu tidak pernah aktif di produksi:

- 8 Nov 20:41 commit ditarik ke /root/normchat dan aset dibuild.
- 8 Nov 21:04 folder di-reset ke `7afeffe` dan aset dibuild ulang, jadi seluruh perbaikan batal.
- 11 Nov 05:31 folder di-checkout ke `fix-bug`. Disk sekarang berisi 73b90b8, sedangkan FPM (OPcache tanpa
  validasi timestamp, start 7 Nov) dan aset `public/build` masih versi 7afeffe.

Bukti di data produksi: pesan 260 dan 269 tersimpan dengan `ai_mode` image/deep_research tetapi teksnya "@normi …",
dan `https://normi.chat/login` masih mengirim `Cache-Control: no-store`.

Karena itu poin 1–8 diperlakukan sebagai "diverifikasi ulang dan dilengkapi", bukan ditulis dari nol.

## 1. Page cache (bolak-balik halaman tanpa muat ulang)

Sudah ada di 73b90b8: HTML `private, no-cache` (boleh masuk Back-Forward Cache), WebSocket ditutup saat
`pagehide` dan disambung lagi saat `pageshow`, pesan yang terlewat disusulkan, speculation rules untuk prefetch.

Tambahan:
- Uji bfcache sungguhan di Chromium dan WebKit (`pageshow.persisted === true` saat kembali).
- Pastikan tidak ada `unload`/`beforeunload` yang memblokir bfcache, termasuk di halaman grup, info, dan profil.
- Daftar grup (`/groups`) ikut dipulihkan dari bfcache dan menyusulkan pratinjau pesan terbaru.

## 2–3. Animasi dan cache gambar

Sudah ada: skeleton berdenyut lalu cross-fade (pola "Skeleton loader and reveal" transitions.dev), URL lampiran
berversi + `immutable`, ETag/304, `carryOverMedia` agar gambar tidak didekode ulang saat bubble dirender ulang.

Tambahan:
- Saat Normi membuat gambar, placeholder di indikator status memakai rasio gambar yang akan dibuat dan berganti
  ke gambar asli dengan transisi yang sama (tanpa lompatan tinggi feed).
- Gambar memakai `decoding="async"` dan ukuran tetap (aspect-ratio dari metadata) supaya feed tidak bergeser.
- Hormati `prefers-reduced-motion`.

## 4. Fitur tidak "nyangkut" setelah terkirim

Sudah ada: mode dilepas setelah kirim. Tambahan: periksa semua jalur kirim (Enter, tombol kirim, kirim dari
pratinjau lampiran/kamera, kirim dari kartu konfirmasi, voice note, draf yang dipulihkan) lewat uji browser.

## 5 + tambahan. Status aktivitas Normi sesuai pekerjaannya

Event `TypingStatus` membawa `activity` dan `detail` (teks pendek). Status dikirim per tahap nyata:

| Fitur | Tahap dan teks status |
|---|---|
| Chat biasa | membaca lampiran ("sedang membaca dokumen" / "sedang melihat gambar" / "sedang mendengarkan pesan suara") lalu "sedang mengetik" |
| Deep Research | "sedang memulai riset tentang …", lalu tiap pencarian web "sedang mencari "kueri"", lalu "sedang menyusun laporan dari N sumber" |
| Buat gambar | "sedang merancang gambar", lalu "sedang membuat gambar" atau "sedang mengedit gambar" |
| Canvas | "sedang menulis program" (permintaan aplikasi/web/kode) atau "sedang menyusun canvas" |
| File | "sedang membuat file PDF/DOCX/XLSX/…" |
| Guided Learning | "sedang menyiapkan materi belajar" |

Deep Research memakai mode streaming Responses API (`stream: true`) supaya kueri pencarian yang sebenarnya bisa
dilaporkan. Hasil akhir (`response.completed`) diproses persis seperti sekarang, jadi penagihan tidak berubah.

## 6. Membalas jawaban fitur: tanya dulu

Sudah ada kartu "Ingin melakukan Deep Research lagi?" dengan Ya/Tidak. Tambahan:
- Juga berlaku saat membalas jawaban fitur tanpa menulis @normi (balasan ke Normi tetap diproses Normi).
- Juga berlaku untuk jawaban File.
- Kartu tidak muncul lagi bila user sudah memilih fitur atau tag fitur sendiri.
- Uji di HP: kartu tidak tertutup keyboard.

## 7. Tag per fitur

@normi (chat), @normi-image, @normi-research, @normi-canvas, @normi-learn, ditambah @normi-file untuk file.
Sudah ada di 73b90b8. Tambahan: tag fitur tampil berwarna di bubble, dan menu mention menampilkan semua tag.

## 8. Responsif semua browser, terutama Safari iOS

Sudah ada: shell mengikuti `visualViewport` (tinggi + offsetTop). Tambahan:
- Kolom ketik memakai font 16px di iOS supaya Safari tidak memperbesar halaman saat fokus (zoom inilah yang
  membuat header tertutup dan kolom ketik keluar layar).
- `-webkit-fill-available` sebagai cadangan, `env(safe-area-inset-*)` untuk notch dan home indicator.
- Uji tampilan di WebKit (iPhone SE, iPhone 15, iPad), Chromium (Android, desktop), dan Firefox.

## 9. Buat file dalam format apa pun

Responder "File" menggantikan DocumentResponder:
- Format biner yang dirender server: PDF, DOCX, XLSX, PPTX (XLSX/PPTX ditulis dengan ZipArchive, tanpa paket baru).
- Format teks apa pun: TXT, MD, CSV, JSON, HTML, XML, YAML, SQL, SVG, ICS, VCF, dan berkas kode (py, js, ts, php,
  java, kt, swift, dart, go, rs, c, cpp, cs, sh, dst.).
- Beberapa berkas sekaligus (mis. proyek kecil) dikirim sebagai satu ZIP.
- Konversi dari lampiran: gambar → PDF (gambar ditanam utuh), dokumen → PDF/DOCX/TXT dari isi teksnya.
- Deteksi lebih luas: "ubah jadi pdf", "jadikan excel", "export ke csv", "simpan sebagai .py", dsb.
- Prompt Normi menyatakan Normi bisa membuat file, jadi tidak lagi menjawab "aku belum bisa mengirim file".

## 10. Canvas siap untuk apa pun, termasuk framework

Canvas tetap satu halaman HTML yang bisa dipratinjau. Framework yang diminta dijalankan langsung di pratinjau:
React/Vue/Preact/Svelte lewat ESM CDN + Babel standalone untuk JSX, Tailwind, Alpine, Chart.js, Three.js, dll.
Untuk framework yang butuh build (Next.js, Laravel, Flutter, Spring), Canvas memberi pratinjau yang bisa jalan,
dan struktur proyek lengkap dikirim sebagai ZIP lewat responder File. CSP pratinjau canvas diperiksa agar CDN
tersebut diizinkan.

## 11. Batas panjang bubble dan "Lihat selengkapnya"

Keputusan user: ±15 baris, dibuka di tempat. Bubble teks (anggota dan Normi) dipotong secara visual saat tingginya lewat batas, dengan gradasi di bawah dan
tombol "Lihat selengkapnya" yang membuka isi penuh di tempat. Tombol "Tampilkan lebih sedikit" setelah dibuka.
Pencarian pesan dan lompat ke pesan otomatis membuka bubble yang dipotong.

## 13. Arahkan ke fitur Normi, bukan aplikasi lain

- Prompt sistem Normi berisi katalog fitur Normi Chat (Buat gambar termasuk edit foto, Deep Research, Canvas,
  Guided Learning, File, polling, cari pesan, ekspor riwayat) dan larangan menyarankan aplikasi luar (Canva,
  ChatGPT, Photoshop, dll.) untuk hal yang bisa dikerjakan fitur Normi.
- Permintaan edit gambar lewat @normi biasa (pesan berisi foto atau membalas foto) dikenali di server.
- Keputusan user: arahkan + tombol satu ketuk, bukan otomatis menjalankan fitur gambar.
- Jawaban Normi bisa membawa saran fitur (`system_meta.suggest_mode`), tampil sebagai tombol di bawah bubble.
  Tombol itu menyiapkan kolom ketik dengan tag fitur, isi permintaan, dan balasan ke pesan aslinya (agar foto
  referensi ikut).

## Notifikasi push (permintaan tambahan)

Web Push standar (VAPID + enkripsi aes128gcm RFC 8291) lewat service worker khusus notifikasi `/sw.js`, tanpa
layanan pihak ketiga dan tanpa paket composer baru. Berlaku di Chrome, Edge, Firefox, Android, dan Safari macOS.
Di iPhone/iPad (iOS 16.4+) hanya untuk Normi Chat yang dibuka dari Layar Utama; kartu ajakan menjelaskannya.

Keputusan user 11 Nov 2026:
- Semua pesan grup dikirim, kecuali grup yang dibisukan (8 jam, 1 minggu, selalu). Mention, balasan ke pesanku,
  dan pemberitahuan Normi untukku tetap dikirim walau dibisukan bila setelan "Saat disebut" menyala.
- Kunci VAPID dibuat Claude dan diisi ke `.env` produksi (`NORMCHAT_VAPID_*`).

Aturan kirim (App\Services\Push\MessagePush), dicek setelah jeda 8 detik:
- pesan sudah dibaca di perangkat mana pun (`group_members.last_read_message_id`) tidak dikirim;
- user yang sedang membuka Normi Chat di salah satu perangkat (sinyal tiap 45 detik selama tab terlihat) tidak
  dikirimi, seperti Telegram;
- topik push per grup, jadi notifikasi yang belum sampai diganti yang terbaru; judul memuat jumlah pesan baru;
- grup yang dibaca di perangkat ini menutup notifikasinya; keluar akun mencabut langganan perangkat itu;
- endpoint hanya boleh ke layanan push browser (FCM, Mozilla, Apple, Windows) untuk mencegah SSRF.

Batasan yang diketahui: notifikasi yang sudah tampil di HP tidak bisa ditarik saat pesannya dibaca di laptop
(Safari dan Chrome mewajibkan setiap push menampilkan notifikasi). Notifikasi itu tertutup saat Normi Chat dibuka
di HP, dan karena aturan di atas, kasus ini hanya terjadi bila semua perangkat sedang tidak membuka Normi Chat.

## 12. Ide fitur

Ditulis terpisah di ringkasan akhir (tidak dibangun).

## Cara kerja dan deploy

- Dikerjakan di salinan (scratchpad/devtree), diuji dengan phpunit dan Playwright (Chromium, WebKit, Firefox).
- Satu migrasi tambahan (tabel `push_subscriptions`, kolom `group_members.muted_until`), tanpa paket composer baru.
- Deploy dijalankan user: salin ke /root/normchat, build aset, recreate `app queue scheduler`.
