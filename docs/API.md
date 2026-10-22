# Normchat API Documentation

Base URL: `https://normchat.twenti.studio/api`

Payments are processed in Rupiah directly through **Midtrans Snap** (`NORMCHAT_PAYMENT_GATEWAY=midtrans`). Login uses **Google**.

> **Interdotz integration is disabled** (`NORMCHAT_INTERDOTZ_ENABLED=false`): SSO, payments via Interdotz, mailbox, and every `/api/webhooks/interdotz/*`, `/api/product/*`, `/api/transactions*` endpoint below respond `404` until the flag is turned back on. The code is kept intact.

---

## POST `/api/webhooks/midtrans`

Midtrans HTTP notification (set as *Payment Notification URL* in the Midtrans dashboard).

- Verified with `signature_key = SHA512(order_id + status_code + gross_amount + MIDTRANS_SERVER_KEY)`; invalid → `403 {"message": "invalid signature"}`.
- `settlement`, or `capture` with `fraud_status` `accept`/absent → order paid; `deny`/`cancel`/`expire`/`failure` → failed; anything else stays pending.
- Idempotent per `order_id` (safe to receive multiple times).

**Response:** `{"message": "processed", "status": "paid" | "failed" | "pending"}` · unknown order → `404`.

---

## Authentication

API endpoints use either:
- **Client Token**: Obtained via Interdotz `POST /api/client/auth` using Normchat's `clientId` and `clientSecret`
- **Webhook**: No auth required — validated by payload signature

---

## Webhooks (Interdotz → Normchat)

### POST `/api/webhooks/interdotz/payment`

Called when a Midtrans payment (via Interdotz) is settled.

**Request Body:**
```json
{
  "reference_id": "nc_pay_abc123",
  "status": "paid",
  "payment_method": "gopay",
  "gateway_transaction_id": "gw_tx_001",
  "paid_at": "2026-04-12T20:00:00Z"
}
```

**Status Values:** `paid`, `settlement`, `capture`, `expire`, `cancel`, `deny`, `failure`

**Response:**
```json
{
  "message": "processed",
  "status": "paid"
}
```

---

## Product Data (Interdotz → Normchat)

### GET `/api/product/data`

Returns user's Normchat profile and data for display on Interdotz product page.
Called by Interdotz via `GET /api/user/products/{clientId}/data`.

**Query Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `userId` | string | Yes | Interdotz user ID |

**Response:**
```json
{
  "profile": {
    "name": "John Doe",
    "email": "john@example.com",
    "avatar_url": "https://...",
    "joined_at": "2026-01-15T10:00:00Z",
    "total_groups_owned": 2,
    "total_groups_member": 3
  },
  "sections": {
    "groups": [
      {
        "id": 5,
        "name": "Tim Marketing",
        "role": "owner",
        "normkredit_remaining": 8.5,
        "created_at": "2026-03-01T08:00:00Z"
      }
    ]
  },
  "topups": [],
  "transactions": [
    {
      "id": 1,
      "group_id": 5,
      "source": "group_creation_midtrans",
      "normkredits": 300,
      "idr_paid": 30000,
      "reference": "txn_abc123",
      "created_at": "2026-03-01T08:00:00Z"
    }
  ]
}
```

---

### GET `/api/product/topup-packages`

Top-up happens inside Normchat in packages of 125 NK paid via QRIS (see *Pricing & Conversion*). This Interdotz endpoint is kept for compatibility and always returns an empty list.

**Response:**
```json
{
  "packages": []
}
```

---

## Transactions

### GET `/api/transactions`

Returns paginated transaction history for a user.

**Query Parameters:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `userId` | string | — | Interdotz user ID (required) |
| `page` | int | 0 | Page number (0-based) |
| `size` | int | 20 | Items per page (max 50) |

**Response:**
```json
{
  "transactions": [
    {
      "id": "1",
      "type": "CHARGE",
      "status": "SUCCESS",
      "reference_type": "group_creation_midtrans",
      "reference_id": "txn_abc123",
      "amount": 30000,
      "currency": "IDR",
      "normkredits": 300,
      "group_id": 5,
      "created_at": "2026-03-01T08:00:00Z"
    }
  ],
  "current_page": 0,
  "total_pages": 1,
  "total_items": 1
}
```

### GET `/api/transactions/{id}`

Returns a single transaction detail.

**Response:**
```json
{
  "id": "1",
  "type": "CHARGE",
  "status": "SUCCESS",
  "reference_type": "group_creation_midtrans",
  "reference_id": "txn_abc123",
  "amount": 30000,
  "currency": "IDR",
  "normkredits": 300,
  "group_id": 5,
  "user_id": 1,
  "created_at": "2026-03-01T08:00:00Z"
}
```

---

## Normchat → Interdotz Integration Flow

### 1. Group creation, patungan, and top-up (Midtrans)

```
Group creation = 250 NK; patungan/top-up = N × 125 NK (only when NORMCHAT_PAYMENT_GATEWAY=interdotz)
  → Normchat creates a PendingPayment (order NCGRP-/NCJOIN-/NCTOPUP-...) with metadata.credits
  → Normchat calls Interdotz createMidtransPayment (amount = credits × Rp120 + 0,7% QRIS fee)
  → User pays on Midtrans, returns to /groups/payment-callback?order=...
  → Interdotz calls POST /api/webhooks/interdotz/payment (or the callback page polls status)
  → Normchat activates the group / membership and adds metadata.credits to the group balance
```

### 2. Transaction Log Sync

```
Interdotz fetches GET /api/product/data?userId={interdotzUserId}
  → Returns profile, groups, topup packages, and transaction history
  → Displayed on Interdotz product profile page

Interdotz fetches GET /api/transactions?userId={interdotzUserId}
  → Returns full paginated transaction history
```

---

## Pricing & Conversion

Defaults live in `config/normchat.php`; superadmins override them at `/superadmin`.

| Item | Default |
|------|---------|
| 1 Normkredit | Rp120 |
| Group creation | 250 NK = Rp30.000 + QRIS fee 0,7% → **Rp30.210** |
| Patungan / top-up | packages of 125 NK = Rp15.000 + 0,7% → **Rp15.105** per package |
| Payment method | QRIS only (Snap `enabled_payments: ["other_qris"]`), one line item incl. fee |

The webhook only grants Normkredit when the paid `gross_amount` ≥ the billed total (incl. fee); otherwise the order is marked `failed` with `gateway_status: amount_mismatch`.

AI usage is charged per request from the real OpenAI cost:

```
NK = (input × input price + cached input × cached price + output × output price) [USD]
     × model multiplier × NORMCHAT_USD_TO_IDR ÷ Rupiah per NK
```

| Category | Model | Multiplier |
|----------|-------|------------|
| Cepat | gpt-6-luna | ×2 |
| Smart (default) | gpt-6.1-sol | ×2,5 |
| Advanced | gpt-6-astra | ×3 |

Rounded up to 0.01 NK. The answer length adapts to the requester's usable credits
(`max_completion_tokens` shrinks instead of refusing), so expensive categories stay usable with small balances.

**Images stay inside the chosen category.** The category model writes the image instructions (JSON plan),
then the category's internal image engine draws or edits the picture (`/v1/images/generations`, or
`/v1/images/edits` when the user attaches/replies to an image to edit):

| Category | Planner | Image engine |
|----------|---------|--------------|
| Cepat | gpt-6-luna | gpt-image-2.5-flare |
| Smart | gpt-6.1-sol | flare; gpt-image-2.5-sunburst when the plan says `precise` |
| Advanced | gpt-6-astra | gpt-image-2.5-sunburst |

Both components are billed from real usage at the category multiplier. The Image API may omit `usage`;
then the engine's `fallback_*` estimates from `config/normchat.php` are used and the log line is marked `estimated`.
Audio transcription (gpt-4o-mini-transcribe) uses `media_multiplier` (×2). Every charge is recorded in `ai_usage_logs`.

---

## Credit Sharing Rules

The group balance is shown as one number, but it is split into:

- **Shared credits** — usable by every member (optionally capped per member per day by the owner, `groups.member_daily_shared_limit`; the owner is exempt).
- **Protected credits** — owned by one person; only they can spend them. They can move protected → shared at any time, never the reverse.

Every purchase (group creation, patungan, top-up) has a "share to group" toggle, on by default at **50%**; the rest becomes the buyer's protected credits.
When someone asks the AI, an estimate is **held** first (`credit_holds`) from the credits *they* may use — shared first by default (per-person setting), then their own protected credits — and settled against actual usage when the reply finishes (unused hold goes back to the pool it came from). Other people's protected credits are never touched.

---

## Interdotz API Endpoints Used by Normchat

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/client/auth` | POST | Get client access token |
| Midtrans payment (Interdotz SDK) | POST | Create a Rupiah payment |
| Midtrans payment status (Interdotz SDK) | GET | Sync payment status on callback |

---

## Error Responses

All error responses follow this format:

```json
{
  "message": "error description"
}
```

| HTTP Code | Description |
|-----------|-------------|
| 400 | Bad request / missing required fields |
| 404 | Resource not found |
| 500 | Internal server error |
