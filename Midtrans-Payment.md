# Interdotz Laravel SDK

Laravel wrapper resmi untuk [Interdotz PHP SDK](../sdk-php/README.md). Menyediakan ServiceProvider, Facade, dan config file siap pakai — integrasi SSO, auth, dan payment Dots Unit tanpa boilerplate.

---

## Daftar Isi

1. [Tentang Package Ini](#tentang-package-ini)
2. [Requirements](#requirements)
3. [Installation](#installation)
4. [Setup](#setup)
5. [Penggunaan — Facade vs Dependency Injection](#penggunaan--facade-vs-dependency-injection)
6. [Implementasi Lengkap — SSO](#implementasi-lengkap--sso)
7. [Implementasi Lengkap — Direct Charge (Dots Unit)](#implementasi-lengkap--direct-charge-dots-unit)
8. [Implementasi Lengkap — Charge dengan Konfirmasi (Dots Unit)](#implementasi-lengkap--charge-dengan-konfirmasi-dots-unit)
9. [Implementasi Lengkap — Midtrans Payment](#implementasi-lengkap--midtrans-payment)
10. [Implementasi Lengkap — Webhook](#implementasi-lengkap--webhook)
11. [Mailbox UI](#mailbox-ui)
12. [Config Reference](#config-reference)
13. [Changelog](#changelog)

---

## Tentang Package Ini

Package ini adalah **Laravel-specific wrapper** di atas `interdotz/sdk-php`. Perbedaannya:

| | `interdotz/sdk-php` | `interdotz/sdk-laravel` |
|---|---|---|
| Target | Framework-agnostic PHP | Laravel 11+ |
| Cara pakai | Manual instantiation | Facade + DI via Service Container |
| Config | Manual | `config/interdotz.php` + `.env` |
| Auto-discovery | Tidak | Ya — cukup `composer require` |

Jika project kamu Laravel, gunakan package ini. Jika bukan, gunakan `interdotz/sdk-php` langsung.

---

## Requirements

- PHP **^8.2**
- Laravel **^11.0**
- `interdotz/sdk-php` **^1.0** (otomatis terinstall sebagai dependency)

---

## Installation

### Via Composer (Public Packagist)

```bash
composer require interdotz/sdk-laravel
```

### Via Private Git Repository

Tambahkan di `composer.json` project Laravel kamu:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/technocrats-studio886/interdotz-sdk-php"
        },
        {
            "type": "vcs",
            "url": "https://github.com/technocrats-studio886/interdotz-sdk-laravel"
        }
    ],
    "require": {
        "interdotz/sdk-laravel": "^1.0"
    }
}
```

```bash
composer install
```

> `interdotz/sdk-php` otomatis ikut terinstall — kamu tidak perlu require keduanya secara manual.

---

## Setup

### 1. Publish Config

```bash
php artisan vendor:publish --tag=interdotz-config
```

File `config/interdotz.php` akan dibuat di project kamu.

### 2. Tambahkan Environment Variables

```env
INTERDOTZ_CLIENT_ID=your-client-id
INTERDOTZ_CLIENT_SECRET=your-client-secret
```

Hanya dua variable ini yang diperlukan. URL API dan SSO sudah hardcode di dalam SDK.

### 3. Done

ServiceProvider dan Facade sudah auto-registered via Laravel package discovery. Tidak perlu tambahkan apapun ke `config/app.php`.

---

## Penggunaan — Facade vs Dependency Injection

Ada dua cara menggunakan SDK ini. Keduanya mengakses instance yang sama dari Service Container.

### Facade

Cocok untuk penggunaan cepat di controller atau route closure.

```php
use Interdotz\Laravel\Facades\Interdotz;

// SSO
$loginUrl = Interdotz::sso()->getLoginUrl(route('sso.callback'));
$tokens   = Interdotz::sso()->handleCallback($request->query());

// Auth
$token = Interdotz::auth()->authenticate($user->interdotz_id);

// Payment
$charge  = Interdotz::payment()->directCharge(...);
$request = Interdotz::payment()->createChargeRequest(...);
$balance = Interdotz::payment()->getBalance(...);

// Webhook
$payload = Interdotz::webhook()->parse($request->getContent());
```

### Dependency Injection

Cocok untuk Service class, lebih mudah di-test karena bisa di-mock di unit test.

```php
use Interdotz\Sdk\InterdotzClient;

class PaymentService
{
    public function __construct(
        private readonly InterdotzClient $interdotz,
    ) {}

    public function charge(User $user, int $amount, string $orderId): void
    {
        $token = $this->interdotz->auth()->authenticate($user->interdotz_id);

        $this->interdotz->payment()->directCharge(
            accessToken:   $token->accessToken,
            amount:        $amount,
            referenceType: 'PURCHASE',
            referenceId:   $orderId,
        );
    }
}
```

Laravel akan otomatis inject `InterdotzClient` yang sudah dikonfigurasi dari `config/interdotz.php`.

---

## Implementasi Lengkap — SSO

### Routes

```php
// routes/web.php
Route::get('/auth/sso/login',    [SsoController::class, 'redirectLogin'])->name('sso.login');
Route::get('/auth/sso/register', [SsoController::class, 'redirectRegister'])->name('sso.register');
Route::get('/auth/callback',     [SsoController::class, 'callback'])->name('sso.callback');
```

### Controller

```php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Interdotz\Laravel\Facades\Interdotz;
use Interdotz\Sdk\Exceptions\AuthException;

class SsoController extends Controller
{
    public function redirectLogin()
    {
        $url = Interdotz::sso()->getLoginUrl(
            redirectUrl: route('sso.callback'),
        );

        return redirect($url);
    }

    public function redirectRegister()
    {
        $url = Interdotz::sso()->getRegisterUrl(
            redirectUrl: route('sso.callback'),
            state:       'dashboard',
        );

        return redirect($url);
    }

    public function callback(Request $request)
    {
        try {
            $tokens = Interdotz::sso()->handleCallback($request->query());

            // Simpan token ke session untuk digunakan di payment operations
            session([
                'interdotz_access_token'  => $tokens->accessToken,
                'interdotz_refresh_token' => $tokens->refreshToken,
            ]);

            $destination = $tokens->state === 'dashboard' ? '/dashboard' : '/';

            return redirect($destination);

        } catch (AuthException $e) {
            return redirect()->route('login')
                ->with('error', 'Login via Interdotz gagal, silakan coba lagi.');
        }
    }
}
```

---

## Implementasi Lengkap — Direct Charge (Dots Unit)

Gunakan ini untuk charge DU langsung tanpa interupsi ke user. Cocok untuk pembelian item, penggunaan fitur premium, atau transaksi rutin.

### Service

```php
namespace App\Services;

use App\Models\Order;
use Interdotz\Sdk\DTOs\Payment\ChargeResponse;
use Interdotz\Sdk\Exceptions\InsufficientBalanceException;
use Interdotz\Sdk\InterdotzClient;

class OrderPaymentService
{
    public function __construct(
        private readonly InterdotzClient $interdotz,
    ) {}

    public function pay(Order $order): ChargeResponse
    {
        $token = $this->interdotz->auth()->authenticate(
            userId: $order->user->interdotz_id,
        );

        return $this->interdotz->payment()->directCharge(
            accessToken:   $token->accessToken,
            amount:        $order->du_amount,
            referenceType: 'PURCHASE',
            referenceId:   (string) $order->id,
        );
    }
}
```

### Controller

```php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderPaymentService;
use Illuminate\Http\Request;
use Interdotz\Sdk\Exceptions\InsufficientBalanceException;
use Interdotz\Sdk\Exceptions\PaymentException;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderPaymentService $paymentService,
    ) {}

    public function checkout(Request $request)
    {
        $order = Order::findOrFail($request->order_id);

        try {
            $charge = $this->paymentService->pay($order);

            $order->update([
                'status'         => 'paid',
                'transaction_id' => $charge->transactionId,
                'paid_at'        => now(),
            ]);

            return response()->json([
                'message'        => 'Pembayaran berhasil',
                'transaction_id' => $charge->transactionId,
                'balance_after'  => $charge->balanceAfter,
            ]);

        } catch (InsufficientBalanceException) {
            return response()->json([
                'message' => 'Saldo Dots Unit tidak cukup. Silakan topup terlebih dahulu.',
            ], 422);

        } catch (PaymentException $e) {
            return response()->json([
                'message' => $e->getCode() === 409
                    ? 'Transaksi ini sudah pernah diproses.'
                    : 'Pembayaran gagal: ' . $e->getMessage(),
            ], 400);
        }
    }
}
```

---

## Implementasi Lengkap — Charge dengan Konfirmasi (Dots Unit)

Gunakan ini saat kamu ingin user menyetujui transaksi secara eksplisit — misalnya untuk langganan atau pembelian nominal besar.

### Routes

```php
// routes/web.php
Route::post('/checkout/{order}/initiate', [CheckoutController::class, 'initiate'])->name('checkout.initiate');
Route::get('/checkout/callback',          [CheckoutController::class, 'callback'])->name('checkout.callback');
```

### Controller

```php
namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Interdotz\Laravel\Facades\Interdotz;
use Interdotz\Sdk\Exceptions\PaymentException;

class CheckoutController extends Controller
{
    public function initiate(Request $request, Order $order)
    {
        $user = $request->user();

        try {
            $token = Interdotz::auth()->authenticate($user->interdotz_id);

            $chargeRequest = Interdotz::payment()->createChargeRequest(
                accessToken:   $token->accessToken,
                userId:        $user->interdotz_id,
                amount:        $order->du_amount,
                referenceType: 'PURCHASE',
                referenceId:   (string) $order->id,
                callbackUrl:   route('checkout.callback'),
                description:   "Pembayaran Order #{$order->id} — {$order->title}",
                productLogo:   asset('images/logo.png'),
            );

            // Simpan referenceId ke session untuk divalidasi saat callback
            session(['pending_order_id' => $order->id]);

            return redirect($chargeRequest->redirectUrl);

        } catch (PaymentException $e) {
            return back()->with('error', 'Gagal memulai pembayaran: ' . $e->getMessage());
        }
    }

    public function callback(Request $request)
    {
        $status  = $request->query('status');
        $orderId = session('pending_order_id');

        if (!$orderId) {
            return redirect('/')->with('error', 'Sesi pembayaran tidak valid.');
        }

        $order = Order::findOrFail($orderId);
        session()->forget('pending_order_id');

        if ($status === 'rejected') {
            $order->update(['status' => 'cancelled']);

            return redirect()->route('orders.show', $order)
                ->with('error', 'Pembayaran dibatalkan.');
        }

        // Status confirmed — update ke pending dan tunggu webhook
        $order->update(['status' => 'pending_payment']);

        return redirect()->route('orders.show', $order)
            ->with('info', 'Pembayaran sedang diproses...');
    }
}
```

> Jangan fulfill order dari callback redirect. Redirect bisa dimanipulasi — gunakan **webhook** sebagai sumber kebenaran.

---

## Implementasi Lengkap — Midtrans Payment

Pembayaran IDR via Midtrans Snap untuk item apapun — bukan Dots Unit.

### Routes

```php
// routes/web.php
Route::post('/checkout/{order}/pay', [MidtransController::class, 'initiate'])->name('midtrans.initiate');
Route::get('/payment/callback',      [MidtransController::class, 'callback'])->name('midtrans.callback');
```

### Controller

```php
namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Interdotz\Laravel\Facades\Interdotz;
use Interdotz\Sdk\Exceptions\PaymentException;

class MidtransController extends Controller
{
    public function initiate(Request $request, Order $order)
    {
        $user = $request->user();

        try {
            $token = Interdotz::auth()->authenticate($user->interdotz_id);

            $payment = Interdotz::payment()->createMidtransPayment(
                accessToken: $token->accessToken,
                referenceId: (string) $order->id,
                amount:      $order->total_price,
                items:       $order->items->map(fn ($item) => [
                    'id'       => (string) $item->id,
                    'name'     => $item->name,
                    'price'    => $item->price,
                    'quantity' => $item->quantity,
                ])->toArray(),
                callbackUrl: route('midtrans.callback'),
                customer:    [
                    'name'  => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                ],
            );

            // Simpan payment ID untuk tracking
            $order->update(['payment_id' => $payment->id]);

            // Redirect ke halaman pembayaran Midtrans
            return redirect($payment->redirectUrl);

        } catch (PaymentException $e) {
            return back()->with('error', 'Gagal membuat pembayaran: ' . $e->getMessage());
        }
    }

    public function callback(Request $request)
    {
        // Callback dari redirect Midtrans — jangan proses fulfillment di sini
        // Tunggu konfirmasi via webhook
        return redirect()->route('orders.index')
            ->with('info', 'Pembayaran sedang diproses, kami akan konfirmasi segera.');
    }
}
```

> Jangan proses fulfillment dari callback redirect — gunakan **webhook** sebagai sumber kebenaran.

---

## Implementasi Lengkap — Webhook

### Routes

```php
// routes/api.php
Route::post('/webhook/interdotz', [WebhookController::class, 'handle']);
```

Exclude route ini dari CSRF di `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'api/webhook/interdotz',
    ]);
})
```

### Controller

```php
namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Interdotz\Laravel\Facades\Interdotz;
use Interdotz\Sdk\Exceptions\InterdotzException;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        try {
            $payload = Interdotz::webhook()->parse($request->getContent());

            // ── Dots Unit events ──────────────────────────────────────────
            if ($payload->isSuccess()) {
                $order = Order::find($payload->data['referenceId']);

                if ($order && $order->status !== 'paid') {
                    $order->update([
                        'status'         => 'paid',
                        'transaction_id' => $payload->data['transactionId'],
                        'paid_at'        => now(),
                    ]);
                }
            }

            if ($payload->isFailed()) {
                Order::find($payload->data['referenceId'])
                    ?->update(['status' => 'failed']);

                Log::info('DU charge failed', [
                    'reference_id' => $payload->data['referenceId'],
                    'reason'       => $payload->data['errorMessage'],
                ]);
            }

            // ── Midtrans events ───────────────────────────────────────────
            if ($payload->isPaymentSettlement()) {
                $order = Order::find($payload->data['reference_id']);

                if ($order && $order->status !== 'paid') {
                    $order->update([
                        'status'                  => 'paid',
                        'gateway_transaction_id'  => $payload->data['gateway_transaction_id'],
                        'payment_method'          => $payload->data['payment_method'],
                        'paid_at'                 => now(),
                    ]);

                    // Trigger fulfillment, kirim email, dll.
                }
            }

            if ($payload->isPaymentFailed()) {
                Order::find($payload->data['reference_id'])
                    ?->update(['status' => 'failed']);

                Log::info('Midtrans payment failed', [
                    'reference_id' => $payload->data['reference_id'],
                    'status'       => $payload->data['status'],
                ]);
            }

            return response()->json(['message' => 'OK']);

        } catch (InterdotzException $e) {
            Log::warning('Interdotz webhook invalid payload', [
                'error' => $e->getMessage(),
                'body'  => $request->getContent(),
            ]);

            return response()->json(['message' => 'Invalid payload'], 400);
        }
    }
}
```

> Perhatikan **idempotency check** di `handleSuccess` — webhook bisa dikirim lebih dari sekali. Selalu cek apakah order sudah diproses sebelum melakukan update.

---

## Mailbox UI

Package ini menyediakan UI mailbox bawaan yang dapat di-mount ke route manapun. Route mailbox **tidak** didaftarkan secara otomatis — kamu harus memanggil `Interdotz::routes()` secara eksplisit.

### Mendaftarkan Routes

```php
// routes/web.php
use Interdotz\Laravel\Facades\Interdotz;

Interdotz::routes();
```

Dengan konfigurasi default, route berikut akan tersedia:

| Method | URL | Deskripsi |
|--------|-----|-----------|
| `GET` | `/interdotz/mailbox/inbox` | Daftar pesan masuk |
| `GET` | `/interdotz/mailbox/sent` | Daftar pesan terkirim |
| `GET` | `/interdotz/mailbox/{mailId}` | Detail pesan |
| `POST` | `/interdotz/mailbox/send` | Kirim pesan baru |
| `PUT` | `/interdotz/mailbox/{mailId}/read` | Tandai satu pesan sebagai dibaca |
| `PUT` | `/interdotz/mailbox/read-all` | Tandai semua pesan sebagai dibaca |
| `DELETE` | `/interdotz/mailbox/{mailId}` | Hapus pesan |

### Kustomisasi Prefix & Middleware

```php
Interdotz::routes([
    'prefix'     => 'my-app/mailbox',   // default: 'interdotz'
    'middleware' => ['web', 'auth'],    // default: ['web']
]);
```

Contoh di atas akan menghasilkan route seperti `/my-app/mailbox/inbox`.

---

## Config Reference

File: `config/interdotz.php`

| Key | Env Variable | Default | Deskripsi |
|-----|-------------|---------|-----------|
| `client_id` | `INTERDOTZ_CLIENT_ID` | `null` | ID client dari admin Interdotz |
| `client_secret` | `INTERDOTZ_CLIENT_SECRET` | `null` | Secret key client |
| `http.timeout` | `INTERDOTZ_TIMEOUT` | `10` | Timeout HTTP request dalam detik |

### Environment Variables

```env
# Wajib
INTERDOTZ_CLIENT_ID=your-client-id
INTERDOTZ_CLIENT_SECRET=your-client-secret

# Opsional
INTERDOTZ_TIMEOUT=10
```

---

## Changelog

### v0.2.0
- Tambah Mailbox UI dengan route bawaan
- `Interdotz::routes()` — daftarkan route mailbox secara eksplisit dengan opsi `prefix` dan `middleware`
- Route mailbox tidak lagi didaftarkan otomatis oleh ServiceProvider

### v0.1.0
- Initial release
- `InterdotzServiceProvider` dengan auto-discovery
- Facade `Interdotz` dengan method `auth()`, `payment()`, `sso()`, `webhook()`
- Payment DU: `directCharge()`, `createChargeRequest()`, `getBalance()`
- Payment Midtrans: `createMidtransPayment()`, `getMidtransPaymentStatus()`
- Webhook: support event DU (`charge.success`, `charge.failed`) dan Midtrans (`payment.settlement`, `payment.failed`)
- Publishable config file via `php artisan vendor:publish --tag=interdotz-config`