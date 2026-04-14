# Normchat API Documentation

Base URL: `https://normchat.technocrats.studio/api`

Normchat is a product of Interdotz. All payments are processed through Interdotz Dots Units (DU).

---

## Authentication

API endpoints use either:
- **Client Token**: Obtained via Interdotz `POST /api/client/auth` using Normchat's `clientId` and `clientSecret`
- **Webhook**: No auth required — validated by payload signature

---

## Webhooks (Interdotz → Normchat)

### POST `/api/webhooks/interdotz/charge`

Called when a Dots Units charge request is confirmed/rejected by user.

**Request Body:**
```json
{
  "status": "CONFIRMED",
  "referenceId": "group_create_5_1713000000",
  "referenceType": "normchat_group_creation",
  "transaction_id": "txn_abc123",
  "amount_charged": 175,
  "balance_after": 825
}
```

**Reference Types:**
| Type | Description | DU Amount |
|------|-------------|-----------|
| `normchat_group_creation` | Group creation payment | 175 DU |
| `normchat_patungan` | Patungan (join group) payment | 25 DU |
| `normchat_topup` | Normkredit top-up | varies |

**Reference ID Formats:**
| Type | Format | Example |
|------|--------|---------|
| Group creation | `group_create_{groupId}_{timestamp}` | `group_create_5_1713000000` |
| Patungan | `patungan_{groupId}_{userId}_{timestamp}` | `patungan_5_12_1713000000` |
| Top-up | `topup_{groupId}_{userId}_{timestamp}` | `topup_5_12_1713000000` |

**Response:**
```json
{
  "message": "processed",
  "reference_id": "group_create_5_1713000000"
}
```

---

### POST `/api/webhooks/interdotz/topup`

Called when user purchases normkredit from Interdotz product profile page.

**Request Body:**
```json
{
  "transaction_id": "txn_xyz789",
  "user_id": "interdotz_user_id",
  "topup_id": "topup_001",
  "package_id": "nk_12",
  "du_charged": 150,
  "group_id": 5,
  "metadata": {
    "group_id": 5
  }
}
```

**Response:**
```json
{
  "message": "topup processed",
  "normkredits": 12,
  "tokens": 30000,
  "group_id": 5
}
```

---

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
  "topups": [
    {
      "id": "nk_12",
      "name": "12 Normkredit",
      "normkredits": 12,
      "tokens": 30000,
      "du_price": 150,
      "description": "12 normkredit (30.000 token AI)"
    }
  ],
  "transactions": [
    {
      "id": 1,
      "group_id": 5,
      "source": "group_creation",
      "normkredits": 12,
      "du_paid": 175,
      "reference": "txn_abc123",
      "created_at": "2026-03-01T08:00:00Z"
    }
  ]
}
```

---

### GET `/api/product/topup-packages`

Returns available normkredit packages for purchase.

**Response:**
```json
{
  "packages": [
    {
      "id": "nk_12",
      "name": "12 Normkredit",
      "normkredits": 12,
      "tokens": 30000,
      "du_price": 150,
      "description": "12 normkredit (30.000 token AI)"
    },
    {
      "id": "nk_24",
      "name": "24 Normkredit",
      "normkredits": 24,
      "tokens": 60000,
      "du_price": 300,
      "description": "24 normkredit (60.000 token AI)"
    },
    {
      "id": "nk_48",
      "name": "48 Normkredit",
      "normkredits": 48,
      "tokens": 120000,
      "du_price": 600,
      "description": "48 normkredit (120.000 token AI)"
    },
    {
      "id": "nk_100",
      "name": "100 Normkredit",
      "normkredits": 100,
      "tokens": 250000,
      "du_price": 1250,
      "description": "100 normkredit (250.000 token AI)"
    }
  ]
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
      "reference_type": "group_creation",
      "reference_id": "txn_abc123",
      "amount": 175,
      "normkredits": 12,
      "tokens": 30000,
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
  "reference_type": "group_creation",
  "reference_id": "txn_abc123",
  "amount": 175,
  "normkredits": 12,
  "tokens": 30000,
  "group_id": 5,
  "user_id": 1,
  "created_at": "2026-03-01T08:00:00Z"
}
```

---

## Normchat → Interdotz Integration Flow

### 1. Group Creation (175 DU)

```
User clicks "Buat Group"
  → Normchat creates group with status "pending_payment"
  → Normchat calls POST /api/client/charge/request on Interdotz
    {
      userId: "interdotz_user_id",
      amount: 175,
      referenceType: "normchat_group_creation",
      referenceId: "group_create_{groupId}_{timestamp}",
      description: "Pembuatan grup Normchat: {groupName} (175 DU)",
      callbackUrl: "https://normchat.technocrats.studio/api/webhooks/interdotz/charge"
    }
  → If chargeRequest returns redirectUrl → user redirected to Interdotz
  → If no redirectUrl → fallback to direct POST /api/client/charge
  → User confirms → Interdotz charges 175 DU
  → Interdotz calls webhook → Normchat activates group + 12 normkredit
```

### 2. Patungan / Join Group (25 DU)

```
User clicks "Bergabung" on join page
  → Normchat calls POST /api/client/charge/request on Interdotz
    {
      userId: "interdotz_user_id",
      amount: 25,
      referenceType: "normchat_patungan",
      referenceId: "patungan_{groupId}_{userId}_{timestamp}",
      description: "Patungan bergabung ke grup: {groupName} (25 DU)",
      callbackUrl: "https://normchat.technocrats.studio/api/webhooks/interdotz/charge"
    }
  → User confirms → 25 DU charged
  → Webhook confirms → user added to group
```

### 3. Normkredit Top-up (150 DU = 12 Normkredit)

```
User selects topup package on Normchat or Interdotz product page
  → Normchat calls POST /api/client/charge/request on Interdotz
    {
      userId: "interdotz_user_id",
      amount: <DU amount based on package>,
      referenceType: "normchat_topup",
      referenceId: "topup_{groupId}_{userId}_{timestamp}",
      description: "Top-up {normkredits} normkredit (... DU)",
      callbackUrl: "https://normchat.technocrats.studio/api/webhooks/interdotz/charge"
    }
  → User confirms → DU charged
  → Webhook confirms → normkredit added to group

OR from Interdotz product page:
  → Interdotz calls POST /api/webhooks/interdotz/topup
  → Normchat converts DU to normkredit and adds to group
```

### 4. Transaction Log Sync

```
Interdotz fetches GET /api/product/data?userId={interdotzUserId}
  → Returns profile, groups, topup packages, and transaction history
  → Displayed on Interdotz product profile page

Interdotz fetches GET /api/transactions?userId={interdotzUserId}
  → Returns full paginated transaction history
```

---

## Pricing & Conversion

| Item | DU Cost | Normkredit | Token AI |
|------|---------|------------|----------|
| Group creation (owner) | 175 DU | 12 NK included | 30.000 token |
| Patungan (join group) | 25 DU | — | — |
| Top-up 12 Normkredit | 150 DU | 12 NK | 30.000 token |
| Top-up 24 Normkredit | 300 DU | 24 NK | 60.000 token |
| Top-up 48 Normkredit | 600 DU | 48 NK | 120.000 token |
| Top-up 100 Normkredit | 1.250 DU | 100 NK | 250.000 token |
| 1 Normkredit | — | 1 NK | 2.500 token |
| Prompt token multiplier | — | — | x1.5 |
| AI image output | — | — | 8.000 token (fixed) |

---

## Interdotz API Endpoints Used by Normchat

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/client/auth` | POST | Get client access token |
| `/api/client/charge` | POST | Direct DU charge (fallback) |
| `/api/client/charge/request` | POST | Request user-approved DU charge |
| `/api/client/balance` | GET | Check user DU balance |

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
