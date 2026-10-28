Pesanku: kau ingat normchat? coba summary dulu sebelum kita bahas hal lain

Pesan AI: Iya, aku ingat. Produk yang awalnya kamu namai NormChat sekarang sudah berganti nama menjadi Normichat. Aku akan merangkum pemahaman terakhirku supaya kita punya landasan yang sama sebelum membahas hal lain.
Summary produk Normichat
1. Konsep utama produk

Normichat adalah aplikasi group chat dengan AI yang bisa digunakan bersama oleh banyak anggota dalam satu grup.

Alih-alih setiap orang menggunakan AI secara terpisah, anggota grup dapat berinteraksi dengan satu AI dalam percakapan yang sama. Mereka dapat bertanya, berdiskusi, dan memanfaatkan AI secara kolaboratif.

Contoh penggunaannya adalah kelompok mahasiswa yang mengerjakan tugas bersama. Mereka bisa bertanya kepada AI langsung di dalam percakapan kelompok sehingga konteks diskusi tidak terpisah-pisah di akun AI masing-masing.

Collaborative AI chat

AI menjadi bagian dari percakapan kelompok dan bisa digunakan oleh beberapa anggota.

NormKredit

Sistem kredit untuk mengatur penggunaan AI, bukan saldo pribadi atau langganan konvensional.

AI berbasis OpenAI API

Pemanggilan model dilakukan melalui backend Normichat. Pengguna tidak menerima API key OpenAI milik sistem.
2. Identitas dan branding

Nama terbaru: Normichat, berasal dari kata normies.

Arah desain logo yang pernah kamu tetapkan:

    Ikon logo disandingkan dengan tulisan Normichat di sebelah kanan.

    Maksimal dua warna dan tanpa gradient.

    Gaya harus terlihat sederhana, punya identitas, dan tidak terasa seperti desain AI generik atau AI slop.

    Kita juga sempat membahas penggunaan ikon saja tanpa teks dan eksplorasi warna bagian tengah ikon.

3. Model bisnis awal: group-based credit

Prinsip awal yang kamu tetapkan cukup spesifik.

Aspek
	

Konsep Normichat

Akun pengguna
	

Gratis untuk bergabung dan mulai menggunakan layanan

Langganan
	

Tidak menggunakan subscription sebagai model utama

Pembayaran awal
	

Pembuat grup membayar saat membuat grup

NormKredit
	

Kredit penggunaan AI yang dimiliki grup

Anggota
	

Dapat menggunakan AI bersama dalam grup

Kontribusi anggota
	

Memungkinkan patungan atau penambahan kredit ke grup

API OpenAI
	

Dikelola sepenuhnya oleh backend

Pembuat grup dapat memilih jumlah NormKredit yang ingin dibeli, tetapi kamu mempertimbangkan batas minimum pembelian untuk menjaga keamanan saldo API dan kelayakan ekonomi Normichat. Batas minimum untuk anggota yang ingin patungan direncanakan lebih rendah daripada batas minimum pembuat grup.

Hal penting: NormKredit adalah kredit penggunaan AI, bukan sekadar saldo rupiah yang ditampilkan dengan nama lain. Sistem backend perlu menentukan berapa kredit yang dikonsumsi oleh setiap permintaan berdasarkan model dan pemakaian token.
4. Masalah utama yang sedang kamu pecahkan

Ada tiga tantangan bisnis dan teknis yang pernah kita bahas.
A. Menjaga keuntungan Normichat

Kamu pernah memberikan angka biaya operasional berikut:

Komponen
	

Biaya

Server
	

Rp300.000/bulan

Domain
	

US$11/tahun

Storage
	

US$10/bulan

Target margin
	

20–30%

Kamu tidak ingin harga hanya menutup biaya token OpenAI. Harga juga harus memperhitungkan infrastruktur, biaya pembayaran, risiko penggunaan berlebihan, dan margin bisnis.

Kita juga membahas kemungkinan mengalikan konsumsi token dengan faktor tertentu saat mengonversinya menjadi NormKredit, misalnya faktor ×2, ×2,5, atau ×3. Ada pula gagasan membedakan faktor berdasarkan model, tetapi faktor finalnya belum dianggap sebagai keputusan yang matang.
B. Melindungi pembuat grup dari penggunaan anggota

Misalnya, pembuat grup membayar Rp30.000 untuk mengisi kredit, tetapi anggota lain justru menghabiskan sebagian besar kredit tersebut sementara pembuatnya sendiri jarang menggunakan AI.

Ini menimbulkan pertanyaan tentang keadilan dan kontrol penggunaan.

Karena itu, desain Normichat perlu memikirkan batas penggunaan anggota, transparansi konsumsi kredit, kontrol pemilik grup, serta mekanisme agar penggunaan bersama tidak membuat pembayar utama merasa dirugikan.
C. Menentukan harga yang realistis untuk mahasiswa

Kamu ingin Normichat tetap terjangkau bagi mahasiswa dan tidak membuat pengguna berpikir bahwa mereka lebih baik membayar ChatGPT secara langsung.

Kisaran kemampuan bayar yang kamu perkirakan adalah sekitar Rp30.000–Rp40.000. Kamu juga pernah mengeksplorasi skenario:

    Rp30.000 untuk paket grup dengan 250 NC.

    Rp15.000 untuk tambahan 125 NC oleh anggota.

Angka tersebut merupakan skenario yang pernah kita bahas, bukan harga final yang sudah tervalidasi atau terbukti menguntungkan.
5. Arah pengembangan: workspace Team dan Business

Pada diskusi berikutnya, kamu mempertimbangkan perluasan Normichat dari grup biasa ke paket workspace yang terinspirasi dari produk kolaborasi AI seperti ChatGPT dan Claude untuk tim.

Arah yang lebih kamu sukai adalah:

Kredit dibeli untuk workspace, kemudian dapat digunakan di semua grup yang berada di dalam workspace tersebut.

Dengan model ini, kredit tidak harus dibeli dan dikelola secara terpisah untuk setiap grup. Workspace menjadi lapisan pengelolaan anggaran AI untuk beberapa grup sekaligus.

Kamu juga mempertimbangkan:

    Paket Team dan Business.

    Batas penggunaan harian dan mekanisme reset.

    Batas penggunaan mingguan berdasarkan kredit paket.

    Kredit yang bisa dipakai lintas grup.

    Pengendalian konsumsi API selama masa paket.

Kita sempat menguji contoh batas 100 NC per hari dan paket 500 NC. Kamu juga mengkritisi skenario batas mingguan yang apabila dijumlahkan justru melebihi kredit paket yang tersedia.

Jadi, aturan penggunaan harus konsisten secara matematis dan tidak menjanjikan jatah kredit yang sebenarnya tidak didanai oleh saldo workspace.
6. Tantangan positioning produk

Ada dua jenis penawaran yang harus memiliki alasan keberadaan yang jelas.

Group biasa
	

Workspace

Berorientasi pada satu grup dan kolaborasi sederhana
	

Berorientasi pada pengelolaan beberapa grup

Pembayaran dan kredit berkaitan dengan grup
	

Kredit dikelola pada tingkat workspace

Cocok untuk kelompok belajar atau komunitas kecil
	

Potensial untuk tim, organisasi, atau komunitas dengan banyak kelompok

Kekhawatiranmu adalah jangan sampai paket workspace hanya terlihat seperti grup biasa dengan harga lebih mahal, atau sebaliknya, grup biasa terlihat sebagai versi buruk dari paket workspace.

Karena itu, perbedaannya harus nyata pada kebutuhan pengguna, pengelolaan kredit, kontrol, dan skala kolaborasi—bukan hanya nama paket.
7. Hal yang belum final

Dari konteks percakapan kita, beberapa hal masih perlu dianggap sebagai hipotesis atau keputusan terbuka:

    Harga minimum grup dan harga paket workspace.

    Nilai rupiah sebenarnya dari satu NormKredit.

    Faktor konversi pemakaian token menjadi NormKredit per model.

    Batas konsumsi anggota dan kontrol pembuat grup.

    Detail mekanisme patungan.

    Aturan kredit harian, mingguan, dan keseluruhan workspace.

    Pembeda manfaat serta positioning paket Group, Team, dan Business.

    Validasi apakah harga dan margin tetap sehat pada pola penggunaan nyata.

Aku tidak menganggap angka-angka yang pernah kita uji sebagai keputusan final karena kita sebelumnya masih mengevaluasi kelayakan model bisnisnya.
Kesimpulan pemahamanku

Normichat bukan hanya aplikasi chat yang ditambahkan chatbot AI. Inti produknya adalah membuat AI dapat digunakan secara kolaboratif, sekaligus menyediakan sistem kredit bersama yang mengendalikan pengeluaran AI dan memungkinkan bisnisnya berkelanjutan.

Dari sisi produk, aku melihat tiga hal yang harus berjalan bersamaan:

    Nilai kolaborasi: AI benar-benar membantu aktivitas kelompok, bukan sekadar memindahkan chatbot ke ruang chat.

    Ekonomi penggunaan: setiap NormKredit memiliki dasar perhitungan yang menjaga pendapatan, biaya API, dan margin.

    Kontrol dan kepercayaan: pembeli maupun anggota memahami siapa yang membayar, bagaimana kredit digunakan, dan apa yang terjadi saat kredit habis.

Itulah konteks terakhir Normichat yang kuingat. Kita bisa memakai ini sebagai titik awal pembahasan berikutnya, dengan membedakan secara jelas mana keputusan yang sudah kamu tetapkan dan mana yang masih perlu kita evaluasi.


Pesanku: oke kita bahas token pada open ai.
kalau aku ada saldo kredit $5 pada akun open ai ku, berapa jumalh token yang akan di gunakan? karena saat ini aku pakai total token ratusan ribu dan yang terpakai hanya $0.06.
selain itu, apakah kredit terpakai untuk input dan output?
Worked for 22s

Pesan AI: Untuk Normichat, hal terpenting yang perlu kamu pahami adalah saldo kredit OpenAI sebesar $5 tidak memiliki jumlah token tetap. Jumlah token yang bisa kamu gunakan bergantung pada model yang dipilih, banyaknya token input dan output, serta tarif masing-masing model.

Pengalamanmu menggunakan ratusan ribu token tetapi hanya menghabiskan $0,06 itu sangat mungkin terjadi. Token dalam jumlah besar tidak selalu mahal, terutama jika kamu menggunakan model dengan tarif rendah.

Ada dua hal yang perlu kita bedakan:

    Kredit saldo API: uang yang tersedia untuk membayar penggunaan API OpenAI.

    Token: satuan teks yang diproses model, yang biayanya dihitung berdasarkan tarif model.

Jadi, kita tidak bisa langsung menyimpulkan bahwa $5 setara dengan sekian ribu token tanpa mengetahui model yang kamu gunakan.
1. Dengan saldo $5, berapa banyak token yang bisa digunakan?

Menurut halaman resmi harga OpenAI API, biaya dihitung per satu juta token dan tarif input berbeda dari tarif output. Berikut simulasi menggunakan tarif standar beberapa model yang tercantum saat ini. 
OpenAI API

Model
	

Input per 1 juta token
	

Output per 1 juta token
	

Jika hanya input, $5 mendapat
	

Jika hanya output, $5 mendapat

GPT-6 Astra
	

$10,00
	

$50,00
	

500.000
	

100.000

GPT-6.1 Sol
	

$2,00
	

$10,00
	

2.500.000
	

500.000

GPT-6.1 Terra
	

$1,00
	

$5,00
	

5.000.000
	

1.000.000

GPT-6 Luna
	

$0,10
	

$0,50
	

50.000.000
	

10.000.000

Perkiraan teoritis: seluruh saldo dipakai untuk satu jenis token saja, berdasarkan tarif standar yang tercantum. Pemakaian nyata dapat berbeda karena kombinasi input/output, konteks, caching, dan penggunaan tools.

Perhatikan bahwa pada model yang murah, saldo $5 bisa mencukupi untuk jutaan token. Sebaliknya, pada model yang lebih mahal, saldo yang sama mungkin hanya mencukupi ratusan ribu token, terutama jika output-nya panjang.
2. Apakah input dan output sama-sama menggunakan kredit?

Ya, keduanya dikenakan biaya. Namun, tarifnya tidak sama.

Input tokens

Token yang dikirim atau diberikan kepada model, termasuk instruksi sistem, pertanyaan pengguna, dan riwayat percakapan yang disertakan dalam permintaan. Hasil tool yang dimasukkan kembali ke konteks juga dapat menambah input.

Output tokens

Token yang dihasilkan model untuk merespons permintaan. Pada model yang menggunakan reasoning, token penalaran yang dikenakan biaya juga dapat menjadi bagian dari penggunaan output.

Jadi, kamu tidak hanya membayar jawaban yang dihasilkan AI. Kamu juga membayar teks yang diproses oleh model sebagai input. Tarifnya masing-masing tercantum pada dokumentasi harga API OpenAI. 
OpenAI API+1
3. Mengapa ratusan ribu token hanya menghabiskan $0,06?

Mari gunakan GPT-6 Luna sebagai contoh tarif murah dari tabel di atas.

Tarifnya adalah:

    Input: $0,10 per 1 juta token.

    Output: $0,50 per 1 juta token.

Misalnya, satu rangkaian penggunaan menghasilkan:

Jenis token
	

Jumlah
	

Biaya

Input
	

500.000
	

$0,05

Output
	

20.000
	

$0,01

Total
	

520.000
	

$0,06

Dengan demikian, 520 ribu token dapat menghabiskan hanya $0,06 pada contoh tersebut.

Ini hanyalah ilustrasi perhitungan, bukan berarti penggunaanmu pasti menggunakan GPT-6 Luna. Untuk mengetahui penyebab persisnya, kita perlu melihat model dan rincian pemakaian pada akunmu.
4. Rumus yang sebaiknya kamu pahami untuk Normichat

Biaya penggunaan API pada dasarnya dapat dihitung dengan rumus:
Biaya=Input tokens1.000.000×Tarif input+Output tokens1.000.000×Tarif output
Biaya=​1.000.000Input tokens​×Tarif input+1.000.000Output tokens​×Tarif output​

Rumus ini merupakan dasar perhitungan teks. Penggunaan input yang tercache dapat memiliki tarif lebih rendah, sedangkan tools tertentu dapat menambahkan biaya tersendiri. 
OpenAI API

Untuk Normichat, ini berarti kamu sebaiknya mencatat input dan output secara terpisah untuk setiap permintaan, lalu menghitung biaya riil berdasarkan model yang digunakan. Jangan menetapkan konsumsi NormKredit hanya dari total gabungan token karena proporsi input dan output dapat mengubah biaya secara signifikan.

Satu hal lagi yang penting: saldo API prabayar memang berkurang seiring penggunaan dan tidak setara dengan kuota token tetap. OpenAI juga menjelaskan bahwa permintaan yang diproses saat saldo habis dapat menyebabkan saldo negatif sementara karena jeda pemrosesan. Karena itu, Normichat sebaiknya menerapkan kontrol pengeluaran di backend, bukan hanya mengandalkan saldo OpenAI sebagai pembatas instan. 
OpenAI Help Center

Boleh kirimkan nama model yang sekarang kamu gunakan di OpenAI API? Dari situ, kita bisa menghitung perkiraan yang jauh lebih relevan untuk penggunaanmu dan menjadikannya dasar perhitungan NormKredit.
This might also interest you.

Pesanku: yaudah bantu jawab

Pesan AI: 5. Tarif resmi OpenAI API yang relevan

Aku sudah memeriksa dokumentasi harga resmi OpenAI. Untuk pemrosesan standar dengan panjang konteks di bawah 272 ribu token, tarif model yang kita bahas adalah sebagai berikut. 
OpenAI

Model
	

Input / 1 juta token
	

Output / 1 juta token

GPT-6 Astra
	

$10,00
	

$50,00

GPT-6.1 Sol
	

$2,00
	

$10,00

GPT-6.1 Terra
	

$1,00
	

$5,00

GPT-6 Luna
	

$0,10
	

$0,50

Dengan saldo $5, perhitungannya menjadi:

Model
	

Token input saja
	

Token output saja

GPT-6 Astra
	

500 ribu
	

100 ribu

GPT-6.1 Sol
	

2,5 juta
	

500 ribu

GPT-6.1 Terra
	

5 juta
	

1 juta

GPT-6 Luna
	

50 juta
	

10 juta

Jumlah tersebut adalah estimasi teoritis jika seluruh saldo digunakan untuk satu jenis token. Penggunaan campuran input dan output akan menghasilkan jumlah berbeda.
6. Apa artinya untuk Normichat?

Misalnya, pengguna Normichat mengirim 10.000 token input dan menerima jawaban 2.000 token output menggunakan GPT-6 Luna.
Biaya input=10.000×$0,10/1.000.000=$0,001Biaya output=2.000×$0,50/1.000.000=$0,001Total biaya=$0,002
Biaya inputBiaya outputTotal biaya​=10.000×$0,10/1.000.000=$0,001=2.000×$0,50/1.000.000=$0,001=$0,002​

Dalam contoh itu, satu interaksi menghabiskan $0,002. Saldo $5 secara teoritis bisa membiayai sekitar 2.500 interaksi dengan pola pemakaian yang sama, sebelum memperhitungkan variasi pemakaian dan biaya tambahan.

Rekomendasiku untuk Normichat: simpan jumlah token input, token output, model yang dipakai, dan biaya aktual dari setiap permintaan API. Setelah itu, baru tentukan berapa NormKredit yang harus dikurangi. Jangan menetapkan satu tarif token universal untuk seluruh model, karena selisih biaya input dan output serta perbedaan harga antar-model cukup besar.
This might also interest you.

Pesanku: nah ini kan sudah memakan $10 dari kreditku dalam artian 500 ribu token input berharga $5 dan 100 ribu output berharga $5

Pesan AI: Betul, pemahamanmu benar! Untuk GPT-6 Astra, berdasarkan tarif standar OpenAI yang tercantum saat ini, perhitungannya seperti ini. 
OpenAI API+1

Jenis token
	

Jumlah token
	

Biaya

Input
	

500.000
	

$5

Output
	

100.000
	

$5

Total
	

600.000
	

$10

Rumusnya:

    Input: 500.000/1.000.000×$10=$5
    500.000/1.000.000×$10=$5

    Output: 100.000/1.000.000×$50=$5
    100.000/1.000.000×$50=$5

Jadi, kalau saldo OpenAI-mu hanya $5, saldo tersebut tidak cukup untuk membiayai 500.000 token input sekaligus 100.000 token output pada tarif tersebut. Jumlah token di tabel sebelumnya adalah dua skenario terpisah, bukan dua jatah yang bisa digabungkan.

Ada satu hal penting untuk Normichat: tarif input GPT-6 Astra adalah 5 kali lebih murah daripada tarif output per token. Karena itu, sistem NormKredit sebaiknya menghitung biaya input dan output secara terpisah, bukan menganggap semua token memiliki harga yang sama.

Pesanku: nah untuk management normkredit aku sekarang buat aturan, model astra (pada produkku bernama advence) baru bisa digunakan jika jumlah kredit >= 500, gimana menurutmu, aman aja ga atau bisa membuat openai kreditku akan menjadi 0 dengan cepat sebelum balik modal untuk mendapatkan seharga persatu juta token untukmodel luna, sol, dan astra.
bantu aku mendesign ini dengan rinci

Pesan AI: Menurutku, aturan Normichat bahwa model Advence (GPT-6 Astra) hanya bisa digunakan ketika saldo grup minimal 500 NormKredit bisa dipertahankan, tetapi belum cukup untuk melindungi saldo OpenAI dan memastikan Normichat balik modal.

Aku ingin memisahkan dua hal: syarat untuk membuka akses model premium dan mekanisme yang menentukan berapa banyak biaya API yang boleh dikeluarkan. Dua hal itu sebaiknya tidak menggunakan aturan yang sama.

Menurut tarif resmi OpenAI yang tersedia saat ini, GPT-6 Astra memiliki tarif standar $10 per satu juta token input dan $50 per satu juta token output. GPT-6.1 Sol bertarif $2/$10, sedangkan GPT-6 Luna $0,10/$0,50. Artinya, biaya output Astra 500 kali biaya output Luna untuk jumlah token yang sama. 
OpenAI API+3

Ini penting karena satu aturan seperti “saldo minimal 500 NC” tidak otomatis memperhitungkan perbedaan biaya tersebut.
1. Pahami dulu risiko aturan 500 NC

Ada dua mekanisme yang harus dipisahkan:

Mekanisme
	

Fungsinya

Syarat akses Advence ≥ 500 NC
	

Menentukan apakah grup memenuhi syarat menggunakan model premium

Batas pengeluaran berdasarkan biaya API
	

Memastikan biaya yang ditimbulkan setiap permintaan tidak melebihi kredit yang tersedia

Misalnya, grup memiliki 500 NC dan boleh menggunakan Advence. Jika backend menghitung pemakaian dengan tarif yang terlalu murah, grup itu bisa menghabiskan biaya API yang sebenarnya jauh lebih besar daripada pendapatan Normichat.

Sebaliknya, kalau 500 NC dikonversi dengan benar berdasarkan biaya API, pemakaian dikurangi sebelum permintaan dikirim, dan batas output diterapkan, model premium bisa digunakan secara terkendali.

Jadi, aku tidak menyarankan menghapus aturan 500 NC sekarang. Aku menyarankan jangan menjadikan aturan itu sebagai satu-satunya pengaman.
2. Simulasikan dulu nilai ekonomi NormKredit

Kita pernah membahas skenario Rp30.000 untuk 250 NC dan Rp15.000 untuk 125 NC. Jika skema tersebut masih digunakan, artinya nilai nominalnya adalah Rp120 per NC.

Untuk simulasi berikut, aku menggunakan asumsi kurs perencanaan Rp17.000 per US$1. Ini bukan klaim kurs pasar hari ini; nanti gantilah dengan kurs efektif yang benar-benar ditanggung Normichat, termasuk biaya konversi pembayaran.

Tarif API di bawah adalah tarif standar untuk teks, dengan asumsi konteks pendek dan tanpa biaya tool tambahan. Sumber: dokumentasi harga OpenAI.

Model Normichat
	

Biaya per 1 juta token input
	

Biaya per 1 juta token output

Luna — GPT-6 Luna
	

Rp1.700
	

Rp8.500

Sol — GPT-6.1 Sol
	

Rp34.000
	

Rp170.000

Advence — GPT-6 Astra
	

Rp170.000
	

Rp850.000

Harga input dan output berbeda. Token input yang tercache juga mempunyai tarif tersendiri, dan biaya konteks panjang dapat berubah. 
OpenAI API+3

Sekarang, misalkan kamu menerapkan faktor pengali biaya API sebesar 1,5× untuk menentukan pembakaran NC.

Rumus awalnya:
NC terpakai=⌈Biaya API aktual (Rp)×1,5Nilai nominal 1 NC (Rp)⌉
NC terpakai=⌈Nilai nominal 1 NC (Rp)Biaya API aktual (Rp)×1,5​⌉

Karena nilai nominal 1 NC dalam simulasi ini adalah Rp120, hasilnya sebagai berikut.

Model
	

NC untuk 1 juta input
	

NC untuk 1 juta output

Luna
	

22 NC
	

107 NC

Sol
	

425 NC
	

2.125 NC

Advence
	

2.125 NC
	

10.625 NC

Angka dibulatkan ke atas agar pembakaran kredit tidak menghasilkan pecahan NC. Ini adalah contoh konversi biaya menjadi NC, bukan harga final yang sudah divalidasi.

Perhatikan model Advence: satu juta token output dapat menghabiskan biaya API sebesar Rp850.000 dalam simulasi ini. Itu setara dengan 10.625 NC menggunakan faktor 1,5×. Karena itu, sistem tidak boleh memakai tarif pembakaran yang sama untuk semua model.
3. Apakah 500 NC cukup untuk menggunakan Advence dengan aman?

Dengan asumsi di atas:

    Saldo 500 NC mempunyai nilai nominal Rp60.000.

    Dengan faktor pembakaran 1,5×, saldo itu hanya boleh menanggung sekitar Rp40.000 biaya API.

    Dengan kurs perencanaan Rp17.000/US$, Rp40.000 setara sekitar US$2,35 biaya API.

Ini bukan berarti pengguna selalu menghabiskan US$2,35. Artinya, jika saldo 500 NC dikelola menggunakan formula tersebut, itulah perkiraan batas pengeluaran API yang ditopang oleh saldo kredit itu sebelum memperhitungkan biaya lain.

Namun, ada catatan penting: faktor 1,5× hanya menghasilkan margin sekitar 33,3% terhadap nilai kredit yang digunakan untuk menutup biaya API, sebelum biaya pembayaran, infrastruktur, pajak, dan biaya operasional lainnya. Faktor tersebut bukan jaminan keuntungan bersih.

Selain itu, saldo 500 NC tidak menjamin bahwa OpenAI hanya akan menagih US$2,35. Hal itu hanya benar jika semua permintaan dihitung dan dibatasi dengan benar, tidak ada pemakaian gratis yang lolos, dan tidak ada biaya tambahan yang terabaikan.
4. Desain sistem NormKredit yang aku rekomendasikan
Lapisan 1 — Kelayakan model

Gunakan aturan akses model yang kamu inginkan. Contohnya, Advence hanya tersedia saat saldo tersedia minimal 500 NC.

Saldo tersedia berarti saldo yang sudah dikurangi kredit yang sedang ditahan untuk permintaan lain. Jangan menghitung kredit yang sedang direservasi sebagai kredit bebas pakai.
Lapisan 2 — Pembatasan setiap permintaan

Sebelum mengirim permintaan ke OpenAI, backend memperkirakan biaya maksimum dari token input dan batas token output.

Jika estimasi biaya itu tidak dapat ditanggung saldo grup, permintaan ditolak sebelum dikirim ke API.
Lapisan 3 — Pembakaran kredit berdasarkan pemakaian aktual

Setelah respons diterima, backend membaca metadata penggunaan dari API, menghitung biaya input dan output berdasarkan model yang benar, lalu menyelesaikan pembakaran NC.

Kredit yang sudah ditahan tetapi tidak jadi digunakan dilepaskan kembali.
Lapisan 4 — Pelindung saldo OpenAI global

Backend menerapkan batas pengeluaran tersendiri untuk seluruh Normichat, melintasi semua grup dan semua model.

Jika batas global tercapai, permintaan baru yang berbiaya tidak boleh dikirim, meskipun suatu grup masih memiliki banyak NC.
A. Tetapkan batas token per permintaan

Untuk MVP, aku menyarankan memulai dengan batas yang konservatif:

Parameter
	

Batas awal yang disarankan

Input maksimum untuk satu permintaan
	

16.000 token

Output maksimum Advence
	

2.000 token

Output maksimum Sol dan Luna
	

2.000 token

Batas konteks riwayat percakapan
	

Termasuk dalam input maksimum, bukan tambahan di luar batas

Angka-angka ini adalah titik awal untuk pengujian, bukan batas yang wajib untuk semua produk. Kamu bisa meningkatkannya setelah mengukur biaya nyata dan melihat pola penggunaan anggota grup.

Batas input harus mencakup system prompt, instruksi, pertanyaan, serta riwayat percakapan yang dikirim ulang kepada model. Jangan menghitung pertanyaan terakhir saja.

Kenapa ini penting? Karena chat grup yang memiliki riwayat panjang dapat membebani input setiap kali model dipanggil, walaupun pengguna hanya mengetik satu kalimat baru.
B. Reservasi NC sebelum memanggil API

Contohnya, satu permintaan Advence diperkirakan menggunakan 16.000 token input dan menghasilkan maksimal 2.000 token output.

Biaya maksimum teks berdasarkan tarif standar:
Input=16.0001.000.000×$10=$0,16Output=2.0001.000.000×$50=$0,10Total=$0,26
InputOutputTotal​=1.000.00016.000​×$10=$0,16=1.000.0002.000​×$50=$0,10=$0,26​

Dengan kurs simulasi Rp17.000 dan faktor 1,5×:
NC reservasi=⌈0,26×17.000×1,5120⌉=56 NC
NC reservasi=⌈1200,26×17.000×1,5​⌉=56 NC

Jadi, sistem dapat menahan 56 NC untuk permintaan tersebut. Jika pemakaian aktual lebih rendah, selesaikan tagihan sesuai biaya aktual dan kembalikan selisih reservasinya.

Nilai 56 NC ini hanya berlaku pada asumsi yang disebutkan. Backend harus menghitung estimasi untuk setiap permintaan, bukan menetapkan 56 NC sebagai tarif tetap semua pertanyaan Advence.
C. Tangani permintaan bersamaan dengan aman

Ini sangat penting karena Normichat adalah group chat.

Misalnya saldo grup 500 NC. Dua anggota mengirim pertanyaan hampir bersamaan. Jika kedua permintaan membaca saldo 500 NC sebelum salah satunya mengurangi saldo, keduanya dapat lolos pemeriksaan dan secara keseluruhan memesan kredit terlalu banyak.

Solusinya adalah mekanisme reservasi atomik:

    Backend memeriksa saldo yang benar-benar tersedia.

    Backend menahan NC dalam satu transaksi yang aman dari pembaruan bersamaan.

    Jika saldo tidak cukup, permintaan tidak dikirim.

    Setelah API selesai, backend menyelesaikan pemakaian aktual dan melepaskan sisa reservasi.

Dengan begitu, satu grup tidak bisa mengirim banyak permintaan yang masing-masing menganggap dirinya mempunyai akses ke saldo penuh.
D. Jangan mengandalkan saldo prabayar OpenAI sebagai pengaman utama

OpenAI menjelaskan bahwa penghentian API setelah kredit prabayar habis tidak selalu terjadi seketika. Penggunaan yang diproses selama jeda dapat menyebabkan saldo negatif sementara dan kemudian dikurangkan dari pembelian kredit berikutnya. 
OpenAI Help Center

Karena itu, Normichat memerlukan global spend guard tersendiri.

Untuk akun uji coba dengan saldo US$5, misalnya, kamu dapat memulai dengan kebijakan berikut:

Kebijakan pengujian
	

Contoh nilai awal

Cadangan saldo yang tidak digunakan untuk permintaan baru
	

US$1,25

Anggaran penggunaan yang dapat dibelanjakan
	

US$3,75

Peringatan ketika pemakaian anggaran mencapai
	

50%, 70%, dan 85%

Ketika batas tercapai
	

Hentikan permintaan baru yang berbiaya

Nilai ini adalah kebijakan internal konservatif untuk pengujian, bukan batas resmi OpenAI. Sesuaikan setelah mengetahui pola permintaan dan waktu penyelesaian transaksi.

Cadangan tersebut membantu mencegah saldo awal US$5 habis terlalu cepat akibat lonjakan penggunaan. Untuk produksi, anggaran sebaiknya mengikuti saldo yang tersedia, dana pembelian kredit yang sudah diterima, pengeluaran yang sedang menunggu penyelesaian, serta kewajiban biaya yang belum dibayar.
5. Bagaimana menentukan harga dan memastikan balik modal?

Di sinilah kita perlu membedakan dua target:

    Balik modal biaya API: pendapatan dari penjualan kredit harus mencukupi biaya API yang ditimbulkan.

    Balik modal bisnis: pendapatan setelah biaya API dan biaya variabel lainnya masih harus mencukupi server, storage, domain, biaya pembayaran, dan operasional lainnya.

Keduanya bukan hal yang sama.

Kalau menggunakan skenario Rp30.000 untuk 250 NC dan faktor 1,5×, nilai nominal kredit adalah Rp30.000, sedangkan biaya API maksimum yang ditopang kredit itu sekitar Rp20.000. Sisanya sekitar Rp10.000 merupakan kontribusi sebelum biaya pembayaran dan biaya operasional lainnya.

Dari angka biaya operasional yang pernah kamu berikan—server Rp300.000/bulan, storage US$10/bulan, dan domain US$11/tahun—jumlah biaya tetapnya sendiri sekitar Rp486.000/bulan menggunakan kurs simulasi Rp17.000/US$, sebelum biaya lainnya. Dengan kontribusi Rp10.000 per pembelian grup pada skenario tadi, diperlukan sekitar 49 pembelian per bulan hanya untuk menutup biaya tetap tersebut. Ini ilustrasi, bukan proyeksi penjualan.

Ada satu konsekuensi produk yang perlu kamu pertimbangkan: jika 1 NC tetap bernilai nominal Rp120, maka ambang 500 NC secara nominal setara Rp60.000. Itu lebih tinggi dari kisaran kemampuan bayar mahasiswa Rp30.000–Rp40.000 yang pernah kamu pertimbangkan. Jika 500 NC memang merupakan saldo minimum akses Advence, pastikan harga paket, bonus kredit, atau mekanisme top-up tidak bertentangan dengan target pasar.

Aku akan mempertahankan ambang 500 NC hanya apabila kamu memang ingin menempatkan Advence sebagai fitur premium. Untuk perlindungan biaya, yang lebih penting adalah reservasi per permintaan, perhitungan berdasarkan tarif model, pembatasan token, dan batas pengeluaran global.
6. Keputusan implementasi yang aku rekomendasikan

Untuk tahap pertama, gunakan aturan ini:

    Advence tetap memerlukan saldo tersedia minimal 500 NC sebagai syarat akses.

    Backend menghitung biaya input, input tercache, output, dan tool yang digunakan berdasarkan tarif model yang benar-benar dipanggil.

    Sebelum permintaan dikirim, backend menahan kredit maksimum yang diperkirakan diperlukan.

    Tetapkan batas awal input 16.000 token dan output 2.000 token per permintaan, lalu evaluasi berdasarkan data nyata.

    Setelah respons, tagihkan pemakaian aktual dan lepaskan sisa reservasi.

    Terapkan pemeriksaan saldo atomik agar pemakaian beberapa anggota tidak melewati saldo grup.

    Terapkan anggaran global OpenAI di luar sistem NormKredit. Jika anggaran global habis, hentikan permintaan baru meskipun saldo grup masih mencukupi.

    Simpan histori setiap transaksi: ID permintaan, model API, token input, token tercache, token output, biaya API aktual, faktor konversi, NC terpakai, dan saldo sebelum/sesudah.

Kesimpulan: aturan 500 NC bisa menjadi syarat kelayakan untuk memakai Advence, tetapi bukan mekanisme keamanan finansial. Keamanan finansialnya berasal dari penetapan harga berdasarkan biaya aktual dan pembatasan pengeluaran sebelum permintaan dikirim. Sistem itu yang melindungi saldo OpenAI serta memberi dasar yang lebih terukur untuk menghitung margin Normichat. 

Pesanku: oia ada satu hal yang penting lupa aku sampaikan.
untuk model luna (di produkku kategori standard) menggunakan multiplier x1.5, model sol (di produkku kategori smart) menggunakan multiplier x2.5, dan model astra (di produkku kategori advence menggunakan multiplier x3. alasannya adalah karena syarat nc tadi sehingga bisa memberikan benefit secara tidak langsung ke user. nah bantu design ulang

Oke, sekarang desain Normichat bisa dibuat lebih presisi karena kita sudah memiliki tiga kebijakan yang jelas: multiplier berbeda untuk setiap kategori model, syarat saldo untuk Advence, dan tujuan menjaga agar saldo API tidak habis sebelum pendapatan kredit menutup biayanya.

Aku akan mempertahankan multiplier yang kamu tetapkan sebagai titik awal, lalu mengevaluasi apakah angkanya masuk akal secara ekonomi dan bagaimana mekanisme kreditnya seharusnya bekerja.
1. Desain dasar NormKredit yang diperbarui

Standard — Luna
×1,5

Model API: gpt-6-luna

Untuk penggunaan sehari-hari dan pertanyaan yang relatif sederhana.

Smart — Sol
×2,5

Model API: gpt-6.1-sol

Untuk tugas yang memerlukan kemampuan model lebih tinggi.

Advence — Astra
×3

Model API: gpt-6-astra

Model premium dengan persyaratan saldo minimal 500 NC.

Asumsiku, multiplier tersebut diterapkan ke biaya API aktual sebelum dikonversi menjadi NormKredit, bukan langsung mengalikan jumlah token. Ini cara yang lebih tepat karena tarif input dan output berbeda, bahkan ketika menggunakan model yang sama.

Secara matematis:
NC Terpakai=Biaya API (Rp)×MultiplierNilai nominal 1 NC (Rp)
NC Terpakai=Nilai nominal 1 NC (Rp)Biaya API (Rp)×Multiplier​​

Rumus ini menjadi fondasi semua perhitungan selanjutnya.
2. Apakah multiplier ×1,5, ×2,5, dan ×3 masuk akal?

Kita uji menggunakan skenario harga yang pernah kamu pertimbangkan:

    Rp30.000 untuk 250 NC, sehingga nilai nominal 1 NC adalah Rp120.

    Kurs perencanaan US$1 = Rp17.000. Ini asumsi simulasi, bukan kurs pasar terverifikasi hari ini.

    Tarif API standar untuk konteks pendek, tanpa biaya tools tambahan atau pemrosesan khusus.

Tarif resmi yang digunakan adalah Luna sebesar US$0,10 per satu juta token input dan US$0,50 per satu juta token output; Sol sebesar US$2 dan US$10; serta Astra sebesar US$10 dan US$50. Input tercache memiliki tarif berbeda. 
OpenAI API+1
Hasil perhitungan

Kategori
	

Multiplier
	

NC per 1 juta input
	

NC per 1 juta output

Standard — Luna
	

×1,5
	

21,25
	

106,25

Smart — Sol
	

×2,5
	

708,34
	

3.541,67

Advence — Astra
	

×3
	

4.250
	

21.250

Nilai NC pada tabel merupakan hasil perhitungan sebelum pembulatan final. Untuk penyelesaian transaksi, sebaiknya backend menyimpan presisi pecahan agar setiap permintaan kecil tidak dibulatkan berlebihan.

Apa yang bisa kita simpulkan?

Multiplier kamu masuk akal sebagai sistem markup biaya API, tetapi bukan sebagai sistem pemberian diskon. Semakin tinggi multiplier, semakin banyak NC yang dibakar untuk biaya API yang sama.

Ada alasan yang masuk akal untuk menetapkan multiplier berbeda: kategori premium dapat memiliki target margin lebih besar dan modelnya dapat membutuhkan biaya lebih tinggi. Namun, perbedaan multiplier itu sendiri tidak otomatis memberikan keuntungan harga kepada pengguna.

Kalau tujuanmu adalah memberi manfaat kepada pengguna yang memenuhi persyaratan 500 NC, manfaat tersebut harus berasal dari akses model premium atau fasilitas lain yang memang kamu berikan. Jangan menganggap syarat saldo 500 NC sebagai kompensasi otomatis atas pembakaran kredit yang lebih tinggi.
Berapa margin kotor dari setiap multiplier?

Jika seluruh biaya API dikalikan dengan multiplier sebelum dikonversi menjadi kredit, maka kontribusi kotor terhadap pendapatan kredit adalah:

Kategori
	

Multiplier
	

Sisa pendapatan setelah biaya API

Standard
	

×1,5
	

33,3%

Smart
	

×2,5
	

60%

Advence
	

×3
	

66,7%

Persentase tersebut dihitung terhadap nilai kredit yang dibakar dan belum dikurangi biaya pembayaran, pajak, infrastruktur, dan biaya operasional lainnya.

Artinya, Standard memiliki ruang lebih kecil untuk menutup biaya non-API dibandingkan Smart dan Advence. Ini harus menjadi pertimbangan ketika menetapkan harga paket dan target pemakaian.
3. Evaluasi khusus syarat 500 NC untuk Advence

Syarat ini perlu dibedakan dari proses pembakaran kredit.

Dengan skenario harga di atas:
500 NC×Rp120=Rp60.000
500 NC×Rp120=Rp60.000

Saldo 500 NC mewakili nilai nominal kredit Rp60.000. Dengan multiplier Advence ×3, kredit tersebut secara teoritis menopang maksimal:
Rp60.0003=Rp20.000
3Rp60.000​=Rp20.000

untuk biaya API, sebelum memperhitungkan biaya lainnya. Dengan kurs simulasi, itu setara sekitar US$1,18.

Jadi, saldo 500 NC dapat menopang beberapa permintaan Astra, tetapi jumlah sebenarnya sangat tergantung pada panjang input, panjang output, dan fitur yang digunakan. Satu juta token output Astra saja berbiaya US$50 pada tarif standar, sehingga tidak mungkin dibiayai saldo 500 NC.
Masalah jika syarat 500 NC diperiksa pada setiap permintaan

Misalnya pengguna memiliki 500 NC dan mengirim permintaan Astra. Setelah biaya aktual diselesaikan, saldo turun menjadi 389 NC.

Ada dua kemungkinan perilaku produk:

    Aturan saldo minimum yang ketat: Astra langsung terkunci setelah saldo turun di bawah 500 NC. Pengguna harus mengisi ulang hingga saldo kembali mencapai ambang tersebut.

    Aturan akses premium: pengguna memenuhi syarat untuk mengaktifkan Astra, tetapi setelahnya setiap permintaan tetap dibatasi oleh saldo yang tersisa dan estimasi biayanya.

Aku merekomendasikan pendekatan kedua. Syarat 500 NC tetap bisa digunakan sebagai persyaratan untuk membuka atau mengaktifkan akses Advence, tetapi bukan alasan untuk mengabaikan pemeriksaan biaya di setiap permintaan.

Dengan cara ini, pengguna tidak merasa telah memenuhi persyaratan saldo premium tetapi langsung kehilangan akses setelah beberapa pertanyaan.

Kalau kamu memang ingin 500 NC selalu menjadi saldo minimum yang harus dipertahankan, itu juga mungkin, tetapi UX-nya perlu dirancang secara sengaja karena saldo di bawah ambang tersebut tidak lagi dapat digunakan untuk Astra.
4. Alur pembakaran NC yang sebaiknya diterapkan

1. Pengguna mengirim pesan

Backend mengetahui kategori dan model yang dipilih.

2. Periksa akses dan saldo

Periksa syarat 500 NC untuk Advence dan saldo grup yang benar-benar tersedia.

3. Estimasikan dan reservasi NC

Hitung input, batas output, multiplier, dan biaya maksimum yang diperkirakan.

4. Kirim permintaan ke OpenAI

Hanya jika permintaan dapat ditanggung saldo grup dan anggaran global.

5. Selesaikan transaksi

Hitung biaya aktual, bakar NC sesuai multiplier, dan kembalikan sisa reservasi.

Aku menyarankan agar sistem ini menerapkan beberapa aturan teknis berikut.

Perhitungan berdasarkan biaya aktual. Jangan menganggap satu token Luna, Sol, dan Astra memiliki harga yang sama. Gunakan model ID dan tarif yang benar-benar dipakai.

Reservasi sebelum permintaan dikirim. Perkiraan biaya maksimum harus terjangkau oleh saldo. Jika tidak, jangan kirim permintaan ke OpenAI.

Transaksi atomik. Dua anggota grup yang bertanya bersamaan tidak boleh sama-sama menggunakan saldo yang sama karena pemeriksaan saldo dilakukan secara terpisah.

Presisi pecahan NC. Hindari membulatkan semua tagihan ke satu NC utuh per permintaan. Untuk Luna khususnya, tagihan banyak permintaan kecil dapat berada jauh di bawah satu NC per permintaan. Gunakan pecahan NC atau satuan internal seperti 1 NC = 1.000 unit mikro-NC.

Anggaran global API. Saldo OpenAI harus memiliki batas pengeluaran internal yang terpisah dari saldo grup. OpenAI sendiri menjelaskan bahwa saldo prabayar tidak selalu menghentikan penggunaan secara seketika ketika kredit habis; penggunaan selama jeda dapat menyebabkan saldo negatif. 
OpenAI Help Center
5. Bagaimana menetapkan kebijakan ekonomi setiap kategori?

Aku menyarankan agar multiplier tetap menjadi konfigurasi per model, bukan ditanam langsung ke dalam kode.

Konfigurasi
	

Standard
	

Smart
	

Advence

Model
	

Luna
	

Sol
	

Astra

Multiplier awal
	

1,5
	

2,5
	

3

Syarat akses
	

Saldo mencukupi
	

Saldo mencukupi
	

Minimal 500 NC untuk akses

Perhitungan biaya
	

Tarif aktual Luna
	

Tarif aktual Sol
	

Tarif aktual Astra

Reservasi biaya
	

Wajib
	

Wajib
	

Wajib

Batas output
	

Wajib
	

Wajib
	

Wajib

Pemeriksaan anggaran global
	

Wajib
	

Wajib
	

Wajib

Selanjutnya, sistem perlu memiliki konfigurasi tarif input, input tercache, output, biaya tools, kurs konversi, nilai nominal NC, multiplier, dan batas output. Simpan versi konfigurasi beserta tanggal berlakunya. Dengan begitu, ketika harga API berubah, transaksi yang sudah selesai tetap dapat diaudit menggunakan tarif yang berlaku saat itu.
6. Keputusan final yang kusarankan

Pertahankan konfigurasi multiplier ×1,5 untuk Standard, ×2,5 untuk Smart, dan ×3 untuk Advence sebagai hipotesis awal. Ketiganya tidak perlu diubah hanya karena Advence memiliki persyaratan saldo 500 NC.

Namun, terapkan tiga pengaman sejak awal:

    Syarat 500 NC hanya menentukan kelayakan akses Advence; kemampuan membiayai setiap permintaan tetap diperiksa secara terpisah.

    NC dibakar berdasarkan biaya API aktual dan multiplier model, dengan reservasi sebelum permintaan dikirim.

    Pengeluaran global OpenAI dibatasi oleh anggaran internal yang mempertahankan cadangan saldo, bukan hanya menunggu saldo prabayar habis.

Setelah itu, evaluasi data penggunaan nyata: biaya API per grup, pendapatan NC yang dibakar, margin per kategori, rata-rata panjang percakapan, dan jumlah permintaan yang gagal karena saldo tidak mencukupi.

Dengan desain tersebut, multiplier menjadi alat pengendalian ekonomi yang terukur, sedangkan syarat 500 NC berfungsi sebagai kebijakan akses produk. Keduanya saling melengkapi tanpa saling menggantikan.

Pesanku: token input yang tercache itu maksudnya gimana dan contohnya gimana

Pesan AI: Token input yang tercache (cached input tokens) adalah token input yang pernah diproses sebelumnya dan bagian awal prompt-nya bisa digunakan kembali oleh OpenAI pada permintaan berikutnya. Karena OpenAI bisa menggunakan kembali hasil pemrosesan bagian tersebut, biayanya lebih murah dibandingkan input biasa. Fitur ini disebut Prompt Caching. 
OpenAI API+1
1. Contoh sederhana di Normichat

Misalnya, kamu mempunyai satu grup yang memakai AI dengan instruksi tetap dan riwayat percakapan.
Permintaan pertama

Input yang dikirim ke AI

Instruksi sistem: 1.000 token

Riwayat percakapan: 2.000 token

Pertanyaan baru: 100 token

Total input: 3.100 token

Bagian yang memenuhi syarat cache belum tentu pernah tersimpan sebelumnya. Karena itu, permintaan pertama belum tentu mendapat diskon cache.
Permintaan kedua

Anggota mengirim pertanyaan baru

Instruksi sistem dan bagian awal riwayat tetap sama.

Pertanyaan baru: 80 token.

Misalnya, 3.000 token dari input kali ini cocok dengan cache yang tersedia, sedangkan 80 token sisanya merupakan input baru.

Input tercache: 3.000 token

Input biasa: 80 token

Angka di atas adalah ilustrasi agar konsepnya mudah dipahami. Dalam penggunaan nyata, jumlah token yang tercache bergantung pada panjang prefix yang cocok, konfigurasi model, dan ketersediaan cache.

Perlu diingat, cached input bukan berarti OpenAI mengabaikan riwayat percakapan. Model tetap menggunakan konteks tersebut untuk menjawab pertanyaan baru; hanya saja, sebagian pemrosesan input bisa digunakan kembali.
2. Berapa penghematan biayanya?

Sebagai contoh matematis, misalnya suatu model mempunyai tarif ilustratif berikut:

Jenis input
	

Tarif per 1 juta token

Input biasa
	

$2,00

Input tercache
	

$0,20

Artinya, input tercache pada contoh ini hanya berbiaya 10% dari input biasa, atau diskon 90%. Tarif sebenarnya berbeda menurut model; dokumentasi OpenAI mencantumkan tarif cache yang khusus untuk tiap model. 
OpenAI API

Misalkan permintaan kedua mempunyai 3.000 token tercache dan 80 token biasa:

    Input tercache: 3.000/1.000.000×$0,20=$0,0006
    3.000/1.000.000×$0,20=$0,0006

    Input biasa: 80/1.000.000×$2=$0,00016
    80/1.000.000×$2=$0,00016

Total biaya inputnya adalah $0,00076 pada contoh tarif tersebut.

Kalau seluruh 3.080 token dikenakan tarif input biasa, biayanya menjadi $0,00616. Jadi, penghematan dalam ilustrasi ini mencapai sekitar 87,7%.

Biaya output tetap dihitung terpisah. Prompt caching tidak membuat token jawaban menjadi gratis atau lebih murah dengan sendirinya. 
OpenAI API
3. Bagaimana Normichat dapat memanfaatkannya?

Ini relevan untuk Normichat karena chat grup biasanya mengirimkan instruksi sistem dan sebagian riwayat percakapan bersama setiap permintaan ke AI.

Agar prompt caching lebih efektif, kamu bisa:

    Menempatkan instruksi sistem dan konteks yang stabil di awal prompt.

    Mempertahankan riwayat lama dan menambahkan pesan baru di bagian akhir, bukan mengubah susunan seluruh riwayat.

    Menghindari informasi yang berubah-ubah, seperti timestamp, di awal prompt jika informasi tersebut membuat prefix berbeda.

    Mencatat jumlah cached input tokens yang benar-benar dilaporkan API, bukan memperkirakannya sendiri.

OpenAI menjelaskan bahwa prefix yang cocok harus cukup panjang dan tersedia dalam cache. Untuk model-model terbaru tertentu, panjang minimum prefix yang dapat di-cache adalah 1.024 token; ketentuan model lainnya dapat berbeda. Cache juga tidak tersedia tanpa batas waktu. 
OpenAI API
4. Pengaruhnya terhadap sistem NormKredit

Ini bagian yang penting untuk desain bisnis kamu.

Misalnya, biaya input biasa suatu permintaan adalah $0,01, tetapi setelah sebagian input menggunakan cache, biaya aktualnya menjadi $0,003.

Kalau multiplier kategori Standard adalah ×1,5, maka biaya untuk konversi menjadi NormKredit harus memakai biaya aktual $0,003, bukan biaya hipotetis $0,01:
Biaya konversi=$0,003×1,5=$0,0045
Biaya konversi=$0,003×1,5=$0,0045

Angka tersebut masih perlu dikonversi ke rupiah dan satuan NC sesuai aturan Normichat.

Dengan demikian, penghematan dari Prompt Caching bisa membantu Normichat menekan biaya API. Namun, sebaiknya jangan langsung menjadikan seluruh penghematan itu sebagai tambahan keuntungan sebelum kamu melihat tingkat cache hit pada penggunaan nyata.

Kesimpulan: cached input tokens adalah bagian input yang pemrosesannya dapat digunakan kembali sehingga tarifnya lebih murah. Untuk Normichat, cara paling akurat adalah membaca rincian penggunaan dan tarif aktual dari respons API, lalu menghitung pembakaran NormKredit berdasarkan biaya aktual tersebut.

Pesanku: pada open ai ada tokenizer, aku coba input bahasa inggris dan terhitung langsung token yang digunakan, tapi kan input, bagaimana dengan output? bagaimana cara menghitung token yang digunakan untuk output?

Pesan AI: Ada dua cara menghitung token output di OpenAI, tetapi untuk Normichat cara yang paling akurat adalah membaca data penggunaan dari respons API, bukan menghitung ulang teks jawabannya.
1. Cara pertama: menggunakan Tokenizer

Kamu bisa membuka OpenAI Tokenizer, lalu menempelkan teks jawaban AI yang sudah dihasilkan.

Misalnya, AI menjawab:

    Artificial intelligence can help students understand complex concepts through interactive explanations and examples.

Kamu tempelkan seluruh jawaban tersebut ke Tokenizer. Tool akan membagi teks menjadi token dan menunjukkan jumlahnya.

Namun, hasil tersebut hanya menghitung token dari teks yang terlihat. Jumlahnya belum tentu sama dengan token output yang ditagihkan OpenAI, karena model tertentu juga menghasilkan token penalaran internal dan token struktural yang tidak terlihat di jawaban. 
OpenAI Help Center+1

Jadi, Tokenizer cocok untuk menguji teks dan memperkirakan ukuran jawaban, tetapi bukan sumber utama untuk menghitung biaya penggunaan API.
2. Cara kedua: membaca token output dari API

Ketika Normichat mengirim permintaan ke OpenAI, respons API menyertakan informasi penggunaan token.

Nama kolomnya bergantung pada endpoint yang kamu gunakan:

Endpoint API
	

Token input
	

Token output
	

Total

Responses API
	

usage.input_tokens
	

usage.output_tokens
	

usage.total_tokens

Chat Completions API
	

usage.prompt_tokens
	

usage.completion_tokens
	

usage.total_tokens

Nama kolom dan perilaku ini dijelaskan dalam dokumentasi resmi OpenAI tentang penggunaan API. 
OpenAI Help Center

Contoh sederhana menggunakan JavaScript dan Responses API:

import OpenAI from "openai";

const client = new OpenAI();

const response = await client.responses.create({
  model: "MODEL_ID",
  input: "Explain artificial intelligence to students."
});

// Penggunaan token aktual dari API
console.log("Input:", response.usage.input_tokens);
console.log("Output:", response.usage.output_tokens);
console.log("Total:", response.usage.total_tokens);

Ganti MODEL_ID dengan ID model yang benar-benar kamu gunakan. Contoh ini menunjukkan cara membaca data penggunaan, bukan ID model tertentu.

Misalnya respons API melaporkan:

{
  "usage": {
    "input_tokens": 100,
    "output_tokens": 250,
    "total_tokens": 350
  }
}

Berarti permintaan tersebut menggunakan 100 token input dan 250 token output, dengan total 350 token.

Perhatikan bahwa 250 token output ini adalah angka yang dilaporkan API, bukan hasil menghitung teks jawaban secara manual.
3. Mengapa jumlah token output bisa lebih banyak daripada teks jawaban?

Pada model yang menggunakan reasoning, sebagian token output dapat digunakan untuk penalaran internal sebelum model memberikan jawaban. Token tersebut tidak selalu muncul pada teks yang kamu lihat, tetapi tetap dihitung dalam penggunaan output dan dapat dikenakan biaya. 
OpenAI Help Center+1

Karena itu, jangan berasumsi bahwa seluruh token output bisa dihitung hanya dari teks jawaban yang ditampilkan.
4. Cara yang sebaiknya diterapkan pada Normichat

Aku merekomendasikan alur berikut:

1. Kirim permintaan ke OpenAI

Backend memilih model dan meneruskan prompt pengguna.

2. Baca data usage

Simpan token input, cached input, token output, dan rincian lain yang dilaporkan API.

3. Hitung biaya aktual

Terapkan tarif yang sesuai dengan model serta jenis tokennya.

4. Konversikan ke NormKredit

Terapkan multiplier Standard ×1,5, Smart ×2,5, atau Advence ×3 pada biaya yang sesuai dengan kebijakan Normichat.

Dengan sistem ini, kamu tidak perlu memprediksi berapa token jawaban AI sebelum jawaban selesai dibuat untuk mengetahui tagihan akhirnya. Sebelum permintaan dikirim, kamu tetap perlu melakukan estimasi dan reservasi kredit agar saldo grup tidak terlampaui. Setelah respons selesai, gunakan data usage untuk menyelesaikan tagihan aktual.

Intinya: Tokenizer menghitung token dari teks yang kamu masukkan, sedangkan usage.output_tokens pada Responses API atau usage.completion_tokens pada Chat Completions memberikan angka penggunaan output yang dilaporkan API. Untuk menghitung pembakaran NormKredit, gunakan angka dari API.

Pesanku: nah aku masih agak bingung nih tentang prompt cache. sebenarnya bagaimana melihat jumlah prompt cache?

    kalau aku pakai 1 api key dari open ai dan membuatkan dan memberikan riwayat chat beberapa lama, apakah tidak boros token dan apakah tidak akan tercampur dengan history chat suatu grup dengan grup lain 
    intinya yang ku maksud itu tentang prompt cache dan penginputan prompt dengan 1 api key

Pesan AI: Aku paham kebingunganmu. Untuk Normichat, ada tiga hal yang sekilas terlihat sama, tetapi sebenarnya berbeda: riwayat chat, prompt caching, dan API key.

Contohnya, Normichat punya Grup A untuk tugas kuliah dan Grup B untuk diskusi bisnis. Keduanya memakai API key OpenAI milik backend Normichat.

    Riwayat chat menentukan informasi percakapan yang dikirim ke AI untuk menjawab pesan baru.

    Prompt caching memungkinkan OpenAI menggunakan kembali pemrosesan bagian prompt yang sama sehingga input tertentu bisa lebih murah.

    API key mengautentikasi permintaan ke akun OpenAI. API key itu sendiri bukan tempat menyimpan riwayat percakapan.

Jadi, memakai satu API key tidak berarti semua grup harus berbagi riwayat chat. Kamu tetap bisa memisahkan konteks setiap grup di backend Normichat.
1. Sebenarnya, bagaimana prompt cache bekerja?

Bayangkan setiap kali anggota grup mengirim pesan, Normichat menyusun prompt untuk AI. Prompt tersebut bisa terdiri dari instruksi sistem, konteks grup, riwayat percakapan, dan pesan terbaru.

OpenAI dapat menyimpan hasil pemrosesan bagian awal prompt yang memenuhi syarat. Kalau permintaan berikutnya memiliki bagian awal yang cocok dan cache-nya masih tersedia, OpenAI dapat menggunakan kembali hasil pemrosesan tersebut. Inilah prompt caching. 
OpenAI API+1
Contoh dua permintaan dari grup yang sama

Permintaan 1

Instruksi Normichat: 1.000 token

Konteks percakapan: 2.000 token

Pesan baru: 100 token

Total input: 3.100 token

Angka ilustrasi. Belum tentu seluruh input memenuhi syarat cache.

Anggota mengirim pesan lagi

Permintaan 2

Bagian awal yang cocok dengan cache: 3.000 token

Tambahan pesan baru: 80 token

Total input: 3.080 token

Jika 3.000 token tersebut benar-benar tercache, biaya inputnya menggunakan tarif cache untuk bagian itu.

Perlu diingat, contoh 3.000 token tersebut hanya ilustrasi. Cache tidak dijamin selalu cocok, dan prompt yang hanya mirip belum tentu bisa menggunakan cache.

Prompt cache bukan riwayat percakapan dan bukan memori AI. Cache hanya membantu mengurangi biaya pemrosesan input yang memenuhi syarat. AI tetap perlu menerima konteks yang relevan untuk menjawab pesan baru.
2. Bagaimana melihat jumlah token yang berhasil tercache?

Kamu bisa memeriksanya dari respons API OpenAI, bukan dengan menghitung sendiri menggunakan Tokenizer.

Contohnya, jika kamu menggunakan Responses API, periksa informasi penggunaan seperti berikut:

{
  "usage": {
    "input_tokens": 3080,
    "input_tokens_details": {
      "cached_tokens": 3000
    },
    "output_tokens": 400,
    "total_tokens": 3480
  }
}

Angka di atas hanyalah contoh. Artinya:

Informasi
	

Jumlah

Total input
	

3.080 token

Input yang tercache
	

3.000 token

Input yang tidak tercache
	

80 token

Output
	

400 token

Dengan contoh tersebut, sekitar 97,4% input berhasil tercache.

Rumusnya:
Cache hit rate=Cached input tokensTotal input tokens×100%
Cache hit rate=Total input tokensCached input tokens​×100%

Untuk memantau Normichat, simpan angka tersebut per permintaan. Kamu juga dapat memakai Prompt Caching Dashboard dan alat diagnostik cache yang disediakan OpenAI. 
OpenAI API+1

Catatan: model atau endpoint tertentu dapat menyediakan informasi tambahan seperti cache_write_tokens. Ini berguna untuk membedakan input biasa, input yang dibaca dari cache, dan input yang dibebankan untuk penulisan cache.
3. Apakah riwayat chat panjang akan boros token?

Ya, bisa. Prompt caching membantu menurunkan biaya input, tetapi tidak membuat riwayat percakapan menjadi gratis.

Misalnya, seorang anggota terus berdiskusi di grup yang sama:

Giliran
	

Riwayat yang dikirim
	

Pesan baru
	

Total input ilustratif

1
	

0
	

100
	

100 token

2
	

100
	

100
	

200 token

10
	

900
	

100
	

1.000 token

50
	

4.900
	

100
	

5.000 token

Ini ilustrasi ketika seluruh riwayat sebelumnya selalu disertakan. Pada giliran ke-50, model bisa menerima sekitar 5.000 token input meskipun pesan terbaru hanya 100 token.

Jika prefix yang sama berhasil tercache, sebagian biaya input bisa lebih murah. Namun, token input masih dihitung dalam penggunaan API dan tetap dikenai tarif yang berlaku, baik tarif biasa, tarif cache, maupun tarif penulisan cache sesuai model dan konfigurasi. 
OpenAI API+1

Untuk itu, aku merekomendasikan tiga pendekatan:

    Pertahankan riwayat terbaru yang relevan, bukan seluruh percakapan selamanya.

    Ringkas percakapan lama secara berkala, misalnya menjadi ringkasan tujuan grup, keputusan penting, dan konteks yang masih diperlukan.

    Pertahankan bagian prompt yang stabil agar peluang cache lebih baik, dan tempatkan informasi yang sering berubah setelah bagian stabil tersebut.

Dengan cara ini, Normichat bisa mengontrol ukuran konteks tanpa menghilangkan informasi penting dari percakapan.
4. Apakah satu API key akan membuat riwayat antargrup tercampur?

Tidak. Satu API key tidak otomatis mencampurkan riwayat percakapan. Pemisahan tersebut menjadi tanggung jawab backend Normichat.

OpenAI menjelaskan bahwa setiap permintaan generasi teks bersifat independen jika kamu mengelola konteks sendiri. Untuk membuat percakapan berlanjut, aplikasi bisa mengirim ulang riwayat yang relevan atau menggunakan mekanisme Conversation State, seperti conversation atau previous_response_id pada Responses API. 
OpenAI API

Contohnya, Normichat memiliki dua grup:

Grup A — Tugas CNN

Simpan group_id, riwayat chat A, dan ID percakapan A.

Konteks: arsitektur CNN, eksperimen, hasil akurasi.

Grup B — Bisnis

Simpan group_id, riwayat chat B, dan ID percakapan B yang berbeda.

Konteks: harga produk, biaya operasional, strategi bisnis.

Satu API key untuk keduanya

Backend menentukan konteks mana yang dikirim. Grup A hanya menerima riwayat A, sedangkan Grup B hanya menerima riwayat B.

Yang harus dihindari adalah menyimpan semua riwayat dalam satu daftar global lalu mengirimkannya ke setiap permintaan tanpa menyaring berdasarkan group_id.

Kalau backend salah mengirimkan riwayat Grup A ke permintaan Grup B, barulah konteks bisa tercampur. Itu masalah pengelolaan data aplikasi, bukan akibat penggunaan satu API key.

Satu hal lagi: jika memakai previous_response_id, jangan menganggap riwayat sebelumnya gratis. OpenAI menyatakan bahwa input dari respons sebelumnya dalam rantai tersebut tetap dikenai biaya input. 
OpenAI API
5. Apakah prompt cache antargrup bisa tercampur?

Ini bagian yang paling penting untuk membedakan cache dari riwayat.

Misalnya, Grup A dan Grup B menggunakan instruksi sistem Normichat yang sama. Karena bagian awal prompt mereka identik, bagian instruksi yang sama berpotensi memanfaatkan cache.

Itu tidak berarti isi percakapan kedua grup digabung. Cache hanya digunakan untuk bagian prefix yang cocok pada permintaan, sedangkan konteks yang berbeda tetap harus diberikan sesuai permintaan masing-masing.

Namun, ada opsi prompt_cache_key pada model yang mendukungnya. Kamu dapat menggunakannya untuk memisahkan akuntansi cache per grup atau pengguna. Pada model yang lebih lama, kunci ini juga membantu mengarahkan permintaan ke cache yang relevan; pada model yang lebih baru, routing otomatis sudah ditangani OpenAI dan kunci tersebut opsional untuk tujuan akuntansi. Kunci ini bukan pengganti ID percakapan dan bukan mekanisme keamanan yang mengisolasi riwayat. 
OpenAI API+1

Untuk Normichat, aku menyarankan:

    Gunakan satu prompt sistem umum yang stabil untuk seluruh aplikasi.

    Simpan riwayat dan ID percakapan secara terpisah per grup.

    Tempatkan konteks khusus grup setelah instruksi umum.

    Pertimbangkan prompt_cache_key yang stabil per grup jika kamu membutuhkan pemisahan pelaporan cache atau kontrol routing sesuai model yang digunakan.

    Jangan pernah mengirim riwayat grup lain hanya karena menggunakan API key atau konfigurasi model yang sama.

6. Desain yang paling masuk akal untuk Normichat

Anggota mengirim pesan

Permintaan masuk membawa ID grup dan pesan baru.

Backend memilih konteks

Ambil riwayat hanya dari grup tersebut, lalu batasi dan ringkas bila diperlukan.

Susun prompt

Instruksi umum yang stabil → konteks grup → riwayat relevan → pesan baru.

Panggil OpenAI dengan API key backend

OpenAI memproses prompt dan memanfaatkan cache jika memenuhi syarat.

Catat biaya dan penggunaan

Simpan input, cached input, output, biaya aktual, dan pembakaran NormKredit.

Saran penting: jangan membuat satu percakapan OpenAI untuk seluruh Normichat. Gunakan identitas percakapan yang berbeda untuk setiap grup, atau kelola riwayat sendiri di database dan kirim hanya riwayat grup terkait. API key boleh sama, tetapi konteks percakapannya harus tetap terisolasi.

Dengan desain seperti ini, kamu bisa memperoleh manfaat prompt caching tanpa mengorbankan pemisahan riwayat maupun kehilangan kendali atas biaya NormKredit.


Pesanku: nah ada beberapa hal yang harus ku pastikan sebelum ku lakukan pengecekan dan penerapan hal yang kita bahas pada programku langsung.
menurutku memang baiknya seluruh riwayat chat 1 grup itu di kirim ke llm agar llm paham hal atau topik yang sedang dibahas, tetapi konsekuensinya adalah menggunakan token akan sangat boros.
bagaimana design yang baik menurutmu?

Pesan AI: Menurutku, untuk Normichat, desain terbaik bukan mengirim seluruh riwayat setiap kali dan bukan pula hanya mengirim pesan terbaru. Gunakan pendekatan hybrid context management: riwayat lengkap tetap disimpan, tetapi AI menerima konteks yang dipilih secara cerdas.

Pendekatan ini sejalan dengan dokumentasi OpenAI tentang pengelolaan conversation state dan compaction, yang ditujukan untuk mempertahankan konteks percakapan panjang tanpa terus membawa seluruh konteks mentah. 
OpenAI API+1
1. Desain yang aku rekomendasikan

Database Normichat

Menyimpan seluruh riwayat pesan, identitas grup, waktu, dan informasi percakapan.

Context Manager

Memilih ringkasan, riwayat terbaru, keputusan penting, dan pesan lama yang relevan.

Prompt Builder

Menyusun prompt secara konsisten dan menjaga bagian awal yang bisa digunakan kembali oleh prompt cache.

OpenAI API

Memproses konteks yang terpilih dan menghasilkan jawaban.

Usage & NormKredit

Mencatat token input, cached input, output, biaya aktual, dan pembakaran NC.

Kuncinya adalah memisahkan penyimpanan riwayat dari konteks yang dikirim ke AI. Kamu tidak perlu menghapus pesan lama untuk menghemat token.
2. Apa saja yang sebaiknya dikirim ke AI?

Aku menyarankan setiap permintaan disusun dari lima bagian.
1

Instruksi sistem yang stabil

Aturan perilaku AI Normichat, format jawaban, aturan keamanan, dan cara memperlakukan percakapan grup.

Tujuan: menjaga perilaku model konsisten dan membantu penggunaan cache ketika prefix yang sama dipakai ulang.
2

Ringkasan grup

Gambaran singkat tentang tujuan grup, topik yang sedang dibahas, keputusan yang telah dibuat, dan konteks yang tetap penting.

Tujuan: memberi orientasi tanpa mengirim seluruh percakapan lama.
3

Informasi lama yang relevan

Ambil pesan atau potongan percakapan lama apabila pertanyaan sekarang merujuk pada pembahasan sebelumnya.

Tujuan: menjaga kemampuan mengingat detail lama tanpa mengirim semua pesan.
4

Riwayat percakapan terbaru

Sertakan beberapa pesan terbaru secara berurutan, termasuk pertanyaan dan jawaban AI.

Tujuan: menjaga alur dialog, memahami kata seperti "itu", "tadi", atau "bagaimana kalau begitu".
5

Pesan terbaru yang harus dijawab

Pesan anggota yang baru masuk.

Tujuan: menjadi permintaan utama pada giliran saat ini.

Dengan struktur ini, AI tetap memahami arah percakapan, sementara sistem dapat mengontrol seberapa besar konteks yang dibawa.
3. Bagaimana mengatur riwayat lama?

Untuk Normichat, aku tidak menyarankan satu ringkasan besar yang memuat semua topik grup. Group chat bisa berisi banyak topik yang tidak berkaitan.

Gunakan tiga lapisan memori:

Lapisan
	

Isi
	

Kapan digunakan

Ringkasan grup
	

Tujuan grup, keputusan penting, konteks jangka panjang
	

Hampir setiap permintaan

Ringkasan topik
	

Ringkasan per pembahasan atau thread
	

Saat topik tersebut aktif atau dirujuk

Riwayat mentah
	

Pesan asli yang tersimpan di database
	

Saat detail spesifik diperlukan

Misalnya Grup A membahas arsitektur CNN pada hari Senin, kemudian membahas anggaran organisasi pada hari Jumat.

Kalau seorang anggota bertanya, “Apa keputusan kita soal optimizer kemarin?”, Context Manager mencari percakapan CNN yang relevan. AI tidak perlu menerima semua percakapan tentang anggaran organisasi.

Sebaliknya, kalau anggota hanya bertanya, “Lanjutkan penjelasan yang tadi,” riwayat terbaru biasanya sudah cukup.

Untuk pencarian pesan lama, kamu bisa memulai dengan pencarian kata kunci dan metadata. Ketika jumlah percakapan makin besar, tambahkan semantic retrieval menggunakan embedding agar sistem bisa menemukan pembahasan yang relevan walaupun kata-katanya berbeda.

Catatan penting: ringkasan dan hasil pencarian tidak boleh dianggap sebagai pengganti permanen pesan asli. Simpan ID pesan sumber agar detail bisa diperiksa kembali dan AI tidak harus mengandalkan ringkasan ketika pengguna meminta informasi presisi.
4. Contoh kebijakan konteks awal untuk MVP

Angka berikut adalah konfigurasi awal untuk diuji, bukan ketentuan universal dari OpenAI.
Context budget awal

Instruksi sistem

Bagian umum yang stabil
500–1.000 token

Ringkasan grup dan topik

Diperbarui sesuai kebutuhan
500–1.000 token

Pesan lama yang ditemukan

Hanya saat relevan
0–2.000 token

Riwayat terbaru

Pesan lengkap dalam batas token
Maks. 6.000 token

Target total input

Termasuk pesan terbaru dan format tambahan
Sekitar 10.000 token

Jika total konteks melewati target, sistem mengurangi pesan yang kurang relevan terlebih dahulu. Jangan memotong teks secara sembarangan di tengah percakapan karena itu bisa menghilangkan hubungan antara pertanyaan dan jawaban.

Batas ini perlu diukur kembali dengan pola penggunaan nyata, terutama ketika pengguna mengirim gambar, dokumen, atau hasil tool yang konsumsi tokennya bisa berbeda.
5. Bagaimana ringkasan diperbarui tanpa membuat biaya baru terlalu besar?

Jangan memanggil model khusus untuk merangkum setelah setiap pesan. Itu berpotensi menambah biaya API dan latensi.

Sebagai gantinya, buat proses pembaruan saat salah satu kondisi berikut terjadi:

    Riwayat terbaru mencapai ambang token tertentu.

    Sudah cukup banyak pesan baru sejak ringkasan terakhir.

    Topik berubah secara signifikan.

    Ada keputusan penting yang perlu disimpan.

    Pengguna merujuk detail lama yang tidak tersedia pada konteks saat ini.

Saat merangkum, instruksikan model untuk mempertahankan fakta, keputusan, batasan, dan hal yang belum selesai. Pisahkan informasi yang dinyatakan langsung oleh anggota dari kesimpulan yang hanya disimpulkan oleh AI. Simpan waktu ringkasan terakhir dan pesan sumber yang diringkas.

Dengan desain ini, pesan lama tetap utuh di database, sedangkan ringkasan dipakai untuk memberikan konteks murah kepada AI.
6. Lalu apa peran prompt caching?

Prompt caching tetap berguna, tetapi fungsinya berbeda dari Context Manager.

OpenAI menerangkan bahwa cache bekerja pada prefix prompt yang cocok. Untuk banyak model terbaru, prefix yang memenuhi syarat memiliki minimum cacheable 1.024 token; persyaratan dan tarif cache bergantung pada model serta konfigurasi. 
OpenAI API

Supaya peluang cache lebih tinggi, susun prompt dengan urutan yang konsisten:

    Instruksi sistem umum yang stabil.

    Bagian konfigurasi yang relatif tetap.

    Konteks grup dan ringkasan.

    Informasi lama hasil pencarian.

    Riwayat terbaru dan pesan baru.

Jangan meletakkan timestamp yang selalu berubah di depan instruksi sistem apabila tidak diperlukan. Perubahan prefix dapat mengurangi pemakaian kembali cache. 
OpenAI API

Satu hal yang perlu kamu ingat: prompt caching tidak menggantikan pembatasan konteks. Cached input tetap termasuk penggunaan input; cache hanya mengubah biaya untuk bagian yang berhasil digunakan kembali.

Ada pula opsi compaction di Responses API untuk mengurangi ukuran konteks percakapan panjang. Ini bisa menjadi alternatif untuk alur tertentu, tetapi perlu diuji terhadap struktur percakapan Normichat dan tidak menghilangkan kebutuhan akan penyimpanan riwayat yang terpisah. 
OpenAI API
7. Bagaimana dengan satu API key untuk semua grup?

Desain ini tetap bisa menggunakan satu API key di backend. Yang harus terpisah adalah konteks dan pengelolaan state-nya.

    Setiap grup mempunyai ID dan riwayat sendiri.

    Ringkasan grup A tidak pernah dimasukkan ke prompt grup B.

    Hasil retrieval harus difilter berdasarkan ID grup dan izin akses.

    Jika menggunakan OpenAI Conversations atau previous_response_id, simpan pemetaan ID percakapan yang berbeda untuk setiap grup.

    Jangan memakai satu rantai respons global untuk seluruh grup.

OpenAI juga menjelaskan bahwa penggunaan previous_response_id tidak membuat semua input sebelumnya gratis; token input dalam rantai respons tetap dapat dikenai biaya. 
OpenAI API

Karena itu, untuk Normichat aku lebih memilih database Normichat sebagai sumber utama riwayat dan Context Manager yang menentukan konteks tiap permintaan. Fitur state OpenAI bisa digunakan jika sesuai dengan arsitektur, tetapi jangan diandalkan sebagai mekanisme penghemat biaya otomatis.
Rekomendasi akhir

Untuk implementasi awal Normichat, terapkan database riwayat lengkap + ringkasan grup/topik + pencarian pesan relevan + jendela percakapan terbaru + prompt caching.

Jangan menghapus percakapan lama. Jangan pula selalu mengirim seluruh percakapan. Kirim hanya konteks yang membantu menjawab pertanyaan saat ini, dan pertahankan sumber aslinya supaya sistem bisa mengambil detail ketika dibutuhkan.

Setelah implementasi, ukur sedikitnya empat metrik: total input token per permintaan, persentase cached input, biaya API per pesan, dan seberapa sering AI gagal mengingat konteks lama. Dari sana kamu dapat mengatur ukuran riwayat dan ringkasan berdasarkan data nyata, bukan perkiraan saja.


Pesanku: aku sudah lumayan tercerahkan. tapi ada satu pertanyaan dan permasalahan yang mengganjal. dari tadi kmu sebutin tentang context manager. emang gimana cara yang baik buat context manager tersebut? itukan sifatnya otomatis gitu, emang ga butuh ai disana? terus kmu sebutin pakai semantic retrieval, itu kayak buat rag dulu kah untuk membuat context managernya atau gimana buat context manager yang kau maksud supaya bener bener halus cara atau penggunaan percakapan user dengan ai, maksudnya ya antar user chatan di grup tuh tanpa tag ai dan memang ga butuh jawaban ai, ketika mereka butuh jawaban ai dari percakapan mereka, ai langsung paham hal yang dibahas gitu, trigger atau moment ai menjawab itu saat di tag saja. nah itu gimana? sebelum kita simpulkan dan akhiri nih

Pesan AI: Nah, sekarang aku lebih memahami pengalaman yang ingin kamu bangun di Normichat. Menurutku, ini justru salah satu keputusan arsitektur paling penting untuk produkmu.

Kamu ingin Normichat terasa seperti grup WhatsApp atau Discord biasa: anggota bebas mengobrol, membahas sesuatu, mengirim informasi, dan saling membalas tanpa AI ikut campur. Namun, ketika seseorang men-tag AI, misalnya @Normi, AI langsung memahami pembicaraan yang sedang berlangsung, termasuk konteks dari pesan anggota lain yang tidak secara langsung ditujukan kepadanya.

Artinya, AI harus selalu siap memahami percakapan, tetapi tidak harus selalu aktif memproses setiap pesan.

Dari kebutuhan ini, aku merekomendasikan Context Manager yang menggabungkan logika backend, penyimpanan konteks, ringkasan berbantuan AI, dan pencarian pesan relevan. Semantic retrieval atau RAG bisa menjadi salah satu bagiannya, tetapi bukan keseluruhan Context Manager.
1. Pertama, bedakan tiga pekerjaan yang selama ini kita sebut Context Manager

A. Context Storage — menyimpan percakapan

Semua pesan anggota disimpan berdasarkan grup, urutan waktu, pengirim, dan ID pesan. Ini pekerjaan database, tidak membutuhkan AI.

B. Context Manager — menentukan konteks

Memilih ringkasan yang tepat, beberapa pesan terakhir, dan pesan lama yang relevan. Sebagian besar keputusan dapat menggunakan kode biasa; beberapa tugas membutuhkan AI.

C. Context Understanding — memahami makna

AI membantu merangkum topik, mengidentifikasi keputusan, atau menemukan hubungan semantik yang tidak dapat ditangkap dengan pencarian kata biasa.

Jadi, jawaban untuk pertanyaanmu: ya, AI bisa dibutuhkan di dalam Context Manager, tetapi tidak perlu dipanggil setiap kali seseorang mengirim pesan.

Kalau setiap pesan memicu AI untuk menganalisis percakapan, kamu akan menambah biaya, latensi, dan kompleksitas. Padahal, banyak pesan hanyalah obrolan biasa.
2. Arsitektur yang cocok untuk Normichat

Aku menyarankan model event-driven context management: setiap pesan menjadi sebuah event yang diproses oleh backend, tetapi hanya event tertentu yang memicu pemanggilan AI.

Anggota mengirim pesan ke grup

Contoh: diskusi tugas, pertanyaan, gambar, atau sekadar obrolan.

Backend menyimpan pesan

Database, pengirim, timestamp, group ID, dan urutan pesan.

Pemeriksaan trigger

Apakah pesan men-tag @Normi?

Tidak di-tag

Tidak ada jawaban AI. Pesan tetap disimpan dan konteks dapat diperbarui.

Di-tag

Ambil konteks relevan, panggil AI, lalu kirim jawaban ke grup.

Pembaruan ringkasan dan indeks pencarian berjalan terpisah di latar belakang bila diperlukan.

Ada satu prinsip yang perlu dijaga: trigger untuk menjawab AI harus berbeda dari trigger untuk memelihara konteks.

    Trigger jawaban: hanya saat pengguna men-tag AI.

    Trigger pemeliharaan konteks: berdasarkan jumlah pesan, ukuran token, perubahan topik, atau jadwal pekerjaan latar belakang.

Dengan begitu, anggota bisa mengobrol tanpa gangguan dari AI.
3. Apakah perlu AI untuk mengelola konteks otomatis?

Perlu untuk beberapa pekerjaan, tetapi bukan semuanya.

Pekerjaan
	

AI diperlukan?
	

Kapan dijalankan?

Menyimpan pesan
	

Tidak
	

Setiap pesan

Menghitung jumlah pesan dan token
	

Tidak
	

Setiap pesan atau sebelum request

Mengambil pesan terbaru
	

Tidak
	

Saat AI di-tag

Menentukan batas riwayat
	

Tidak harus
	

Saat menyusun konteks

Membuat ringkasan percakapan
	

Ya, biasanya
	

Ketika ambang tertentu tercapai

Mengidentifikasi keputusan penting
	

AI membantu
	

Saat membuat atau memperbarui ringkasan

Mencari pesan lama berdasarkan makna
	

Bisa memakai embedding
	

Saat konteks lama diperlukan

Menghasilkan jawaban
	

Ya
	

Saat AI di-tag

Artinya, tidak perlu ada satu AI yang terus-menerus membaca semua pesan untuk menentukan apa yang sedang terjadi.
Contoh pemeliharaan ringkasan

Misalnya, sebuah grup memiliki 200 pesan. Sistem sudah mempunyai ringkasan yang mencakup pesan 1–100, lalu pesan baru terus masuk.

Saat pesan baru mencapai ambang tertentu, backend dapat menjadwalkan pekerjaan untuk memperbarui ringkasan berdasarkan ringkasan sebelumnya dan kumpulan pesan baru. AI hanya dipanggil ketika pekerjaan tersebut memang diperlukan.

Ringkasan sebaiknya menyimpan:

    Topik yang sedang dibahas.

    Tujuan dan konteks penting.

    Keputusan yang sudah disepakati.

    Fakta dan batasan yang diberikan anggota.

    Pertanyaan yang belum terselesaikan.

    Rentang ID pesan sumber dan waktu pembaruan.

Jangan memperbarui ringkasan setelah setiap pesan. Mulailah dengan pemicu berdasarkan ukuran konteks atau jumlah pesan baru, kemudian evaluasi frekuensi dan biaya aktual.

Perlu dicatat bahwa ringkasan yang tidak diperbarui setiap saat bisa sedikit tertinggal. Itu tidak masalah jika Context Manager juga menyertakan pesan-pesan terbaru saat AI dipanggil.
4. Apakah harus membuat RAG?

Menurutku, ya, tetapi jangan langsung membangun RAG yang kompleks. Mulailah dengan context retrieval sederhana, lalu tambahkan semantic retrieval ketika memang dibutuhkan.

RAG (Retrieval-Augmented Generation) adalah pendekatan mengambil informasi yang relevan dari sumber eksternal sebelum mengirimkannya sebagai konteks kepada model. Untuk Normichat, sumber tersebut adalah riwayat percakapan dan ringkasan grup.

Ada tiga tingkat yang bisa kamu terapkan.
Level 1

Recent Context

Ambil ringkasan grup dan sejumlah pesan terbaru.

Cocok untuk MVP, terutama untuk percakapan yang masih pendek dan berurutan.

Kelebihan: sederhana, cepat, dan biaya implementasi rendah. Kekurangan: sulit menjawab pertanyaan tentang detail lama yang tidak ada di konteks terbaru.
Level 2

Hybrid Retrieval

Gabungkan ringkasan, pesan terbaru, pencarian kata kunci, metadata, dan semantic search.

Cocok ketika grup sudah mempunyai banyak pesan dan beragam topik.

Kelebihan: bisa menemukan pesan lama berdasarkan kata maupun makna. Kekurangan: perlu indeks pencarian dan evaluasi relevansi.
Level 3

Advanced Context Management

Tambahkan segmentasi topik, ringkasan per topik, pengambilan konteks adaptif, dan evaluasi otomatis untuk kasus ambigu.

Kelebihan: lebih fleksibel untuk grup yang sangat aktif. Kekurangan: lebih kompleks dan memerlukan pengujian lebih banyak.

Aku akan mulai dari Level 1 dan menyiapkan struktur database agar bisa berkembang ke Level 2. Jangan langsung membangun sistem embedding, reranking, dan banyak agen hanya karena produkmu menggunakan AI.
Bagaimana semantic retrieval bekerja?

Misalnya, 300 pesan lalu seseorang berkata:

    "Jadi akhirnya kita pilih optimizer yang mana?"

Pencarian kata biasa mungkin tidak menemukan jawaban karena pesan lama berbunyi:

    "Menurutku kita pakai AdamW saja karena hasil validasinya lebih stabil."

Semantic retrieval dapat menemukan pesan tersebut karena maknanya berkaitan, walaupun kata-katanya berbeda.

Dalam implementasi yang lebih matang, sistem dapat mencari berdasarkan group_id, rentang waktu, topik, dan embedding pesan. Hasil pencarian kemudian dipadukan dengan ringkasan dan percakapan terbaru.

Namun, embedding tidak otomatis memahami seluruh percakapan atau menggantikan model generatif. Embedding membantu menemukan kandidat pesan relevan; model AI tetap menyusun jawaban menggunakan konteks yang dipilih.
5. Bagaimana AI langsung paham ketika di-tag, meskipun sebelumnya tidak ikut menjawab?

Ini inti desain Normichat.

Bayangkan percakapan grup seperti ini:

Noel

Kita coba pakai AdamW untuk eksperimen CNN berikutnya.

Anggota 2

Setuju, tapi learning rate-nya mungkin perlu diturunkan.

Anggota 3

Kalau pakai 0,0001 gimana?

Noel

@Normi, menurutmu bagaimana?

Normi
Menurutku, AdamW dengan learning rate 0,0001 layak diuji. Namun, sebaiknya bandingkan dengan learning rate awal yang kamu gunakan sebelumnya, menggunakan pembagian data dan konfigurasi eksperimen yang sama. Dengan begitu, kamu bisa melihat apakah perubahan learning rate benar-benar meningkatkan hasil validasi.

Contoh percakapan ilustratif.

Normi tidak perlu membaca dan menganalisis setiap pesan secara langsung saat pesan itu masuk. Ketika di-tag, backend menyusun konteks yang diperlukan dari riwayat yang sudah tersimpan.

Untuk menjawab contoh tersebut, Context Manager bisa melakukan langkah berikut:

    Mengambil pesan terbaru dalam rentang waktu atau batas token tertentu.

    Memahami bahwa pertanyaan “menurutmu bagaimana?” merujuk pada diskusi AdamW dan learning rate.

    Menyertakan ringkasan eksperimen CNN jika ada informasi relevan dari pembahasan sebelumnya.

    Mencari pesan lama jika ada detail penting yang belum tersedia, misalnya learning rate yang digunakan pada eksperimen sebelumnya.

    Mengirim konteks terpilih bersama pesan yang men-tag Normi ke model.

    Menampilkan jawaban di grup dan mencatat pemakaian token.

Jangan menjalankan semantic retrieval untuk setiap pesan yang masuk. Jalankan ketika AI dipanggil dan konteks yang tersedia belum cukup, atau ketika sistem memerlukan pencarian informasi lama.

Untuk pertanyaan sederhana seperti “apa arti optimizer?”, pencarian riwayat lama mungkin tidak diperlukan sama sekali. Context Manager bisa menggunakan instruksi umum dan pesan terbaru saja.
6. Bagaimana kalau anggota men-tag AI dengan pertanyaan yang ambigu?

Misalnya:

    @Normi, apakah itu sudah benar?

Sistem tidak cukup hanya mengambil pesan yang berisi kata “itu”. Ia perlu memahami rujukan dari percakapan terdekat.

Karena itu, aku merekomendasikan strategi retrieval bertingkat:

    Selalu sertakan percakapan terbaru. Ini menjadi sumber utama untuk memahami rujukan seperti “itu”, “tadi”, “dia”, dan “bagaimana kalau begitu”.

    Sertakan ringkasan topik aktif. Ringkasan memberi gambaran yang lebih luas tanpa mengirim semua riwayat.

    Cari pesan lama jika diperlukan. Gunakan kata kunci, metadata, atau semantic retrieval untuk melengkapi konteks.

    Minta klarifikasi jika konteks tetap tidak cukup. Jangan mengarang detail atau mengambil percakapan dari topik yang tidak berkaitan.

Satu prinsip penting: hasil retrieval harus tetap dibatasi berdasarkan group_id dan izin akses. Dengan demikian, pencarian tidak pernah memasukkan pesan dari grup lain ke prompt Normi.
7. Desain implementasi yang kusarankan untuk MVP

Berikut pembagian komponen yang menurutku realistis untuk Normichat.

Komponen
	

Tanggung jawab

Message Service
	

Menyimpan semua pesan grup dan metadata

Trigger Handler
	

Mendeteksi mention AI dan menentukan apakah AI harus menjawab

Context Manager
	

Menentukan konteks yang diperlukan untuk satu permintaan

Summary Worker
	

Memperbarui ringkasan ketika ambang tertentu tercapai

Retrieval Service
	

Mencari pesan lama ketika dibutuhkan

Prompt Builder
	

Menyusun instruksi, ringkasan, hasil retrieval, dan pesan terbaru

AI Gateway
	

Memanggil OpenAI dan membaca penggunaan token

NormKredit Service
	

Menghitung biaya, melakukan reservasi, dan menyelesaikan pembakaran NC

Tidak semua komponen harus berupa microservice terpisah. Untuk tahap awal, kamu bisa mengimplementasikannya sebagai modul dalam satu backend dengan pekerjaan latar belakang untuk ringkasan dan indeks pencarian.
Alur ketika pesan biasa masuk

    Simpan pesan.

    Perbarui metadata dan penghitung konteks.

    Jadwalkan pembaruan ringkasan jika diperlukan.

    Jangan memanggil model generatif untuk menghasilkan jawaban.

Alur ketika AI di-tag

    Ambil konteks terbaru.

    Ambil ringkasan grup dan topik aktif.

    Evaluasi apakah pencarian pesan lama diperlukan.

    Susun prompt dengan batas token yang ditentukan.

    Periksa saldo NormKredit dan reservasi biaya.

    Panggil OpenAI.

    Simpan jawaban dan rincian penggunaan token.

8. Hal yang perlu kamu tetapkan sebelum implementasi

Ada tiga keputusan yang sebaiknya kamu buat terlebih dahulu:

    Konteks terbaru: berapa banyak pesan atau token yang selalu disertakan.

    Kebijakan ringkasan: kapan ringkasan dibuat, kapan diperbarui, dan informasi apa yang harus dipertahankan.

    Kebijakan retrieval: kapan pencarian pesan lama dilakukan dan bagaimana relevansinya dinilai.

Aku tidak akan langsung menetapkan angka final untuk semuanya sebelum melihat arsitektur programmu, karena keputusan tersebut bergantung pada struktur database, model yang dipakai, panjang percakapan, dan batas biaya yang kamu inginkan.

Kesimpulannya, untuk Normichat aku merekomendasikan Context Manager berbasis aturan backend, dengan ringkasan berbantuan AI dan semantic retrieval yang dipanggil sesuai kebutuhan. Anggota tetap bisa bercakap-cakap secara natural tanpa AI menyela. Ketika Normi di-tag, sistem mengambil konteks yang relevan agar jawabannya terasa menyambung dengan diskusi grup, tanpa harus mengirim seluruh riwayat setiap kali.

Pesanku: terus kalau ambang batas 100 tapi pesan digrup masih ada 50 atau bahkan kurang dan ai di tag, gimana membuat context manager dan understanding managernya?

Pesan AI: Kuncinya, ambang batas 100 pesan itu digunakan untuk menentukan kapan ringkasan perlu diperbarui, bukan menentukan kapan AI boleh mendapatkan konteks. Jadi, walaupun jumlah pesan baru belum mencapai 100, ketika anggota menandai @Normi, AI tetap harus memperoleh konteks yang dibutuhkan untuk menjawab.

Untuk Normichat, aku menyarankan agar Context Manager dan Context Understanding Manager menangani dua tanggung jawab yang berbeda.
1. Bedakan dua kondisi ini terlebih dahulu
Skenario A — Grup baru memiliki 50 pesan

Belum pernah membuat ringkasan

50 pesan tersimpan

Anggota mengirim: “@Normi, menurut kalian solusi yang tadi paling bagus yang mana?”

Karena belum ada ringkasan, Context Manager dapat mengambil seluruh 50 pesan dan mengirimkannya sebagai konteks ke AI, selama masih sesuai dengan batas token dan anggaran yang ditetapkan.

Tidak perlu menunggu pesan mencapai 100. AI bisa langsung memahami percakapan sebelumnya dari pesan aslinya.
Skenario B — Ringkasan lama mencakup pesan 1–100

Setelah itu, muncul 50 pesan baru

Ringkasan pesan 1–100

Pesan asli 101–150

Ketika @Normi dipanggil, Context Manager mengambil ringkasan pesan 1–100 ditambah pesan asli 101–150.

Walaupun baru ada 50 pesan setelah ringkasan terakhir, AI tetap mendapatkan konteks lama dan percakapan terbaru tanpa perlu membuat ringkasan baru terlebih dahulu.
2. Bagaimana cara kerja Context Manager?

Context Manager bertugas memilih dan menyusun informasi yang akan diberikan kepada AI. Ia tidak harus memanggil AI untuk setiap keputusan.

Alur sederhananya seperti ini:

1. Anggota menandai @Normi

Trigger Handler mendeteksi permintaan AI

2. Context Manager mengambil data

Ringkasan yang tersedia, pesan setelah ringkasan, dan pesan terbaru

3. Periksa kecukupan konteks

Apakah konteks cukup, terlalu panjang, atau perlu mencari pesan lama?

4. Prompt Builder menyusun konteks

Menggabungkan instruksi sistem, konteks terpilih, dan pertanyaan anggota

5. AI memberikan jawaban

Context Manager perlu mengetahui sampai pesan mana ringkasan terakhir mencakup percakapan. Jangan hanya menyimpan ringkasan teks tanpa metadata.

Contoh struktur datanya:

{
  "group_id": "group_123",
  "summary": "Grup sedang mendiskusikan solusi proyek...",
  "summary_covered_until_seq": 100,
  "latest_message_seq": 150
}

summary_covered_until_seq berarti ringkasan sudah mencakup percakapan sampai nomor urut pesan 100. Maka, pesan yang belum masuk ringkasan adalah 101–150.

Secara sederhana:
unsummarized_count = (
    latest_message_seq - summary_covered_until_seq
)

Angka tersebut dipakai untuk mengetahui seberapa banyak pesan baru yang belum tercakup. Angka itu tidak boleh menjadi syarat untuk mengizinkan AI menjawab.
3. Lalu apa tugas Context Understanding Manager?

Context Understanding Manager menangani persoalan yang berbeda: menentukan informasi mana yang relevan dengan maksud pertanyaan anggota.

Misalnya, percakapan grup membahas tiga topik:

    Pesan 1–40: ide proyek.

    Pesan 41–80: pembagian tugas.

    Pesan 81–100: pilihan teknologi.

    Pesan 101–150: diskusi lanjutan tentang teknologi.

Lalu seseorang bertanya:

    @Normi, kenapa kita akhirnya memilih teknologi yang kedua?

Context Manager mengambil ringkasan dan pesan terbaru. Namun, jika ringkasannya hanya mengatakan bahwa grup membahas tiga teknologi tanpa mencatat alasan pemilihannya, informasi itu belum cukup.

Di sinilah pemahaman konteks dibutuhkan. Sistem perlu mencari pesan lama yang menjelaskan alasan pemilihan tersebut, bukan sekadar mengirim pesan paling baru.

Alurnya bisa seperti ini:

    Pahami pertanyaan. Identifikasi bahwa frasa “teknologi yang kedua” merujuk pada keputusan terdahulu.

    Periksa konteks yang tersedia. Lihat ringkasan dan pesan baru. Jika alasan pemilihan sudah ada, tidak perlu mencari lebih jauh.

    Cari informasi tambahan bila perlu. Ambil pesan relevan dari riwayat grup yang lebih lama menggunakan pencarian kata kunci, metadata, atau semantic search.

    Gabungkan hasilnya. Context Manager menyusun ringkasan lama, pesan terbaru, serta pesan terdahulu yang relevan ke dalam satu konteks.

    Kirim ke model jawaban. AI menjawab berdasarkan konteks tersebut. Jika informasi pendukung tidak ditemukan, AI seharusnya mengakui ketidakpastian atau meminta klarifikasi.

Satu hal penting: Context Understanding Manager tidak harus berupa panggilan AI tambahan setiap kali ada mention. Untuk MVP, kamu dapat memakai aturan backend dan membiarkan model utama memahami konteks yang telah disusun. Pencarian tambahan atau panggilan model untuk meringkas baru dilakukan saat memang diperlukan.
4. Kapan ringkasan diperbarui?

Pisahkan dua alur ini.

Kejadian
	

Tindakan sistem

Pesan baru masuk, belum mencapai ambang 100
	

Simpan pesan; tidak perlu langsung memanggil AI untuk meringkas

Anggota menandai @Normi, tetapi baru ada 50 pesan baru
	

Ambil ringkasan yang ada + 50 pesan baru

Anggota menandai @Normi, tetapi konteks belum cukup
	

Cari pesan lama yang relevan

Jumlah pesan baru mencapai 100 sejak ringkasan terakhir
	

Jadwalkan pembaruan ringkasan

Anggota menandai @Normi saat pembaruan ringkasan sedang berjalan
	

Tetap susun konteks dari ringkasan yang valid dan pesan setelah batas cakupannya

Saat membuat ringkasan baru, simpan juga nomor urut pesan terakhir yang dicakupnya. Contohnya, ringkasan baru mencakup pesan 1–200, sehingga pesan 201 dan seterusnya tetap diambil dari riwayat asli sampai ringkasan berikutnya selesai.
5. Rancangan yang aku rekomendasikan untuk Normichat

Mulailah dengan tiga komponen utama:

    Context Manager: mengatur pengambilan pesan, ringkasan, batas token, dan penyusunan konteks.

    Context Understanding Manager: membantu memilih konteks yang relevan dan mendeteksi kapan informasi tambahan perlu dicari. Pada tahap awal, gunakan aturan sederhana dahulu; tambahkan semantic search saat dibutuhkan.

    Summary Worker: memperbarui ringkasan secara terjadwal atau berbasis ambang, tanpa membuat AI harus membalas percakapan biasa.

Dengan rancangan ini, grup boleh saja hanya memiliki 10, 50, atau 99 pesan. Ketika AI ditandai, ia tetap bisa menjawab berdasarkan riwayat yang tersedia. Ambang batas 100 hanyalah strategi pemeliharaan ringkasan untuk mengendalikan biaya dan efisiensi, bukan batas minimal konteks.

Prinsip desain terpenting: summary maintenance berjalan berdasarkan kebutuhan pemeliharaan data, sedangkan context assembly berjalan setiap kali AI perlu menjawab. Keduanya terhubung, tetapi jangan dibuat saling bergantung.