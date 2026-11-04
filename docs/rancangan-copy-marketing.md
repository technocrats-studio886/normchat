# Rancangan: copywriting situs marketing Normi Chat

Branch: `stagging-v1` · Tanggal: 4 Nov 2026 · Status: revisi 3 (anti-slop) diterapkan 4 Nov 2026. View di `resources/views/marketing/` adalah sumber teks terbaru; tabel di bawah dari revisi 2.

Dasar:
- Evaluasi copywriting yang ditempel user (7/10) dan tambahan soal mekanisme patungan, disesuaikan dengan grup gratis (`docs/rancangan-grup-gratis-login.md`).
- Arahan revisi 2: jangan sekadar memperkenalkan fitur. Tunjukkan dampak dan manfaat bagi user dengan diksi yang menjual, tanpa menjauh dari konsep utama.

## Revisi 4: gerak, struktur hero, font, footer, legal (4 Nov 2026)

Keputusan user:
- JavaScript biasa + CSS (tanpa Alpine, CSP tetap ketat).
- Panah hanya di CTA utama.
- Gerak di semua halaman marketing dan halaman login.
- Font Bricolage Grotesque (judul) + Hanken Grotesk (isi).
- Logo di footer diletakkan sebelum nama.
- Terms dan Privacy berbahasa Inggris atas nama Twenti Studio, tunduk pada hukum Indonesia.

Rinciannya:
- **Hero /home.** Judul utama rata tengah memenuhi lebar, dua CTA di bawahnya, lalu penjelasan. Di bawahnya demo chat (kiri) dan empat manfaat (kanan): Decide sooner, Keep the group in the loop, Split the cost, Start for free.
- **Demo chat hidup** (`partials/live-demo-script`):
  - Pesan muncul berurutan, `@normi` disorot, "Thinking" tampil, lalu jawaban Normi mengalir kata demi kata. Satu siklus sekitar 10 detik.
  - Demo berhenti saat tidak terlihat atau tab disembunyikan.
  - Tanpa JS atau dengan reduced-motion, percakapan tampil lengkap dan statis.
- **Scroll reveal.** Fade up bertahap sekali per elemen. Hero tidak ikut.
- **Latar ambient.** Gradien radial biru tipis bergerak pelan, ditambah glow di belakang demo.
- **Micro-interaction.** Kartu naik 3px dengan border dan bayangan halus, ikon bergerak sedikit. Tombol scale 1.02. CTA utama punya panah yang bergeser dan spinner saat diklik.
- **Halaman masuk.** Satu kolom di tengah (tidak dibelah). Isinya berurutan: brand, judul, penjelasan, demo chat hidup, tawaran, kartu masuk dengan persetujuan Syarat dan Privasi, lalu tautan kaki.
- **Footer.** Logo dan nama, tiga kolom (Product, Company, Legal), lalu hak cipta Twenti Studio dan tautan legal.
- **Legal.** `/terms` dan `/privacy` disusun dari cara aplikasi memperlakukan data: Google, OpenAI, Midtrans, cadangan 30 hari, ekspor 7 hari, sesi 5 hari, dan hapus akun lewat permintaan. Perlu ditinjau ahli hukum.

## Revisi 3: pembersihan AI slop (4 Nov 2026)

Keputusan user: aturan anti-slop diterapkan penuh, klaim Advanced diganti versi kredibel, empat saran reviewer dipakai, persentase biaya QRIS tidak disebut (dipotong pihak ketiga).

Ciri slop yang dibersihkan (rujukan: skill copywriting, SKILL-STOP-SLOP):

Hero `/home` memakai teks pilihan user (4 Nov 2026): "Stop copying AI answers into your group chat. Just mention @normi, and everyone gets the same answer, right where the conversation happens. All powered by one shared balance."

| Pola | Contoh lama | Pengganti |
|---|---|---|
| Label: penjelasan | "Mulai gratis: grup pertamamu siap…" | "Grup pertamamu langsung siap dengan 70 Normkredit, gratis." |
| Pasangan staccato | "Chip in once. Top up together." / "Start free. Grow together." | "Pay for Normi together, with no monthly subscription." / "Your first group is on us." |
| Fragmen dramatis | "Your group already uses AI. Just not together." | "Your group already uses AI, each person in their own chat." |
| Kontras biner | "Pay for answers, not for chatting." / "Why groups, not individuals" | "You pay when Normi answers." / "Your group decides together." |
| Daftar negatif | "No reminders, no chasing one person to pay." | "When the balance gets low, any member can add more Normkredit." |
| Daftar tiga | "think, learn, and work together" | "learn and work together" |
| Kata mutlak | every screen, never lost, everyone benefits, nobody | "on your phone or laptop", "Keep your group's history", "One balance for the whole group" |
| Penutup punchy | "That's the point." / "Ten minutes… settled in one reply" | dihapus / "Normi read ten minutes of discussion and summed it up in one reply." |
| False agency | "The answer lands in front of everyone", "joining makes the group stronger" | "Normi replies in the group", dihapus |
| Klaim tanpa bukti | "On par with the most capable AI models available today", "premium subscription" | "Our most capable level" |
| Biaya pihak ketiga | "A 0.7% service fee is shown before you pay." | "You pay with QRIS." |

## 0. Keputusan user (4 Nov 2026)

| Hal | Keputusan |
|---|---|
| Home pemasaran | Kembali di **`/home`** (Inggris). `/` tetap halaman masuk berbahasa Indonesia, dengan tautan ke `/home`. |
| Bahasa | Situs marketing **tetap Inggris**. Normi, `@normi`, Normkredit tidak diterjemahkan. |
| Kategori model | Dibandingkan **tanpa nama model**. Advanced dijelaskan setara dengan model AI teratas saat ini. |
| Pesan harga | **"Chip in once to join, top up together."** Grup gratis jadi cara mencoba. |

## 1. Kerangka pesan

**Janji utama:** One AI for the whole group.

**Alasan user peduli** (dipakai berulang di semua halaman):

| Manfaat | Dampak yang dijual | Bukti di produk |
|---|---|---|
| Decide faster | The group stops going in circles. Someone asks @normi, everyone reads the same answer, and the chat moves on. | Normi answers inside the group |
| Nobody gets left behind | Late joiners scroll up and catch up. No forwarded screenshots, no "what did the AI say?" | Answers stay in the conversation |
| Pay less, together | One balance replaces a subscription for every member. A few people chipping in cover the whole group. | Shared Normkredit, patungan |
| Keep the conversation human | Normi stays quiet until you call it, so the group still sounds like your group. | Answers only on @normi |

**Kalimat payung** (penutup/footer): AI that helps your whole group move forward, together.

**Aturan diksi**
- Kata kerja hasil: decide, finish, catch up, move on, get it done.
- Kata yang menjual tapi tetap jujur: together, shared, one, free to start, nobody left out.
- Tidak dipakai:
  - klaim angka tanpa data;
  - kata kosong (seamless, empower, revolutionary, next-level);
  - pola "not X, but Y";
  - kalimat "Label: penjelasan" yang terkesan definisi (keputusan user 4 Nov 2026), termasuk di contoh obrolan dan judul tab;
  - tiga frasa puitis berderet;
  - em dash di tengah kalimat;
  - tanda seru.
- Pola setiap bagian: **manfaat (judul) → dampak di keseharian (isi) → fitur sebagai bukti (kalimat terakhir)**.

Angka 70 / 50 / 2 / Rp30,000 / 250 / Rp15,000 / 125 / Rp120 / 500 dibaca dari setelan, bukan ditulis tetap.

## 2. Halaman `/home`

### Hero
- **H1:** One AI for the whole group.
- **Sub:** Stop copying AI answers into the group chat. Mention @normi and everyone gets the same answer, right where the conversation happens, paid from one balance you share.
- **CTA utama:** Start your free group
- **CTA kedua:** See how it works
- **Catatan kecil:** Free to start. Sign in with Google, and your first group comes with 70 Normkredit.
- **Contoh obrolan:**
  - Rina: Have we agreed on the proposal structure yet?
  - Dimas: Not yet. The budget section is still empty.
  - You: @normi summarize the budget points from the chat above.
  - Normi: The group agreed on a spending cap per person and a shared fund for printing costs.
  - **Keterangan di bawahnya:** Ten minutes of back-and-forth, settled in one reply everyone can see.

**Empat manfaat di bawah hero:**

| Judul | Isi |
|---|---|
| Decide faster | One answer the whole group reads, so the discussion moves forward. |
| Everyone in the loop | No screenshots, no "what did the AI say?" It's all in the chat. |
| Split the cost | One shared balance instead of an AI subscription for each person. |
| Free to start | Your first group and your first 2 friends cost nothing. |

### Masalah
- **H2:** Your group already uses AI. Just not together.
- **Isi:** One friend asks AI for a summary. Another asks for ideas. A third pays for a subscription nobody else can use. Then come the screenshots, the different answers, and the same question asked five times. Normi Chat brings one AI into the conversation, so your group asks once, sees the same answer, and gets on with it.

### Cara kerja
- **H2:** Up and running in a minute.

| Langkah | Isi |
|---|---|
| 1. Start your group | Sign in with Google. Your first group is ready with 70 Normkredit, free. |
| 2. Bring your people | Share the group code. Your first 2 friends join free. After that, each new member chips in once and adds to the shared balance. |
| 3. Ask @normi | Type @normi in the chat. The answer lands in front of everyone, and the conversation keeps going. |

### Manfaat (pengganti "fitur")
- **H2:** Everything your group needs to get things done with AI.
- "Pay once, everyone benefits" dijadikan kartu besar selebar satu baris; enam kartu lain mengisi dua baris tiga kolom.

| Judul (manfaat) | Isi (dampak → bukti) |
|---|---|
| **Pay once, everyone benefits** (kartu besar) | Stop paying for AI one person at a time. Your group shares one Normkredit balance. Anyone can use it, anyone can top it up, and there's no monthly subscription to cancel. |
| A chat that still feels like yours | Normi stays out of the way until someone types @normi. Your jokes, plans, and side talk stay between you. |
| The right brain for every question | Quick answers for everyday questions, a stronger mind for writing and analysis, and Advanced for the hard problems, on par with the most capable AI models available today. |
| Settle it with a vote | Start a poll right in the chat, let everyone vote, and stop the "so what did we decide?" messages. |
| No more "wait, what changed?" | Edit a message and the earlier version stays visible, so everyone trusts what they read. |
| Your group's work, never lost | Back up and restore your group's history, or download it as PDF or DOCX for the report, the minutes, or the archive. |
| With you on every screen | Open it on your phone or laptop in the browser. Add it to your home screen, nothing to install. |

### Untuk siapa
- **H2:** Built for groups that think, learn, and work together.

| Kelompok | Isi |
|---|---|
| Study groups | Crack hard topics together. Ask @normi once, and the whole group studies from the same explanation. |
| Small teams | Turn a long discussion into a clear summary and next steps, without anyone writing the minutes. |
| Communities and organizations | Plan events, draft announcements, and share one AI assistant funded by everyone's contribution. |

### Cuplikan harga
- **H2:** Start free. Grow together.
- **Isi:** Your first group is on us, with 70 Normkredit and 2 friends who join free. When your group grows, new members chip in once to join, and anyone can top up when the balance runs low. Nobody pays a monthly subscription.
- **CTA:** See prices

### Penutup
- **H2:** Give your group one AI to share.
- **Isi:** Sign in with Google and start your free group in about a minute.
- **CTA:** Start your free group

## 3. Halaman `/pricing`

### Hero
- **H1:** Chip in once. Top up together.
- **Sub:** One balance for the whole group, no monthly subscription, and you only spend when Normi answers. Start free, and grow when your group is ready.

### How group contributions work

| Langkah | Isi |
|---|---|
| 1. Start free | Your first group comes with 70 Normkredit and your second with 50. The first 2 friends who join each free group pay nothing. Try it with your people before anyone spends a rupiah. |
| 2. Chip in to join | Once the free spots are taken, and in every group you create after that, each new member chips in once to join, from Rp15,000 for 125 Normkredit. Every contribution goes straight into the group's balance, so joining makes the whole group stronger. |
| 3. Top up together, anytime | When the balance runs low, any member can add more. No reminders, no chasing one person to pay. |
| 4. One balance, everyone benefits | Everyone uses Normi from the same balance. Nobody needs a separate AI subscription. |

### Tabel grup

| | Free groups (your 1st and 2nd) | Groups after that |
|---|---|---|
| Create a group | Free | Rp30,000 |
| Starting balance | 70 / 50 Normkredit | 250 Normkredit |
| Members who join free | First 2 | None |
| Joining after that | Chip in once, from Rp15,000 | Chip in once, from Rp15,000 |
| Top up | Any member | Any member |
| Monthly fee | None | None |

### Normkredit, simply
- **Judul:** Pay for answers, not for chatting.
- **Isi:** Normkredit is your group's shared AI balance. 1 Normkredit is Rp120. Chatting with each other is always free; Normi uses Normkredit only when it answers. Longer answers, images, and stronger models use a little more.

### Choose your level of AI
- **Judul:** Pick the power each question needs.
- **Isi:** You choose the level for every message, so everyday questions stay light and hard problems get the best.

| | Fast | Smart | Advanced |
|---|---|---|---|
| Best for | Quick questions and everyday chat | Summaries, writing, and analysis | Hard problems and deep reasoning |
| What you get | Fast, light answers | A strong all-rounder | On par with the most capable AI models available today |
| Normkredit per answer | Lowest | Medium | Highest |
| Available | Always | Always | When the group balance reaches 500 Normkredit |

**Catatan di bawah tabel:** The kind of AI people pay a premium subscription for, shared by your whole group.

### FAQ
- **Is there a free plan?** Yes. Every account gets two free groups. The first comes with 70 Normkredit and the second with 50, and in each one the first 2 friends join free.
- **Do I pay every time I use Normi?** No. Normi uses your group's shared balance. You only pay when you chip in to join or top up.
- **Do new members have to pay?** In a free group, the first 2 join free. After that, every new member chips in once to join, and that contribution goes into the shared balance everyone uses.
- **What happens when the balance runs out?** Normi pauses until someone tops up. Your group can keep chatting as usual.
- **Can I talk to Normi privately?** No. Normi lives in groups, so everyone sees the same answers. That's the point.
- **How do I pay?** With QRIS. The service fee (0.7%) is shown before you pay.

*Catatan:* contoh berapa jawaban per 250 Normkredit belum dicantumkan sampai ada data pemakaian sungguhan.

## 4. Halaman `/about`

- **H1:** We build AI that a whole group can use.
- **Pembuka:** Most people use AI alone, and the answer stays on one screen. We think the best answers belong in the conversation, where everyone can read them, question them, and build on them.
- **Prinsip** (menggantikan asal nama "normies"):
  - **Judul:** AI for everyone in the room.
  - **Isi:** Not everyone in a group knows how to prompt an AI, and they shouldn't have to. With Normi, one person asks and the whole group benefits.
- **What we decided:**
  - Normi lives in groups only. There is no private chat with Normi, so the group always shares the same answers.
  - Normi answers only when someone mentions @normi. The conversation stays yours.
  - The balance belongs to the group, never to one person. Everyone contributes, everyone benefits.
- **Penutup:** Normi Chat is made by Twenti Studio.
- **CTA:** Start your free group

## 5. Halaman `/contact`

- **H1:** Talk to us.
- **Sub:** Questions about prices, groups, or Normi? Send us a message and we'll reply by email.
- Form dan topik tetap.

## 6. Elemen global

**Navigasi:** Home · About · Prices · Contact · **Start free**
- Tombol menuju `/` (halaman masuk).
- Logo menuju `/home`.

**Footer:**
- Baris merek: One AI for the whole group.
- Bawah: Made by Twenti Studio. (Sysnavia dan Uplift tidak disebut, keputusan user 4 Nov 2026.)

**Halaman masuk (`/`):** kaki halaman menjadi "Beranda · Tentang · Harga · Kontak". "Beranda" menuju `/home`.

**SEO:**

| Halaman | Judul | Deskripsi |
|---|---|---|
| Home | Normi Chat \| One AI for the whole group | Mention @normi in your group chat and everyone gets the same answer. Share one balance. Start free. |
| Prices | Prices \| Normi Chat \| Start free, chip in together | Two free groups to start. Chip in once to join, top up together, no monthly subscription. |
| About | About Normi Chat | We build AI that a whole group can use. Normi Chat is made by Twenti Studio. |
| Contact | Contact Normi Chat | Questions about prices, groups, or Normi? We reply by email. |

## 7. Urutan eksekusi (setelah disetujui)

1. Rute `/home` dan view `marketing/home.blade.php` baru. Navigasi, logo, dan kaki halaman masuk diperbarui.
2. `/pricing`: langkah patungan, tabel grup, Normkredit, tabel level AI, FAQ.
3. `/about` dan `/contact`.
4. Uji:
   - `MarketingPagesTest`: semua halaman, angka dari setelan, grup gratis mati → tanpa klaim gratis.
   - Audit kontras dan tanpa geser horizontal di 360/390 px.
   - Tangkapan layar desktop/HP.


## Revisi 5 (4 Nov 2026)

**Mode Normi di /home.** Bagian baru `#modes`, judulnya "Ask Normi to teach, draw, build, or research." Isinya empat kartu: Guided Learning, Create image, Canvas, Deep Research. Pola tiap kartu:

1. judul manfaat;
2. "Use it when/for…" (tujuan pemakaian, permintaan user);
3. cara kerja sesuai `App\Services\Ai\ModePrompts` dan responder masing-masing.

Unggah file dan polling tidak diperkenalkan di sini (keputusan user).

**Konsistensi harga.** Semua halaman memakai urutan yang sama: dua grup pertama gratis (70 lalu 50 Normkredit), 2 teman pertama di tiap grup gratis ikut tanpa biaya, lalu mulai grup ke-3 Rp30.000 dengan 250 Normkredit. Halaman yang diselaraskan: login, /home (meta, langkah 1, bagian harga), /pricing (judul, meta, hero, kolom tabel "From your 3rd group"). Bila grup kedua dimatikan di superadmin, teks otomatis berubah ke "first group" dan "From your 2nd group". Semuanya dijaga `FreeGroupsTest::test_every_public_page_tells_the_same_free_group_and_price_story`.

**Identitas.** Normi Chat dikembangkan oleh Twenti Studio. Sysnavia/Uplift tidak disebut di halaman mana pun (`MarketingPagesTest`).
