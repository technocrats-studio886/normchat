@extends('marketing.layout', ['active' => 'pricing'])

@section('title', 'Harga Normi Chat — Biaya Membuat Grup dan Normkredit')
@section('description', 'Lihat harga membuat grup Normi Chat, jumlah Normkredit awal, harga paket patungan, dan cara penggunaan AI dihitung.')

@php
    $idr = fn (int $value) => 'Rp'.number_format($value, 0, ',', '.');
    $nk = fn (int $value) => number_format($value, 0, ',', '.');
@endphp

@section('content')
    <section class="page-hero">
        <div class="wrap">
            <p class="eyebrow">Harga yang sederhana dan transparan</p>
            <h1>Satu saldo untuk memulai kolaborasi Anda.</h1>
            <p class="lead">Mulai dengan satu grup, satukan anggota Anda, dan gunakan bantuan AI ketika dibutuhkan. Tambahkan Normkredit seiring berkembangnya kebutuhan grup.</p>
            <p class="hero-note">Satu saldo bersama. Tidak ada langganan per orang.</p>
        </div>
    </section>

    <section class="rule" style="padding-top:72px">
        <div class="wrap">
            <div class="price-main">
                <div class="price-side">
                    <span class="tier">Buat grup</span>
                    <span class="price-figure">{{ $idr($groupQuote->baseAmount) }}</span>
                    <span class="price-unit">per grup, termasuk {{ $nk($groupQuote->credits) }} Normkredit awal</span>
                    <p>Satu ruang percakapan untuk grup Anda, dilengkapi kemampuan AI dan fitur pengelolaan percakapan.</p>
                    <a href="{{ $startUrl }}" class="btn btn-primary">Buat Grup</a>
                </div>
                <div class="price-list">
                    <h3>Yang termasuk</h3>
                    <ul class="checklist">
                        <li>{{ $nk($groupQuote->credits) }} Normkredit sebagai saldo awal grup.</li>
                        <li>Fitur obrolan grup secara real-time.</li>
                        <li>Partisipasi AI yang dikonfigurasi untuk grup.</li>
                        <li>Pengaturan akses dan pengelolaan anggota.</li>
                        <li>Fitur pencadangan dan pemulihan percakapan.</li>
                        <li>Ekspor percakapan ke format PDF dan DOCX.</li>
                    </ul>
                </div>
            </div>
            <p class="price-note">Pembayaran melalui QRIS. Biaya layanan QRIS sebesar {{ $feeLabel }}% ditambahkan pada total yang ditagih, dan ditampilkan sebelum Anda membayar.</p>
        </div>
    </section>

    <section class="rule">
        <div class="wrap">
            <p class="eyebrow">Pembelian tambahan</p>
            <h2 class="h2">Tambahkan kapasitas saat Anda membutuhkannya.</h2>
            <p class="lead" style="margin-top:20px">Mulai dengan biaya yang jelas. Anggota grup dapat menambah saldo Normkredit ketika grup membutuhkan kapasitas penggunaan AI yang lebih besar.</p>

            <div class="buy-grid">
                <div class="buy">
                    <h3>Patungan &amp; isi ulang Normkredit</h3>
                    <p class="amount">{{ $idr($packageQuote->baseAmount) }} <small>/ paket · {{ $nk($packageQuote->credits) }} Normkredit</small></p>
                    <p>Tambahkan kredit penggunaan AI ke saldo grup Anda. Beli satu paket, atau beberapa paket sekaligus hingga {{ $maxPackages }} paket dalam satu pembayaran.</p>
                    <a href="{{ $startUrl }}" class="btn btn-ghost">Isi Ulang Normkredit</a>
                </div>
                <div class="buy">
                    <h3>Harga per Normkredit</h3>
                    <p class="amount">{{ $idr($rupiahPerCredit) }} <small>/ Normkredit</small></p>
                    <p>Biaya dasar setiap Normkredit. Konsumsi aktualnya bergantung pada penggunaan dan pengali model AI yang dipilih, sehingga Anda hanya membayar sesuai pemakaian.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="rule">
        <div class="wrap split split-wide">
            <div>
                <p class="eyebrow">Transparansi</p>
                <h2 class="h2">Ketahui apa yang Anda bayarkan.</h2>
            </div>
            <div class="stack">
                <p>Normi Chat memisahkan biaya pembuatan grup dari penambahan saldo agar komponen biaya lebih mudah dipahami.</p>
                <p>Membuat grup mencakup akses ke seluruh fitur beserta saldo awal. Saldo tambahan dapat dibeli ketika dibutuhkan.</p>
                <p>Penggunaan AI dihitung melalui token. Jumlah token yang dikonsumsi setiap permintaan dapat berbeda, bergantung pada penggunaan aktual dan pengali model AI yang dipilih.</p>
                <p class="emph">Karena itu, konsumsi AI tidak selalu sama untuk setiap permintaan. Periksa sisa Normkredit grup Anda untuk memahami kapasitas penggunaan AI yang tersedia.</p>
            </div>
        </div>
    </section>

    <section class="rule" id="faq">
        <div class="wrap faq-grid">
            <div>
                <p class="eyebrow">Pertanyaan umum</p>
                <h2 class="h2" style="font-size:clamp(24px,3vw,32px)">Pertanyaan seputar harga.</h2>
            </div>
            <div class="faq-list">
                <details>
                    <summary>Apakah Normi Chat memiliki paket gratis?</summary>
                    <p>Normi Chat berbasis saldo berbayar. Membuat grup membutuhkan {{ $idr($groupQuote->baseAmount) }} dan sudah termasuk {{ $nk($groupQuote->credits) }} Normkredit awal.</p>
                </details>
                <details>
                    <summary>Apa itu Normkredit?</summary>
                    <p>Normkredit adalah satuan kredit yang digunakan untuk mengelola penggunaan AI di Normi Chat. Satu Normkredit dihargai {{ $idr($rupiahPerCredit) }}. Konsumsi token aktual bergantung pada penggunaan dan pengali model AI yang dipilih.</p>
                </details>
                <details>
                    <summary>Apakah saya bisa menambah kredit AI?</summary>
                    <p>Ya. Anda dapat membeli paket Normkredit tambahan, masing-masing {{ $nk($packageQuote->credits) }} Normkredit seharga {{ $idr($packageQuote->baseAmount) }}, ketika grup membutuhkan kapasitas penggunaan AI yang lebih besar.</p>
                </details>
                <details>
                    <summary>Bagaimana jika anggota ingin ikut menanggung biaya?</summary>
                    <p>Anggota dapat ikut menambah saldo grup melalui patungan. Hasilnya masuk ke satu saldo yang dipakai bersama oleh seluruh anggota, sesuai ketentuan akses yang ditetapkan pemilik grup.</p>
                </details>
                <details>
                    <summary>Apakah chat antaranggota ikut memotong saldo?</summary>
                    <p>Tidak. Saldo hanya terpotong ketika AI menjawab permintaan yang dipanggil melalui mention @normi.</p>
                </details>
                <details>
                    <summary>Model AI apa yang digunakan?</summary>
                    <p>Pemilik grup memilih model AI yang digunakan dalam grup dari pilihan yang tersedia di aplikasi. Setiap model memiliki pengali yang berbeda, dan pengali itulah yang memengaruhi besar potongan Normkredit.</p>
                </details>
            </div>
        </div>
    </section>

    <section class="rule closing">
        <div class="wrap">
            <h2>Siap memulai percakapan bersama?</h2>
            <p class="lead">Mulai dengan satu ruang percakapan, satukan anggota grup Anda, dan tambahkan kapasitas sesuai kebutuhan.</p>
            <div class="hero-actions">
                <a href="{{ $startUrl }}" class="btn btn-primary">Mulai Sekarang</a>
                <a href="{{ $base }}/contact" class="btn btn-ghost">Hubungi Kami</a>
            </div>
        </div>
    </section>
@endsection
