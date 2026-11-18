<section class="nc-panel nc-box p-4 mt-6">
    <h2 class="text-lg font-semibold">Sumber biaya AI · 30 hari</h2>
    <p class="mt-2 text-sm text-slate-500">Data panggilan yang tercatat di aplikasi. Pengujian atau aplikasi lain dalam project OpenAI yang sama dapat menyebabkan selisih dengan rekonsiliasi.</p>
    <div class="mt-4 overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="border-b border-slate-200 text-left"><th class="py-2">Jenis</th><th class="py-2 text-right">Panggilan tercatat</th><th class="py-2 text-right">Biaya USD</th></tr></thead>
            <tbody>
                @foreach($costDiagnostics['kinds'] as $row)
                    <tr class="border-b border-slate-200"><td class="py-2">{{ $row['kind'] }}</td><td class="text-right tabular-nums">{{ $row['calls'] }}</td><td class="text-right tabular-nums">${{ number_format($row['usd'], 6, '.', ',') }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-sm text-slate-600">Input tercache {{ $costDiagnostics['cache_hit_percent'] }}% · Token reasoning {{ number_format($costDiagnostics['reasoning']) }} · Token penulisan cache {{ number_format($costDiagnostics['cache_writes']) }}</p>
    <p class="mt-1 text-xs text-slate-500">Reasoning dan penulisan cache diukur sejak pembaruan ini; nilai historisnya belum tersedia. Token reasoning sudah termasuk token output dan tidak ditagih dua kali.</p>
    <p class="mt-2 text-sm text-slate-600">Gunakan project OpenAI khusus produksi dan isi anggaran bulanan serta cadangan saldo di bawah untuk membatasi biaya produksi.</p>
</section>
