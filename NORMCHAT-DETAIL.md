# NORMCHAT - Dokumentasi Teknis Detail

## 1. Overview

**Normchat** adalah mobile-first group chat platform dengan AI participant. Didesain sebagai chat room berbasis grup di mana anggota bisa berdiskusi secara realtime dan memanggil AI (OpenAI, Claude, Gemini) lewat mention system (@ai, @claude, dll).

- **Domain:** `normchat.technocrats.studio`
- **Viewport:** Mobile-first web app (PWA-enabled)
- **Arsitektur:** Server-rendered Blade + Alpine.js, realtime via Laravel Reverb (WebSocket)
- **Monetisasi:** Subscription-based + token top-up + patungan (crowdfunding) system via Trakteer

---

## 2. Tech Stack

### Backend
| Teknologi | Versi | Kegunaan |
|-----------|-------|----------|
| **PHP** | 8.3+ | Runtime utama |
| **Laravel** | 13.x | Framework backend (routing, ORM, auth, queue, broadcasting, etc.) |
| **Laravel Reverb** | 1.8+ | WebSocket server untuk realtime broadcasting |
| **Laravel Socialite** | 5.25 | OAuth integration (Google SSO) — tapi TIDAK digunakan secara langsung, OAuth diimplementasikan manual |
| **barryvdh/laravel-dompdf** | 3.1 | PDF export generation |
| **phpoffice/phpword** | 1.1 | DOCX export generation |

### Frontend
| Teknologi | Versi | Kegunaan |
|-----------|-------|----------|
| **TailwindCSS** | 4.0 | Utility-first CSS framework |
| **Alpine.js** | (manual) | Client-side interactivity |
| **Laravel Echo** | 2.3.1 | WebSocket client for realtime events |
| **Pusher.js** | 8.4.3 | WebSocket protocol client (digunakan Echo) |
| **Vite** | 8.0 | Build tool + HMR |

### Infrastructure
| Teknologi | Versi | Kegunaan |
|-----------|-------|----------|
| **PostgreSQL** | 16 Alpine | Database utama |
| **Redis** | 7 Alpine | Cache, session store, queue broker |
| **Nginx** | 1.27 Alpine | Reverse proxy + static file serving |
| **Docker** | Multi-stage | Containerized deployment |
| **Docker Compose** | - | Orchestration 6 services |

### External Services
| Service | Kegunaan |
|---------|----------|
| **Google OAuth** | Social login (SSO) — satu-satunya metode autentikasi |
| **Trakteer** | Payment gateway (webhook-based) |
| **OpenAI API** | AI provider (GPT models) |
| **Anthropic API** | AI provider (Claude models) |
| **Google Gemini API** | AI provider (Gemini models) |

---

## 3. Docker Architecture

6 services dalam `docker-compose.yml`:

| Service | Container | Port | Deskripsi |
|---------|-----------|------|-----------|
| **nginx** | `normchat-nginx` | `127.0.0.1:8082:80` | Reverse proxy, serve static files |
| **app** | `normchat-app` | - | PHP-FPM, jalankan migrate + cache saat startup |
| **queue** | `normchat-queue` | - | `queue:work redis --queue=default --tries=3 --sleep=1 --max-time=3600` |
| **reverb** | `normchat-reverb` | `8080` (internal) | `reverb:start --host=0.0.0.0 --port=8080` |
| **postgres** | `normchat-postgres` | `5432` (internal) | Database dengan healthcheck `pg_isready` |
| **redis** | `normchat-redis` | `6379` (internal) | Cache/queue/session dengan AOF persistence |

### Volumes
- `postgres_data` — PostgreSQL data persistence
- `redis_data` — Redis AOF persistence
- `normchat_storage` — Shared storage untuk attachments, exports, backups (mounted ke `/opt/normchat/storage`)

### Startup Command (app container)
```bash
php artisan migrate --force &&
php artisan config:cache &&
php artisan route:cache &&
php artisan view:cache &&
php artisan storage:link --force 2>/dev/null || true &&
php-fpm
```

---

## 4. Database Schema

### 4.1 `users`
| Kolom | Tipe | Constraint | Deskripsi |
|-------|------|------------|-----------|
| `id` | bigint | PK, auto-increment | - |
| `name` | varchar(255) | NOT NULL | Nama user dari Google |
| `email` | varchar(255) | UNIQUE | Email dari Google |
| `email_verified_at` | timestamp | NULLABLE | Waktu verifikasi email |
| `password` | varchar(255) | NULLABLE | Tidak digunakan (Google SSO only) |
| `avatar_url` | varchar(255) | NULLABLE | URL avatar Google |
| `auth_provider` | varchar(255) | NULLABLE | Selalu 'google' |
| `provider_user_id` | varchar(255) | NULLABLE | Google sub ID |
| `access_token_encrypted` | text | NULLABLE | OAuth access token (encrypted) |
| `refresh_token_encrypted` | text | NULLABLE | OAuth refresh token (encrypted) |
| `token_expires_at` | timestamp | NULLABLE | Waktu token expire |
| `api_key_encrypted` | text | NULLABLE | User-provided API key (encrypted) |
| `remember_token` | varchar(100) | NULLABLE | Laravel remember me token |
| `created_at` | timestamp | NULLABLE | - |
| `updated_at` | timestamp | NULLABLE | - |

**Unique constraint:** `(auth_provider, provider_user_id)`

**Relasi:**
- HasMany → `Group` (as owner via `owner_id`)
- HasMany → `GroupMember` (via `user_id`)
- HasOne → `AiConnection` (via `user_id`)

**Model Methods:**
- `storeOAuthTokens($access, $refresh, $expiresAt)` — Simpan token terenkripsi
- `storeApiKey($key)` — Simpan API key terenkripsi
- `getAccessToken()` → string|null — Dekripsi access token
- `getRefreshToken()` → string|null — Dekripsi refresh token
- `getApiKey()` → string|null — Dekripsi API key
- `isTokenExpired()` → bool — Cek apakah token sudah expired
- `hasValidCredentials()` → bool — Cek apakah ada token/API key yang valid

---

### 4.2 `roles`
| Kolom | Tipe | Constraint | Deskripsi |
|-------|------|------------|-----------|
| `id` | bigint | PK | - |
| `key` | varchar(255) | UNIQUE | Identifier: `owner`, `admin`, `member` |
| `name` | varchar(255) | NOT NULL | Display name |
| `description` | text | NULLABLE | Deskripsi role |
| `created_at` | timestamp | NULLABLE | - |
| `updated_at` | timestamp | NULLABLE | - |

**Relasi:**
- BelongsToMany → `Permission` (via `role_permissions`)

---

### 4.3 `permissions`
| Kolom | Tipe | Constraint | Deskripsi |
|-------|------|------------|-----------|
| `id` | bigint | PK | - |
| `key` | varchar(255) | UNIQUE | Identifier permission |
| `name` | varchar(255) | NOT NULL | Display name |
| `description` | text | NULLABLE | Deskripsi permission |
| `created_at` | timestamp | NULLABLE | - |
| `updated_at` | timestamp | NULLABLE | - |

**Permission keys yang digunakan di middleware/policy:**
- `manage_billing` — Akses settings grup
- `export_chat` — Export chat ke PDF/DOCX
- `recover_history` — Backup & restore
- `add_member` — Promote member
- `remove_member` — Remove member dari grup

**Relasi:**
- BelongsToMany → `Role` (via `role_permissions`)

---

### 4.4 `role_permissions` (Pivot)
| Kolom | Tipe | Constraint | Deskripsi |
|-------|------|------------|-----------|
| `id` | bigint | PK | - |
| `role_id` | bigint | FK → roles, CASCADE | - |
| `permission_id` | bigint | FK → permissions, CASCADE | - |
| `created_at` | timestamp | NULLABLE | - |
| `updated_at` | timestamp | NULLABLE | - |

**Unique constraint:** `(role_id, permission_id)`

---

### 4.5 `groups`
| Kolom | Tipe | Constraint | Deskripsi |
|-------|------|------------|-----------|
| `id` | bigint | PK | - |
| `name` | varchar(255) | NOT NULL | Nama grup |
| `description` | text | NULLABLE | Deskripsi grup |
| `owner_id` | bigint | FK → users, CASCADE | Pemilik grup |
| `password_hash` | varchar(255) | NULLABLE | Bcrypt hash password join |
| `approval_enabled` | boolean | DEFAULT false | Apakah butuh approval join |
| `share_id` | varchar(8) | UNIQUE | 6-char random ID untuk invite link |
| `ai_provider` | varchar(255) | NULLABLE | Provider AI: openai/claude/gemini |
| `ai_model` | varchar(255) | NULLABLE | Model AI spesifik (e.g., gpt-4o) |
| `ai_persona_style` | text | NULLABLE | Custom persona style AI |
| `ai_persona_guardrails` | text | NULLABLE | Custom guardrails AI |
| `created_at` | timestamp | NULLABLE | - |
| `updated_at` | timestamp | NULLABLE | - |
| `deleted_at` | timestamp | NULLABLE | SoftDeletes |

**Model boot:** Auto-generate `share_id` (6 char uppercase random, unique termasuk soft-deleted records)

**Relasi:**
- BelongsTo → `User` (owner)
- HasMany → `GroupMember`
- HasMany → `Message`
- HasMany → `AiConnection` (via owner_id matching user_id)
- HasMany → `GroupBackup`
- HasMany → `Export`
- HasOne → `Subscription`
- HasOne → `GroupToken`
- HasMany → `GroupTokenContribution`

**Model Methods:**
- `getModelMultiplier()` → float — Ambil cost multiplier dari config `ai_models.providers.{provider}.models.{model}.multiplier`
- `getModelLabel()` → string — Format label "Provider - Model" dari config

---

### 4.6 `group_members`
| Kolom | Tipe | Constraint | Deskripsi |
|-------|------|------------|-----------|
| `id` | bigint | PK | - |
| `group_id` | bigint | FK → groups, CASCADE | - |
| `user_id` | bigint | FK → users, CASCADE | - |
| `role_id` | bigint | FK → roles, CASCADE | Role di grup ini |
| `status` | varchar(255) | DEFAULT 'active' | Status: active, pending |
| `invited_by` | bigint | FK → users, NULL ON DELETE | Siapa yang invite |
| `approved_by` | bigint | FK → users, NULL ON DELETE | Siapa yang approve |
| `joined_at` | timestamp | NULLABLE | Waktu join |
| `created_at` | timestamp | NULLABLE | - |
| `updated_at` | timestamp | NULLABLE | - |

**Unique constraint:** `(group_id, user_id)`

---

### 4.7 `messages`
| Kolom | Tipe | Constraint | Deskripsi |
|-------|------|------------|-----------|
| `id` | bigint | PK | - |
| `group_id` | bigint | FK → groups, CASCADE | Grup tujuan |
| `message_type` | varchar(255) | DEFAULT 'text' | Tipe: text, image, voice, file |
| `sender_type` | varchar(255) | NOT NULL | 'user' atau 'ai' |
| `sender_id` | bigint unsigned | NULLABLE | FK ke users (null untuk AI) |
| `content` | text | NULLABLE | Isi pesan (nullable untuk attachment-only) |
| `attachment_disk` | varchar(255) | NULLABLE | Storage disk: 'normchat_attachments' |
| `attachment_path` | varchar(255) | NULLABLE | Path file di disk |
| `attachment_mime` | varchar(255) | NULLABLE | MIME type file |
| `attachment_original_name` | varchar(255) | NULLABLE | Nama asli file |
| `attachment_size` | bigint unsigned | NULLABLE | Ukuran file (bytes) |
| `created_at` | timestamp | NULLABLE | - |
| `updated_at` | timestamp | NULLABLE | - |
| `deleted_at` | timestamp | NULLABLE | SoftDeletes |

**Indexes:**
- `(group_id, created_at)` — Untuk query messages per grup
- `(group_id, message_type)` — Untuk filter berdasarkan tipe

**Attachment storage pattern:** `group-{id}/{Y/m}/{uuid}.{ext}`

---

### 4.8 `message_versions`
| Kolom | Tipe | Constraint | Deskripsi |
|-------|------|------------|-----------|
| `id` | bigint | PK | - |
| `message_id` | bigint | FK → messages, CASCADE | Pesan yang diedit |
| `version_number` | unsigned int | DEFAULT 1 | Nomor versi |
| `content_snapshot` | text | NOT NULL | Snapshot konten sebelum edit |
| `edited_by` | bigint | FK → users, NULL ON DELETE | Siapa yang edit |
| `edited_at` | timestamp | NOT NULL | Waktu edit |
| `created_at` | timestamp | NULLABLE | - |
| `updated_at` | timestamp | NULLABLE | - |

---

### 4.9 `approvals`
| Kolom | Tipe | Constraint | Deskripsi |
|-------|------|------------|-----------|
| `id` | bigint | PK | - |
| `group_id` | bigint | FK → groups, CASCADE | - |
| `user_id` | bigint | FK → users, CASCADE | User yang request join |
| `status` | varchar(255) | DEFAULT 'pending' | pending/approved/rejected |
| `requested_at` | timestamp | NOT NULL | - |
| `approved_by` | bigint | FK → users, NULL ON DELETE | - |
| `rejected_by` | bigint | FK → users, NULL ON DELETE | - |
| `note` | text | NULLABLE | Catatan approval/rejection |
| `created_at` | timestamp | NULLABLE | - |
| `updated_at` | timestamp | NULLABLE | - |

> **Note:** Tabel ini ada di migration tapi belum ada controller logic untuk approval flow.

---

### 4.10 `ai_connections`
| Kolom | Tipe | Constraint | Deskripsi |
|-------|------|------------|-----------|
| `id` | bigint | PK | - |
| `user_id` | bigint | FK → users, CASCADE | UNIQUE per user |
| `provider` | varchar(255) | NOT NULL | openai/claude/gemini |
| `access_token` | text | NULLABLE | Encrypted API key/token |
| `refresh_token` | text | NULLABLE | Encrypted refresh token |
| `expires_at` | timestamp | NULLABLE | Token expiry |
| `created_at` | timestamp | NULLABLE | - |
| `updated_at` | timestamp | NULLABLE | - |

**Unique constraint:** `(user_id)` — Satu koneksi AI per user

**Note:** Tabel ini di-recreate via migration `2026_03_24_140000`, mengubah dari group-level ke user-level.

---

### 4.11 `subscriptions`
| Kolom | Tipe | Constraint | Deskripsi |
|-------|------|------------|-----------|
| `id` | bigint | PK | - |
| `group_id` | bigint | FK → groups, CASCADE | - |
| `plan_name` | varchar(255) | DEFAULT 'normchat-main' | Nama plan |
| `status` | varchar(255) | DEFAULT 'active' | active/inactive |
| `billing_cycle` | varchar(255) | DEFAULT 'monthly' | Cycle billing |
| `main_price` | decimal(12,2) | DEFAULT 99 | Harga plan (overridden ke 25000) |
| `included_seats` | unsigned int | DEFAULT 2 | Jumlah seat termasuk dalam plan |
| `created_at` | timestamp | NULLABLE | - |
| `updated_at` | timestamp | NULLABLE | - |

**Relasi:**
- BelongsTo → `Group`
- HasMany → `SubscriptionSeat`
- HasMany → `SubscriptionPayment`

---

### 4.12 `subscription_seats`
| Kolom | Tipe | Constraint | Deskripsi |
|-------|------|------------|-----------|
| `id` | bigint | PK | - |
| `subscription_id` | bigint | FK → subscriptions, CASCADE | - |
| `user_id` | bigint | FK → users, CASCADE | - |
| `seat_type` | varchar(255) | DEFAULT 'included' | included/extra |
| `active` | boolean | DEFAULT true | Aktif atau tidak |
| `created_at` | timestamp | NULLABLE | - |
| `updated_at` | timestamp | NULLABLE | - |

**Unique constraint:** `(subscription_id, user_id)`

---

### 4.13 `subscription_payments`
| Kolom | Tipe | Constraint | Deskripsi |
|-------|------|------------|-----------|
| `id` | bigint | PK | - |
| `subscription_id` | bigint | FK → subscriptions, CASCADE | - |
| `group_id` | bigint | FK → groups, CASCADE | - |
| `created_by` | bigint | FK → users, CASCADE | Siapa yang bayar |
| `payment_type` | varchar(255) | DEFAULT 'add_seat_dummy' | Tipe pembayaran |
| `reference` | varchar(255) | UNIQUE | Reference ID (order_id) |
| `seat_count` | unsigned int | DEFAULT 0 | Jumlah seat dibeli |
| `unit_price` | unsigned int | DEFAULT 0 | Harga per seat |
| `total_amount` | unsigned int | DEFAULT 0 | Total harga |
| `status` | varchar(255) | DEFAULT 'paid' | Status pembayaran |
| `metadata_json` | json | NULLABLE | Data tambahan |
| `created_at` | timestamp | NULLABLE | - |
| `updated_at` | timestamp | NULLABLE | - |

**Index:** `(group_id, created_at)`

---

### 4.14 `group_tokens`
| Kolom | Tipe | Constraint | Deskripsi |
|-------|------|------------|-----------|
| `id` | bigint | PK | - |
| `group_id` | bigint | FK → groups, CASCADE | UNIQUE |
| `total_tokens` | bigint | DEFAULT 0 | Total token yang pernah diterima |
| `used_tokens` | bigint | DEFAULT 0 | Token yang sudah terpakai |
| `remaining_tokens` | bigint | DEFAULT 0 | Sisa token |
| `created_at` | timestamp | NULLABLE | - |
| `updated_at` | timestamp | NULLABLE | - |

**Model Methods:**
- `addTokens(int $amount)` — Tambah token (increment total + remaining)
- `addCredits(float $credits)` — Tambah normkredit (1 normkredit = 1000 token)
- `consumeTokens(int $actualTokens, float $multiplier)` → int|false — Konsumsi token dengan multiplier, return false jika saldo tidak cukup
- `hasEnoughTokens(int $estimated, float $multiplier)` → bool
- `formattedRemaining()` → string — Format: "10.0K", "1.5M", "500"
- `getCreditsAttribute()` → float — Computed: remaining_tokens / 1000
- `getTotalCreditsAttribute()` → float — Computed: total_tokens / 1000

**Normkredit System:**
- 1 normkredit = 1,000 token
- 1 normkredit = Rp 1.000
- Token dikonsumsi dengan multiplier berdasarkan model AI

---

### 4.15 `group_token_contributions`
| Kolom | Tipe | Constraint | Deskripsi |
|-------|------|------------|-----------|
| `id` | bigint | PK | - |
| `group_id` | bigint | FK → groups, CASCADE | - |
| `user_id` | bigint | FK → users, CASCADE | Siapa yang kontribusi |
| `source` | varchar(255) | NOT NULL | 'subscription', 'topup', 'patungan' |
| `token_amount` | bigint | NOT NULL | Jumlah token |
| `price_paid` | int | DEFAULT 0 | Harga yang dibayar (Rupiah) |
| `payment_reference` | varchar(255) | NULLABLE | Reference pembayaran |
| `created_at` | timestamp | NULLABLE | - |
| `updated_at` | timestamp | NULLABLE | - |

**Index:** `(group_id, source)`

---

### 4.16 `pending_payments`
| Kolom | Tipe | Constraint | Deskripsi |
|-------|------|------------|-----------|
| `id` | bigint | PK | - |
| `user_id` | bigint | FK → users, CASCADE | - |
| `group_id` | bigint | FK → groups, NULL ON DELETE | NULLABLE (null untuk subscription awal) |
| `order_id` | varchar(40) | UNIQUE | Format: NC-SUB-XXXXXXXX / NC-TOKEN-XXXXXXXX / NC-SEAT-XXXXXXXX |
| `payment_type` | varchar(30) | NOT NULL | 'subscription', 'topup', 'add_seat' |
| `expected_amount` | int | NOT NULL | Jumlah yang harus dibayar (Rupiah) |
| `status` | varchar(20) | DEFAULT 'pending' | pending/paid/expired/failed |
| `metadata_json` | json | NULLABLE | Data spesifik per tipe |
| `paid_at` | timestamp | NULLABLE | Waktu terkonfirmasi bayar |
| `expires_at` | timestamp | NULLABLE | Waktu kadaluarsa (24 jam) |
| `created_at` | timestamp | NULLABLE | - |
| `updated_at` | timestamp | NULLABLE | - |

**Indexes:**
- `(status, order_id)`
- `(user_id, status)`

**Model Methods:**
- `isPending()` → bool
- `isPaid()` → bool
- `isExpired()` → bool
- `markPaid()` — Set status=paid, paid_at=now

---

### 4.17 `chat_message_queues`
| Kolom | Tipe | Constraint | Deskripsi |
|-------|------|------------|-----------|
| `id` | bigint | PK | - |
| `group_id` | bigint | FK → groups, CASCADE | - |
| `message_id` | bigint | FK → messages, CASCADE | Pesan yang memicu AI |
| `status` | varchar(255) | DEFAULT 'queued' | queued/processing/processed/failed |
| `queued_at` | timestamp | NULLABLE | Waktu masuk queue |
| `processed_at` | timestamp | NULLABLE | Waktu selesai proses |
| `error_message` | varchar(255) | NULLABLE | Pesan error jika gagal |
| `created_at` | timestamp | NULLABLE | - |
| `updated_at` | timestamp | NULLABLE | - |

**Index:** `(group_id, status, id)`

---

### 4.18 `group_backups`
| Kolom | Tipe | Constraint | Deskripsi |
|-------|------|------------|-----------|
| `id` | bigint | PK | - |
| `group_id` | bigint | FK → groups, CASCADE | - |
| `backup_type` | varchar(255) | NOT NULL | Tipe backup |
| `storage_path` | varchar(255) | NOT NULL | Path di disk 'normchat_backups' |
| `created_by` | bigint | FK → users, CASCADE | Siapa yang buat |
| `created_at` | timestamp | NOT NULL | - |

---

### 4.19 `recovery_logs`
| Kolom | Tipe | Constraint | Deskripsi |
|-------|------|------------|-----------|
| `id` | bigint | PK | - |
| `group_id` | bigint | FK → groups, CASCADE | - |
| `backup_id` | bigint | FK → group_backups, CASCADE | Backup yang di-restore |
| `restored_by` | bigint | FK → users, CASCADE | Siapa yang restore |
| `restored_at` | timestamp | NOT NULL | Waktu restore |
| `reason` | text | NULLABLE | Alasan restore |

---

### 4.20 `exports`
| Kolom | Tipe | Constraint | Deskripsi |
|-------|------|------------|-----------|
| `id` | bigint | PK | - |
| `group_id` | bigint | FK → groups, CASCADE | - |
| `file_name` | varchar(255) | NOT NULL | Nama file output |
| `storage_path` | varchar(255) | NOT NULL | Path di disk 'normchat_exports' |
| `file_type` | varchar(255) | NOT NULL | 'pdf' atau 'docx' |
| `status` | varchar(255) | DEFAULT 'queued' | queued/done/failed |
| `created_by` | bigint | FK → users, CASCADE | - |
| `created_at` | timestamp | NOT NULL | - |

---

### 4.21 `audit_logs`
| Kolom | Tipe | Constraint | Deskripsi |
|-------|------|------------|-----------|
| `id` | bigint | PK | - |
| `group_id` | bigint | FK → groups, NULL ON DELETE | NULLABLE (untuk event global) |
| `actor_id` | bigint | FK → users, NULL ON DELETE | User yang melakukan aksi |
| `action` | varchar(255) | NOT NULL | Action key |
| `target_type` | varchar(255) | NULLABLE | Model class target |
| `target_id` | bigint unsigned | NULLABLE | ID target |
| `metadata_json` | json | NULLABLE | Data tambahan |
| `created_at` | timestamp | NOT NULL | - |

**Action keys yang digunakan:**
- `auth.connect` — Login via Google SSO
- `auth.logout` — Logout
- `group.create` — Buat grup baru
- `group.member_joined` — Member join via share link
- `group.member_role_changed` — Promote/demote member
- `group.member_removed` — Remove member
- `chat.send_message` — Kirim pesan
- `settings.update_ai_persona` — Update persona AI
- `settings.set_group_ai_provider` — Set provider AI
- `settings.restore_backup` — Restore backup

---

## 5. Routes & Endpoints

### 5.1 Public Routes (No Auth)

| Method | URI | Controller@Method | Deskripsi |
|--------|-----|-------------------|-----------|
| GET | `/` | `SubscriptionController@landing` | Landing page marketing |
| GET | `/normchat` | `SubscriptionController@landing` | Alias landing |
| GET | `/pricing` | `SubscriptionController@pricing` | Halaman pricing |
| GET | `/login` | `AuthController@landing` | Halaman login |
| GET | `/auth/google` | `AuthController@redirectToGoogle` | Redirect ke Google OAuth |
| GET | `/auth/google/callback` | `AuthController@handleGoogleCallback` | Callback Google OAuth |
| POST | `/webhooks/trakteer` | `TrakteerWebhookController@handle` | Webhook Trakteer (no CSRF) |

### 5.2 Auth + Join Routes

| Method | URI | Controller@Method | Middleware | Deskripsi |
|--------|-----|-------------------|------------|-----------|
| GET | `/join/{shareId}` | `GroupController@showJoin` | auth | Form join grup |
| POST | `/join/{shareId}` | `GroupController@joinViaShareId` | auth | Submit join + patungan |

### 5.3 Authenticated Routes

#### Payment Flow
| Method | URI | Controller@Method | Deskripsi |
|--------|-----|-------------------|-----------|
| GET | `/payment/detail` | `SubscriptionController@paymentDetail` | Halaman detail pembayaran subscription |
| POST | `/payment/detail` | `SubscriptionController@pay` | Buat PendingPayment subscription |
| GET | `/payment/waiting` | `SubscriptionController@paymentWaiting` | Halaman menunggu konfirmasi bayar |
| GET | `/payment/status` | `SubscriptionController@paymentStatus` | AJAX polling status pembayaran (JSON) |
| GET | `/payment/success` | `SubscriptionController@paymentSuccess` | Halaman sukses bayar |

**Request Body POST `/payment/detail`:**
```
plan: required, in:normchat-pro
```

**Response GET `/payment/status`:**
```json
{ "status": "pending" }
{ "status": "paid", "redirect": "/payment/success" }
{ "status": "expired" }
{ "status": "not_found" } // 404
```

#### Token Purchase
| Method | URI | Controller@Method | Deskripsi |
|--------|-----|-------------------|-----------|
| GET | `/tokens/buy` | `SubscriptionController@buyTokensForm` | Form beli normkredit |
| POST | `/tokens/buy` | `SubscriptionController@buyTokens` | Submit pembelian token |
| GET | `/tokens/buy/success` | `SubscriptionController@buyTokensSuccess` | Halaman sukses beli token |

**Request Body POST `/tokens/buy`:**
```
group_id: required, exists:groups,id
mode: required, in:by_credits,by_price
credit_amount: required_if:mode,by_credits, nullable, numeric, min:1
price_amount: required_if:mode,by_price, nullable, integer, min:1000
```

#### Groups
| Method | URI | Controller@Method | Deskripsi |
|--------|-----|-------------------|-----------|
| GET | `/groups` | `GroupController@index` | Daftar grup user |
| GET | `/groups/create` | `GroupController@create` | Form buat grup (cek subscription) |
| POST | `/groups` | `GroupController@store` | Simpan grup baru |

**Request Body POST `/groups`:**
```
name: required, string, max:120
description: nullable, string, max:500
password: required, string, min:4, max:100
approval_enabled: nullable
ai_provider: required, in:[openai,claude,gemini]
ai_model: required, in:[dynamic from config]
```

#### Chat
| Method | URI | Controller@Method | Middleware | Deskripsi |
|--------|-----|-------------------|------------|-----------|
| GET | `/chat/last` | `ChatController@openLast` | auth | Redirect ke last chat |
| GET | `/groups/{group}/chat` | `ChatController@show` | group.permission | Tampilkan chat room |
| POST | `/groups/{group}/messages` | `ChatController@store` | group.permission | Kirim pesan |
| GET | `/groups/{group}/messages/{message}/attachment` | `ChatController@attachment` | group.permission | Download attachment |

**Request Body POST `/groups/{group}/messages`:**
```
content: nullable, string, max:3000
attachment: nullable, file, max:15360 (15MB), mimes:jpg,jpeg,png,webp,gif,heic,heif,mp3,wav,ogg,webm,mp4,aac,m4a
```

**Response (JSON jika expectsJson):**
```json
{
  "ok": true,
  "message": {
    "id": 123,
    "message_type": "text",
    "sender_type": "user",
    "sender_id": 1,
    "sender_name": "John",
    "content": "Hello @ai",
    "attachment_url": null,
    "attachment_mime": null,
    "attachment_original_name": null,
    "created_at": "2026-03-25T10:00:00+00:00"
  }
}
```

#### Settings (require `manage_billing` permission)
| Method | URI | Controller@Method | Permission | Deskripsi |
|--------|-----|-------------------|------------|-----------|
| GET | `/groups/{group}/settings` | `SettingsController@show` | manage_billing | Dashboard settings |
| GET | `/groups/{group}/settings/history-export` | `SettingsController@historyExport` | manage_billing | Riwayat export & backup |
| GET | `/groups/{group}/settings/ai-persona` | `SettingsController@aiPersonaEditor` | manage_billing | Editor persona AI |
| POST | `/groups/{group}/settings/ai-persona` | `SettingsController@saveAiPersona` | manage_billing | Simpan persona AI |
| GET | `/groups/{group}/settings/seat-management` | `SettingsController@seatManagement` | manage_billing | Manajemen seat |
| POST | `/groups/{group}/settings/ai` | `SettingsController@createAiConnection` | manage_billing | Set API key AI |
| POST | `/groups/{group}/settings/export` | `SettingsController@createExport` | export_chat | Generate export PDF/DOCX |
| POST | `/groups/{group}/settings/backup` | `SettingsController@createBackup` | recover_history | Buat backup snapshot |
| POST | `/groups/{group}/settings/backup/{backup}/restore` | `SettingsController@restoreBackup` | recover_history | Restore dari backup |

**Request Body POST `.../ai-persona`:**
```
ai_persona_style: nullable, string, max:1000
ai_persona_guardrails: nullable, string, max:1000
```

**Request Body POST `.../ai`:**
```
provider_name: required, in:openai,claude,gemini
access_token: required, string, min:20, max:4096
```

**Request Body POST `.../export`:**
```
file_type: required, in:pdf,docx
```

**Request Body POST `.../backup/{backup}/restore`:**
```
reason: nullable, string, max:500
```

#### Members
| Method | URI | Controller@Method | Permission | Deskripsi |
|--------|-----|-------------------|------------|-----------|
| POST | `/groups/{group}/members/{member}/promote` | `GroupController@promoteMember` | add_member | Ubah role member |
| POST | `/groups/{group}/members/{member}/remove` | `GroupController@removeMember` | remove_member | Hapus member |

**Request Body POST `.../promote`:**
```
role: required, in:admin,member
```

#### Profile
| Method | URI | Controller@Method | Deskripsi |
|--------|-----|-------------------|-----------|
| GET | `/profile` | `ProfileController@show` | Profil user + subscriptions |
| GET | `/profile/security` | `ProfileController@security` | Halaman keamanan |

#### Seat Payment
| Method | URI | Controller@Method | Deskripsi |
|--------|-----|-------------------|-----------|
| GET | `/subscribe/add-seat/{group}` | `SubscriptionController@addSeatForm` | Form tambah seat |
| POST | `/subscribe/add-seat/{group}` | `SubscriptionController@processAddSeat` | Proses tambah seat |
| GET | `/subscribe/add-seat/{group}/success` | `SubscriptionController@addSeatSuccess` | Halaman sukses |
| GET | `/subscribe/add-seat/{group}/payments` | `SubscriptionController@addSeatPaymentHistory` | Riwayat pembayaran seat |

**Request Body POST `.../add-seat/{group}`:**
```
seat_count: required, integer, min:1, max:20
```

#### PWA
| Method | URI | Deskripsi |
|--------|-----|-----------|
| GET | `/manifest.webmanifest` | PWA manifest JSON |
| GET | `/sw.js` | Service Worker |

---

## 6. Trakteer Webhook System

### Flow
1. User membuat PendingPayment (order_id: `NC-SUB-XXXXXXXX`, `NC-TOKEN-XXXXXXXX`, atau `NC-SEAT-XXXXXXXX`)
2. User diarahkan ke halaman waiting page yang polling `/payment/status` via AJAX
3. User membayar di Trakteer dan menyertakan order_id di supporter_message
4. Trakteer mengirim webhook POST ke `/webhooks/trakteer`
5. Server verifikasi `X-Webhook-Token` header
6. Ekstrak order_id dari `supporter_message` menggunakan regex: `/\b(NC-(?:SUB|TOKEN|SEAT)-[A-Z0-9]{8})\b/i`
7. Cari PendingPayment yang cocok dan masih pending
8. Verifikasi `price >= expected_amount`
9. Fulfill berdasarkan payment_type:
   - **subscription:** Hanya mark paid (user bisa lanjut buat grup)
   - **topup:** Tambah token ke GroupToken, catat GroupTokenContribution
   - **add_seat:** Increment `subscription.included_seats`, catat SubscriptionPayment

### Webhook Headers
```
X-Webhook-Token: {TRAKTEER_WEBHOOK_TOKEN}
Content-Type: application/json
```

### Webhook Payload (from Trakteer)
```json
{
  "id": "...",
  "created_at": "...",
  "type": "...",
  "is_paid": true,
  "is_test": false,
  "supporter_name": "...",
  "supporter_email": "...",
  "supporter_message": "NC-SUB-ABC12345",
  "quantity": 1,
  "unit_price": 25000,
  "price": 25000,
  "net_amount": 22000,
  "order_id": "...",
  "payment_method": "...",
  "media": "..."
}
```

---

## 7. AI System

### Multi-Provider Architecture

Provider dan model dikonfigurasi via `config/ai_models.php` (config file). Setiap grup memilih satu provider + model saat pembuatan.

### AI Mention Triggers

| Provider | Triggers |
|----------|----------|
| OpenAI | `@ai`, `@openai`, `@chatgpt` |
| Claude | `@ai`, `@claude`, `@anthropic` |
| Gemini | `@ai`, `@gemini`, `@google` |

### Credential Resolution (urutan prioritas)
1. **User OAuth token** — dari `users.access_token_encrypted`
2. **User API key** — dari `users.api_key_encrypted` atau `ai_connections.access_token`
3. **Server env key** — dari `.env` (`OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `GEMINI_API_KEY`)

### AI Processing Flow
1. User kirim pesan dengan mention (@ai, @claude, dll)
2. `ChatController@store` membuat `ChatMessageQueue` record (status: queued)
3. `ProcessGroupChatQueueJob` di-dispatch ke Redis queue
4. Job mengambil lock `group-chat-queue:{groupId}` (30 detik)
5. Loop: proses semua queued items satu per satu
6. Cek token balance (minimum 1000 * multiplier)
7. Ambil 16 recent messages sebagai konteks
8. Resolve credentials (user → env fallback)
9. Call provider API:
   - **OpenAI:** `POST https://api.openai.com/v1/chat/completions` (chat format dengan system prompt)
   - **Claude:** `POST https://api.anthropic.com/v1/messages` (plain transcript format)
   - **Gemini:** `POST https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent` (plain transcript format)
10. Consume tokens dari GroupToken (actualTokens * multiplier)
11. Simpan AI response sebagai Message (sender_type: 'ai')
12. Broadcast via `MessageSent` event ke WebSocket

### AI Persona System
- `groups.ai_persona_style` — Instruksi gaya AI (max 1000 chars)
- `groups.ai_persona_guardrails` — Batasan/larangan AI (max 1000 chars)
- Disisipkan ke system prompt / plain transcript

### Token Consumption
```
effectiveTokens = ceil(actualTokens * multiplier)
```
Jika saldo tidak cukup: response tetap dikirim + warning message, saldo dipaksa ke 0.

---

## 8. Realtime System

### Tech Stack
- **Server:** Laravel Reverb (built-in WebSocket server)
- **Client:** Laravel Echo + Pusher.js
- **Protocol:** Private channels (authenticated)

### Event: `MessageSent`
- Implements `ShouldBroadcastNow` (broadcast langsung, bukan via queue)
- Channel: `private-group.{groupId}`
- Event name: `message.sent`

**Broadcast Payload:**
```json
{
  "group_id": 1,
  "message": {
    "id": 123,
    "message_type": "text",
    "sender_type": "user",
    "sender_id": 1,
    "sender_name": "John",
    "content": "Hello!",
    "attachment_url": "/groups/1/messages/123/attachment",
    "attachment_mime": "image/jpeg",
    "attachment_original_name": "photo.jpg",
    "created_at": "2026-03-25T10:00:00+00:00"
  }
}
```

### Reverb Configuration
- Internal: `reverb:8080` (HTTP, container-to-container)
- External: Via Nginx reverse proxy ke `wss://normchat.technocrats.studio:443`

---

## 9. RBAC System

### Architecture
- **Roles table** — Predefined roles: `owner`, `admin`, `member`
- **Permissions table** — Granular permission keys
- **role_permissions** — Many-to-many pivot
- **group_members.role_id** — Role assignment per member per grup

### Enforcement Layers
1. **EnsureGroupPermission middleware** — Route-level check. Owner always passes. Others checked via `role.permissions.contains('key', permissionKey)`.
2. **GroupPolicy** — Laravel Policy untuk controller authorization via `$this->authorize()`.

### Policy Methods
| Method | Akses |
|--------|-------|
| `view()` | Owner atau active member |
| `chat()` | Same as view |
| `manageSettings()` | Owner atau has `manage_billing` permission |
| `exportChat()` | Owner atau has `export_chat` permission |
| `createBackup()` | Owner atau has `recover_history` permission |
| `restoreBackup()` | Same as createBackup |
| `manageMembers()` | Owner atau has `remove_member` permission |
| `promoteMember()` | **Owner only** |

---

## 10. Export & Backup System

### Export (PDF/DOCX)
- **Job:** `GenerateExportJob` — Tapi dipanggil **synchronous** di `SettingsController@createExport` via `(new GenerateExportJob($export->id))->handle()`
- **PDF:** DomPDF rendering
- **DOCX:** PhpWord generation
- **Storage:** Disk `normchat_exports`
- **Download:** Response langsung ke user setelah generate

### Backup (JSON Snapshot)
- **Job:** `CreateBackupSnapshotJob` — Dispatched ke queue (async)
- **Content:** JSON snapshot berisi `messages` array + `members` array
- **Storage:** Disk `normchat_backups`

### Restore
- **Transaction:** Force delete semua messages, recreate dari snapshot
- **Members:** Restore role & status, skip yang user-nya sudah tidak ada
- **Owner:** Selalu di-ensure tetap sebagai member
- **Logging:** RecoveryLog + AuditLog record

---

## 11. Patungan (Crowdfunding) System

Saat member join grup via share link, mereka wajib bayar:
1. **Seat fee:** Rp 4.000 (fixed)
2. **Patungan:** Minimum Rp 10.000 (user tentukan sendiri)

### Kalkulasi
```
normkredit = patungan_amount / 1000
tokenAmount = normkredit * 1000
totalPay = patungan_amount + seat_fee
```

### Flow
1. User buka `/join/{shareId}`
2. Masukkan password grup + jumlah patungan
3. Server verifikasi password (bcrypt)
4. Tambah token ke GroupToken
5. Catat GroupTokenContribution (source: 'patungan')
6. Increment `subscription.included_seats`
7. Buat GroupMember (role: 'member')
8. Redirect ke chat

> **Note:** Patungan saat ini langsung tanpa verifikasi pembayaran — tidak melalui Trakteer webhook. Ini berbeda dari subscription/topup/add_seat flow.

---

## 12. Authentication System

### Google SSO Only
- Tidak ada password-based auth
- Implementasi manual (bukan Socialite driver)
- CSRF protection via state parameter

### Flow
1. User klik "Login dengan Google"
2. Redirect ke `https://accounts.google.com/o/oauth2/v2/auth` dengan scope: `openid profile email`
3. Callback: validate state, exchange code for token via `https://oauth2.googleapis.com/token`
4. Fetch profile via `https://www.googleapis.com/oauth2/v3/userinfo`
5. Create/update user (match by provider_user_id atau email)
6. Store encrypted OAuth tokens
7. Login + remember
8. Redirect berdasarkan subscription status:
   - Ada paid pending → `/groups/create`
   - Ada active subscription → `/groups`
   - Belum ada → `/payment/detail`

---

## 13. Pages & Views

| View Path | Deskripsi |
|-----------|-----------|
| `marketing/landing.blade.php` | Landing page marketing untuk non-auth users |
| `auth/landing.blade.php` | Halaman login (Google SSO button) |
| `auth/api-key-connect.blade.php` | Form koneksi API key AI |
| `subscription/pricing.blade.php` | Halaman pricing plans |
| `subscription/payment-detail.blade.php` | Detail pembayaran subscription |
| `subscription/payment-waiting.blade.php` | Waiting page (polling payment status) |
| `subscription/payment-success.blade.php` | Sukses bayar subscription |
| `subscription/buy-tokens.blade.php` | Form beli normkredit |
| `subscription/buy-tokens-success.blade.php` | Sukses beli normkredit |
| `subscription/add-seat.blade.php` | Form tambah seat |
| `subscription/add-seat-success.blade.php` | Sukses tambah seat |
| `subscription/add-seat-payments.blade.php` | Riwayat pembayaran seat |
| `groups/index.blade.php` | Daftar grup user |
| `groups/create.blade.php` | Form buat grup baru (pilih nama, password, AI provider/model) |
| `groups/join.blade.php` | Form join grup via share link (password + patungan) |
| `chat/show.blade.php` | Chat room utama (realtime messages, mention, attachments) |
| `settings/show.blade.php` | Dashboard settings grup (token info, audit logs) |
| `settings/history-export.blade.php` | Riwayat export & backup |
| `settings/ai-persona.blade.php` | Editor AI persona (style + guardrails) |
| `settings/seat-management.blade.php` | Manajemen seat subscription |
| `profile/show.blade.php` | Profil user + daftar subscriptions |
| `profile/security.blade.php` | Halaman keamanan account |
| `layouts/app.blade.php` | Layout utama aplikasi |
| `pwa/sw.blade.php` | Service Worker untuk PWA |

**Total: 24 views**

---

## 14. User Flows

### 14.1 Flow: Registrasi + Buat Grup Pertama
1. Buka `/` → Landing page
2. Klik login → `/login` → Klik "Login dengan Google"
3. Redirect ke Google → Izinkan → Callback
4. Redirect ke `/payment/detail` (belum ada subscription)
5. Pilih plan → Create PendingPayment → Redirect ke `/payment/waiting`
6. Bayar di Trakteer dengan order_id di pesan → Webhook fulfill
7. Polling `/payment/status` → status: paid → Redirect ke `/payment/success`
8. Klik "Buat Grup" → `/groups/create`
9. Isi nama, deskripsi, password, pilih AI provider + model
10. Submit → Grup dibuat + Subscription + GroupToken (10 normkredit) → Redirect ke chat

### 14.2 Flow: Join Grup (Patungan)
1. Dapat share link: `/join/{shareId}`
2. Login dulu jika belum
3. Isi password grup + jumlah patungan (min Rp 10.000)
4. Submit → Token ditambahkan ke grup, seat bertambah
5. Redirect ke chat sebagai member

### 14.3 Flow: Chat + AI
1. Buka `/groups/{group}/chat`
2. Ketik pesan, contoh: "Halo @ai apa kabar?"
3. Submit → Message saved → Broadcast via WebSocket
4. ChatMessageQueue created → ProcessGroupChatQueueJob dispatched
5. Job cek mention → resolve credentials → call AI API
6. AI response saved → Broadcast via WebSocket
7. Token dikonsumsi dari saldo grup

### 14.4 Flow: Top-up Normkredit
1. Buka `/tokens/buy`
2. Pilih grup target + jumlah (by credits atau by price)
3. Submit → PendingPayment created → Redirect ke waiting
4. Bayar di Trakteer → Webhook fulfill → Token ditambahkan ke grup
5. Polling detect paid → Redirect ke success page

### 14.5 Flow: Export Chat
1. Buka settings → History Export
2. Pilih format (PDF/DOCX)
3. Klik Export → `GenerateExportJob` dijalankan synchronous
4. Download file langsung

### 14.6 Flow: Backup & Restore
1. Buka settings → History Export → Klik "Buat Backup"
2. Job dispatched ke queue → JSON snapshot dibuat
3. Untuk restore: pilih backup → isi alasan → Submit
4. Dalam transaction: hapus semua message, recreate dari snapshot
5. Recovery log dicatat

---

## 15. Business Model & Pricing

| Item | Harga | Deskripsi |
|------|-------|-----------|
| **Subscription** | Rp 25.000 | Per grup, includes 2 seats + 10 normkredit |
| **Extra Seat** | Rp 4.000 / seat | Beli tambahan seat |
| **Normkredit Top-up** | Rp 1.000 / normkredit | 1 normkredit = 1.000 token |
| **Patungan Join** | Min Rp 10.000 + Rp 4.000 seat | Member baru wajib kontribusi |

### Token Economics
- 1 normkredit = 1.000 token = Rp 1.000
- Token dikonsumsi per AI request: `actualTokens * modelMultiplier`
- Model multiplier dikonfigurasi per model di `config/ai_models.php`

---

## 16. Environment Variables

Key environment variables dari `docker-compose.yml` dan `.env.example`:

| Variable | Default | Deskripsi |
|----------|---------|-----------|
| `APP_URL` | `https://normchat.technocrats.studio` | Base URL aplikasi |
| `DB_CONNECTION` | `pgsql` | Database driver |
| `DB_HOST` | `postgres` | Database host |
| `DB_DATABASE` | `normchat` | Nama database |
| `SESSION_DRIVER` | `redis` | Session storage |
| `CACHE_STORE` | `redis` | Cache storage |
| `QUEUE_CONNECTION` | `redis` | Queue driver |
| `BROADCAST_CONNECTION` | `reverb` | Broadcasting driver |
| `REVERB_APP_ID` | `normchat` | Reverb app identifier |
| `REVERB_APP_KEY` | `normchat-key` | Reverb authentication key |
| `REVERB_APP_SECRET` | - | Reverb secret (required) |
| `REVERB_HOST` | `reverb` | Internal Reverb host |
| `REVERB_PORT` | `8080` | Internal Reverb port |
| `VITE_REVERB_HOST` | `normchat.technocrats.studio` | Public WebSocket host |
| `VITE_REVERB_PORT` | `443` | Public WebSocket port |
| `VITE_REVERB_SCHEME` | `https` | Public WebSocket scheme |
| `NORMCHAT_STORAGE_ROOT` | `/opt/normchat/storage` | Storage path for attachments/exports/backups |
| `FILESYSTEM_DISK` | `normchat_attachments` | Default filesystem disk |
| `TRAKTEER_WEBHOOK_TOKEN` | - | Token verifikasi webhook |
| `TRAKTEER_PAGE_URL` | `https://trakteer.id/normchat` | URL halaman Trakteer |
| `GOOGLE_CLIENT_ID` | - | Google OAuth client ID |
| `GOOGLE_CLIENT_SECRET` | - | Google OAuth client secret |
| `GOOGLE_REDIRECT` | - | Google OAuth callback URL |

---

## 17. Security Measures

### Authentication
- Google SSO dengan CSRF state validation
- OAuth tokens disimpan terenkripsi via Laravel `Crypt`
- API keys disimpan terenkripsi
- Session di Redis dengan secure cookie

### Authorization
- RBAC via roles + permissions + middleware + policy
- Owner bypass di semua permission check
- Group-scoped authorization (member hanya akses grup sendiri)

### API Protection
- Trakteer webhook verifikasi via `X-Webhook-Token` + `hash_equals()`
- CSRF protection pada semua form (kecuali webhook)
- Cache lock pada chat submission (prevent race condition)
- Cache lock pada AI queue processing (prevent duplicate)

### Input Validation
- Request validation pada semua controller methods
- File upload: max 15MB, whitelist MIME types
- Message content: max 3000 chars
- Password grup: min 4, max 100 chars
