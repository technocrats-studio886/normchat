<?php

namespace Tests\Feature;

use App\Services\Ai\Files\FileRequest;
use Tests\TestCase;

/** Ragam kalimat permintaan file dari eval model sungguhan 23 Nov 2026 (docs/rancangan-worker-dan-file-nov.md). */
class FileRequestPhrasingTest extends TestCase
{
    public function test_everyday_indonesian_forms_are_recognised(): void
    {
        $cases = [
            'buatkan kuis 5 soal pilihan ganda sejarah proklamasi beserta kunci jawaban, kirim filenya' => 'pdf',
            'mau dong dibikinin pdf jadwal ronda' => 'pdf',
            'tolong pdf-in jawaban tadi' => 'pdf',
            'bikinin excelnya ya' => 'xlsx',
            'saya butuh file excel buat catat kas RT' => 'xlsx',
            'bisa buatkan pptnya?' => 'pptx',
            'jawaban tadi dijadikan word dong' => 'docx',
            'jelaskan materi fotosintesis dalam bentuk pdf' => 'pdf',
            'buat rekap absensi dalam xls' => 'xlsx',
            'tolong buat invoice untuk PT Maju Jaya: jasa desain logo Rp2.500.000, revisi 2x @Rp500.000, PPN 11%' => 'pdf',
            'buatkan proposal kegiatan donor darah' => 'pdf',
            'bikinin kwitansi pembayaran sewa tenda 800rb' => 'pdf',
        ];
        foreach ($cases as $text => $format) {
            $this->assertSame($format, FileRequest::detect($text)?->format(), $text);
        }
    }

    public function test_questions_about_files_stay_chat(): void
    {
        foreach ([
            'kenapa pdf yang dikirim tadi rusak?',
            'file pdf saya nggak bisa dibuka, kenapa ya?',
            'bagaimana cara bikin ppt yang menarik?',
            'bagaimana cara menyimpan chat dalam bentuk pdf?',
            'jelaskan cara mengubah word ke pdf',
            'apa bedanya pdf dan docx?',
            'buatkan caption instagram untuk promo kopi',
            'cv saya sudah bagus belum?',
            'buatkan logo untuk laporan kegiatan',
        ] as $text) {
            $this->assertNull(FileRequest::detect($text), $text);
        }
    }
}
