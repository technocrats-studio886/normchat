# Normchat (Laravel)

Normchat adalah mobile web app group chat manusia + AI (PWA-ready), dengan stack:
- Laravel full-stack
- PostgreSQL
- Laravel Reverb (realtime)
- Redis (queue/background + cache/session)
- Local VPS storage (`/opt/normchat/storage`)

## Infrastruktur Default

Konfigurasi template sudah diarahkan ke requirement produksi di [.env.example](.env.example):
- `DB_CONNECTION=pgsql`
- `BROADCAST_CONNECTION=reverb`
- `QUEUE_CONNECTION=redis`
- `CACHE_STORE=redis`
- `SESSION_DRIVER=redis`
- `NORMCHAT_STORAGE_ROOT=/opt/normchat/storage`

Disk storage yang disiapkan di [config/filesystems.php](config/filesystems.php):
- `normchat_exports` -> `/opt/normchat/storage/exports`
- `normchat_backups` -> `/opt/normchat/storage/backups`
- `normchat_attachments` -> `/opt/normchat/storage/attachments`

## Setup Lokal

1. Install dependency:
```bash
composer install
npm install
```

2. Buat `.env` dari `.env.example`, lalu isi kredensial PostgreSQL, Redis, dan Reverb.

3. Generate key + migrate:
```bash
php artisan key:generate
php artisan migrate --seed
```

4. Build asset frontend:
```bash
npm run build
```

5. Jalankan service aplikasi:
```bash
php artisan serve
php artisan queue:work redis --queue=default
php artisan reverb:start
```

## Akses Testing Langsung

Setelah service jalan, akses endpoint ini dari browser:
- `http://127.0.0.1:8000/login` (login provider)
- `http://127.0.0.1:8000/pricing` (pricing)
- `http://127.0.0.1:8000/groups` (dashboard, wajib login)

Untuk validasi otomatis sebelum QA manual:
```bash
php artisan test
npm run build
```

Catatan logo login:
- Simpan file logo provider ke folder `public/provider-logos/` dengan nama persis:
	- `gemini.png`
	- `claude.png`
	- `chatgpt.png`

## Setup Docker (Direkomendasikan)

Error `Class "Redis" not found` akan hilang jika jalan via Docker karena image PHP sudah mengaktifkan extension `redis`.

1. Siapkan env untuk Docker:
```bash
cp .env.docker.example .env
```

2. Jalankan semua service:
```bash
docker compose up --build
```

3. Akses aplikasi:
- App: `http://127.0.0.1:8000`
- Reverb websocket: `ws://127.0.0.1:18080`

Catatan: port PostgreSQL dan Redis tidak dipublish ke host untuk menghindari bentrok port lokal. Keduanya tetap bisa diakses oleh service internal Docker.

Service yang jalan otomatis di [docker-compose.yml](docker-compose.yml):
- `app` (Laravel web)
- `queue` (Redis queue worker)
- `reverb` (Realtime server)
- `postgres` (Database)
- `redis` (Cache/session/queue)

## Konfigurasi Domain Landing -> App

Use-case target:
- Landing: `https://sysnavia.com/normchat`
- App flow (pricing/login/payment): `https://normchat.sysnavia.com`

Set env berikut saat deploy:

```env
APP_URL=https://normchat.sysnavia.com
MARKETING_URL=https://sysnavia.com
NORMCHAT_APP_URL=https://normchat.sysnavia.com
NORMCHAT_MARKETING_PATH=/normchat
GOOGLE_REDIRECT_URI=https://normchat.sysnavia.com/auth/google/callback
SESSION_DOMAIN=.sysnavia.com
```

Catatan:
- Landing page menghasilkan link `Use Now` langsung ke `NORMCHAT_APP_URL/pricing`.
- Route alias landing juga tersedia di `/normchat`.

### Jika Landing dan App Berada di Repo Berbeda

Bisa connect. Polanya adalah redirect lintas domain biasa, bukan share codebase.

Skema:
1. Repo Landing (`sysnavia.com`) menaruh CTA ke `https://normchat.sysnavia.com/pricing`.
2. Repo App (repo ini) menangani flow auth/payment sampai masuk home.

Hal penting agar aman/stabil:
- Set `GOOGLE_REDIRECT_URI` ke domain app: `https://normchat.sysnavia.com/auth/google/callback`.
- Gunakan `SESSION_DOMAIN=.sysnavia.com` jika ingin cookie lintas subdomain.
- Pastikan CORS/cookie `Secure` aktif saat HTTPS penuh.
- Tidak perlu monorepo; URL antar domain sudah cukup.

## Checklist Docker Sebelum Push/Deploy

Status saat ini:
- `docker-compose.yml` sudah tidak hardcoded localhost; nilai env bisa dioverride dari `.env`.
- Service DB/Redis tidak dipublish ke host (lebih aman, tidak bentrok port lokal).
- Reverb dipublish ke host di `18080` untuk koneksi websocket dari browser.

Checklist produksi:
1. Set `APP_ENV=production` dan `APP_DEBUG=false`.
2. Isi secret production (`APP_KEY`, DB password, Redis password jika dipakai, OAuth keys).
3. Build image + run container:
	- `docker compose up -d --build`
4. Jalankan optimasi di container app:
	- `php artisan config:cache`
	- `php artisan route:cache`
	- `php artisan view:cache`
5. Pasang reverse proxy (Nginx/Traefik/Caddy) untuk:
	- `sysnavia.com/normchat` -> service `app`
	- `normchat.sysnavia.com` -> service `app`

Jika ingin lebih production-grade, ganti built-in PHP server (`php -S`) dengan Nginx + PHP-FPM.

## Final Checklist Siap Push Server

Jalankan urutan berikut sebelum push/deploy:
```bash
php artisan migrate
php artisan test
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Realtime Chat

- Event broadcast: [app/Events/MessageSent.php](app/Events/MessageSent.php)
- Channel auth: [routes/channels.php](routes/channels.php)
- Client Echo: [resources/js/bootstrap.js](resources/js/bootstrap.js)

## Queue Jobs

- Export: [app/Jobs/GenerateExportJob.php](app/Jobs/GenerateExportJob.php)
- Backup snapshot: [app/Jobs/CreateBackupSnapshotJob.php](app/Jobs/CreateBackupSnapshotJob.php)
- Trigger dari settings: [app/Http/Controllers/SettingsController.php](app/Http/Controllers/SettingsController.php)

## Catatan Produksi VPS

Pastikan folder berikut ada dan writable oleh user PHP:
- `/opt/normchat/storage/exports`
- `/opt/normchat/storage/backups`
- `/opt/normchat/storage/attachments`
