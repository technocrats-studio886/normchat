# Rancangan: perilaku chat ala WhatsApp (5 Nov 2026)

Enam permintaan user, berdasarkan gambar WhatsApp: pesan sistem "menambahkan … Kirim riwayat pesan", kartu "Anda yang membuat grup ini", dan penanda tanggal "Kemarin" di tengah atas.

## Keputusan user (5 Nov 2026)

| Hal | Keputusan |
|---|---|
| Draf | **Sinkron semua perangkat** (server), dan **hanya berlaku 24 jam**. Draf yang lebih tua tidak ditampilkan, tidak dihitung dalam urutan, dan dibersihkan terjadwal. |
| Kirim riwayat pesan | Membuka **seluruh riwayat**. |
| Normi AI untuk penanya yang riwayatnya tertutup | **Hanya pesan yang bisa ia lihat**. Ingatan jangka panjang grup (ringkasan, pencarian ingatan) tidak dipakai sampai riwayatnya dikirim. |
| Peristiwa yang dicatat | Bergabung, keluar, dikeluarkan, riwayat dikirim, **nama grup diubah**, **foto grup diubah/dihapus**, **deskripsi diubah** (dengan isi sebelum dan sesudah). |
| Batas panjang | **Nama 60, deskripsi 300 karakter**, dengan penghitung sisa karakter di kolom isian (buat grup, Info grup). |

## Kondisi sekarang (dari kode)

| Hal | Sekarang |
|---|---|
| Membuka chat | Selalu gulir ke paling bawah (`read-state.js`, `scrollChatToBottom`). Feed memuat 80 pesan terakhir, tanpa muat-lebih-lama. |
| Draf | Tidak ada (hanya draf sementara saat mode edit pesan). |
| Urutan grup | Sidebar desktop urut pesan terakhir saat halaman dimuat, tidak berubah sendiri. Daftar HP (`groups.index`) urut tanggal grup dibuat. |
| Pesan sistem | Tidak ada. `sender_type` hanya `user`/`ai`. |
| Riwayat untuk anggota baru | Semua anggota melihat seluruh riwayat. |
| Penanda tanggal | Tidak ada. |

## 1. Masuk ke bagian terakhir yang dilihat

- Bila ada pesan belum dibaca (`last_read_message_id` < pesan terbaru, dan user pernah membuka grup), feed tidak digulir ke bawah, dan membuka chat tidak lagi langsung menandai semuanya terbaca. Pesan ditandai terbaca saat user menggulir sampai bawah. Di atas pesan belum dibaca pertama dipasang pemisah **"N pesan belum dibaca"**, lalu feed digulir sehingga pemisah itu ada di sepertiga atas layar.
- Pesan belum dibaca pertama di luar 80 pesan yang dimuat: feed dibuka di pesan tertua yang dimuat, dengan pemisah di atasnya.
- Tidak ada pesan belum dibaca: tetap ke paling bawah seperti sekarang.
- Status baca baru maju saat user menggulir sampai bawah (perilaku sekarang). Tombol ⌄ menampilkan jumlah yang belum dibaca.

## 2. Draf

- Teks yang diketik tapi belum dikirim disimpan sebagai draf per user per grup. Keputusan sinkron atau per perangkat ditanyakan; rekomendasinya sinkron, tabel `group_drafts`.
- Disimpan otomatis 600 ms setelah berhenti mengetik, saat pindah grup, dan saat halaman ditutup (`sendBeacon`). Dihapus saat pesan terkirim atau teks dikosongkan.
- Kembali ke grup: kolom ketik terisi lagi dengan draf, termasuk balasan yang sedang dikutip bila ada.
- Daftar grup (sidebar desktop dan daftar HP): grup berdraf naik ke **paling atas**, dengan pratinjau berlabel **Draf** (warna aksen) diikuti teksnya. Antar grup berdraf, urutannya dari draf terbaru.

## 3. Urutan grup

- Urutan: grup berdraf, lalu pesan terakhir terbaru ke terlama. Grup tanpa pesan memakai waktu dibuat. Sama di sidebar desktop dan daftar grup HP.
- Langsung berubah tanpa memuat ulang. Sidebar/daftar ikut kanal `group.{id}` setiap grup yang tampil. Saat `message.sent` masuk, baris grup itu naik ke atas (di bawah grup berdraf), pratinjau dan jamnya diperbarui, dan lencana belum dibaca bertambah bila bukan pesan sendiri dan bukan grup yang sedang dibuka.

## 4. Pemberitahuan anggota + "Kirim riwayat pesan"

- **Pesan sistem baru:** `sender_type = 'system'`, `message_type = 'system'`, `content` kosong, `system_event` + `system_meta` (json: actor_id, actor_name, target_id, target_name).
  - Ditampilkan sebagai pita di tengah, bukan bubble.
  - Teks dibuat per penonton ("Kamu bergabung …" untuk diri sendiri).
- **Peristiwa:**
  - `member_joined`: "Budi bergabung lewat tautan undangan."
  - `member_left`: "Budi keluar."
  - `member_removed`: "Sari mengeluarkan Budi."
  - `history_shared`: "Sari mengirim riwayat pesan ke Budi."
  - `name_changed`: "Sari mengganti nama grup dari «A» menjadi «B»."
  - `photo_changed` / `photo_removed`: "Sari mengganti foto grup." / "Sari menghapus foto grup."
  - `description_changed`: "Sari mengubah deskripsi grup.", dengan isi sebelum dan sesudah tampil di bawahnya (dua baris kecil, sebelum dicoret). Bila deskripsi dikosongkan: "Sari menghapus deskripsi grup."
- **Riwayat untuk anggota baru:** kolom `group_members.history_from_message_id`.
  - Saat bergabung, termasuk gabung ulang, diisi id pesan terakhir grup. Anggota itu hanya melihat pesan sesudahnya, ditambah pemberitahuan bergabungnya sendiri.
  - Anggota lama tetap `null`, artinya seluruh riwayat.
- **Kirim riwayat pesan:**
  - Pemilik melihat tautan "Kirim riwayat pesan" di pemberitahuan bergabung, selama riwayat anggota itu masih tertutup dan anggotanya masih aktif.
  - Mengetuknya meminta konfirmasi, lalu `POST /groups/{group}/members/{member}/history` mengosongkan batasnya (seluruh riwayat, atau sesuai jawaban pertanyaan).
  - Setelah itu muncul pemberitahuan `history_shared`, tautannya hilang, dan feed anggota itu dimuat ulang lewat realtime.
- **Batas berlaku di:**
  - feed chat;
  - pratinjau dan lencana belum dibaca di daftar grup;
  - lampiran (`chat.attachment`);
  - Media/Tautan/Berkas di Info grup;
  - cari pesan;
  - ekspor riwayat;
  - kutipan balasan ke pesan sebelum batas, yang tampil "Pesan sebelum kamu bergabung".
- Normi AI untuk penanya yang riwayatnya tertutup: ditanyakan.

## 5. Kartu "grup dibuat"

- **Kartu di paling atas feed** (bukan baris pesan; dibuat dari `groups.created_at` dan pemiliknya):
  - foto grup (pemilik: lencana kamera yang membuka menu foto Info grup);
  - "Kamu yang membuat grup ini" atau "Sari membuat grup ini";
  - "Grup · N anggota";
  - "Anggota bisa mengundang teman lewat tautan atau kode grup.";
  - tombol **Tambah anggota** (lembar Tautan grup) dan **Undang via tautan atau kode QR** (lembar Tautan grup langsung di Kode QR).
- Tampil bila feed sudah sampai pesan paling awal yang boleh dilihat user (kurang dari 80 pesan, atau anggota baru). Kartu ajakan grup gratis di atas feed tetap seperti sekarang.

## 6. Penanda tanggal

- **Di dalam feed:** pita tanggal di antara pesan beda hari, yaitu "Hari ini", "Kemarin", lalu "2 November" (tahun ini) atau "2 November 2025" (tahun lain), sesuai zona waktu user.
- **Penanda melayang di tengah atas:** hanya saat digulir oleh user (bukan guliran otomatis saat chat dibuka atau pesan baru masuk), menampilkan tanggal bagian yang sedang terlihat. Hilang 1,5 detik setelah guliran berhenti dan muncul lagi saat digulir melewati batas hari. Tidak muncul saat halaman pertama dibuka tanpa digulir.

## 7. Sedang mengetik (permintaan user 5 Nov 2026)

- Indikator di bawah pesan terakhir menampilkan **foto profil bulat** setiap orang yang sedang mengetik. Normi AI memakai logonya. Maksimal tiga foto, saling menumpuk ke kanan.
- Teks memakai nama depan:
  - "Budi sedang mengetik…"
  - "Normi AI dan Budi sedang mengetik…"
  - "Normi AI, Dimas, dan Budi sedang mengetik…"
- Titik tiga beranimasi, dan diam bila pengguna memilih gerak dikurangi.
- Foto dikirim lewat whisper `typing` (`sender_avatar` dari meta `auth-user-avatar`). Status Normi tetap dari event `typing.status`.

## Ekspor (poin 4 dan 5)

PDF dan DOCX memuat:
- "Sari membuat grup ini · 1 Nov 2026" di awal, bila rentang ekspor mencakup tanggal dibuat;
- pemberitahuan bergabung, keluar, dikeluarkan, dan kirim riwayat, sebagai baris tengah bergaya keterangan (bukan bubble), sudut pandang orang ketiga;
- untuk pengekspor yang riwayatnya tertutup, hanya pesan yang boleh ia lihat.

## Uji

- Feature: batas riwayat di semua tempat di atas, kirim riwayat (hanya pemilik, sekali, anggota aktif), pesan sistem per peristiwa, draf (simpan, ambil, hapus saat kirim, urutan), urutan grup HP, ekspor memuat peristiwa.
- Browser: pemisah belum dibaca + posisi awal, draf kembali + naik ke atas, urutan berubah saat pesan masuk, pita tanggal dan penanda melayang (muncul saat gulir, hilang 1,5 detik), kartu grup dibuat, Kirim riwayat pesan dari sisi pemilik dan anggota baru.
