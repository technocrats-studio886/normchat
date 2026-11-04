<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Support\UserTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Superadmin › Pengumuman: popup atau banner untuk semua user, dengan jadwal tayang. */
class AdminAnnouncementController extends Controller
{
    public function index(): View
    {
        return view('superadmin.announcements.index', [
            'announcements' => Announcement::query()->with('creator:id,name')->latest('id')->paginate(30),
        ]);
    }

    public function create(Request $request): View
    {
        $source = $request->integer('copy') ? Announcement::find($request->integer('copy')) : null;

        return view('superadmin.announcements.form', ['announcement' => $source?->replicate()->fill(['is_active' => false]) ?? new Announcement(['display' => Announcement::POPUP, 'tone' => 'info'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $announcement = Announcement::create($this->validated($request) + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        $this->audit($request, $announcement);

        return redirect()->route('superadmin.announcements.index')->with('success', $announcement->is_active ? 'Pengumuman ditayangkan.' : 'Draf pengumuman disimpan.');
    }

    public function edit(Announcement $announcement): View
    {
        return view('superadmin.announcements.form', ['announcement' => $announcement]);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $announcement->update($this->validated($request) + ['updated_by' => $request->user()->id]);
        $this->audit($request, $announcement);

        return redirect()->route('superadmin.announcements.index')->with('success', 'Pengumuman disimpan.');
    }

    /** Tayangkan sekarang atau hentikan. */
    public function toggle(Request $request, Announcement $announcement): RedirectResponse
    {
        $publish = ! $announcement->is_active || $announcement->state() === 'selesai';
        $announcement->update($publish
            ? ['is_active' => true, 'starts_at' => now(), 'ends_at' => $announcement->ends_at?->isPast() ? null : $announcement->ends_at, 'updated_by' => $request->user()->id]
            : ['is_active' => false, 'updated_by' => $request->user()->id]);
        AuditLog::record($publish ? 'admin.announcement_published' : 'admin.announcement_stopped', $request->user()->id, $announcement, ['title' => $announcement->title]);

        return back()->with('success', $publish ? 'Pengumuman ditayangkan.' : 'Pengumuman dihentikan.');
    }

    public function destroy(Request $request, Announcement $announcement): RedirectResponse
    {
        AuditLog::record('admin.announcement_deleted', $request->user()->id, $announcement, ['title' => $announcement->title]);
        $announcement->delete();

        return redirect()->route('superadmin.announcements.index')->with('success', 'Pengumuman dihapus.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'max:1000'],
            'display' => ['required', Rule::in([Announcement::POPUP, Announcement::BANNER])],
            'tone' => ['required', Rule::in(['info', 'penting'])],
            'cta_label' => ['nullable', 'required_with:cta_url', 'string', 'max:40'],
            // Hanya https atau jalur di dalam aplikasi; tidak ada javascript:, data:, dsb.
            'cta_url' => ['nullable', 'required_with:cta_label', 'string', 'max:500', 'regex:#^(https://[^\s]+|/[^/\s][^\s]*)$#'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'publish' => ['nullable', 'boolean'],
        ], [
            'cta_url.regex' => 'Tautan harus diawali https:// atau / (halaman di dalam aplikasi).',
        ]);

        $zone = UserTime::zone($request->user());
        $toUtc = fn (?string $value) => filled($value) ? CarbonImmutable::parse($value, $zone)->utc() : null;
        $startsAt = $toUtc($data['starts_at'] ?? null);
        $endsAt = $toUtc($data['ends_at'] ?? null);
        if ($startsAt && $endsAt && $endsAt->lte($startsAt)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['ends_at' => 'Waktu selesai harus setelah waktu mulai.']);
        }

        return [
            'title' => trim($data['title']),
            'body' => trim($data['body']),
            'display' => $data['display'],
            'tone' => $data['tone'],
            'cta_label' => filled($data['cta_label'] ?? null) ? trim($data['cta_label']) : null,
            'cta_url' => filled($data['cta_url'] ?? null) ? trim($data['cta_url']) : null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_active' => $request->boolean('publish'),
        ];
    }

    private function audit(Request $request, Announcement $announcement): void
    {
        AuditLog::record($announcement->is_active ? 'admin.announcement_published' : 'admin.announcement_saved', $request->user()->id, $announcement, ['title' => $announcement->title]);
    }
}
