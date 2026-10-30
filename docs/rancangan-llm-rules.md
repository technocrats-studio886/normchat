# Rancangan penerapan llm-rules sebelum launch

Branch: `feat/llm-rules` · Sumber: `llm-rules.md` · Status: semua keputusan terjawab, menunggu persetujuan sebelum implementasi.

Dokumen ini memetakan rekomendasi `llm-rules.md` ke kode Normi Chat yang ada sekarang: mana yang sudah
berjalan, mana yang kurang, dan bagaimana kekurangannya dibangun.

## 0. Dua saldo yang berbeda (K9)

| | NormKredit (NC) | Saldo akun OpenAI |
|---|---|---|
| Milik | Grup (dibeli/dipatungi anggota) | Normi Chat (akun OpenAI milikmu) |
| Gunanya | Hak anggota memakai Normi di grup | Membayar setiap panggilan ke OpenAI |
| Berkurang saat | Anggota men-tag Normi: biaya nyata jawaban itu (termasuk token konteks yang ikut dikirim) × multiplier model | Setiap panggilan OpenAI, termasuk yang tidak ditagih ke user |
| Pekerjaan latar belakang (ringkasan, embedding, pemahaman pertanyaan) | Tidak memotong NC | Memotong saldo OpenAI |
| Dijaga oleh | `CreditWallet` (hold/settle), `ModelAccess` (500 NC Advanced), batas harian | `SpendGuard` (anggaran bulanan + saldo dan cadangan), kurs harian dari superadmin |

## 1. Posisi kode sekarang

| Aturan di llm-rules | Kondisi di kode | Status |
|---|---|---|
| Multiplier per kategori (Luna ×1,5, Sol ×2,5, Astra ×3) diterapkan ke biaya API aktual | `AiModel.multiplier`, `UsageMeter::weightedCostUsd()`, `NormkreditPricing::creditsForCost()` | Sudah |
| 500 NC hanya syarat akses Advanced, bukan batas biaya | `ModelAccess`: buka di 2× modal (500), tutup di bawah 1× (250), Advanced tidak boleh memakai 250 terakhir | Sudah |
| Estimasi + reservasi atomik sebelum request | `AiTask::planChat()` → `RequestBudget::plan()` → `CreditWallet::hold()` (row lock) | Sudah |
| Batas output per request | `max_output_tokens` 4.000 (superadmin), dipendekkan otomatis bila saldo kurang | Sudah |
| Tagih dari usage API: input, cached input, output, alat | `ChatCompletion` membaca `prompt_tokens_details.cached_tokens` / `input_tokens_details.cached_tokens`; web search & transkripsi ikut meter | Sudah |
| Kembalikan sisa reservasi | `CreditWallet::settle()` / `release()` | Sudah |
| NC pecahan | Kolom `decimal(14,4)`, tapi tagihan dibulatkan ke atas 0,01 dan minimal 0,01 | Sebagian |
| Ledger per transaksi lengkap (request id, tarif, saldo sebelum/sesudah) | `ai_usage_logs` punya token, biaya, kurs, multiplier, `balance_after`; tidak ada `balance_before`, request id OpenAI, tarif per token, dan setoran/penyesuaian ada di tabel lain | Sebagian |
| Konfigurasi harga berversi dengan tanggal berlaku | `app_settings` + `ai_models` ditimpa langsung; histori hilang | Belum |
| Anggaran global OpenAI (cadangan, peringatan 50/70/85%, berhenti di batas) | Hanya `daily_group_credit_limit` per grup; tidak ada batas total | Belum |
| Satu API key backend, konteks terisolasi per grup | Isolasi per `group_id` sudah benar. Tapi `OpenAiClient::forOwner()` masih mencoba koneksi AI / API key owner grup sebelum key server (warisan, tanpa UI) | Perlu dirapikan |
| Prompt stabil di depan agar cache kena | System prompt di depan, tapi riwayat = 16 pesan terakhir yang bergeser setiap ada pesan baru, jadi prefix berubah terus | Belum |
| Context Manager: ringkasan + pesan terbaru + pesan lama relevan + batas token | 16 pesan terakhir, tanpa batas token, tanpa ringkasan, tanpa pencarian | Belum |
| Context Understanding Manager: memahami maksud pertanyaan, menilai kecukupan konteks, mencari bila perlu | Tidak ada | Belum |
| Tiga lapisan memori: ringkasan grup, ringkasan topik, riwayat asli | Hanya riwayat asli | Belum |
| AI paham siapa bicara apa | Riwayat dikirim sebagai `role: user` tanpa nama pengirim | Belum |
| Metrik: token input/request, % cached, biaya per pesan | Superadmin melihat biaya & NC per model/grup; token cached tersimpan tapi tidak ditampilkan | Sebagian |

Skema Workspace/Team/Business di llm-rules belum final, jadi tidak masuk rancangan ini.

## 2. Alur satu permintaan @normi setelah perubahan

```
Pesan masuk ──► simpan (tanpa AI) ──► menyebut @normi / membalas Normi?
                                         │ tidak → selesai
                                         ▼ ya (antrean per grup, berurutan)
1. Kelayakan      model aktif, grup tidak beku, ModelAccess (500 NC Advanced), batas harian
2. Pemahaman      ContextUnderstanding: aturan dulu; Luna hanya bila pertanyaan ambigu (saldo OpenAI, bukan NC)
3. Konteks        ContextManager: ringkasan grup + jendela terbaru + ringkasan topik & pesan lama yang relevan
4. Estimasi       token input (perkiraan) + batas output → biaya provider USD → NC
5. Reservasi      SpendGuard (saldo OpenAI, USD)  lalu  CreditWallet::hold (NC grup)   — keduanya atomik
6. Panggil        OpenAI dengan key server, prompt_cache_key per grup
7. Selesaikan     usage API → biaya aktual per jenis token → NC; settle/release keduanya; tulis ledger
8. Pemeliharaan   bila ambang tercapai: antrekan SummarizeGroupContextJob (tidak menunda jawaban)
```

Pemicu jawaban (tag) dan pemicu pemeliharaan (ambang ringkasan) terpisah, sesuai llm-rules (K12):
obrolan tanpa tag hanya disimpan (+ embedding di latar belakang), tidak ada AI yang membaca setiap pesan masuk.
Pemahaman dibentuk dari dua hal: memori yang dipelihara berkala (ringkasan grup & topik, embedding) dan
penyusunan konteks saat Normi di-tag. Karena itu Normi langsung nyambung walau sebelumnya diam.

## 3. Anggaran global OpenAI (SpendGuard)

Tujuan: saldo OpenAI tidak pernah habis duluan karena banyak grup memakai Normi bersamaan. Saldo prabayar
OpenAI bisa minus sebelum request berhenti, jadi batasnya harus di aplikasi.

**Dua batas sekaligus (K1)** — request berbayar baru berhenti pada batas mana pun yang tercapai duluan:

1. **Anggaran bulanan:** superadmin mengisi USD per bulan; hitungan mulai lagi tiap tanggal 1 pukul 00.00 WIB.
2. **Saldo OpenAI + cadangan:** superadmin mengisi saldo prabayar OpenAI saat ini dan cadangan minimum
   (mis. sisakan $10). Sistem mengurangi saldo itu dengan setiap biaya aktual. Setiap kali top-up di OpenAI,
   superadmin mengisi saldo baru (tercatat siapa dan kapan). Dashboard menampilkan perkiraan sisa saldo agar
   bisa dicocokkan dengan halaman billing OpenAI.

**Data**

- `ai_spend_periods`: `period` (mis. `2026-11`), `budget_usd`, `spent_usd`, `reserved_usd`, `alerted_percent`.
  Satu baris per periode, dikunci `FOR UPDATE` saat reservasi.
- `ai_provider_balance`: `balance_usd` (saldo terakhir yang diisi), `reserve_usd`, `set_at`, `set_by`, `spent_since_set_usd`.
  Riwayat pengisian saldo disimpan di log audit.
- `credit_holds` + kolom `provider_usd_reserved` (estimasi biaya provider yang ditahan untuk request itu).
- Pengaturan superadmin: anggaran bulanan (USD), ambang peringatan (bawaan 50/70/85%), batas berhenti (bawaan 100%),
  saldo OpenAI dan cadangan.

**Aturan**

- Sebelum request, keduanya harus lolos:
  `spent + reserved + estimasi ≤ anggaran × batas` dan
  `saldo − spent_since_set − reserved − estimasi ≥ cadangan`.
  Bila salah satu gagal, request ditolak sebelum NC ditahan; penanya menerima pemberitahuan pribadi
  "Normi AI sedang tidak bisa dipakai. Coba lagi nanti." Tidak ada NC terpotong.
- Estimasi = biaya provider dengan input perkiraan dan output maksimal (sama dengan dasar hold NC), ditambah
  perkiraan alat (gambar, web search, transkripsi) dari `config('normchat.*')`.
- Setelah request: `spent += biaya aktual`, `reserved -= estimasi`. Request gagal: hanya `reserved -= estimasi`.
- Semua biaya provider ikut dihitung, termasuk ringkasan konteks (bagian 6) walaupun tidak ditagih ke grup.
- Tahanan yang macet (job mati) dilepas oleh perintah terjadwal setelah 15 menit, untuk NC maupun USD.

**Peringatan (K2): banner + email**

- Ambang dihitung untuk kedua batas: persen anggaran bulanan terpakai, dan persen saldo OpenAI di atas cadangan terpakai.
- Saat melewati 50/70/85% dan saat berhenti di 100%: sekali per ambang per periode (atau per pengisian saldo)
  → catatan log audit, banner di dashboard superadmin (hilang setelah anggaran/saldo diperbarui), dan email ke
  semua alamat `NORMCHAT_SUPERADMIN_EMAILS`.
- Email dikirim lewat antrean (tidak menunda request). **Prasyarat deploy:** SMTP di `.env` produksi
  (`MAIL_MAILER=smtp`, host, port, user, password, alamat pengirim). Selama masih `log`, email hanya tercatat di log
  dan banner tetap jalan. Halaman pengaturan superadmin punya tombol "Kirim email uji".

### 3.1 Rekonsiliasi harian dengan OpenAI (K15)

Biaya yang dihitung sistem dari usage per request bisa sedikit beda dengan tagihan nyata. Tiap hari pukul 07.00 WIB,
`normchat:reconcile-openai-costs` mengambil biaya nyata hari sebelumnya dari OpenAI Costs API
(`GET /v1/organization/costs`, per hari UTC, difilter `project_ids` = project Normi Chat).

- Kunci: `OPENAI_ADMIN_KEY` (Admin API key, hanya untuk endpoint biaya, tidak pernah dipakai memanggil model) dan
  `OPENAI_PROJECT_ID` di `.env`. Filter project penting bila akun OpenAI juga dipakai aplikasi lain.
- Tabel `ai_cost_reconciliations`: `date` (UTC), `computed_usd` (jumlah `provider_cost_usd` hari itu),
  `openai_usd`, `diff_usd`, `diff_percent`, `applied_at`.
- Selisih diterapkan ke SpendGuard: `spent_usd` periode dan `spent_since_set_usd` saldo OpenAI dikoreksi ke angka
  nyata. Tagihan NC yang sudah terjadi tidak diubah.
- Selisih > 5% → banner + email (indikasi tarif di versi harga sudah tidak sesuai).
- Data OpenAI bisa terlambat beberapa jam; bila hari kemarin belum lengkap, dicoba lagi tiap jam sampai pukul 23.00.
- Bila Admin key tidak diisi, rekonsiliasi dilewati dan dashboard menulis "Rekonsiliasi nonaktif".

## 4. Harga berversi dan ledger

### 4.1 `pricing_versions`

Setiap kali superadmin menyimpan pengaturan harga atau tarif/multiplier model, dibuat versi baru (bukan menimpa):

- `effective_from`, `created_by`, `note`
- `settings` (json): `rupiah_per_credit`, `card_margin_percent`, `media_multiplier`, `max_output_tokens`, `max_credits_per_request`, …
  (kurs sendiri ada di `exchange_rates`, bagian 4.3)
- `models` (json): per `model_id` → tarif input, cached input, output (USD/1 jt token), multiplier, ambang buka/tutup

`NormkreditPricing` dan `AiModel` tetap dipakai kode seperti sekarang; yang berubah sumber nilainya adalah versi aktif.
Halaman superadmin menampilkan riwayat versi (siapa, kapan, apa yang berubah).

### 4.2 `credit_ledger` (satu pintu, ditulis oleh `CreditWallet`)

Semua perubahan saldo NC grup tercatat di satu tabel, di dalam transaksi DB yang sama dengan perubahannya:

| Kolom | Isi |
|---|---|
| `type` | `opening`, `deposit`, `hold`, `settle`, `release`, `admin_adjust` |
| `amount` | NC bertanda (+ masuk, − keluar) |
| `balance_before`, `balance_after` | saldo tersedia grup |
| `reserved_before`, `reserved_after` | saldo yang ditahan |
| `group_id`, `user_id`, `credit_hold_id`, `ai_usage_log_id`, `contribution_id`, `admin_action` | rujukan |
| `pricing_version_id` | versi harga saat transaksi |

`ai_usage_logs` ditambah: `provider_request_id` (`id` respons OpenAI), `balance_before`, `pricing_version_id`,
`cache_hit_percent` (dihitung saat simpan), dan `breakdown_json` menyimpan tarif per jenis token yang dipakai.
Dengan ini satu transaksi bisa diaudit ulang tanpa melihat pengaturan saat ini.

**Saldo awal (K17):** saat migrasi, setiap grup yang sudah punya saldo mendapat satu baris `opening`
(dari 0 ke saldo saat itu), sehingga total ledger = saldo untuk semua grup, termasuk grup lama.
Ledger belum punya tampilan; isinya untuk audit di database.

### 4.3 Kurs USD→IDR diisi superadmin (K8, direvisi)

Open Exchange Rates ternyata berbayar, jadi kurs tidak diambil otomatis. Superadmin mengisinya manual setiap hari.

- **Tabel `exchange_rates`:** satu baris per pengisian — `market_rate` (diisi superadmin), `card_margin_percent`,
  `applied_rate` (= `market_rate` × (1 + selisih kartu)), `set_by`, `note`, `created_at`. Tidak pernah ditimpa.
  Pengisian juga tercatat di log audit.
- **Tanpa acuan dari luar:** tidak ada panggilan ke layanan kurs; superadmin mencari kurs sendiri.
- **Selisih kartu (K8b): tetap +2%.** Superadmin mengisi kurs pasar; sistem memakai kurs × 1,02. Persentasenya ada di
  pengaturan harga dan ikut versi harga (4.1).
- **Lupa memperbarui:** kurs terakhir tetap dipakai, tagihan NC tetap jalan. Lewat 24 jam tanpa pengisian baru →
  banner + email ke superadmin (dicek tiap jam oleh scheduler, sekali per keterlambatan).
- **Belum pernah diisi:** dipakai `NORMCHAT_USD_TO_IDR` dari `.env` apa adanya (tanpa selisih kartu), dan dashboard
  menampilkan "Kurs belum pernah diisi".
- **Batas wajar isian:** 5.000–50.000 per USD; isian yang beda > 10% dari kurs terakhir meminta konfirmasi ulang di form
  (mencegah salah ketik nol).
- **Konsistensi tagihan:** kurs dikunci saat hold. Hold, settle, `ai_usage_logs.usd_to_idr`, dan ledger memakai kurs
  yang sama (`exchange_rate_id` disimpan), walau kurs diganti di tengah request.
- Halaman superadmin: kurs dipakai, kurs pasar yang diisi, selisih kartu, siapa & kapan mengisi, riwayat 30 hari.
- `pricing_versions.settings` tidak menyimpan kurs; yang berversi adalah persentase selisih kartu.

### 4.4 Presisi (K6)

`creditsForCost()` tidak lagi membulatkan ke atas 0,01 dan tidak punya minimum 0,01: tagihan dihitung sampai
0,0001 NC (dibulatkan ke atas pada digit keempat), sama dengan presisi kolom saldo. Tampilan ke user tetap 2 desimal;
tagihan di bawah 0,01 tampil "< 0,01 NC". Hold juga memakai 4 desimal.

## 5. Context Manager dan Context Understanding Manager

### 5.1 Komponen (modul di backend yang sama, bukan layanan terpisah)

Pembagian mengikuti llm-rules: Context Storage (menyimpan), Context Manager (memilih & menyusun),
Context Understanding Manager (memahami maksud & relevansi), Summary Worker (memelihara memori).

| Komponen di llm-rules | Di kode |
|---|---|
| Context Storage | `messages` (sudah) + `message_embeddings`, `group_context_summaries`, `group_context_topics` (baru) |
| Trigger Handler | `Message::isAddressedToAi()` + `ChatMessageQueue` (sudah) |
| Context Understanding Manager | `App\Services\Ai\Context\ContextUnderstanding` (baru, 5.5) |
| Context Manager | `App\Services\Ai\Context\ContextManager` (baru) — menggantikan `CONTEXT_MESSAGES = 16` |
| Summary Worker | `App\Jobs\SummarizeGroupContextJob` (baru, 5.4) |
| Retrieval Service | `App\Services\Ai\Context\HistorySearch` (baru, 5.6) |
| Prompt Builder | `PromptBuilder` (diubah: urutan, nama pengirim) |
| AI Gateway | `OpenAiClient` (diubah: key server saja, `prompt_cache_key`, request id) |
| NormKredit Service | `CreditWallet` + `SpendGuard` (baru) |

### 5.2 Susunan prompt (urutan tetap, demi cache)

Bagian yang jarang berubah di depan, bagian yang berbeda tiap pertanyaan di belakang:

1. Instruksi Normi umum — identik untuk semua grup, tanpa tanggal/jam.
2. Persona grup (gaya bicara, batasan) — berubah hanya saat owner/admin mengubahnya.
3. Ringkasan grup — berubah hanya saat Summary Worker berjalan.
4. Jendela riwayat — dimulai dari jangkar tetap (5.4), lalu bertambah di ujung.
5. Konteks tambahan untuk pertanyaan ini — ringkasan topik yang relevan + potongan pesan lama hasil pencarian.
   Diletakkan setelah jendela karena isinya berbeda per pertanyaan; bila di depan, cache selalu meleset.
6. Pesan yang men-tag Normi (+ kutipan balasan, lampiran, gambar), diawali tanggal hari ini.

`prompt_cache_key = "group-{id}"`. Pesan grup A tidak pernah masuk prompt grup B: semua query konteks
memakai `group_id`, tidak menyertakan pesan terhapus untuk semua (`deleted_at`) dan pemberitahuan pribadi
(`visible_to_user_id`).

### 5.3 Anggaran token konteks (awal, bisa diubah superadmin)

| Bagian | Batas |
|---|---|
| Instruksi + persona | ±1.000 |
| Ringkasan grup | ≤ 600 |
| Jendela riwayat | ≤ 6.000 |
| Ringkasan topik relevan (maks. 2 topik) | 0 – 800 |
| Pesan lama relevan | 0 – 2.000 |
| Target total input (tanpa lampiran pesan yang men-tag) | ±10.500 |

Bila melewati batas, yang dibuang lebih dulu: pesan lama relevan berskor rendah, lalu topik kedua, lalu pesan
paling awal di jendela.

**Batas keras input per permintaan (K21):** 16.000 token (pengaturan harga superadmin, ikut versi harga). Dihitung
dari pesan final yang dikirim ke model, termasuk dokumen dan gambar pada pesan yang men-tag. Bila lewat, konteks
dipotong berurutan: pesan lama hasil pencarian, lalu topik, lalu pesan paling awal di jendela, lalu ringkasan grup.
Bila pesan yang men-tag sendiri sudah melewati batas, Normi menolak dengan pemberitahuan pribadi
"Pesan atau lampirannya terlalu panjang untuk dijawab Normi" tanpa menahan NC maupun saldo OpenAI.
Pemotongan selalu per pesan utuh, tidak di tengah kalimat. Isi dokumen di riwayat tetap dipotong 1.500 karakter
seperti sekarang; dokumen pada pesan yang men-tag tetap sampai 16.000 karakter.

### 5.4 Memori tiga lapis, Summary Worker, dan jangkar jendela (K11)

| Lapisan | Isi | Dipakai |
|---|---|---|
| Ringkasan grup | Tujuan grup, keputusan penting lintas topik, fakta & batasan jangka panjang, siapa memegang apa | Hampir setiap tag |
| Ringkasan topik | Per pembahasan (mis. "Arsitektur CNN", "Anggaran acara"): konteks, keputusan, fakta, pertanyaan terbuka | Saat topik itu aktif atau dirujuk |
| Riwayat asli | Pesan di `messages` | Jendela terbaru + hasil pencarian |

**Tabel**

- `group_context_summaries` (ringkasan grup + status ingatan; satu baris per grup):
  `summary`, `covered_until_message_id`, `window_start_message_id`, `source_message_count`, `model`,
  `input_tokens`, `output_tokens`, `provider_cost_usd`, `stale_at`, `embedded_until_message_id`, `rebuild_requested_at`.
- `group_context_topics`: `group_id`, `title`, `summary`, `first_message_id`, `last_message_id`,
  `key_message_ids` (json: pesan sumber keputusan/fakta penting, supaya detail presisi bisa diambil dari pesan asli),
  `embedding` (vektor judul + ringkasan, untuk memilih topik), `last_activity_at`, `stale_at`.
- Semua teks biasa, tidak dienkripsi.

**Isi yang diminta ke model** (format JSON tetap, structured output): untuk grup dan tiap topik — konteks, keputusan
yang disepakati, fakta & batasan dari anggota, pertanyaan yang belum selesai. Yang hanya disimpulkan Normi ditandai
terpisah dari yang dinyatakan anggota. Ringkasan tidak menggantikan pesan asli: `key_message_ids` selalu disimpan.

**Satu panggilan Luna per pembaruan** (bukan per topik). Masukan: ringkasan grup lama + judul 30 topik terakhir +
ringkasan 10 topik yang paling baru aktif + pesan baru (≤ 6.000 token per cicilan). Keluaran: ringkasan grup baru,
daftar topik yang diperbarui (id lama) atau dibuat (id baru) beserta rentang dan pesan sumbernya. Topik yang tidak
disebut tidak berubah. Topik tanpa pesan 30 hari tetap tersimpan tapi tidak lagi diberikan sebagai "topik aktif".

Cara kerja:

- **Grup kecil (belum ada ringkasan, riwayat muat dalam 6.000 token):** semua pesan dikirim apa adanya. Tidak perlu menunggu 100 pesan.
- **Jendela:** pesan dari `window_start_message_id` sampai terbaru. Jangkar ini tetap sampai ringkasan berikutnya,
  jadi prefix prompt hanya bertambah di ujung dan cache bisa kena. Saat ringkasan diperbarui, jangkar dipindah ke
  30 pesan terakhir saat itu, sehingga rujukan seperti "itu" dan "tadi" tetap punya pesan aslinya.
- **Pemicu pembaruan:** pesan sejak jangkar ≥ 100, atau tokennya melewati 6.000. Dicek saat Normi di-tag;
  job diantrekan setelah jawaban terkirim. Grup yang tidak pernah men-tag Normi tidak pernah diringkas.
- **Grup yang sudah ada saat launch (K14):** tidak dibangun di muka. Tag pertama setelah deploy dijawab dengan jendela
  terbaru + pencarian kata kunci, lalu `SummarizeGroupContextJob` (dari awal) dan `EmbedGroupHistoryJob` diantrekan.
  Jawaban berikutnya memakai memori lengkap.
- **Saat job berjalan:** permintaan berikutnya memakai ringkasan lama + pesan setelah cakupannya (tidak menunggu).
- **Masukan job:** ringkasan lama + pesan setelah `covered_until_message_id`, dicicil per 6.000 token bila banyak.
- **Model ringkasan:** kategori termurah yang aktif (sekarang Luna).
- **Biaya (K3):** ditanggung Normi, tidak memotong NC grup. Tetap masuk SpendGuard (anggaran & saldo OpenAI) dan
  tercatat di `ai_usage_logs` dengan `kind = context_summary`, `charged_credits = 0`, supaya tampil di metrik superadmin.
  Bila SpendGuard menolak, ringkasan ditunda; jawaban Normi tetap memakai ringkasan lama + jendela.
- **Pesan dihapus untuk semua / diedit setelah diringkas (K4): bangun ulang segera.**
  Saat pesan dengan `id ≤ covered_until_message_id` dihapus untuk semua atau diedit, ringkasan aktif langsung diberi
  `stale_at` dan tidak dipakai lagi, begitu juga topik yang rentangnya memuat pesan itu. `SummarizeGroupContextJob`
  diantrekan dalam mode "dari awal": ringkasan grup dan semua topik grup itu dihapus, lalu dibangun ulang dari semua
  pesan yang masih ada (isi terbaru, tanpa yang terhapus), dicicil per 6.000 token. Selama job berjalan Normi memakai
  jendela terbaru + pencarian (topik yang tidak basi tetap boleh dipakai). Beberapa penghapusan/edit beruntun digabung
  menjadi satu job (debounce 1 menit). Pemulihan dari cadangan juga memicu bangun ulang. Ringkasan dan topik lama yang
  memuat isi terhapus dihapus dari tabel, bukan hanya ditandai.

### 5.5 Context Understanding Manager (K10: hibrida)

Tugasnya: saat Normi di-tag, memahami apa yang sebenarnya ditanyakan, menilai apakah ringkasan + jendela sudah
cukup, dan bila belum, menentukan apa yang harus dicari. Hasilnya diberikan ke Context Manager.

**Langkah 1 — aturan backend (selalu, tanpa biaya):**

- Bila seluruh riwayat grup sebelum pesan ini muat di jendela → selesai, tidak perlu mencari (grup 10/50/99 pesan).
- Pesan yang dibalas (reply) berada di luar jendela → pesan itu + 1 sebelum/sesudah selalu diambil.
- Kata rujukan masa lalu/keputusan: "kemarin", "minggu lalu", "dulu", "sebelumnya", "akhirnya", "keputusan",
  "kesepakatan", "yang mana", "kenapa kita", "yang kedua", "yang tadi", "itu", "tersebut", …
- Kata kunci (setelah membuang kata umum bahasa Indonesia) yang tidak muncul di jendela.
- Judul topik yang disebut langsung di pertanyaan.
- Pertanyaan umum yang tidak merujuk obrolan ("apa arti optimizer?") → cukup ringkasan grup + jendela.

**Langkah 2 — panggilan Luna, hanya bila ambigu:** dijalankan bila ada riwayat di luar jendela **dan** pertanyaan
memakai kata rujukan yang tidak bisa dipetakan oleh aturan (tidak ada kata kunci yang cocok di jendela atau judul topik).
Masukan kecil (±1.500 token): pertanyaan, 10 pesan terakhir jendela, judul + satu kalimat topik-topik grup.
Keluaran JSON:

```json
{
  "maksud": "menanyakan alasan grup memilih teknologi kedua (PostgreSQL) dibanding MongoDB",
  "perlu_konteks_lama": true,
  "kata_pencarian": ["alasan pilih PostgreSQL", "perbandingan MongoDB PostgreSQL"],
  "topik": [12],
  "kejelasan": "jelas"
}
```

- `kata_pencarian` dipakai Retrieval (5.6) sebagai pengganti kata kunci mentah; `maksud` ikut ke prompt sebagai
  catatan untuk model utama ("Maksud pertanyaan menurut konteks: …").
- Biaya: saldo OpenAI, tidak memotong NC, masuk SpendGuard, tercatat `kind = context_understanding`.
- Batas waktu 8 detik. Bila gagal, ditolak SpendGuard, atau waktu habis → lanjut dengan hasil aturan saja.
- Menambah ±1–2 detik hanya pada pertanyaan ambigu; pertanyaan biasa tidak terpengaruh.

**Langkah 3 — pemilihan topik:** topik dipilih dari (a) `topik` hasil Luna, (b) judul yang disebut langsung,
(c) kemiripan embedding pertanyaan dengan embedding topik (ambang skor), dan (d) topik yang sedang berjalan bila
awal pembahasannya sudah di luar jendela. Maksimal 2 topik.

**Langkah 4 — kecukupan:** bila setelah pencarian tidak ada yang relevan dan `kejelasan` = ambigu, prompt diberi
catatan agar Normi meminta klarifikasi singkat, bukan mengarang atau mengambil topik lain.

### 5.6 Pencarian pesan lama (K5: kata kunci + semantik)

**Kapan dijalankan:** hanya bila Context Understanding Manager memutuskan perlu (5.5). Tidak untuk setiap pesan.

**Dua jalur, digabung:**

1. Kata kunci: indeks full-text Postgres `GIN (to_tsvector('simple', content))` pada `messages`, peringkat `ts_rank`.
   Frasa pencarian (termasuk dari Luna) dipecah jadi kata bermakna dan digabung OR; pesan yang cocok dengan lebih
   banyak kata naik peringkatnya. Kata umum dan kata penilaian ("benar", "salah", "bagus", …) tidak dihitung kata kunci.
2. Semantik: embedding pesan disimpan di Postgres dengan ekstensi pgvector; pertanyaan di-embed saat pencarian,
   dicari dengan jarak kosinus (indeks HNSW).

Hasil kedua jalur digabung dengan Reciprocal Rank Fusion, diambil ≤ 8 pesan teratas beserta 1 pesan sebelum dan
sesudahnya, maksimal 2.000 token, ditandai di prompt sebagai "Potongan riwayat lama yang mungkin relevan (dengan
tanggal)". Bila tetap tidak ketemu, Normi diinstruksikan mengakui tidak tahu atau meminta klarifikasi.

**Cakupan dan isolasi:** grup itu saja, pesan sebelum jangkar jendela, tidak terhapus, bukan pemberitahuan pribadi.
Filter `group_id` ada di query, bukan disaring setelahnya.

**Embedding**

- Tabel `message_embeddings`: `message_id` (unik), `group_id`, `embedding vector(n)`, `model`, `content_hash`, `created_at`.
- Model embedding OpenAI termurah yang aktif; tarifnya masuk versi harga (bagian 4.1).
- Biaya dari saldo OpenAI seperti ringkasan: tidak memotong NC, masuk SpendGuard, tercatat `kind = embedding`.
- Hanya grup yang memakai Normi: saat grup pertama kali men-tag Normi, riwayatnya di-embed bertahap di latar belakang
  (`EmbedGroupHistoryJob`, per 100 pesan). Setelah itu pesan baru di grup yang memakai Normi dalam 30 hari terakhir
  di-embed per batch tiap menit (`EmbedNewMessagesJob`). Pesan kosong, notice, dan lampiran tanpa teks dilewati;
  dokumen memakai 1.500 karakter pertama hasil ekstraksi, voice note memakai transkrip yang sudah ada.
- Pesan dihapus untuk semua → embedding-nya dihapus. Pesan diedit → `content_hash` berubah, di-embed ulang.
- Bila SpendGuard menolak, embedding ditunda; pencarian tetap jalan dengan kata kunci saja.
- Tidak dienkripsi (konsisten dengan keputusan isi chat tanpa enkripsi).

**Infrastruktur:** `postgres:16-alpine` tidak berisi pgvector. Image diganti dengan image buatan sendiri
`FROM postgres:16-alpine` + pgvector yang dikompilasi (versi dipatok), **bukan** `pgvector/pgvector:pg16` (berbasis
Debian/glibc): pindah dari musl ke glibc mengubah collation dan bisa merusak indeks teks yang sudah ada.
Data volume tetap dipakai (major version sama). Deploy: backup DB → build image → recreate postgres →
`CREATE EXTENSION vector` lewat migrasi. Uji dulu di salinan volume produksi.

**Pesan sumber topik (K18):** setiap topik yang dipilih membawa pesan sumbernya (`key_message_ids`, maks. 5 terbaru
per topik) sebagai potongan riwayat lama, berbagi anggaran 2.000 token dengan hasil pencarian. Diberikan juga bila
topik dipilih tanpa pencarian. Hanya pesan sebelum jendela, tidak terhapus, bukan pemberitahuan pribadi.

**Sumber jawaban sebelumnya (K23):** setiap jawaban Normi mencatat id pesan lama yang dipakainya
(`ai_usage_logs.breakdown_json.context.sources`, hanya angka). Selama jawaban Normi terakhir masih di jendela, sumber itu
ikut lagi di pertanyaan berikutnya (di atas pesan sumber topik, dalam anggaran 2.000 token), supaya pertanyaan lanjutan
tidak membantah jawaban yang sudah benar.

**Rentang waktu (K19, prioritas, bukan saringan):** kata waktu di pertanyaan diubah jadi rentang menurut zona waktu
penanya: "hari ini", "tadi pagi/siang/sore", "tadi malam/semalam", "kemarin", "kemarin lusa", "N hari lalu",
nama hari ("Senin" = Senin terakhir sebelum hari ini), "minggu lalu" (Senin–Minggu pekan sebelumnya),
"bulan lalu", "tanggal N" (tanggal N terakhir yang sudah lewat). Pesan di rentang itu menjadi daftar peringkat
ketiga di RRF dan hasil kata kunci/semantik yang jatuh di rentang mendapat tambahan skor; pesan di luar rentang
tetap bisa muncul bila sangat relevan. Pertanyaan yang hanya menyebut waktu ("kemarin bahas apa?") mengambil
pesan di rentang itu.

### 5.7 Konteks "per saat pesan itu"

Konteks selalu dibangun per pesan yang men-tag Normi, bukan per saat job berjalan:

- Jendela dan pencarian hanya memakai pesan dengan `id` lebih kecil dari pesan penanya. Ini penting untuk
  "Simpan dan jawab ulang" pada pesan lama: Normi tidak melihat percakapan yang terjadi sesudahnya.
- Ringkasan grup dan ringkasan topik hanya dipakai bila cakupannya berakhir sebelum pesan penanya; bila tidak,
  cukup jendela + pencarian.
- Semua mode (chat, Guided Learning, Canvas, Deep Research, dokumen, perencana gambar) memakai `ContextUnderstanding`
  + `ContextManager` yang sama.
- Pesan diteruskan tampil dengan label "(diteruskan dari X)"; polling tampil sebagai pertanyaan + pilihannya.

### 5.8 Nama pengirim di riwayat

Setiap pesan anggota di riwayat diberi awalan nama: `Noel: Kita coba pakai AdamW ...`. Jawaban Normi tetap
`role: assistant` tanpa awalan. Pesan yang men-tag Normi diawali `Noel (bertanya ke Normi): ...`.
Ini yang membuat Normi bisa menjawab "menurut kalian solusi yang tadi paling bagus yang mana?" dengan benar.

### 5.9 Privasi ingatan Normi (K16)

- Anggota, admin, dan owner grup tidak melihat ringkasan grup/topik pada launch; ingatan bekerja di belakang layar.
- Superadmin juga tidak (aturan G1: superadmin tidak membaca isi chat). Dashboard hanya menampilkan jumlah dan biaya.
- `ai_usage_logs`, ledger, log audit, dan log aplikasi tidak menyimpan teks ringkasan, topik, `maksud`, atau
  `kata_pencarian`; hanya token, biaya, dan id.
- "Hapus untuk saya" tidak mengubah ingatan (pesannya tetap ada untuk anggota lain). "Hapus untuk semua", penghapusan
  oleh moderator, dan edit memicu bangun ulang (K4).
- Grup dihapus → ringkasan, topik, dan embedding grup itu ikut dihapus. Grup dipulihkan superadmin → ingatan dibangun
  ulang saat tag pertama berikutnya.

## 6. Hal kecil yang ikut dirapikan

- `OpenAiClient::forOwner()` diganti `OpenAiClient::server()` (K7): semua request memakai `OPENAI_API_KEY` server.
  Koneksi AI dan API key owner tidak dibaca lagi; kolom dan tabelnya dibiarkan di DB pada rilis ini.
- Batas output bawaan tetap 4.000 token (K13), bisa diubah superadmin; Canvas dan Deep Research tetap memakai batasnya
  sendiri. Saran 2.000 di llm-rules tidak dipakai karena sisa hold selalu dikembalikan saat settle.
- `estimateInputTokens()` memakai ±3,5 karakter per token (teks Indonesia lebih boros dari Inggris) supaya hold
  jarang kurang.
- Notice "Normkredit grup habis" setelah jawaban tetap seperti sekarang.

## 7. Metrik di superadmin (halaman Keuangan & AI)

- Pemakaian anggaran OpenAI periode ini: terpakai, ditahan, sisa, ambang yang sudah lewat.
- Per model: request, token input rata-rata/request, % input tercache, biaya provider, NC terbakar, margin.
- Pemeliharaan konteks (dari saldo OpenAI, bukan NC): jumlah & biaya ringkasan, embedding, dan panggilan pemahaman;
  persentase tag yang memicu pencarian dan yang memicu panggilan Luna.
- Request ditolak: karena saldo grup, karena kunci Advanced, karena anggaran global.
- Riwayat versi harga.
- Rekonsiliasi: biaya hitungan sistem vs biaya nyata OpenAI per hari, selisih dan persentasenya.
- Biaya OpenAI rata-rata per jawaban, per model (K20).
- Normi gagal mengingat (K20): per jawaban dicatat otomatis bila Normi diminta klarifikasi karena rujukan tidak
  ketemu, atau pertanyaan merujuk pembicaraan sebelumnya (kata rujukan, kata waktu, membalas pesan lama) tapi
  pencarian kosong. Pertanyaan umum yang tidak ketemu tidak dihitung. Jumlah dan persen per minggu. Tanpa isi chat.
- Biaya OpenAI yang tidak ditagih: pemeliharaan ingatan + permintaan yang ditolak setelah ada biaya (`kind = refused`).
  Laporan "permintaan/jawaban" (dashboard, per model, per grup, detail grup) tidak menghitung keduanya.

## 8. Urutan pengerjaan dan uji

1. Image Postgres + pgvector (alpine), diuji di salinan volume produksi.
2. Migrasi: `pricing_versions`, `exchange_rates`, `credit_ledger`, `ai_spend_periods`, `ai_provider_balance`,
   kolom baru `credit_holds` & `ai_usage_logs`, `group_context_summaries`, `group_context_topics`, `message_embeddings`, indeks full-text
   `messages`. Versi harga pertama diisi dari pengaturan sekarang; kurs pertama dari `.env`.
3. `OpenAiClient::server()` + `prompt_cache_key` + request id.
4. Ledger di `CreditWallet` + presisi 0,0001. Uji: saldo sebelum/sesudah cocok di setiap jenis transaksi; dua request paralel.
5. Kurs harian manual. Uji: selisih kartu diterapkan, isian di luar batas ditolak, beda > 10% minta konfirmasi,
   pengingat sekali setelah 24 jam, `.env` dipakai bila belum pernah diisi, kurs terkunci saat hold.
6. `SpendGuard` + peringatan. Uji: tolak di batas anggaran dan di batas cadangan saldo, peringatan sekali per ambang,
   email masuk antrean, tahanan macet dilepas. Rekonsiliasi harian: respons Costs API tiruan, koreksi saldo,
   selisih > 5% memicu peringatan, Admin key kosong → dilewati.
7. Versi harga + tampilan superadmin. Uji: log lama tetap memakai tarif versinya.
8. `ContextManager` + perubahan `PromptBuilder` (nama, urutan, jangkar, konteks per pesan). Uji: isolasi grup,
   pesan terhapus & notice tidak masuk, batas token, grup 10/50/99 pesan, jawab ulang pesan lama.
9. `SummarizeGroupContextJob` (ringkasan grup + topik). Uji: ambang 100, jangkar berpindah, topik dibuat/diperbarui,
   permintaan saat job berjalan, bangun ulang saat hapus/edit, JSON rusak dari model ditolak tanpa merusak memori lama.
10. Embedding + `HistorySearch`. Uji: hanya grup sendiri, hanya sebelum jangkar, batas 2.000 token, embedding terhapus
    ikut pesan, jalan dengan kata kunci saja bila anggaran habis.
11. `ContextUnderstanding`. Uji: grup kecil tidak mencari; reply ke pesan lama; pertanyaan umum tidak mencari;
    pertanyaan ambigu memanggil Luna sekali; Luna gagal/timeout → aturan saja; skenario llm-rules (AdamW,
    "teknologi yang kedua", "solusi yang tadi") dijalankan sebagai uji percakapan.
12. Metrik superadmin, audit kontras, E2E chat, lalu perintah deploy.

**Prasyarat dari user sebelum deploy:** SMTP produksi, angka anggaran bulanan,
saldo OpenAI saat ini + cadangan, Admin API key dan project ID OpenAI (rekonsiliasi).

## 9. Keputusan

| # | Pertanyaan | Jawaban |
|---|---|---|
| K1 | Bentuk anggaran global OpenAI | Keduanya: anggaran bulanan + saldo OpenAI dengan cadangan (bagian 3) |
| K2 | Kanal peringatan anggaran | Banner superadmin + email; SMTP produksi jadi prasyarat (bagian 3) |
| K3 | Siapa membayar biaya ringkasan konteks | Ditanggung Normi, tetap masuk anggaran global (5.4) |
| K4 | Pesan dihapus untuk semua / diedit yang sudah masuk ringkasan | Bangun ulang segera (5.4) |
| K5 | Tingkat pencarian pesan lama untuk launch | Kata kunci + semantik (pgvector) (5.5) |
| K6 | Presisi tagihan NC | 0,0001 NC, tampilan 2 desimal (4.4) |
| K7 | Koneksi AI / API key milik owner grup | Selalu key server (6) |
| K8 | Kurs USD→IDR | Diisi manual superadmin tiap hari, tanpa acuan luar, selisih kartu +2%, pengingat bila > 24 jam (4.3) |
| K9 | NC vs saldo OpenAI | NC hanya terpotong saat tag (termasuk token konteks); pemeliharaan konteks memotong saldo OpenAI saja (0) |
| K10 | Cara Context Understanding Manager memahami pertanyaan | Hibrida: aturan dulu, Luna hanya bila ambigu (5.5) |
| K11 | Lapisan memori | Ringkasan grup + ringkasan topik + riwayat asli (5.4) |
| K12 | Kapan Normi memahami obrolan | Saat di-tag + pemeliharaan berkala; tidak membaca setiap pesan (2) |
| K13 | Batas output bawaan | Tetap 4.000 token (6) |
| K14 | Memori grup lama saat launch | Dibangun saat tag pertama setelah deploy (5.4) |
| K15 | Rekonsiliasi dengan biaya nyata OpenAI | Harian lewat Costs API + Admin key (3.1) |
| K16 | Anggota melihat/mengatur ingatan Normi | Tidak untuk launch (5.9) |
| K17 | Saldo grup yang sudah ada sebelum ledger | Baris `opening` per grup saat migrasi (4.2) |
| K18 | Pesan sumber topik saat menjawab | Ikut, maks. 5 per topik, dalam anggaran 2.000 token (5.6) |
| K19 | Kata waktu di pertanyaan | Prioritaskan rentangnya, bukan saringan ketat (5.6) |
| K20 | Metrik gagal mengingat & biaya per pesan | Tanda otomatis per jawaban + biaya rata-rata per jawaban (7) |
| K21 | Batas keras input per permintaan | 16.000 token, potong konteks dulu, tolak bila pesannya sendiri lewat (5.3); isi Canvas yang direvisi tidak dihitung |
| K22 | Voice note di riwayat | Maks. 5 voice note terbaru tanpa transkrip ditranskripsi per pertanyaan; sisanya penanda pesan suara (10) |
| K23 | Pertanyaan lanjutan membantah jawaban Normi sebelumnya (uji alur 30 Okt) | Sumber jawaban terakhir ikut lagi selama jawabannya di jendela (5.6) |

## 10. Hasil tinjauan kode (30 Okt 2026)

Dua putaran tinjauan kode menemukan dan memperbaiki:

- Rekonsiliasi dibandingkan dengan catatan SpendGuard sendiri (`ai_spend_holds` yang selesai), bukan log pemakaian,
  supaya biaya tanpa log tidak terhitung dua kali. Bucket kosong = belum siap; selama data hari itu belum lengkap
  (18 jam setelah hari berakhir) koreksi hanya boleh menaikkan pemakaian. Jadwal mencocokkan kemarin dan lusa kemarin.
- Biaya OpenAI dari jawaban yang tidak ditagih atau permintaan yang ditolak setelah ada biaya tetap dicatat sekali
  di SpendGuard dan log (0 NC).
- Key peringatan anggaran memuat waktu anggaran diubah, jadi peringatan muncul lagi setelah anggaran dinaikkan.
- Bangun ulang ingatan yang terhenti melanjutkan dari kemajuan terakhir; job ringkasan maks. 4 cicilan per jalan,
  job embedding maks. 4 batch; keduanya di antrean `maintenance` (worker `--queue=default,maintenance`).
- Selisih kartu yang diubah di Pengaturan langsung membuat kurs tagihan baru dari kurs pasar terakhir.
- Saldo awal ledger memasukkan saldo yang sedang ditahan.
- Pencarian semantik memakai `hnsw.iterative_scan` agar grup kecil di antara banyak grup tetap menemukan pesannya;
  tetangga hasil pencarian diambil dalam satu query; embedding pertanyaan dibatasi 8 detik.
- Isi Canvas yang direvisi hanya dikirim sekali ke model (sebelumnya dua kali).
- "tadi" saja tidak dianggap rentang waktu; "minggu/bulan kemarin" dikenali sebelum "kemarin".
- Jawaban yang dijawab ulang memperbarui ingatan seperti pesan yang diedit.
- Batas skala yang dicatat (belum bug): semua panggilan OpenAI mengunci satu baris `ai_spend_guard`; ditinjau ulang
  bila volume sudah besar.
