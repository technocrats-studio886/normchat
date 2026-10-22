<?php

namespace App\Services\Chat;

use App\Models\Group;
use App\Models\Message;
use App\Models\PollVote;

/**
 * Polling di chat ditulis sebagai pesan teks:
 *
 *   📊 POLL: Pertanyaan
 *   1. Pilihan A
 *   2. Pilihan B
 *
 * Format yang sama dikenali frontend (parsePollContent di app.js), chat, dan export.
 */
class PollService
{
    private const MAX_OPTIONS = 8;

    /** @return array{question: string, options: list<array{number: int, label: string}>}|null */
    public function parse(?string $content): ?array
    {
        $normalized = trim(str_replace(["\r\n", "\r"], "\n", (string) $content));
        if ($normalized === '' || ! preg_match('/^📊\s*poll:/iu', $normalized)) {
            return null;
        }

        $lines = array_values(array_filter(array_map('trim', explode("\n", $normalized)), fn ($line) => $line !== ''));
        $question = trim((string) preg_replace('/^📊\s*poll:\s*/iu', '', $lines[0]));
        if ($question === '') {
            return null;
        }

        $options = [];
        foreach (array_slice($lines, 1) as $line) {
            if (preg_match('/^(\d+)[\.)]\s+(.+)$/u', $line, $matches) && (int) $matches[1] > 0 && trim($matches[2]) !== '') {
                $options[] = ['number' => (int) $matches[1], 'label' => trim($matches[2])];
            }
        }

        return count($options) < 2 ? null : ['question' => $question, 'options' => array_slice($options, 0, self::MAX_OPTIONS)];
    }

    public function hasOption(array $poll, int $optionNumber): bool
    {
        return in_array($optionNumber, array_column($poll['options'], 'number'), true);
    }

    public function vote(Group $group, Message $pollMessage, int $userId, int $optionNumber): void
    {
        PollVote::updateOrCreate(
            ['poll_message_id' => $pollMessage->id, 'user_id' => $userId],
            ['group_id' => $group->id, 'option_number' => $optionNumber, 'voted_at' => now()]
        );
    }

    /**
     * Statistik polling untuk tampilan chat, dikunci dengan ID pesan (string).
     *
     * @param  list<int>  $pollMessageIds
     */
    public function statsFor(Group $group, array $pollMessageIds, int $viewerUserId): array
    {
        $polls = $this->pollsAmong(
            Message::query()->where('group_id', $group->id)->whereIn('id', $pollMessageIds)->get(['id', 'content'])
        );
        if ($polls === []) {
            return [];
        }

        $counts = $this->voteCounts(array_keys($polls));
        $viewerVotes = PollVote::query()
            ->where('user_id', $viewerUserId)
            ->whereIn('poll_message_id', array_keys($polls))
            ->pluck('option_number', 'poll_message_id');

        $stats = [];
        foreach ($polls as $pollId => $poll) {
            $optionCounts = [];
            foreach ($poll['options'] as $option) {
                $optionCounts[(string) $option['number']] = (int) ($counts[$pollId][$option['number']] ?? 0);
            }

            $stats[(string) $pollId] = [
                'poll_id' => $pollId,
                'question' => $poll['question'],
                'options' => $poll['options'],
                'option_counts' => $optionCounts,
                'total_votes' => array_sum($optionCounts),
                'my_vote_option' => (int) ($viewerVotes[$pollId] ?? 0),
            ];
        }

        return $stats;
    }

    /**
     * Ringkasan polling untuk export: setiap pilihan dengan jumlah dan persentase suara.
     *
     * @param  iterable<Message>  $messages
     */
    public function summariesFor(iterable $messages): array
    {
        $polls = $this->pollsAmong($messages);
        $counts = $polls === [] ? [] : $this->voteCounts(array_keys($polls));

        $summaries = [];
        foreach ($polls as $pollId => $poll) {
            $options = array_map(fn (array $option) => $option + ['count' => (int) ($counts[$pollId][$option['number']] ?? 0)], $poll['options']);
            $totalVotes = array_sum(array_column($options, 'count'));

            $summaries[$pollId] = [
                'question' => $poll['question'],
                'options' => array_map(fn (array $option) => $option + [
                    'percent' => $totalVotes > 0 ? round($option['count'] / $totalVotes * 100, 1) : 0,
                ], $options),
                'total_votes' => $totalVotes,
            ];
        }

        return $summaries;
    }

    /** @param iterable<Message> $messages @return array<int, array> definisi polling per ID pesan */
    private function pollsAmong(iterable $messages): array
    {
        $polls = [];
        foreach ($messages as $message) {
            $poll = $this->parse($message->content);
            if ($poll) {
                $polls[(int) $message->id] = $poll;
            }
        }

        return $polls;
    }

    /** @return array<int, array<int, int>> [poll_id][option_number] => jumlah suara */
    private function voteCounts(array $pollIds): array
    {
        $counts = [];
        PollVote::query()
            ->selectRaw('poll_message_id, option_number, COUNT(*) as votes_total')
            ->whereIn('poll_message_id', $pollIds)
            ->groupBy('poll_message_id', 'option_number')
            ->get()
            ->each(function (PollVote $row) use (&$counts) {
                $counts[(int) $row->poll_message_id][(int) $row->option_number] = (int) $row->getAttribute('votes_total');
            });

        return $counts;
    }
}
