# Rancangan: login langsung di normi.chat + grup gratis

Branch: `stagging-v1` · Tanggal: 4 Nov 2026 · Status: disetujui 4 Nov 2026; selesai, lulus uji, dan ter-deploy 4 Nov 2026 09.07 UTC (belum di-commit).

Menggantikan bonus 20 NK per grup baru dari `docs/rancangan-ux-admin-pengumuman-bonus.md` bagian 6 (B1).

## Konteks

Bonus 20 NK per grup baru (commit 88163c8, sudah live sejak 4 Nov 07:09) **dicabut**. Penggantinya model "mulai gratis" mirip ChatGPT:

- Membuka normi.chat langsung menampilkan halaman login yang berisi kalimat marketing.
- Login pertama otomatis membuatkan grup gratis, lalu user langsung masuk ke chat grup itu.

Kondisi kode saat ini:

- Membuat grup selalu bayar (250 NK = Rp30.210).
- Gabung grup selalu wajib patungan minimal 1 paket (125 NK).
- Belum ada batas anggota, belum ada konsep gratis, dan belum ada hook untuk user baru.
- `/` menampilkan home marketing berbahasa Inggris.

## Keputusan user (4 Nov 2026)

| Hal | Keputusan |
|---|---|
| Jatah grup gratis | 2 per user. Grup ke-1: 70 NK, grup ke-2: 50 NK. Grup ke-3 dan seterusnya memakai model bayar yang sudah ada. |
| User lama | Jatah dihitung dari grup yang pernah dibuat (termasuk yang dibayar dan yang sudah dihapus). 0 grup → otomatis dapat grup 70 NK. 1 grup → grup berikutnya gratis 50 NK. ≥2 grup → bayar. Menghapus grup tidak mengembalikan jatah. |
| Anggota gratis | Pemilik + 2 anggota gratis di **kedua** grup gratis. Anggota ke-3 dan seterusnya membayar patungan. **Slot tidak kembali** walau anggota gratis keluar. |
| Grup berbayar | Tetap seperti sekarang: semua yang gabung membayar patungan. |
| Sandi grup otomatis | Tidak ada. Gabung cukup dengan kode grup; pemilik bisa memasang sandi di Pengaturan grup. |
| Setelah login pertama | Langsung ke chat grup gratis, dengan kartu sambutan berisi kode grup dan ajakan mengundang 2 teman. |
| Bonus 20 NK | Dicabut. Halaman Promo diganti menjadi **Grup gratis**: superadmin mengatur 70 / 50 / 2 dan saklar aktif. Riwayat bonus lama tetap tercatat. |
| Situs marketing | `/` = login berbahasa Indonesia. /about, /pricing, /contact (Inggris) tetap ada, ditautkan kecil di kaki halaman login. |
| Judul login | "Dari obrolan jadi rencana." |

## Langkah 0 (selesai)

Tulis `docs/rancangan-grup-gratis-login.md` dari rancangan ini. Ini sesuai aturan "rancangan dulu", dan persetujuan rancangan ini dianggap persetujuan dokumen tersebut. Bagian bonus di `docs/rancangan-ux-admin-pengumuman-bonus.md` diberi catatan "diganti oleh …".

## 1. Data (migrasi baru)

**`users.free_groups_used`** (tinyint, default 0)
- Backfill: `LEAST(2, jumlah grup milik user)`, menghitung grup yang sudah dihapus (soft delete) dan mengabaikan status `pending_payment`.

**`groups`**
- `free_member_slots` (smallint, default 0; 0 = grup berbayar).
- `free_joins_used` (smallint, default 0).
- Nilai slot dikunci saat grup dibuat.

**`app_settings`**
- Hapus kunci `group_bonus_*`.
- Kunci baru di `NormkreditPricing::EDITABLE`:
  - `free_groups_enabled` (bool, true)
  - `free_first_group_credits` (70)
  - `free_second_group_credits` (50)
  - `free_group_member_slots` (2)
- Default ditaruh di `config/normchat.php` bagian pricing. Cast `bool` yang sudah ada dipakai ulang; cast `string` dihapus bila tidak terpakai lagi.

**`password_hash`** sudah nullable. Grup otomatis menyimpan `null`.

## 2. Layanan `app/Services/FreeGroups.php` (baru)

`nextCredits(User): ?float`
- Hasilnya 70, 50, atau `null`.
- `null` bila fitur mati, user superadmin, atau `free_groups_used >= 2`.

`create(User, name, ?password, ?description): Group`
- Dijalankan dalam transaksi dengan `lockForUpdate` pada baris user, lalu syaratnya dicek ulang.
- Membuat grup berstatus `active` dengan `free_member_slots` = setelan saat itu.
- Owner jadi anggota lewat `GroupMembership` (bagian dari `activateGroup` dipecah menjadi `addOwner()` tanpa ledger pembayaran).
- Saldo awal diberikan lewat `NormkreditLedger::bonus()` / `CreditWallet::bonus()` yang sudah ada:
  - Sumber kontribusi baru `GroupTokenContribution::SOURCE_FREE_GROUP = 'free_group'`, label "Saldo awal gratis", harga 0, bukan pendapatan.
  - Jenis ledger tetap `bonus`.
- `free_groups_used` dinaikkan, dan dicatat AuditLog `group.create_free`.

`ensureFirstGroup(User): ?Group`
- Membuat grup "Grup {nama depan}" tanpa sandi bila `free_groups_used === 0`.
- Idempoten.

**Pemicu**
- Listener `Login` di `AppServiceProvider` memanggil `ensureFirstGroup`. Bila grup dibuat, id-nya disimpan di sesi `welcome_group_id`.
- `GroupController@index` juga memanggil `ensureFirstGroup`, supaya user lama yang masih punya sesi aktif ikut mendapat grup.
- `GroupController@index` lalu mengalihkan ke `chat.show` bila ada `welcome_group_id` (sekali saja).
- Login lewat tautan `/join/{kode}` tetap kembali ke halaman gabung, karena `intended` tidak dibajak.

## 3. Buat grup (`GroupController@create/store`, `groups/create.blade.php`)

- Bila `nextCredits()` ada: form menampilkan "Gratis · 50 Normkredit · 2 teman pertama gabung tanpa biaya", tombol "Buat grup gratis".
- `store` memanggil `FreeGroups::create` tanpa checkout, lalu langsung ke chat.
- Sandi tetap seperti form sekarang.
- Bila jatah habis: alur bayar 250 NK seperti sekarang.
- Semua kode bonus 20 NK dihapus dari `create`/`store`/view.
- `PaymentFulfillment` tetap membaca `bonus_credits` dari metadata, khusus untuk checkout yang dimulai sebelum deploy (kedaluwarsa 1 jam). Ditandai untuk dibersihkan nanti.

## 4. Gabung grup (`showJoin`/`joinViaShareId`, `JoinGroupRequest`, `groups/join.blade.php`)

- Hanya grup `active` yang bisa digabung. Ini sekaligus menutup celah: grup `pending_payment`/`frozen` saat ini masih bisa digabung dan dibayar.
- Bila `password_hash` kosong, kolom sandi disembunyikan dan tidak dicek.
- Bila `free_joins_used < free_member_slots`: tombol "Gabung gratis (sisa N slot)", tanpa pemilih paket.
  - Dalam transaksi: kunci baris grup, cek ulang slot.
  - Tambah anggota lewat `GroupMembership` (bagian anggota dari `activateJoin` tanpa ledger).
  - `free_joins_used + 1`, AuditLog `group.member_joined` dengan `free: true`.
- Bila tidak ada slot: patungan seperti sekarang.
- `packages` di `JoinGroupRequest` hanya wajib bila tidak ada slot gratis.

## 5. Halaman login di `/`

**Rute**
- `/` → `AuthController@landing`. Tamu melihat login; user yang sudah login diarahkan ke `groups.index`, dan `?next=` tetap dihormati.
- `/login` tetap, merender halaman yang sama.
- `/normchat` dialihkan ke `/`.
- `LandingController@home` dan `marketing/home.blade.php` dihapus. `loginUrl` di composer marketing menunjuk ke `/`.

**Tata letak `auth/landing.blade.php`** (gaya halaman login ChatGPT, memakai token tema aplikasi terang/gelap)
- Desktop: kiri berisi judul, sub-judul, demo chat kecil, dan tawaran gratis. Kanan berisi kartu login.
- HP: logo, judul, sub-judul singkat, tombol Google, tawaran gratis. Demo chat disembunyikan.
- Kaki halaman: "Tentang · Harga · Kontak" (ke halaman Inggris).
- Error `auth` yang sekarang tampil dua kali (di view dan di layout) diperbaiki.

**Copy** (skill copywriting + panduan suara di `normchat-copywriting-id.md`: "kamu", kalimat pendek, tanpa tanda pisah panjang, tanpa pola "Bukan X, tapi Y")

| Bagian | Teks |
|---|---|
| `<title>` | Normi Chat \| Grup chat dengan AI |
| Meta | Ajak teman ke satu grup dan tanya @normi bareng. Mulai gratis dengan 70 Normkredit. |
| Judul | **Dari obrolan jadi rencana.** |
| Sub-judul | Ajak teman ke satu grup, lalu minta @normi merangkum, menghitung, atau mencarikan ide. Semua melihat jawaban yang sama. |
| Tawaran | Grup pertamamu langsung siap dengan 70 Normkredit, gratis. 2 teman pertama yang kamu ajak juga bergabung tanpa biaya. |
| Demo chat | Sari: "Jadi fix berangkat Sabtu? Budget per orang berapa?" · Budi: "@normi rangkum dong" · Normi: "Berangkat Sabtu 07.00 dari kampus, patungan Rp150.000 per orang untuk 4 orang. Tinggal siapa yang bawa mobil yang belum diputuskan." |
| Kartu login | **Masuk ke Normi Chat** · Pakai akun Google-mu. · [Masuk dengan Google] · (Interdotz bila aktif) |
| Di bawah tombol | Gratis untuk mulai. Tanpa langganan. |

- Angka 70 dan 2 diambil dari setelan, bukan ditulis tetap di view.
- Catatan Syarat/Privasi tidak dipasang, karena halamannya belum ada.

**Situs Inggris**
- Jawaban FAQ "Is there a free plan?" di `marketing/pricing.blade.php` diperbarui: first group free with 70 NC + 2 friends, second 50 NC, lalu Rp30.000.
- CTA "Get started" ke `/`.

## 6. Kartu sambutan di chat

Tampil di ruang chat grup gratis untuk pemilik, selama `free_joins_used < free_member_slots` dan belum ditutup (sesi):

> Grup ini gratis dengan 70 Normkredit. Ajak 2 teman tanpa biaya pakai kode **ABC123**.

Tombolnya "Salin kode" dan "Tutup". Halaman Pengaturan grup menampilkan "Anggota gratis: 1 dari 2 terpakai" dan label "Gratis".

## 7. Superadmin

- Promo diganti **Grup gratis** (`AdminFreeGroupController`, route `superadmin.free-groups`, menu kelompok Uang).
- Isinya saklar, NK grup ke-1, NK grup ke-2, dan slot anggota gratis. Setiap simpan dicatat lewat `PricingVersions::record` + audit `admin.update_free_groups`.
- Ringkasan angka: grup gratis dibuat (ke-1/ke-2), anggota gratis bergabung, total NK gratis.
- Dihapus:
  - `AdminPromoController`
  - `superadmin/promo.blade.php`
  - route promo
  - label `admin.update_promo` diganti
- Keuangan: baris "Bonus" diganti "Saldo gratis X NK" (jumlah `free_group` + `group_creation_bonus` lama), tetap terpisah dari pendapatan.
- Detail grup dan daftar grup admin diberi label "Gratis".

## Berkas utama

- **Baru:**
  - `app/Services/FreeGroups.php`
  - `app/Http/Controllers/Admin/AdminFreeGroupController.php`
  - `resources/views/superadmin/free-groups.blade.php`
  - migrasi `2026_11_04_*`
  - `tests/Feature/FreeGroupsTest.php`
  - dokumen rancangan
- **Ubah:**
  - `GroupController.php`
  - `GroupMembership.php`
  - `NormkreditPricing.php`
  - `config/normchat.php`
  - `PaymentFulfillment.php`
  - `JoinGroupRequest.php`
  - `AuthController.php`
  - `LandingController.php`
  - `AppServiceProvider.php`
  - `routes/web.php`
  - views `auth/landing`, `groups/create`, `groups/join`, `chat/show` (kartu sambutan), `settings/show`, `superadmin/_menu`, `superadmin/finance`, `marketing/pricing`
  - `GroupTokenContribution.php`
  - `AdminActions.php`
  - `AdminReportController.php`
  - `tests/Feature/UxAdminFeaturesTest.php` (test bonus diganti)

## Uji

1. **Feature test** (dicek dengan mutasi):
   - Login pertama membuat 1 grup 70 NK tanpa sandi, lalu diarahkan ke chat. Login ulang tidak membuat grup lagi.
   - User lama dengan 1 grup → form buat grup gratis 50 NK. Dengan 2 grup → alur bayar.
   - Grup yang dihapus tetap dihitung. Superadmin tidak pernah dapat grup.
   - Gabung grup gratis: 2 orang pertama gratis, orang ke-3 diarahkan ke patungan. Anggota gratis keluar → slot tidak kembali.
   - Grup tanpa sandi bisa digabung dengan kode saja. Grup `pending_payment`/`frozen` tidak bisa digabung.
   - Setelan superadmin mengubah nilai untuk grup berikutnya saja.
   - Saldo gratis tidak dihitung pendapatan.
   - `/` untuk tamu = login, untuk user = `/groups`.
2. Suite lengkap (`php -d memory_limit=2G`).
3. Audit kontras 4 tema (tambah halaman login, form gratis, gabung gratis, kartu sambutan, Grup gratis admin) dan cek tanpa geser horizontal di 360/390 px.
4. E2E chat + riwayat.
5. Uji alur browser di server uji pribadi (nc-app):
   - tamu → login
   - user baru → grup otomatis → chat dengan kartu sambutan
   - 2 teman gabung gratis dengan kode
   - teman ke-3 melihat patungan
   - grup ke-2 gratis 50 NK
   - grup ke-3 ke pembayaran
   - superadmin ubah setelan
6. Tangkapan layar halaman login desktop/HP.
7. Pesan commit + perintah deploy (backup DB otomatis di `deploy-server.sh`, lalu recreate `app queue scheduler`).
