@extends('marketing.layout', ['active' => 'contact'])

@section('title', 'Hubungi Normi Chat — Informasi dan Dukungan')
@section('description', 'Hubungi Normi Chat untuk pertanyaan mengenai harga, integrasi AI, pengelolaan grup, dan dukungan teknis.')

@section('content')
    <section class="page-hero">
        <div class="wrap">
            <p class="eyebrow">Hubungi kami</p>
            <h1>Ada yang ingin ditanyakan? Mari mulai percakapan.</h1>
            <p class="lead">Ingin mengenal Normi Chat lebih jauh, memahami harga, atau mencari informasi mengenai fitur yang tersedia?</p>
            <p class="lead">Kami membuka ruang untuk pertanyaan dan masukan Anda. Ceritakan apa yang ingin Anda ketahui agar kami dapat memahami kebutuhan Anda dengan lebih baik.</p>
        </div>
    </section>

    <section class="rule" id="form">
        <div class="wrap split split-wide">
            <div>
                <p class="eyebrow">Kirim pesan</p>
                <h2 class="h2">Apa yang bisa kami bantu?</h2>
                <p class="lead" style="margin-top:20px">Sampaikan pertanyaan Anda atau ceritakan kebutuhan yang ingin Anda selesaikan. Informasi yang Anda berikan akan membantu kami memahami konteks pertanyaan tersebut.</p>
                @if ($contactEmail !== '')
                    <p class="price-note" style="margin-top:28px">Atau kirim langsung ke</p>
                    <a class="mail-link" href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>
                @endif
            </div>

            <div>
                @if (session('contact_status') === 'sent')
                    <div class="alert alert-ok" role="status" style="margin-bottom:24px">Terima kasih telah menghubungi kami. Pesan Anda berhasil dikirim.</div>
                @elseif (session('contact_status') === 'failed' || $errors->any())
                    <div class="alert alert-bad" role="alert" style="margin-bottom:24px">Pesan Anda belum berhasil dikirim. Periksa kembali informasi yang dimasukkan, kemudian coba lagi.</div>
                @endif

                @if ($formEnabled)
                    <form class="form" method="POST" action="{{ route('marketing.contact.send') }}" novalidate>
                        @csrf
                        <div class="hp" aria-hidden="true">
                            <label for="website">Jangan diisi</label>
                            <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="field @error('name') has-error @enderror">
                            <label for="name">Nama Lengkap</label>
                            <input id="name" type="text" name="name" value="{{ old('name') }}" placeholder="Masukkan nama lengkap Anda." autocomplete="name" maxlength="120" required>
                            @error('name') <span class="err">{{ $message }}</span> @enderror
                        </div>

                        <div class="field @error('email') has-error @enderror">
                            <label for="email">Alamat Email</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="Masukkan alamat email Anda." autocomplete="email" maxlength="160" required>
                            @error('email') <span class="err">{{ $message }}</span> @enderror
                        </div>

                        <div class="field @error('topic') has-error @enderror">
                            <label for="topic">Topik Pertanyaan</label>
                            <select id="topic" name="topic" required>
                                <option value="" disabled @selected(old('topic') === null)>Pilih topik</option>
                                @foreach ($topics as $topic)
                                    <option value="{{ $topic }}" @selected(old('topic') === $topic)>{{ $topic }}</option>
                                @endforeach
                            </select>
                            @error('topic') <span class="err">{{ $message }}</span> @enderror
                        </div>

                        <div class="field @error('message') has-error @enderror">
                            <label for="message">Pesan</label>
                            <textarea id="message" name="message" placeholder="Ceritakan pertanyaan, kebutuhan, atau kendala yang sedang Anda hadapi..." maxlength="4000" required>{{ old('message') }}</textarea>
                            @error('message') <span class="err">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-foot">
                            <button type="submit" class="btn btn-primary">Kirim Pesan</button>
                            <p class="form-note">Jangan sertakan kata sandi, API key, atau informasi sensitif lainnya dalam pesan Anda.</p>
                        </div>
                    </form>
                @else
                    <div class="alert alert-bad" role="status">Formulir kontak belum diaktifkan di lingkungan ini.</div>
                @endif
            </div>
        </div>
    </section>

    <section class="rule">
        <div class="wrap split split-wide">
            <div>
                <p class="eyebrow">Belum yakin?</p>
                <h2 class="h2" style="font-size:clamp(22px,2.8vw,30px)">Belum yakin harus memulai dari mana?</h2>
            </div>
            <div class="stack">
                <p>Tidak masalah. Ceritakan apa yang ingin Anda lakukan dengan Normi Chat, baik untuk menyatukan kelompok, memahami harga, maupun menjelajahi integrasi AI yang tersedia.</p>
                <p class="emph">Informasi yang lebih jelas membantu kami memahami pertanyaan Anda.</p>
                <div><a href="#form" class="btn btn-ghost">Kirim Pertanyaan</a></div>
            </div>
        </div>
    </section>

    <section class="rule" id="faq">
        <div class="wrap faq-grid">
            <div>
                <p class="eyebrow">Pertanyaan umum</p>
                <h2 class="h2" style="font-size:clamp(24px,3vw,32px)">Mungkin ini yang Anda cari.</h2>
            </div>
            <div class="faq-list">
                <details>
                    <summary>Di mana saya bisa melihat informasi harga Normi Chat?</summary>
                    <p>Kunjungi halaman Pricing untuk mengetahui biaya membuat grup, Normkredit awal yang didapat, serta pilihan penambahan saldo.</p>
                    <div class="faq-item-action"><a href="{{ $base }}/pricing" class="btn btn-ghost">Lihat Harga</a></div>
                </details>
                <details>
                    <summary>Model AI apa yang digunakan Normi Chat?</summary>
                    <p>Pemilik grup memilih model AI yang digunakan dalam grup dari pilihan yang tersedia di aplikasi. Penggunaannya bergantung pada konfigurasi AI untuk grup Anda.</p>
                </details>
                <details>
                    <summary>Bagaimana cara mulai menggunakan Normi Chat?</summary>
                    <p>Pelajari harga yang tersedia, masuk ke akun Anda, buat grup, konfigurasi pengaturan AI, kemudian undang anggota untuk mulai berdiskusi.</p>
                    <div class="faq-item-action"><a href="{{ $startUrl }}" class="btn btn-ghost">Mulai Sekarang</a></div>
                </details>
            </div>
        </div>
    </section>

    <section class="rule closing">
        <div class="wrap">
            <h2>Setiap kolaborasi dimulai dari sebuah percakapan.</h2>
            <p class="lead">Ceritakan kebutuhan, pertanyaan, atau rencana Anda bersama Normi Chat.</p>
            <p class="lead" style="margin-top:12px;color:#fff">Mari mulai dengan percakapan.</p>
            <div class="hero-actions">
                <a href="#form" class="btn btn-primary">Hubungi Normi Chat</a>
            </div>
        </div>
    </section>
@endsection
