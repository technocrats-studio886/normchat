<?php

namespace Tests\Eval;

use App\Enums\AiMode;
use App\Events\MessageSent;
use App\Events\TypingStatus;
use App\Jobs\ProcessGroupChatQueueJob;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Services\AttachmentTextExtractor;
use App\Services\Credits\CreditWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

/**
 * Eval pembuatan file dengan model OpenAI sungguhan (docs/rancangan-worker-dan-file-nov.md).
 * Hanya jalan dengan NORMI_EVAL=1 dan OPENAI_API_KEY; NORMI_EVAL_MODEL memilih model katalog (bawaan gpt-6-luna).
 * Hasil per kasus ditulis ke storage/eval/<model>/ (file jadi + ringkasan JSON) untuk diperiksa.
 */
class NormiFileEvalTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Group $group;

    /** @var list<array{url: string, request: string, response: string}> */
    private array $calls = [];

    private array $allCalls = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('NORMI_EVAL') !== '1' || ! getenv('OPENAI_API_KEY')) {
            $this->markTestSkipped('Eval file hanya jalan dengan NORMI_EVAL=1 dan OPENAI_API_KEY.');
        }
        \Illuminate\Support\Facades\Http::allowStrayRequests();
        config()->set('services.openai.api_key', getenv('OPENAI_API_KEY'));
        config()->set('normchat.usd_to_idr', 17950);
        // Sama dengan produksi (.env NORMCHAT_DOCUMENTS_ALL_GROUPS=true).
        config()->set('normchat.documents_all_groups', true);
        Event::fake([MessageSent::class, TypingStatus::class]);
        Storage::fake('normchat_attachments');
        Event::listen(ResponseReceived::class, function (ResponseReceived $event) {
            $this->calls[] = ['url' => $event->request->url(), 'request' => mb_substr($event->request->body(), -3000), 'full' => $event->request->body(), 'response' => mb_substr($event->response->body(), 0, 20000)];
        });

        $this->owner = User::factory()->create(['name' => 'Rina Pratama']);
        $this->group = Group::create(['name' => 'Panitia Kampung', 'owner_id' => $this->owner->id, 'password_hash' => Hash::make('1234'),
            'status' => 'active', 'ai_model' => getenv('NORMI_EVAL_MODEL') ?: 'gpt-6-luna']);
        GroupMember::create(['group_id' => $this->group->id, 'user_id' => $this->owner->id, 'role_id' => Role::forKey(Role::OWNER)->id, 'status' => 'active', 'joined_at' => now()]);
        app(CreditWallet::class)->deposit($this->group, 5000);
    }

    /**
     * [prompt, format yang diharapkan (null = boleh teks/apa saja, 'text' = jawaban teks tanpa file,
     *  'pdf|docx' = salah satu), kata yang wajib ada di isi, konteks pesan sebelumnya]
     */
    public static function cases(): array
    {
        return [
            'notulen-pdf' => ['@normi buatkan pdf notulen rapat RT malam ini. Hadir 12 warga. Iuran kebersihan naik jadi 25rb per bulan mulai Desember. Kerja bakti Minggu depan jam 7 pagi.', 'pdf', ['25', 'kerja bakti']],
            'surat-word' => ['@normi tolong bikinin surat izin tidak masuk kuliah besok karena sakit demam, format word ya. Nama saya Rina Pratama NIM 11231072 prodi Informatika.', 'docx', ['Rina', '11231072']],
            'anggaran-excel' => ['@normi buatkan excel anggaran acara 17an: konsumsi 1,5jt, hadiah lomba 2jt, dekorasi 750rb, sound system 1jt. Kasih total di bawahnya.', 'xlsx', ['Konsumsi', '5250000']],
            'ppt-penyuluhan' => ['@normi bikin ppt 5 slide tentang bahaya judi online buat penyuluhan remaja karang taruna', 'pptx', ['judi']],
            'kontak-csv' => ['@normi export daftar ini ke csv: Andi 081234, Budi 082345, Citra 083456', 'csv', ['Andi', '082345']],
            'python-bmi' => ['@normi buatkan file python untuk menghitung BMI dari input berat dan tinggi', 'py', ['input', 'tinggi']],
            'file-mode-piket' => ['@normi-file jadwal piket kelas Senin sampai Jumat untuk 10 siswa: Ani, Budi, Caca, Dodi, Euis, Fajar, Gina, Hadi, Indah, Joko', null, ['Senin', 'Joko']],
            'tabel-ke-excel' => ["@normi jadikan tabel ini excel ya\n| Nama | Nilai |\n|---|---|\n| Ani | 80 |\n| Budi | 90 |", 'xlsx', ['Ani', '90']],
            'proposal-dokumen' => ['@normi buatkan proposal kegiatan bakti sosial donor darah di balai desa dalam bentuk dokumen', 'pdf|docx', ['donor darah']],
            'cv-pdf' => ['@normi buat CV saya dalam pdf: nama Rina Pratama, S1 Informatika ITK lulus 2024, magang 6 bulan sebagai backend developer di startup logistik, skill Laravel dan PostgreSQL', 'pdf', ['Rina', 'Laravel']],
            'invoice-pdf' => ['@normi tolong buat invoice untuk PT Maju Jaya: jasa desain logo Rp2.500.000, revisi 2x @Rp500.000, PPN 11%. Hitung totalnya.', 'pdf', ['Maju Jaya', '3.885.000']],
            'kuis-file' => ['@normi buatkan kuis 5 soal pilihan ganda sejarah proklamasi beserta kunci jawaban, kirim filenya', 'pdf|docx', ['1945']],
            'json-provinsi' => ['@normi buatkan file json berisi 5 provinsi di Kalimantan dan ibukotanya', 'json', ['Samarinda']],
            'rangkum-diskusi' => ['@normi rangkum diskusi di atas jadi pdf', 'pdf', ['Balikpapan'], [
                'Rapat panitia: lokasi lomba dipindah ke lapangan Balikpapan Baru karena balai desa direnovasi.',
                'Setuju, tapi tenda harus disewa 2 buah, sekitar 800 ribu.',
                'Konsumsi tetap dari ibu-ibu PKK, budget 1,2 juta.',
            ]],
            'tanya-beda-format' => ['@normi apa bedanya pdf dan docx?', 'text', []],
            'tanya-cara-ppt' => ['@normi jelaskan cara membuat ppt yang menarik untuk sidang', 'text', []],
            // Ragam kalimat sehari-hari (putaran 2).
            'dibikinin-pdf' => ['@normi mau dong dibikinin pdf jadwal ronda malam: Senin Pak Udin, Selasa Pak Rahmat, Rabu Pak Joko', 'pdf', ['Rahmat']],
            'butuh-excel' => ['@normi saya butuh file excel buat catat kas RT, kolom tanggal, keterangan, masuk, keluar, saldo', 'xlsx', ['Saldo']],
            'pptnya-tanya' => ['@normi bisa buatkan pptnya? materi pengenalan UMKM digital untuk ibu-ibu PKK, 5 slide', 'pptx', ['UMKM']],
            'laporan-kegiatan' => ['@normi buatkan laporan kegiatan kerja bakti kemarin, diikuti 30 warga, membersihkan selokan sepanjang 200 meter', 'pdf|docx', ['200']],
            'surat-lamaran' => ['@normi buatkan surat lamaran kerja posisi admin gudang di PT Sinar Kaltim atas nama Rina Pratama', 'pdf|docx', ['Sinar Kaltim']],
            'soal-word' => ['@normi buatkan 5 soal latihan pecahan kelas 5 SD dalam word beserta kuncinya', 'docx', ['pecahan']],
            'jelaskan-dalam-pdf' => ['@normi jelaskan materi fotosintesis untuk anak SMP dalam bentuk pdf', 'pdf', ['klorofil']],
            'rab-dua-format' => ['@normi buatkan RAB renovasi dapur dalam excel: keramik 2jt, cat 500rb, tukang 3jt', 'xlsx', ['5500000']],
            'balas-jadi-word' => ['@normi jadikan word ya', 'docx', ['Kalimantan Timur'], [], ['reply_ai' => "## Ringkasan Ibu Kota Nusantara\n\nIKN berada di Kalimantan Timur. Pembangunan tahap pertama mencakup istana dan kantor kementerian."]],
            'balas-pdf-in' => ['@normi pdf-in dong', 'pdf', ['iuran'], [], ['reply_user' => 'Pengumuman: iuran kas bulan Desember dibayar paling lambat tanggal 5 ke bendahara.']],
            'balas-kirim-pdf' => ['@normi kirim dalam bentuk pdf', 'pdf', ['mangrove'], [], ['reply_ai' => "Tiga manfaat hutan mangrove:\n1. Menahan abrasi pantai\n2. Habitat ikan dan kepiting\n3. Menyerap karbon"]],
            'tanya-pdf-rusak' => ['@normi kenapa pdf yang dikirim tadi tidak bisa dibuka di hp?', 'text', []],
            'caption-bukan-file' => ['@normi buatkan caption instagram untuk promo kopi susu gula aren', 'text', []],
            'ringkas-lampiran' => ['@normi ringkas isi dokumen ini', 'text', [], [], ['attach_pdf' => "# Tata Tertib Lomba\n\nPeserta wajib hadir 30 menit sebelum lomba. Keputusan juri tidak dapat diganggu gugat."]],
        ];
    }

    #[DataProvider('cases')]
    public function test_file_request(string $prompt, ?string $expect, array $mustContain, array $context = [], array $opts = []): void
    {
        $friend = User::factory()->create(['name' => 'Budi']);
        GroupMember::create(['group_id' => $this->group->id, 'user_id' => $friend->id, 'role_id' => Role::forKey(Role::MEMBER)->id, 'status' => 'active', 'joined_at' => now()]);
        foreach ($context as $i => $line) {
            Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $i % 2 ? $friend->id : $this->owner->id, 'message_type' => 'text', 'content' => $line]);
        }
        $replyTo = null;
        if (isset($opts['reply_ai']) || isset($opts['reply_user'])) {
            $replyTo = Message::create(['group_id' => $this->group->id, 'message_type' => 'text', 'content' => $opts['reply_ai'] ?? $opts['reply_user'],
                ...(isset($opts['reply_ai']) ? ['sender_type' => 'ai'] : ['sender_type' => 'user', 'sender_id' => $friend->id])]);
        }
        $attachment = [];
        if (isset($opts['attach_pdf'])) {
            $file = app(\App\Services\Ai\Files\FileBuilder::class)->build([['name' => 'tata-tertib.pdf', 'content' => $opts['attach_pdf']]], 'pdf', '');
            Storage::disk('normchat_attachments')->put('eval/tata-tertib.pdf', $file['binary']);
            $attachment = ['message_type' => 'file', 'attachment_disk' => 'normchat_attachments', 'attachment_path' => 'eval/tata-tertib.pdf',
                'attachment_mime' => 'application/pdf', 'attachment_original_name' => 'tata-tertib.pdf', 'attachment_size' => strlen($file['binary'])];
        }
        $message = Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $this->owner->id, 'message_type' => 'text',
            'content' => $prompt, 'ai_mode' => AiMode::fromContent($prompt), 'reply_to_message_id' => $replyTo?->id, ...$attachment]);
        ChatMessageQueue::enqueue($message);
        $started = microtime(true);
        (new ProcessGroupChatQueueJob($this->group->id))->handle();

        $reply = Message::where('sender_type', 'ai')->where('reply_to_message_id', $message->id)->latest('id')->first();
        $problems = [];
        $text = '';
        $ext = $reply?->attachment_original_name ? strtolower(pathinfo($reply->attachment_original_name, PATHINFO_EXTENSION)) : null;
        if (! $reply) {
            $problems[] = 'tidak ada jawaban (status antrean: '.ChatMessageQueue::where('message_id', $message->id)->value('status').')';
        } elseif ($expect === 'text') {
            if ($ext) {
                $problems[] = "pertanyaan dijawab dengan file .{$ext}";
            }
        } elseif (! $ext) {
            $problems[] = 'tidak ada file; jawaban: '.mb_substr((string) $reply->content, 0, 200);
        } else {
            if ($expect && ! in_array($ext, explode('|', $expect), true)) {
                $problems[] = "format .{$ext}, diminta {$expect}";
            }
            $text = $this->read($reply, $ext, $problems);
            foreach ($mustContain as $needle) {
                if (mb_stripos(str_replace(['.', ','], '', $text), str_replace(['.', ','], '', $needle)) === false && mb_stripos($text, $needle) === false) {
                    $problems[] = "isi tidak memuat \"{$needle}\"";
                }
            }
            foreach (['/\*\*[^*]+\*\*/u' => 'tanda ** Markdown mentah', '/^#{1,6}\s/mu' => 'judul # Markdown mentah', '/\|\s*-{3,}/u' => 'tabel Markdown mentah',
                '/"files"\s*:|\{"title"/u' => 'JSON spesifikasi bocor ke isi', '/\b(?:lorem ipsum|placeholder|TODO)\b|\[(?:isi|nama|tanggal)[^\]]*\]|\.\.\.\s*$/miu' => 'placeholder'] as $pattern => $label) {
                // Soal isian bernomor ("1. Fungsi klorofil adalah ...") bukan placeholder.
                $checked = (string) preg_replace('/^\s*\d+[.)]\s.*\.\.\.\s*$/mu', '', $text);
                if (in_array($ext, ['pdf', 'docx', 'pptx'], true) && preg_match($pattern, $checked)) {
                    $problems[] = $label;
                }
            }
            if (trim((string) $reply->content) === '' || preg_match('/^File .+ sudah siap\.$/u', (string) $reply->content)) {
                $problems[] = 'ringkasan kosong';
            }
        }

        $model = (string) $this->group->ai_model;
        $dir = storage_path('eval/'.$model);
        @mkdir($dir, 0777, true);
        $name = $this->dataName();
        if ($ext) {
            file_put_contents("{$dir}/{$name}.{$ext}", Storage::disk('normchat_attachments')->get($reply->attachment_path));
        }
        file_put_contents("{$dir}/{$name}.json", json_encode([
            'prompt' => $prompt, 'expect' => $expect, 'ok' => $problems === [], 'problems' => $problems, 'seconds' => round(microtime(true) - $started, 1),
            'reply' => $reply?->content, 'file' => $reply?->attachment_original_name, 'text' => mb_substr($text, 0, 4000),
            'calls' => array_map(fn ($c) => ['url' => $c['url'], 'response' => $c['response']], $this->calls),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->assertSame([], $problems, $name.': '.implode('; ', $problems));
    }

    private function read(Message $reply, string $ext, array &$problems): string
    {
        $binary = (string) Storage::disk('normchat_attachments')->get($reply->attachment_path);
        if (in_array($ext, ['docx', 'xlsx', 'pptx'], true)) {
            $tmp = tempnam(sys_get_temp_dir(), 'ev');
            file_put_contents($tmp, $binary);
            $zip = new ZipArchive;
            if ($zip->open($tmp) !== true) {
                $problems[] = "berkas .{$ext} rusak (bukan ZIP)";

                return '';
            }
            if ($ext === 'pptx') {
                $slides = 0;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $slides += (int) preg_match('#^ppt/slides/slide\d+\.xml$#', $zip->getNameIndex($i));
                }
                if ($slides < 3) {
                    $problems[] = "hanya {$slides} slide";
                }
            }
            $zip->close();
            unlink($tmp);
        }
        if ($ext === 'pdf' && ! str_starts_with($binary, '%PDF')) {
            $problems[] = 'PDF rusak';
        }
        if ($ext === 'json' && json_decode($binary) === null) {
            $problems[] = 'JSON tidak valid';
        }
        if ($ext === 'py' && str_contains($binary, '```')) {
            $problems[] = 'code fence ikut tertulis';
        }

        return in_array($ext, ['pdf', 'docx', 'pptx', 'xlsx'], true)
            ? (string) app(AttachmentTextExtractor::class)->extract($reply, 60000)
            : $binary;
    }

    /** Tanya jawab dokumen: jawaban benar dan token input yang terpakai (dibandingkan antar versi kode). */
    public static function documentQuestions(): array
    {
        $rab = "RENCANA ANGGARAN BIAYA LOMBA 17 AGUSTUS\nNo | Uraian | Jumlah\n1 | Hadiah lomba anak | Rp1.500.000\n2 | Konsumsi panitia | Rp900.000\n3 | Sewa tenda | Rp800.000\n4 | Sound system | Rp600.000\nTotal | | Rp3.800.000";
        $paragraphs = [];
        for ($i = 1; $i <= 140; $i++) {
            $paragraphs[] = "Bagian {$i}. Kegiatan rutin kelurahan pada minggu ke-{$i} meliputi rapat koordinasi, pendataan warga, dan pemeliharaan fasilitas umum. Laporan ini mencatat kehadiran, kendala lapangan, serta tindak lanjut yang disepakati bersama para ketua RT.";
        }
        array_splice($paragraphs, 97, 0, ['Bagian khusus. Pengadaan pompa air untuk masjid Al-Ikhlas disepakati sebesar Rp4.350.000 dan dibayar dari kas kelurahan.']);
        $big = "LAPORAN TAHUNAN KELURAHAN 2026\n".implode("\n", $paragraphs);

        return [
            'dok-kecil-balas' => [$rab, '@normi berapa biaya sewa tenda?', '800', 'reply'],
            'dok-kecil-riwayat' => [$rab, '@normi total anggaran lombanya berapa?', '3.800.000', 'history'],
            'dok-besar-fakta' => [$big, '@normi berapa biaya pompa air masjid di laporan itu?', '4.350.000', 'reply'],
        ];
    }

    #[DataProvider('documentQuestions')]
    public function test_document_question(string $document, string $question, string $expect, string $how): void
    {
        Storage::disk('normchat_attachments')->put('eval/dokumen.txt', $document);
        $doc = Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $this->owner->id, 'message_type' => 'file',
            'content' => 'ini dokumennya', 'attachment_disk' => 'normchat_attachments', 'attachment_path' => 'eval/dokumen.txt',
            'attachment_original_name' => 'dokumen.txt', 'attachment_mime' => 'text/plain', 'attachment_size' => strlen($document)]);
        // Indeks seperti di produksi (scheduler / job konversi), di kode lama maupun baru.
        if ($doc->fresh()->doc_indexed_at === null) {
            app(\App\Services\Ai\Documents\DocumentIndex::class)->index($doc->fresh());
        }
        Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $this->owner->id, 'message_type' => 'text', 'content' => 'oke nanti kucek']);
        $tokens = [];
        $answers = [];
        foreach ([$question, $question.' Jawab singkat saja.'] as $round => $text) {
            $message = Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $this->owner->id, 'message_type' => 'text',
                'content' => $text, 'reply_to_message_id' => $how === 'reply' ? $doc->id : null]);
            ChatMessageQueue::enqueue($message);
            $this->calls = [];
            (new ProcessGroupChatQueueJob($this->group->id))->handle();
            $this->allCalls = [...($this->allCalls ?? []), ...$this->calls];
            $answers[] = (string) Message::where('reply_to_message_id', $message->id)->where('sender_type', 'ai')->value('content');
            foreach ($this->calls as $call) {
                if (str_ends_with($call['url'], '/chat/completions')) {
                    $usage = json_decode($call['response'], true)['usage'] ?? [];
                    $sent = json_decode($call['full'] ?? '{}', true)['messages'] ?? [];
                    $tokens[] = ['input' => $usage['prompt_tokens'] ?? 0, 'cached' => $usage['prompt_tokens_details']['cached_tokens'] ?? 0,
                        'blok' => substr(sha1(json_encode(array_slice($sent, 0, 2))), 0, 8), 'blok_awal' => mb_substr(json_encode($sent[1] ?? null, JSON_UNESCAPED_UNICODE), 0, 300)];
                }
            }
        }
        $dir = storage_path('eval/dokumen-'.$this->group->ai_model);
        @mkdir($dir, 0777, true);
        file_put_contents($dir.'/'.$this->dataName().'.bodies.json', json_encode(array_values(array_map(fn ($c) => $c['full'] ?? null, array_filter($this->allCalls ?? [], fn ($c) => str_ends_with($c['url'], '/chat/completions'))))));
        file_put_contents($dir.'/'.$this->dataName().'.json', json_encode(['tokens' => $tokens, 'answers' => $answers], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        foreach ($answers as $answer) {
            $this->assertStringContainsString($expect, str_replace(' ', '', $answer), $this->dataName().': '.$answer);
        }
    }
}
