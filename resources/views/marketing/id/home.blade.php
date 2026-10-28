@extends('marketing.layout', ['active' => 'home'])

@section('title', 'Normi Chat — Platform Obrolan Grup dengan AI')
@section('description', 'Satukan manusia dan AI dalam satu ruang obrolan grup. Berdiskusi secara real-time, panggil AI saat dibutuhkan, dan kelola riwayat percakapan dengan Normi Chat.')

@section('content')
        {{-- Hero --}}
        <section class="hero" id="beranda">
            <div class="wrap hero-grid">
                <div>
                    <p class="eyebrow">Obrolan grup, dengan kemampuan AI</p>
                    <h1>Bukan sekadar mengobrol. Saatnya <em>berpikir dan berkembang</em> bersama.</h1>
                    <p class="lead">Normi Chat menyatukan manusia dan AI dalam satu ruang percakapan. Bertukar ide secara real-time, diskusikan berbagai hal bersama tim, dan panggil AI saat membutuhkan bantuan. Semua berlangsung dalam satu percakapan tanpa berpindah-pindah aplikasi.</p>
                    <div class="hero-actions">
                        <a href="{{ $startUrl }}" class="btn btn-primary">Mulai Sekarang</a>
                        <a href="{{ $base }}/pricing" class="btn btn-ghost">Lihat Harga</a>
                    </div>
                    <p class="hero-note">Satu ruang untuk tim Anda, ide-ide Anda, dan percakapan bersama AI.</p>
                </div>

                <div>
                    <div class="chat" role="img" aria-label="Contoh percakapan grup yang memanggil AI dengan @normi">
                        <div class="chat-bar">
                            <strong>Tim Proposal Klien</strong>
                            <span>4 anggota</span>
                        </div>
                        <div class="chat-body">
                            <div class="msg">
                                <small>Rina</small>
                                <p>Struktur proposalnya sudah kita sepakati belum?</p>
                            </div>
                            <div class="msg">
                                <small>Dimas</small>
                                <p>Belum. Bagian anggaran masih kosong, padahal kita sudah bahas panjang tadi pagi.</p>
                            </div>
                            <div class="msg me">
                                <small>Anda</small>
                                <p><span class="tag">@normi</span> rangkum poin anggaran dari diskusi di atas.</p>
                            </div>
                            <div class="msg ai">
                                <small>Normi AI</small>
                                <p>Dari diskusi di atas ada dua keputusan yang belum tertulis:
                                <span style="display:block;margin-top:6px">1. Biaya tim dipisah dari biaya produksi.<br>2. Ada opsi cadangan bila jadwal mundur.</span></p>
                            </div>
                        </div>
                        <div class="chat-foot">Tulis pesan, atau sebut @normi…</div>
                    </div>
                    <p class="chat-caption">Contoh percakapan. AI hanya menjawab ketika dipanggil.</p>
                </div>
            </div>
        </section>

        {{-- Pengenalan --}}
        <section class="rule" id="mengenal">
            <div class="wrap intro-grid">
                <div>
                    <p class="eyebrow">Mengenal Normi Chat</p>
                    <h2 class="h2">Ide yang lebih besar dimulai dari percakapan yang tepat.</h2>
                </div>
                <div class="intro-copy">
                    <p>Ide-ide hebat jarang lahir sendirian. Semuanya berkembang melalui pertanyaan, pertukaran sudut pandang, diskusi, dan bantuan yang hadir pada waktu yang tepat.</p>
                    <p>Normi Chat menghadirkan semua itu dalam satu ruang percakapan. Anda bisa berdiskusi dengan tim, berbagi pemikiran, dan melibatkan AI ketika membutuhkan perspektif tambahan.</p>
                    <p class="emph">Tidak perlu memisahkan diskusi dari bantuan AI. Tidak perlu mengganggu alur percakapan hanya untuk mencari jawaban.</p>
                </div>
            </div>
        </section>

        {{-- Fitur --}}
        <section class="rule" id="fitur">
            <div class="wrap">
                <div class="features-head">
                    <p class="eyebrow">Fitur</p>
                    <h2 class="h2">Semua yang dibutuhkan untuk percakapan yang lebih bermakna.</h2>
                    <p class="lead">Mulai dari percakapan real-time hingga bantuan AI sesuai kebutuhan, Normi Chat menyediakan fitur untuk membantu grup berdiskusi, berkolaborasi, dan mengelola percakapan dengan lebih terarah.</p>
                </div>

                <article class="feature">
                    <span class="n">01</span>
                    <h3>AI yang membantu, bukan mengganggu.<span class="kicker">AI sesuai kebutuhan</span></h3>
                    <div>
                        <p>AI seharusnya memberikan bantuan, bukan mengambil alih percakapan. Di Normi Chat, AI merespons ketika Anda memanggilnya melalui mention <strong>@normi</strong>.</p>
                        <p>Ajukan pertanyaan, eksplorasi ide, atau minta bantuan tanpa khawatir AI menyela setiap pesan anggota grup. Anda menentukan kapan AI perlu terlibat.</p>
                    </div>
                </article>

                <article class="feature">
                    <span class="n">02</span>
                    <h3>Semua tetap terhubung dalam satu percakapan.<span class="kicker">Percakapan real-time</span></h3>
                    <div>
                        <p>Kirim pesan dan ikuti perkembangan diskusi secara real-time. Pertanyaan, tanggapan, dan jawaban AI tetap berada dalam ruang yang sama sehingga konteks diskusi lebih mudah diikuti.</p>
                        <p>Tidak perlu memecah percakapan ke berbagai tempat.</p>
                    </div>
                </article>

                <article class="feature">
                    <span class="n">03</span>
                    <h3>Grup Anda, aturan Anda.<span class="kicker">Kontrol grup yang fleksibel</span></h3>
                    <div>
                        <p>Buat grup, undang anggota, dan kelola akses sesuai kebutuhan. Dengan pengaturan akses grup dan hak pengguna, Anda mengatur siapa yang dapat berpartisipasi dalam percakapan.</p>
                        <p>Ruang kolaborasi yang baik dimulai dari pengelolaan yang jelas.</p>
                    </div>
                </article>

                <article class="feature">
                    <span class="n">04</span>
                    <h3>Percakapan penting layak disimpan.<span class="kicker">Riwayat yang dapat dikelola</span></h3>
                    <div>
                        <p>Simpan dan kelola riwayat percakapan melalui fitur pencadangan dan pemulihan. Ketika membutuhkan dokumentasi, ekspor percakapan ke format PDF atau DOCX agar mudah dibaca dan dijadikan referensi.</p>
                        <p>Informasi penting tidak seharusnya berhenti berguna ketika percakapan selesai.</p>
                    </div>
                </article>

                <article class="feature">
                    <span class="n">05</span>
                    <h3>Percakapan Anda, di mana saja.<span class="kicker">Dirancang untuk mobile</span></h3>
                    <div>
                        <p>Normi Chat dirancang dengan pendekatan mobile-first agar nyaman dipakai melalui browser ponsel. Navigasi yang ringkas dan interaksi yang praktis membantu Anda tetap terhubung tanpa harus selalu berada di depan komputer.</p>
                        <p>Buka, berdiskusi, dan lanjutkan ide Anda kapan pun dibutuhkan.</p>
                    </div>
                </article>
            </div>
        </section>

        {{-- Cara kerja --}}
        <section class="rule" id="cara-kerja">
            <div class="wrap">
                <p class="eyebrow">Mulai berkolaborasi</p>
                <h2 class="h2">Tiga langkah menuju percakapan yang lebih produktif.</h2>
                <p class="lead" style="margin-top:20px">Mulai dengan sederhana. Satukan anggota grup Anda, kemudian libatkan AI ketika dibutuhkan.</p>

                <div class="steps">
                    <div class="step">
                        <span class="n">01</span>
                        <h3>Buat ruang percakapan</h3>
                        <p>Buat grup baru, tentukan pengaturan akses, lalu pilih model AI yang ingin digunakan dalam grup.</p>
                    </div>
                    <div class="step">
                        <span class="n">02</span>
                        <h3>Undang anggota Anda</h3>
                        <p>Bagikan Share ID kepada anggota dan kelola akses mereka agar percakapan tetap berada di ruang yang sesuai.</p>
                    </div>
                    <div class="step">
                        <span class="n">03</span>
                        <h3>Mulai berdiskusi</h3>
                        <p>Kirim pesan secara real-time, bertukar ide, dan panggil AI melalui mention ketika membutuhkan informasi atau sudut pandang tambahan.</p>
                    </div>
                </div>

                <div class="section-cta">
                    <a href="{{ $startUrl }}" class="btn btn-primary">Buat Grup Anda</a>
                </div>
            </div>
        </section>

        {{-- Nilai --}}
        <section class="value" id="nilai">
            <div class="wrap value-grid">
                <h2>AI tidak harus mengambil alih percakapan untuk membuat perbedaan.</h2>
                <div class="value-copy">
                    <p>Kolaborasi yang baik terjadi ketika setiap orang memiliki kesempatan untuk berpikir, bertanya, dan mengembangkan gagasan bersama.</p>
                    <p>Normi Chat menghadirkan AI sebagai bagian dari proses tersebut tanpa menjadikannya pusat dari setiap interaksi.</p>
                    <p class="strong">Manusia tetap menjadi inti percakapan. AI hadir untuk membantu ketika dibutuhkan.</p>
                    <div><a href="{{ $base }}/about" class="btn btn-dark">Kenali Normi Chat</a></div>
                </div>
            </div>
        </section>

        {{-- Penutup --}}
        <section class="rule closing">
            <div class="wrap">
                <h2>Mari mulai percakapan yang lebih baik.</h2>
                <p class="lead">Satukan anggota grup Anda dalam satu ruang, libatkan AI saat dibutuhkan, dan berikan kesempatan bagi setiap ide untuk berkembang.</p>
                <div class="hero-actions">
                    <a href="{{ $startUrl }}" class="btn btn-primary">Mulai Sekarang</a>
                    <a href="{{ $base }}/pricing" class="btn btn-ghost">Lihat Paket Harga</a>
                </div>
            </div>
        </section>
@endsection
