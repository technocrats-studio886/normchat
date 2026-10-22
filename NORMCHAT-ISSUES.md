# NORMCHAT - Analisis Permasalahan

Dokumen ini berisi temuan masalah dari analisis codebase Normchat, dikelompokkan berdasarkan kategori: Security, Performance, Architecture, Code Quality, Business Logic, dan Infrastructure.

---

## 1. Security Issues

### 1.1 Patungan Tanpa Verifikasi Pembayaran [CRITICAL]

**File:** `app/Http/Controllers/GroupController.php:187-271`

```php
public function joinViaShareId(Request $request, string $shareId): RedirectResponse
{
    // ...
    $patunganAmount = (int) $request->input('patungan_amount');
    $tokenAmount = (int) ($normkredit * self::TOKENS_PER_CREDIT);

    // Add tokens to group — LANGSUNG tanpa verifikasi bayar
    $groupToken->addTokens($tokenAmount);

    // Join as member — LANGSUNG
    GroupMember::updateOrCreate(/* ... */);
}
```

**Masalah:**
- User cukup submit form dengan `patungan_amount` dan langsung mendapat akses ke grup + token ditambahkan
- Tidak ada payment verification — berbeda dengan subscription/topup/add_seat yang melalui Trakteer webhook
- Siapa pun bisa join grup dan "mengklaim" kontribusi token tanpa benar-benar membayar
- Ini adalah bypass total dari payment system

**Rekomendasi:**
- Implementasi flow yang sama seperti subscription: buat PendingPayment → waiting → webhook verify
- Atau minimal gunakan server-side verification sebelum menambahkan token
- Sementara, patungan sebaiknya disabled sampai payment flow terintegrasi

---

### 1.2 Manual OAuth Implementation Tanpa Socialite [HIGH]

**File:** `app/Http/Controllers/AuthController.php:40-62, 66-120`

```php
public function redirectToGoogle(): RedirectResponse
{
    // Manual URL building instead of Socialite
    $params = http_build_query([
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect'],
        'response_type' => 'code',
        // ...
    ]);
    return redirect('https://accounts.google.com/o/oauth2/v2/auth?' . $params);
}
```

**Masalah:**
- Laravel Socialite sudah ada di `composer.json` (v5.25) tapi TIDAK digunakan
- Implementasi manual rentan terhadap:
  - Missing security checks yang sudah ditangani Socialite
  - Token exchange bugs
  - Profile parsing inconsistencies
- Socialite sudah menangani state validation, token exchange, profile normalization secara robust

**Rekomendasi:**
- Gunakan Socialite driver bawaan: `Socialite::driver('google')->redirect()` dan `->user()`
- Hapus semua private helper methods (`exchangeCodeForToken`, `getGoogleProfile`, `createOrLoginUser`)
- Socialite menangani semua edge case OAuth secara battle-tested

---

### 1.3 Email-based User Merge Vulnerability [HIGH]

**File:** `app/Http/Controllers/AuthController.php:238-240`

```php
// Try matching by email if provider ID not found
if (! $user && ! empty($profile['email'])) {
    $user = User::query()->where('email', $profile['email'])->first();
}
```

**Masalah:**
- Jika user A sudah register, dan attacker membuat Google account dengan email yang sama (belum verified di Google), attacker bisa mengambil alih akun user A
- Google memungkinkan multiple accounts dengan email yang sama dalam edge cases
- Ini adalah account takeover vulnerability

**Rekomendasi:**
- Hanya match by `(auth_provider, provider_user_id)`, jangan fallback ke email
- Atau jika fallback email dibutuhkan, hanya merge jika Google email sudah verified (`email_verified` claim dari Google)

---

### 1.4 Webhook Amount Validation Terlalu Permissif [MEDIUM]

**File:** `app/Http/Controllers/TrakteerWebhookController.php:82-84`

```php
if ($paidAmount < $pending->expected_amount) {
    // reject
}
```

**Masalah:**
- Hanya cek `$paidAmount < $pending->expected_amount` — artinya overpayment langsung diterima tanpa handling
- Tidak ada cek apakah Trakteer `id` sudah pernah diproses (no idempotency check)
- Webhook bisa di-replay jika ada bug di Trakteer side

**Rekomendasi:**
- Simpan `trakteer_id` dari payload dan cek uniqueness sebelum fulfill
- Handle overpayment: log warning, atau refund difference, atau tambahkan excess sebagai bonus token
- Tambahkan idempotency check: jika PendingPayment sudah paid, return early

---

### 1.5 API Keys Disimpan di Multiple Places [MEDIUM]

**File:** `app/Models/User.php` (access_token_encrypted, api_key_encrypted) + `app/Models/AiConnection.php` (access_token)

**Masalah:**
- Credential AI disimpan di 2 tempat berbeda:
  1. `users` table: `access_token_encrypted`, `api_key_encrypted` (via User model methods)
  2. `ai_connections` table: `access_token` (via AiConnection model)
- `SettingsController@createAiConnection` menyimpan ke `ai_connections`
- `ProcessGroupChatQueueJob@resolveCredentials` membaca dari User model (`getAccessToken`, `getApiKey`)
- **Credential yang disimpan di ai_connections tidak pernah dibaca oleh AI processing job**

**Rekomendasi:**
- Pilih satu tempat penyimpanan credential
- Jika menggunakan `ai_connections`, update `resolveCredentials` untuk membaca dari sana
- Hapus kolom duplikat yang tidak digunakan

---

### 1.6 Attachment Path Traversal Risk [LOW]

**File:** `app/Http/Controllers/ChatController.php:200-214`

```php
public function attachment(Group $group, Message $message): StreamedResponse
{
    abort_unless((int) $message->group_id === (int) $group->id, 404);
    abort_if(! $message->attachment_path || ! $message->attachment_disk, 404);

    return Storage::disk($message->attachment_disk)->response($message->attachment_path, /* ... */);
}
```

**Masalah:**
- `attachment_path` berasal dari database yang diisi server-side (bukan user input langsung)
- Tapi jika ada SQL injection atau data corruption, `attachment_path` bisa berisi path traversal (`../../../etc/passwd`)
- Saat ini risikonya rendah karena path dibangun server-side, tapi tidak ada sanitasi

**Rekomendasi:**
- Validasi `attachment_path` tidak mengandung `..` atau absolute path sebelum serve
- Gunakan `basename()` check atau regex validation

---

## 2. Performance Issues

### 2.1 Export Dijalankan Synchronous [HIGH]

**File:** `app/Http/Controllers/SettingsController.php:46-73`

```php
public function createExport(Request $request, Group $group): RedirectResponse|StreamedResponse
{
    // ...
    // Generate immediately so user gets direct download on button click.
    (new GenerateExportJob($export->id))->handle();
    // ...
}
```

**Masalah:**
- `GenerateExportJob` dipanggil `->handle()` langsung (synchronous), bukan di-dispatch ke queue
- Untuk grup dengan banyak pesan, PDF/DOCX generation bisa memakan waktu lama
- PHP-FPM worker akan blocked, mengurangi concurrency
- Request timeout risk (Nginx default 60s, PHP max_execution_time)
- Memory spike saat generate PDF untuk ribuan pesan

**Rekomendasi:**
- Dispatch ke queue: `GenerateExportJob::dispatch($export->id)`
- Implementasi polling/notification untuk download setelah selesai
- Atau gunakan streaming response untuk progress indication

---

### 2.2 Chat Load Tanpa Pagination [MEDIUM]

**File:** `app/Http/Controllers/ChatController.php:65-72`

```php
$messages = Message::query()
    ->where('group_id', $group->id)
    ->with('sender:id,name')
    ->orderByDesc('id')
    ->take(80)
    ->get()
    ->reverse()
    ->values();
```

**Masalah:**
- Fixed limit 80 messages di-load setiap kali buka chat
- Tidak ada infinite scroll / load more mechanism
- Pesan lama tidak bisa diakses dari UI
- 80 messages mungkin terlalu banyak untuk initial load (terutama dengan attachment metadata)

**Rekomendasi:**
- Implementasi cursor-based pagination (load 30 → scroll up → load 30 lagi)
- Atau implementasi AJAX endpoint untuk load older messages

---

### 2.3 AI Job Unbounded While Loop [MEDIUM]

**File:** `app/Jobs/ProcessGroupChatQueueJob.php:39-66`

```php
while (true) {
    $queueItem = ChatMessageQueue::query()
        ->where('group_id', $this->groupId)
        ->where('status', 'queued')
        ->orderBy('id')
        ->first();

    if (! $queueItem) {
        break;
    }
    // process...
}
```

**Masalah:**
- Loop tanpa batasan jumlah iterasi — jika ada spam, satu job bisa memproses ratusan items
- Lock 30 detik (`Cache::lock(..., 30)`) mungkin tidak cukup untuk memproses banyak AI calls
- Setiap AI call butuh ~5-30 detik (HTTP timeout)
- Lock bisa expire saat masih processing, menyebabkan race condition

**Rekomendasi:**
- Tambahkan max iterations (e.g., 5 items per job, re-dispatch jika masih ada)
- Extend lock duration atau gunakan lock refresh mechanism
- Tambahkan rate limiting per grup

---

### 2.4 Group Index N+1 Query Potential [LOW]

**File:** `app/Http/Controllers/GroupController.php:29-36`

```php
$groups = Group::query()
    ->where('owner_id', $user->id)
    ->orWhereHas('members', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'))
    ->with(['members', 'groupToken'])
    ->withCount('members')
    ->latest()
    ->get();
```

**Masalah:**
- `with(['members'])` loads ALL members of ALL groups — bisa besar jika banyak grup aktif
- `withCount('members')` + `with(['members'])` redundant — count sudah bisa dihitung dari loaded relation

**Rekomendasi:**
- Gunakan `withCount('members')` saja tanpa `with(['members'])` jika hanya butuh count di index page
- Atau batasi: `with(['members' => fn($q) => $q->take(5)])` untuk avatar preview

---

## 3. Architecture Issues

### 3.1 Socialite Dependency Unused [MEDIUM]

**File:** `composer.json` — `"laravel/socialite": "^5.25"`

**Masalah:**
- Socialite di-require di composer.json tapi TIDAK digunakan di manapun
- OAuth diimplementasikan manual di `AuthController`
- Dead dependency menambah attack surface dan maintenance burden

**Rekomendasi:**
- Gunakan Socialite (recommended) — atau hapus dari `composer.json`

---

### 3.2 AiConnection Model Disconnect [MEDIUM]

**File:** `app/Models/AiConnection.php`, `app/Http/Controllers/SettingsController.php:154-183`, `app/Jobs/ProcessGroupChatQueueJob.php:239-257`

**Masalah:**
- `SettingsController@createAiConnection` menyimpan credential ke `ai_connections` table
- `ProcessGroupChatQueueJob@resolveCredentials` membaca dari `User` model (access_token_encrypted, api_key_encrypted)
- **ai_connections table never read by AI processing** — data yang disimpan user lewat settings tidak pernah dipakai
- `Group->aiConnections()` relation menggunakan `hasMany('user_id', 'owner_id')` yang secara semantik salah (ai_connections milik user, bukan group)

**Rekomendasi:**
- Update `resolveCredentials` untuk membaca dari `AiConnection` model terlebih dahulu
- Atau hapus `ai_connections` table dan simpan semua di `users` table
- Fix relationship semantics

---

### 3.3 Inconsistent Business Constants [MEDIUM]

**File:** Multiple controllers

```php
// GroupController.php
private const INCLUDED_CREDITS = 10;
private const TOKENS_PER_CREDIT = 1_000;
private const PLAN_PRICE = 25000;
private const SEAT_PRICE = 4000;
private const MIN_PATUNGAN = 10000;
private const PRICE_PER_NORMKREDIT = 1000;

// SubscriptionController.php
private const PLAN_PRICE = 25000;
private const INCLUDED_TOKENS = 10_000;
private const PRICE_PER_CREDIT = 1000;
private const ADD_SEAT_PRICE = 4000;
private const JOIN_MIN_PATUNGAN = 10000;
private const PAYMENT_EXPIRY_HOURS = 24;
```

**Masalah:**
- Business constants (harga, jumlah token, dll) di-hardcode di multiple controllers
- Jika harga berubah, harus update di 2+ tempat
- Nama constants tidak konsisten (`SEAT_PRICE` vs `ADD_SEAT_PRICE`, `TOKENS_PER_CREDIT` vs `INCLUDED_TOKENS`)
- Rawan out-of-sync

**Rekomendasi:**
- Pindahkan ke config file: `config/normchat.php`
- Atau buat class shared: `App\Support\NormchatPricing`
- Single source of truth untuk semua business constants

---

### 3.4 Approval Flow Belum Diimplementasi [LOW]

**File:** `database/migrations/2026_03_23_060600_create_normchat_domain_tables.php:86-96`

**Masalah:**
- Tabel `approvals` sudah ada di migration
- `groups.approval_enabled` field ada dan bisa di-set saat create group
- Tapi tidak ada controller logic yang menggunakan approval system
- Join via share link langsung masuk tanpa approval meskipun `approval_enabled = true`

**Rekomendasi:**
- Implementasi approval flow di `GroupController@joinViaShareId`
- Atau hapus `approval_enabled` flag dan `approvals` table jika tidak direncanakan

---

### 3.5 Message Versioning Belum Diimplementasi [LOW]

**File:** Tabel `message_versions` ada di migration tapi tidak ada controller/model logic untuk edit message

**Masalah:**
- Tidak ada endpoint untuk edit message
- Tidak ada logic yang membuat MessageVersion records
- Dead table di database

**Rekomendasi:**
- Implementasi message edit feature
- Atau remove table jika not planned

---

## 4. Code Quality Issues

### 4.1 Hardcoded AI API URLs [MEDIUM]

**File:** `app/Jobs/ProcessGroupChatQueueJob.php:288, 316, 352`

```php
Http::withToken($token)->post('https://api.openai.com/v1/chat/completions', /* ... */);
Http::withHeaders([...])->post('https://api.anthropic.com/v1/messages', /* ... */);
Http::withToken($token)->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", /* ... */);
```

**Masalah:**
- API URLs hardcoded di job file
- Jika perlu switch ke proxy, staging endpoint, atau API version update — harus edit source code

**Rekomendasi:**
- Pindahkan ke config: `config('services.openai.endpoint')`, etc.
- Atau environment variable: `OPENAI_API_BASE_URL`

---

### 4.2 Mixed Language (Bahasa Indonesia + English) [LOW]

**File:** Semua controllers, validation messages, audit actions

**Masalah:**
- Error messages dalam Bahasa Indonesia (`'Kirim teks, gambar, atau voice note.'`)
- Audit log actions dalam English (`'group.member_joined'`, `'settings.restore_backup'`)
- Variable names dan comments dalam English
- Flash messages campuran (`'success'`, `'info'`)

**Rekomendasi:**
- Konsisten — pilih satu bahasa untuk user-facing messages
- Gunakan Laravel localization (`__()`, `trans()`) untuk internationalization

---

### 4.3 Restore Backup Force Delete Tanpa Soft Delete Respect [MEDIUM]

**File:** `app/Http/Controllers/SettingsController.php:210`

```php
Message::query()->where('group_id', $group->id)->forceDelete();
```

**Masalah:**
- Restore backup menggunakan `forceDelete()` yang menghapus semua pesan permanen, termasuk yang di-soft-delete
- Restored messages tidak memiliki original `created_at` timestamps — semua dibuat ulang dengan `created_at` baru
- Attachment references hilang — message baru tidak memiliki attachment columns dari backup
- Attachment files di storage tetap ada tapi jadi orphaned

**Rekomendasi:**
- Preserve `created_at` timestamps dari backup snapshot
- Handle attachment data dalam backup/restore
- Cleanup orphaned attachment files setelah restore

---

## 5. Business Logic Issues

### 5.1 Subscription Lifecycle Incomplete [HIGH]

**File:** `app/Http/Controllers/SubscriptionController.php`, `app/Http/Controllers/GroupController.php`

**Masalah:**
- Subscription dibuat saat group creation, status langsung `active`
- Tidak ada mechanism untuk:
  - Subscription expiry / renewal
  - Subscription cancellation
  - Payment reminder sebelum expire
  - Grace period setelah expire
- `billing_cycle` field ada tapi tidak ada cron/scheduler yang mengecek expiry
- User yang sudah bayar sekali bisa pakai selamanya tanpa renewal

**Rekomendasi:**
- Tambahkan `expires_at` ke subscriptions table
- Buat scheduled command untuk check expired subscriptions
- Implementasi renewal flow

---

### 5.2 Token Balance Race Condition [MEDIUM]

**File:** `app/Jobs/ProcessGroupChatQueueJob.php:154-168`

```php
if ($usageTokens > 0 && $groupToken) {
    $effectiveTokens = $groupToken->consumeTokens($usageTokens, $multiplier);

    if ($effectiveTokens === false) {
        // Still send response but warn
        // Force consume whatever is left
        if ($groupToken->remaining_tokens > 0) {
            $groupToken->increment('used_tokens', $groupToken->remaining_tokens);
            $groupToken->update(['remaining_tokens' => 0]);
        }
    }
}
```

**Masalah:**
- `consumeTokens` tidak menggunakan database-level locking (no `FOR UPDATE`)
- Jika 2 AI requests processed nearly simultaneously, keduanya bisa read `remaining_tokens` > 0 dan consume lebih dari yang tersedia
- `increment`/`decrement` pada Eloquent bukan atomic di level application

**Rekomendasi:**
- Gunakan `DB::raw('remaining_tokens - ?')` dengan `WHERE remaining_tokens >= ?` untuk atomic update
- Atau gunakan Redis untuk token balance tracking (atomic operations)
- Wrap dalam database transaction dengan `lockForUpdate()`

---

### 5.3 Gemini Authentication Method Salah [MEDIUM]

**File:** `app/Jobs/ProcessGroupChatQueueJob.php:346-351`

```php
$response = Http::withToken($token)
    ->timeout(30)
    ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", /* ... */);
```

**Masalah:**
- Gemini API menggunakan API key via query parameter (`?key=API_KEY`), bukan Bearer token
- `Http::withToken($token)` mengirim `Authorization: Bearer {token}` header
- Jika credential adalah API key (bukan OAuth token), ini akan gagal
- Hanya berfungsi jika user credential adalah OAuth access token (bukan API key)

**Rekomendasi:**
- Untuk Gemini API key, gunakan query parameter:
  ```php
  Http::post("https://.../{model}:generateContent?key={$token}", /* ... */);
  ```
- Deteksi apakah credential adalah OAuth token atau API key, dan gunakan auth method yang sesuai

---

### 5.4 Share ID Collision Risk [LOW]

**File:** `app/Models/Group.php:43-48`

```php
public static function generateUniqueShareId(): string
{
    do {
        $id = strtoupper(Str::random(6));
    } while (static::withTrashed()->where('share_id', $id)->exists());

    return $id;
}
```

**Masalah:**
- 6 character uppercase alphanumeric = 36^6 ≈ 2.18 miliar kombinasi
- Saat ini cukup, tapi seiring bertambahnya grup, collision check bisa menjadi slow
- `Str::random(6)` menggunakan `[a-zA-Z0-9]` tapi `strtoupper()` mengecilkan domain jadi `[A-Z0-9]`
- Termasuk soft-deleted records (`withTrashed()`)

**Rekomendasi:**
- Pertimbangkan 8 characters untuk margin lebih besar
- Atau gunakan UUID-based short ID (NanoID pattern)

---

## 6. Infrastructure Issues

### 6.1 Environment Variables Duplicated Across Services [MEDIUM]

**File:** `docker-compose.yml`

**Masalah:**
- Environment variables di-copy-paste ke 4 services (app, queue, reverb, nginx)
- Jika ada perubahan, harus update di semua services
- Rawan missed updates

**Rekomendasi:**
- Gunakan `env_file` directive di docker-compose:
  ```yaml
  env_file:
    - .env
  ```
- Atau gunakan `x-common-env: &common-env` YAML anchor

---

### 6.2 Storage Volume Shared Tanpa Access Control [LOW]

**File:** `docker-compose.yml` — `normchat_storage:/opt/normchat/storage`

**Masalah:**
- Volume `normchat_storage` di-mount ke app, queue, dan reverb containers
- Semua service punya write access ke semua storage
- Tidak ada separation antara attachments, exports, dan backups di Docker level

**Rekomendasi:**
- Pertimbangkan separate volumes per concern (attachments, exports, backups)
- Atau gunakan read-only mount untuk service yang hanya perlu baca

---

### 6.3 Nginx Port Binding Hanya Localhost [LOW]

**File:** `docker-compose.yml:13`

```yaml
ports:
  - "127.0.0.1:8082:80"
```

**Masalah:**
- Port binding ke `127.0.0.1:8082` — hanya accessible dari host machine
- Ini sebenarnya **bagus** untuk security (intended for reverse proxy)
- Tapi jika ada host Nginx/Caddy di depannya, konfigurasinya tidak ada di repo

**Note:** Ini bukan issue per se, tapi documentation gap — konfigurasi reverse proxy host tidak ada di codebase.

---

## 7. Summary

| Severity | Count | Issues |
|----------|-------|--------|
| **CRITICAL** | 1 | Patungan tanpa verifikasi pembayaran |
| **HIGH** | 4 | Manual OAuth, email merge vulnerability, export synchronous, subscription lifecycle incomplete |
| **MEDIUM** | 10 | Webhook idempotency, API key storage duplication, constants duplication, AiConnection disconnect, AI loop unbounded, token race condition, Gemini auth method, restore data loss, hardcoded URLs, env duplication |
| **LOW** | 6 | Attachment path risk, N+1 query, approval flow missing, message versioning missing, share ID collision, storage access |

**Total: 21 issues identified**

### Top Priority Fixes
1. **Patungan payment verification** — Saat ini siapa pun bisa join dan "mengklaim" token tanpa bayar
2. **Use Socialite** — Hapus manual OAuth implementation
3. **Fix email merge vulnerability** — Jangan fallback ke email matching
4. **Fix AiConnection disconnect** — Credential yang disimpan user tidak terbaca
5. **Add subscription expiry** — Tanpa ini, subscription berlaku selamanya
