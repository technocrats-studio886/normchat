<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiRequestRefusal;
use App\Models\AiUsageLog;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupTokenContribution;
use App\Models\User;
use App\Services\NormkreditPricing;
use App\Support\UserTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Superadmin › Ringkasan, Keuangan & AI, ekspor CSV, dan log audit admin (tanpa fitur hapus). */
class AdminReportController extends Controller
{
    public function dashboard(NormkreditPricing $pricing): View
    {
        $today = CarbonImmutable::now(UserTime::zone())->startOfDay()->utc();
        $month = now()->subDays(30);
        // Permintaan = jawaban Normi; biaya termasuk pemeliharaan ingatan.
        $usage = fn ($since) => AiUsageLog::where('created_at', '>=', $since)
            ->selectRaw('count(*) filter (where kind not in (' . self::placeholders(AiUsageLog::NOT_ANSWERS) . ')) as requests,'
                . ' coalesce(sum(charged_credits), 0) as charged, coalesce(sum(provider_cost_idr), 0) as cost_idr', AiUsageLog::NOT_ANSWERS)->first();
        $sales = fn ($since) => GroupTokenContribution::where('created_at', '>=', $since)->where('price_paid', '>', 0)
            ->selectRaw('count(*) as payments, coalesce(sum(price_paid), 0) as revenue')->first();

        return view('superadmin.dashboard', [
            'users' => ['total' => User::count(), 'new' => User::where('created_at', '>=', $month)->count(), 'blocked' => User::whereNotNull('blocked_at')->count()],
            'groups' => ['active' => Group::where('status', 'active')->count(), 'frozen' => Group::where('status', Group::STATUS_FROZEN)->count(), 'new' => Group::where('created_at', '>=', $month)->count()],
            'salesToday' => $sales($today),
            'salesMonth' => $sales($month),
            'usageToday' => $usage($today),
            'usageMonth' => $usage($month),
            'rupiahPerCredit' => $pricing->rupiahPerCredit(),
        ]);
    }

    /** "?, ?, ?" untuk whereIn mentah. */
    public static function placeholders(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '?'));
    }

    public function finance(Request $request, NormkreditPricing $pricing): View
    {
        [$from, $to, $fromInput, $toInput] = $this->range($request);

        $transactions = GroupTokenContribution::whereBetween('created_at', [$from, $to])
            ->with(['group' => fn ($query) => $query->withTrashed()->select('id', 'name'), 'user:id,name'])
            ->latest('id')->paginate(30)->withQueryString();

        $revenueByDay = GroupTokenContribution::whereBetween('created_at', [$from, $to])->where('price_paid', '>', 0)
            ->selectRaw("to_char(created_at at time zone 'UTC' at time zone ?, 'YYYY-MM-DD') as day, sum(price_paid) as revenue, sum(credit_amount) as credits, count(*) as payments", [UserTime::zone()])
            ->groupBy('day')->orderBy('day')->get();

        // Jawaban yang ditagih ke grup; pemeliharaan ingatan (dari saldo OpenAI) dihitung terpisah.
        $usageByModel = AiUsageLog::whereBetween('created_at', [$from, $to])->whereNotIn('kind', AiUsageLog::NOT_ANSWERS)
            ->groupBy('model')->selectRaw('model, count(*) as requests, sum(charged_credits) as charged, sum(provider_cost_idr) as cost_idr,'
                . ' avg(input_tokens) as avg_input, sum(cached_input_tokens) as cached, sum(input_tokens) as input')
            ->orderByDesc('cost_idr')->get();

        $maintenance = AiUsageLog::whereBetween('created_at', [$from, $to])->whereIn('kind', AiUsageLog::NOT_ANSWERS)
            ->groupBy('kind')->selectRaw('kind, count(*) as calls, sum(input_tokens) as input, sum(provider_cost_idr) as cost_idr')
            ->get()->keyBy('kind');

        // Log sebelum penanda "searched" ada: dianggap mencari bila ada pesan lama yang ikut.
        $searched = "coalesce((breakdown_json->'context'->>'searched')::boolean, (breakdown_json->'context'->>'retrieved')::int > 0)";
        $recallFailed = "coalesce((breakdown_json->'context'->>'recall_failed')::boolean, false)";
        $answers = AiUsageLog::whereBetween('created_at', [$from, $to])->whereNotIn('kind', AiUsageLog::NOT_ANSWERS)
            ->selectRaw("count(*) as total,"
                . " count(*) filter (where {$searched}) as searched,"
                . " count(*) filter (where (breakdown_json->'context'->>'understanding')::boolean) as understood,"
                . " count(*) filter (where (breakdown_json->'context'->>'summary')::boolean) as with_summary,"
                . " count(*) filter (where {$recallFailed}) as recall_failed")
            ->first();

        // Normi gagal mengingat per minggu (Senin–Minggu, zona waktu superadmin): diminta klarifikasi, atau
        // riwayat lama dicari tapi tidak ada yang ketemu. Hanya angka dari log, tanpa isi chat.
        $recallByWeek = AiUsageLog::whereBetween('created_at', [$from, $to])->whereNotIn('kind', AiUsageLog::NOT_ANSWERS)
            ->selectRaw("to_char(date_trunc('week', created_at at time zone 'UTC' at time zone ?), 'YYYY-MM-DD') as week,"
                . " count(*) as total, count(*) filter (where {$recallFailed}) as failed", [UserTime::zone()])
            ->groupBy('week')->orderBy('week')->get();

        $refusals = AiRequestRefusal::whereBetween('created_at', [$from, $to])
            ->groupBy('reason')->selectRaw('reason, count(*) as total')->pluck('total', 'reason');

        $rupiahPerCredit = $pricing->rupiahPerCredit();

        $usageByGroup = AiUsageLog::whereBetween('ai_usage_logs.created_at', [$from, $to])
            ->join('groups', 'groups.id', '=', 'ai_usage_logs.group_id')
            ->groupBy('groups.id', 'groups.name')
            ->selectRaw('groups.id, groups.name, count(*) filter (where ai_usage_logs.kind not in (' . self::placeholders(AiUsageLog::NOT_ANSWERS) . ')) as requests,'
                . ' sum(charged_credits) as charged, sum(provider_cost_idr) as cost_idr', AiUsageLog::NOT_ANSWERS)
            ->orderByDesc('cost_idr')->limit(20)->get();

        return view('superadmin.finance', compact(
            'transactions', 'revenueByDay', 'usageByModel', 'usageByGroup', 'maintenance', 'answers', 'recallByWeek', 'refusals', 'rupiahPerCredit', 'fromInput', 'toInput'
        ));
    }

    public function audit(): View
    {
        return view('superadmin.audit', [
            // Termasuk catatan lama berawalan "superadmin." (sebelum log audit admin disatukan).
            'logs' => AuditLog::where(fn ($query) => $query->where('action', 'like', 'admin.%')->orWhere('action', 'like', 'superadmin.%'))
                ->with('actor:id,name')->latest('id')->paginate(40),
        ]);
    }

    /** Ekspor CSV: grup, user, transaksi, atau pemakaian AI (rentang tanggal untuk dua terakhir). */
    public function csv(Request $request, string $type): StreamedResponse
    {
        [$from, $to] = $this->range($request);
        AuditLog::record('admin.export_csv', $request->user()->id, null, ['type' => $type]);

        [$header, $rows] = match ($type) {
            'grup' => [
                ['ID', 'Nama', 'Status', 'Pemilik', 'Email pemilik', 'Saldo NK', 'Dibuat'],
                Group::with(['owner:id,name,email', 'groupToken'])->orderBy('id')->lazy()->map(fn (Group $group) => [
                    $group->id, $group->name, $group->status, $group->owner?->name, $group->owner?->email,
                    $group->groupToken?->credits ?? 0, UserTime::format($group->created_at, 'Y-m-d H:i'),
                ]),
            ],
            'user' => [
                ['ID', 'Nama', 'Email', 'Diblokir', 'Terdaftar'],
                User::orderBy('id')->lazy()->map(fn (User $user) => [
                    $user->id, $user->name, $user->email, $user->blocked_at ? 'ya' : 'tidak', UserTime::format($user->created_at, 'Y-m-d H:i'),
                ]),
            ],
            'transaksi' => [
                ['ID', 'Waktu', 'Grup', 'User', 'Sumber', 'NK', 'Dibayar (Rp)', 'Referensi', 'Catatan'],
                GroupTokenContribution::whereBetween('created_at', [$from, $to])->with(['group' => fn ($query) => $query->withTrashed(), 'user'])->orderBy('id')->lazy()
                    ->map(fn ($row) => [$row->id, UserTime::format($row->created_at, 'Y-m-d H:i'), $row->group?->name, $row->user?->name, $row->source, $row->credit_amount, $row->price_paid, $row->payment_reference, $row->note]),
            ],
            'ai' => [
                ['ID', 'Waktu', 'Grup', 'Model', 'Jenis', 'Token masuk', 'Token masuk tercache', 'Token keluar', 'Biaya (USD)', 'Kurs', 'Biaya (Rp)', 'Multiplier', 'NK ditagih', 'Saldo sebelum', 'Saldo sesudah', 'Versi harga', 'Request OpenAI'],
                AiUsageLog::whereBetween('created_at', [$from, $to])->with(['group' => fn ($query) => $query->withTrashed()])->orderBy('id')->lazy()
                    ->map(fn ($row) => [
                        $row->id, UserTime::format($row->created_at, 'Y-m-d H:i'), $row->group?->name, $row->model, $row->kind,
                        $row->input_tokens, $row->cached_input_tokens, $row->output_tokens, $row->provider_cost_usd, $row->usd_to_idr, $row->provider_cost_idr,
                        $row->multiplier, $row->charged_credits, $row->balance_before, $row->balance_after, $row->pricing_version_id, $row->provider_request_id,
                    ]),
            ],
            default => abort(404),
        };

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, $header, ';');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, sprintf('normi-%s-%s.csv', $type, now()->format('Ymd-His')), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string, 3: string} rentang UTC + nilai input (zona admin) */
    private function range(Request $request): array
    {
        $zone = UserTime::zone();
        $data = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $fromInput = $data['from'] ?? CarbonImmutable::now($zone)->subDays(29)->format('Y-m-d');
        $toInput = $data['to'] ?? CarbonImmutable::now($zone)->format('Y-m-d');

        return [
            CarbonImmutable::parse($fromInput, $zone)->startOfDay()->utc(),
            CarbonImmutable::parse($toInput, $zone)->endOfDay()->utc(),
            $fromInput,
            $toInput,
        ];
    }
}
