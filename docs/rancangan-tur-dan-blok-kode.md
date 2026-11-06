# Rancangan: tur pengenalan + blok kode yang bisa disalin (6 Nov 2026)

## 1. Tur pengenalan (driver.js, MIT)

**Kapan tampil**
- Pengguna baru: sekali, sesudah login pertama. Ia langsung mendarat di chat grup gratisnya, dan tur dimulai di sana.
- Pengguna lama (yang sudah ada sebelum fitur ini): sekali, saat membuka chat berikutnya (`users.tour_seen_at` masih kosong).
- **Kembali setelah 3 minggu:** saat login, bila aktivitas terakhir di semua sesinya (`user_sessions.last_active_at`) lebih tua dari 21 hari, tur ditandai tampil lagi. Sesi berakhir sendiri setelah 5 hari diam, jadi pengguna yang kembali setelah 3 minggu pasti login ulang.
- Tidak tampil di setiap login. Superadmin tidak mendapat tur.
- Menutup tur di langkah mana pun (Selesai, X, Esc) mengisi `tour_seen_at`.
- Menu Akun punya **Lihat tur lagi**, yang membuka chat terakhir lalu langsung menjalankan tur.

**Langkah** (permintaan user 6 Nov 2026; langkah yang elemennya tidak tampil di layar dilewati):
1. **Cara chat** (kolom ketik): ketik dan kirim, lalu tulis @normi untuk bertanya ke Normi.
2. **Tombol +** dan kegunaan tiap isinya: Unggah file, Buat polling, Guided Learning, Buat gambar, Canvas, Deep Research.
3. **Pilihan model** dengan penjelasan singkat tiap model. Nama dan keterangannya diambil dari menu model yang aktif, jadi selalu sama dengan setelan superadmin.
4. **Pengaturan grup**: ketuk bagian atas chat untuk membuka info grup.
5. **Buat grup baru**: desktop menyorot tombol + di kolom kiri. HP menyorot tombol ← lalu menjelaskan tombol + di kanan bawah daftar grup.

**Tampilan:** tombol Lanjut, Kembali, Selesai, dan X; progres "3 dari 5"; warna mengikuti token tema terang/gelap. Pustaka dimuat hanya saat tur akan tampil.

**Server:**
- Migrasi `users.tour_seen_at`.
- Listener Login menandai `session('tour_due')`.
- `POST /tour/seen`.
- `GET /tour` (Lihat tur lagi).
- Halaman chat mengirim `data-tour-due`.

## 2. Blok kode di bubble chat

- Blok ```bahasa di jawaban Normi, dan juga di pesan anggota, tampil dengan:
  - kepala berisi nama bahasa (sql, php, js, …), ikon **<>**, dan ikon **salin**;
  - **pewarnaan sintaks** dari highlight.js (BSD-3), yang dimuat hanya bila ada blok kode. Warna token mengikuti tema terang/gelap dan lulus audit kontras.
- **Ikon salin:** seluruh isi kode disalin ke clipboard (ada cadangan bila clipboard API tidak tersedia). Ikon berubah jadi centang selama 1,5 detik, dan muncul toast "Kode disalin".
- **Ikon <>:** kode dimasukkan ke kolom ketik dalam bentuk ```bahasa … ```, siap diubah. Bila kolom ketik sudah berisi teks, kode ditambahkan di bawahnya, tidak menimpa. Kursor ditaruh di akhir, dan draf ikut tersimpan.
- Pesan anggota yang berisi ``` dirender sebagai blok kode, sehingga kode yang dikirim balik tetap rapi. Teks lain di pesan itu tetap teks biasa.
- Kode inline `…` tetap seperti sekarang.

## 3. Pengaman input pengguna

Permintaan user (6 Nov 2026): HTML, markdown, sintaks kode, atau karakter aneh yang diketik pengguna di mana pun (chat, persona, nama, deskripsi, polling, nama berkas) harus tampil sebagai teks, tidak menjadi XSS, dan tidak merusak tampilan.

Yang sudah aman dan diuji ulang:
- Blade selalu meng-escape (`{!!` hanya untuk ikon SVG statis dan surel yang lewat `SafeHtml::clean`).
- Data awal pesan memakai `@json` dengan `JSON_HEX_TAG`, jadi `</script>` di pesan tidak menutup tag.
- Di JS semua input lewat `escapeHtml`/`escapeAttr`. Markdown Normi meng-escape dulu dan hanya menautkan `http(s)://`.
- Lampiran disajikan dengan `X-Content-Type-Options: nosniff` dan `CSP: sandbox` (SVG/HTML tidak bisa menjalankan skrip). Foto grup di-encode ulang ke WebP.
- Pratinjau tautan hanya menerima gambar `https://`.

Yang diperbaiki:
- **Diagram mermaid**: label kini teks SVG (`htmlLabels: false`). Sebelumnya `<img src=…>` di label menjadi gambar sungguhan; skripnya memang dibuang, tetapi gambar dari situs luar tetap dimuat.
- **Ekspor DOCX**:
  - Pesan berisi `&` atau `<` membuat berkas Word rusak, karena PhpWord bawaannya tidak meng-escape. Escaping kini dinyalakan selama ekspor saja, sebab `AiDocumentGenerator` sudah meng-escape sendiri.
  - Jawaban Normi dengan tautan `javascript:` atau tautan kosong menggagalkan seluruh ekspor. Tautannya kini ditulis sebagai teks.
- **Karakter kendali arah** (U+202A–202E, U+2066–2069) dibuang dari nama pengguna, nama dan deskripsi grup, isi pesan, dan nama berkas (`App\Support\Bidi`, mutator model). Di tampilan juga dibuang lewat `escapeHtml`, untuk data lama. Tanpa ini, "tagihan U+202E 0005" tampil "5000", "gnp.exe" tampil "exe.png", dan pemberitahuan ikut terbalik.
- **Pagar ``` tanpa isi** tampil sebagai teks, bukan kotak kode kosong.

Uji:
- `tests/Feature/UserInputSafetyTest.php`: halaman (chat, daftar grup, info, persona, gabung), ekspor PDF/DOCX, dan kendali arah.
- Harness browser `uji-xss.mjs`: muatan di pesan, jawaban Normi (markdown, tabel, kode, mermaid), polling, nama berkas, nama anggota, nama dan deskripsi grup, persona, judul pratinjau tautan, pemberitahuan, dan hasil cari. Cek di sana: tidak ada skrip yang jalan, tidak ada elemen `on*`, tidak ada tautan `javascript:`, tidak ada pelanggaran CSP, dan teks tampil apa adanya.

## Uji

**Feature:**
- `tour_seen_at`;
- tur jatuh tempo untuk pengguna baru, pengguna lama, dan sesudah 21 hari tidak aktif, tetapi tidak di login biasa;
- `POST /tour/seen`;
- Lihat tur lagi;
- superadmin tanpa tur.

**Browser:**
- tur tampil sekali, 5 langkah di desktop, langkah yang tak tampil dilewati di HP, dan setelah ditutup tidak muncul lagi saat dimuat ulang;
- blok kode berlabel dan berwarna;
- salin mengisi clipboard;
- <> mengisi kolom ketik (menimpa kosong, menambah di bawah teks);
- pesan anggota dengan ``` tampil sebagai blok kode;
- kontras di 4 tema.
