# Rancangan 8 fitur produktivitas grup (12 Nov 2026)

Permintaan user 11 Nov 2026: bangun 8 ide fitur dari ringkasan fix-bug, "eksekusi aja langsung, tapi jangan ganggu
production". Semua dikerjakan di salinan (scratchpad/devtree), diuji di testcopy, dan baru masuk /root/normchat
saat user menjalankan rilis.

Prinsip yang dipakai semua fitur:
- Memakai pola yang sudah ada: antrean Normi (ChatMessageQueue → GroupAiAssistant), tagihan NK lewat AiTask,
  pesan privat Normi (`visible_to_user_id`), scheduler Laravel, dan event realtime grup.
- Daftar yang bisa berubah (sematan, tugas) cukup diberi tanda "berubah" lewat realtime, lalu tiap browser
  mengambil ulang daftarnya sendiri. Dengan begitu anggota baru yang riwayatnya belum dikirim tidak melihat isi
  pesan lama lewat sematan.
- Grup yang dibekukan hanya bisa dibaca: semua aksi tulis ditolak (423).

## 1. Ringkas pesan yang belum dibaca ("Apa yang kulewatkan?")

- Di pembatas "N pesan belum dibaca" ada tombol **Ringkas dengan Normi** bila N ≥ 5.
- Tombol mengirim pertanyaan "@normi ringkas N pesan yang belum kubaca" ke grup, dengan rentang pesan yang belum dibaca
  di `system_meta.catch_up` (diisi server, bukan dari teks).
- `CatchUpResponder` membaca pesan di rentang itu (sesuai hak lihat peminta, tanpa pemberitahuan pribadi siapa pun),
  lalu meringkas untuk peminta: yang menyebut/menanyai dia di atas, lalu topik, keputusan, jadwal dan tugas, file atau
  tautan penting.
- Jawaban terlihat semua anggota dan memakai Normkredit grup seperti @normi biasa (keputusan user 11 Nov 2026).

## 2. Pengingat dan jadwal

- Lewat chat: "@normi ingatkan grup Jumat jam 7 bawa laptop" → `ReminderResponder` membaca waktu (zona waktu
  penanya), isi, ulang (sekali/harian/mingguan), dan sasaran (grup atau hanya aku). Jawaban Normi memuat jadwal dan
  tombol **Batalkan pengingat**.
- Lewat menu: tombol + → **Buat pengingat** (isi, tanggal, jam, ulang, untuk grup atau hanya aku). Tanpa NK.
- Daftar: menu ⋮ → **Pengingat** (pengingat grup dan pengingat pribadiku yang akan datang, bisa dibatalkan
  pembuatnya atau moderator).
- Pengiriman: `normchat:send-reminders` tiap menit memposting pesan Normi "⏰ …" ke grup (atau privat ke
  pembuatnya). Notifikasi push ikut terkirim seperti pesan lain, dan pengingat menembus grup yang dibisukan
  (diperlakukan seperti mention).

## 3. Tugas dan checklist bersama

- Tabel `group_tasks`: judul, penanggung jawab (opsional), tenggat (opsional), selesai oleh siapa dan kapan, pesan
  asal.
- Menu ⋮ → **Tugas grup**: tambah, centang selesai, hapus (pembuat atau moderator), "Lihat pesan" ke pesan asal.
- Aksi pesan **Jadikan tugas**.
- Lewat chat: "@normi buat checklist dari pembahasan tadi" → `TaskResponder` membuat beberapa tugas sekaligus
  (nama penanggung jawab dicocokkan ke anggota) dan menampilkan tombol **Buka Tugas grup**.

## 4. Pesan terjadwal

- Tombol + → **Jadwalkan pesan**: isi diambil dari kolom ketik (termasuk balasan yang sedang dipilih), pilihan cepat
  (nanti malam 19.00, besok 07.00, Senin 08.00) atau tanggal dan jam sendiri.
- Pil "N pesan terjadwal" di atas kolom ketik membuka daftar milikku: **Kirim sekarang** atau **Batalkan**.
- `normchat:send-scheduled` tiap menit mengirimnya sebagai pesan biasa atas nama pengirim. Bila berisi @normi,
  Normi menjawab saat pesan terkirim. Pesan gagal bila pengirim sudah keluar atau grup dibekukan.
- Hanya teks (lampiran tidak bisa dijadwalkan). Maksimal 20 pesan terjadwal per orang per grup.

## 5. Sematkan pesan

- Aksi pesan **Sematkan** / **Lepas sematan**, untuk semua anggota aktif. Maksimal 5; sematan ke-6 menggeser yang
  paling lama (seperti WhatsApp). Pesan yang dihapus untuk semua otomatis lepas.
- Bilah di bawah header menampilkan sematan terbaru. Ketuk untuk melompat ke pesannya, lalu bilah beralih ke
  sematan berikutnya. Tombol daftar membuka semua sematan dan tombol lepasnya.
- Pemberitahuan di tengah chat: "Budi menyematkan pesan".

## 6. Terjemahkan pesan

- Aksi pesan **Terjemahkan**: teks berbahasa asing diterjemahkan ke Bahasa Indonesia, teks Bahasa Indonesia ke
  Bahasa Inggris. Hasil tampil di bawah teks asli dan bisa disembunyikan.
- Model termurah lewat MaintenanceAi (tetap dijaga SpendGuard, tidak memotong NK grup), di-cache 30 hari per isi
  pesan. Dibatasi 20 kali per menit per orang, maksimal 4.000 karakter.

## 7. Bertanya ke isi dokumen grup

- PDF, DOCX, PPTX, XLSX, dan file teks yang dikirim ke grup diindeks (`document_chunks`: potongan ±1.200
  karakter + embedding) oleh `normchat:index-documents` tiap menit, termasuk dokumen lama.
- Saat pertanyaan ke Normi menyebut dokumen ("menurut modul", "di slide kemarin", "pdf yang dikirim Budi", nama
  filenya), potongan paling relevan (kata kunci + makna) ikut ke prompt beserta nama file dan pengirimnya.
  Status Normi "sedang membaca dokumen grup".
- Hak lihat tetap: hanya dokumen grup itu, sebelum pertanyaan, tidak terhapus, dan sesudah batas riwayat penanya.
- Aksi **Tanya Normi** pada pesan file: langsung membalas file itu dengan @normi.

## 8. Rekap mingguan otomatis

- Info grup → **Rekap mingguan** (moderator): nyala/mati, hari, dan jam (zona waktu pemilik grup). Bawaan mati.
- `normchat:weekly-recaps` tiap jam memilih grup yang jadwalnya tiba. Grup dengan kurang dari 10 pesan dalam
  seminggu dilewati.
- Isi rekap: topik utama, keputusan, tugas yang selesai dan yang masih terbuka, pengingat minggu depan, file dan
  tautan penting, pertanyaan yang belum terjawab. Diposting sebagai pesan Normi biasa.
- Memakai Normkredit grup (ditagih atas nama pemilik grup). Bila saldo tidak cukup, rekap dilewati.

## Keputusan user (11 Nov 2026)

1. Ringkasan pesan belum dibaca diposting ke grup (terlihat semua anggota) dan memotong NK.
2. Semua anggota boleh menyematkan dan melepas sematan.
3. Terjemahan gratis (tidak memotong NK).
4. Rekap mingguan bawaan mati; moderator yang menyalakan; memotong NK grup.

Keputusan teknis lain yang diambil sendiri: pesan terjadwal hanya teks; pengindeksan dokumen hanya untuk grup yang memakai
Normi dalam 30 hari terakhir (sama dengan embedding pesan); rekap memakai model termurah atas nama pemilik grup.

## Hasil uji

- phpunit 402 test lulus (18 test baru di `tests/Feature/GroupProductivityTest.php`).
- Playwright (Chromium desktop, WebKit iPhone SE/13): ringkasan, sematan, tugas, pengingat, pesan terjadwal, terjemahan,
  Tanya Normi tentang file, dan setelan rekap mingguan di Info grup.
