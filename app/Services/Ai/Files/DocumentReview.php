<?php

namespace App\Services\Ai\Files;

use App\Models\Message;
use App\Services\Ai\AiReply;
use RuntimeException;

/** Follow-ups use the same visible source and request. Corrections belong to this conversation only. */
final class DocumentReview
{
    public static function read(Message $source, string $answer, array $meta, array &$resume, string $kind): AiReply|array
    {
        $pages = $meta['doc_pages'] ?? null;
        $step = $meta['doc_step'] ?? null;
        $answer = trim(preg_replace('/^@normi(?:-file)?\s+/iu', '', trim($answer)));
        $ask = static function (string $text, string $next, array $more = []) use (&$resume, $kind) {
            return AiReply::notice($kind, $text)->withMeta([...$resume, ...$more, 'doc_step' => $next, ...($next === 'pages' && $kind === 'document' ? ['file_stage' => 'pilot_pages'] : [])]);
        };
        if ($step === 'pages') {
            $count = (int) $meta['doc_count'];
            if (!preg_match('/^(?:halaman\s+)?(\d{1,6})(?:\s*[-–]\s*(\d{1,6}))?[.!]?$/iu', $answer, $m)
                || ($first = (int) $m[1]) < 1 || ($last = (int) ($m[2] ?? $first)) < $first || $last > $count || $last - $first >= 35) {
                return $ask("Pilih 1–35 halaman dari PDF {$count} halaman, misalnya “halaman 1-".min(10, $count)."”.", 'pages', ['doc_count' => $count]);
            }
            $pages = "{$first}-{$last}";
        }
        try {
            $doc = PilotDocument::read($source, $pages);
        } catch (DocumentPageLimit $limit) {
            return $ask($limit->getMessage().' Balas “halaman 1-'.min(10, $limit->pages).'” atau tulis rentang pilihanmu. Hasil hanya mencakup halaman pilihan.', 'pages', ['doc_count' => $limit->pages]);
        } catch (RuntimeException $error) {
            return AiReply::notice($kind, $error->getMessage());
        }
        $resume['doc_pages'] = $pages;
        $resume['pilot_version'] = $doc['version'];
        $same = ($meta['pilot_version'] ?? '') === $doc['version'] && ($meta['doc_pages'] ?? null) === $pages;
        $edits = $same ? ($meta['doc_headers'] ?? []) : [];
        $reviewing = $same && ($step === 'table' || ($meta['file_stage'] ?? '') === 'pilot_table' || ($step === null && isset($meta['pilot_chat_origin'])));
        $confirmed = $same && !empty($meta['pilot_table']);
        $headers = [];
        if ($doc['tables']) {
            foreach ($doc['sections'] as $i => $line) {
                if ($i > 0 && preg_match('/^\s*\|(?:\s*:?-+:?\s*\|)+\s*$/', reset($line))) {
                    $headers[] = $i - 1;
                }
            }
            if (count($headers) === count($doc['tables'])) {
                foreach ($headers as $i => $index) {
                    $row = $doc['sections'][$index];
                    $names = array_map('trim', explode('|', trim(trim(reset($row)), '|')));
                    if (count($names) === count($doc['tables'][$i]['rows'][0])) {
                        $doc['tables'][$i]['rows'][0] = $names;
                    }
                }
            }
        }
        $issue = '';
        if ($reviewing && preg_match('/^koreksi\s+tabel\s+(\d+)\s*:\s*(.+)$/iu', $answer, $parts)) {
            $number = (int) $parts[1];
            $names = array_map('trim', explode('|', $parts[2]));
            $table = $doc['tables'][$number - 1] ?? null;
            if ($table && count($names) === count($table['rows'][0]) && !in_array('', $names, true)
                && mb_strlen($parts[2]) <= 1200) {
                $edits[$number] = $names;
            } else {
                $issue = "Nomor tabel atau jumlah nama kolom belum cocok.\n\n";
            }
        }
        // Match each Markdown header by its separator. Refuse an ambiguous mapping.
        if ($edits) {
            foreach ($edits as $number => $names) {
                $i = $headers[$number - 1] ?? null;
                if (count($headers) !== count($doc['tables']) || $i === null) {
                    return $ask('Susunan tabel tidak bisa dipetakan dengan pasti. Balas dengan rentang halaman tempat tabel itu berada.', 'pages', ['doc_count' => $doc['page_count'] ?? 35]);
                }
                $row = &$doc['sections'][$i];
                $row[array_key_first($row)] = '| '.implode(' | ', $names).' |';
                unset($row);
                $doc['tables'][$number - 1]['rows'][0] = $names;
            }
        }
        $resume['doc_headers'] = $edits;
        if ($doc['tables'] && !$confirmed && !($reviewing && preg_match('/^kolom benar[.! ]*$/iu', $answer))) {
            return $ask($issue.PilotDocument::tableQuestion($doc), 'table', [
                ...($kind === 'document' ? ['file_stage' => 'pilot_table'] : []),
                'doc_labels' => array_map(fn ($table) => array_values($table['rows'][0]), $doc['tables']),
            ]);
        }
        $resume['pilot_table'] = true;
        return $doc;
    }
}
