# API Reference — Exchange Product API

> **Base URL:** `http://localhost/api` (Laravel XAMPP)
> **Auth:** JWT Bearer Token (`Authorization: Bearer {token}`)
> **Content-Type:** `application/json`
> **ID Format:** UUID v4 for all resource IDs

---

## Table of Contents

1. [Authentication & Auth Tokens](#1-authentication--auth-tokens)
2. [Public Endpoints](#2-public-endpoints)
3. [User Endpoints](#3-user-endpoints)
4. [Admin Endpoints](#4-admin-endpoints)
5. [Shared Endpoints (admin + user)](#5-shared-endpoints-admin--user)
6. [Error Handling](#6-error-handling)
7. [Pagination & Filtering](#7-pagination--filtering)
8. [File Upload](#8-file-upload)
9. [Migration Mapping Table (Laravel → Go)](#9-migration-mapping-table-laravel--go)
10. [Known Issues & Conflicts](#10-known-issues--conflicts)

---

## 1. Authentication & Auth Tokens

| Parameter | Value |
|-----------|-------|
| Library | `tymon/jwt-auth` |
| Algorithm | HMAC-SHA256 |
| Token TTL | 60 minutes |
| Refresh TTL | 20160 minutes (2 weeks) |
| Blacklist | Enabled |
| Lock Subject | true |

**JWT claims include:** `sub`, `role` (`"user"` or `"admin"`), `exp`, `iat`.

**Token header for authenticated requests:**
```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

---

## 2. Public Endpoints

### 2.1 Health Check
```
GET /
```
Returns API basic info. No auth required.

**Response (200):**
```json
{
  "success": true,
  "message": "Exchange Product API — silakan baca README untuk dokumentasi.",
  "data": null,
  "status": 200
}
```

---

### 2.2 Admin Login
```
POST /auth/systemAdmin/login
```
**Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `email` | string | ✅ | Admin email address |
| `password` | string | ✅ | Password, min 6 chars |

**Rate limit:** 5 attempts per IP per 60s.

**Response (200):**
```json
{
  "success": true,
  "message": "Login admin berhasil.",
  "data": {
    "admin": { "id": "uuid", "name": "string", "email": "string", "status": "active" },
    "access_token": "eyJ...",
    "token_type": "bearer",
    "expires_in": 3600
  },
  "status": 200
}
```

**Error (401):**
```json
{
  "success": false,
  "message": "Login gagal.",
  "error": { "auth": ["Email atau password salah."] },
  "status": 401
}
```

---

### 2.3 User Register
```
POST /auth/user/register
```
**Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | ✅ | Max 255 |
| `email` | string | ✅ | Unique, valid email |
| `password` | string | ✅ | Min 6 chars |
| `phone_number` | string | ❌ | Max 30 |
| `address` | string | ❌ | Free text |

**Response (201):**
```json
{
  "success": true,
  "message": "Registrasi berhasil.",
  "data": {
    "user": {
      "id": "uuid", "name": "John Doe", "email": "john@example.com",
      "phone_number": "08123456789", "address": "Jakarta", "status": "active"
    },
    "access_token": "eyJ...", "token_type": "bearer", "expires_in": 3600
  },
  "status": 201
}
```

---

### 2.4 User Login
```
POST /auth/user/login
```
Same body as admin login (`email`, `password`). Same rate limit (5/60s).

**Response (200):**
```json
{
  "success": true,
  "message": "Login berhasil.",
  "data": {
    "user": {
      "id": "uuid", "name": "string", "email": "string",
      "phone_number": "string|null", "address": "string|null", "status": "active"
    },
    "access_token": "eyJ...", "token_type": "bearer", "expires_in": 3600
  },
  "status": 200
}
```

**Banned/inactive account (403):**
```json
{
  "success": false,
  "message": "Login gagal.",
  "error": { "auth": ["Akun dibanned."] },
  "status": 403
}
```

---

### 2.5 Admin Logout
```
POST /auth/systemAdmin/logout
```
**Middleware:** `role:admin`

Invalidates current JWT token.

**Response (200):**
```json
{ "success": true, "message": "Logout berhasil.", "data": null, "status": 200 }
```

---

### 2.6 User Logout
```
POST /auth/user/logout
```
**Middleware:** `role:user`

**Response (200):**
```json
{ "success": true, "message": "Logout berhasil.", "data": null, "status": 200 }
```

---

### 2.7 Google Auth — Get Redirect URL
```
GET /auth/user/google/redirect
```
Returns Google OAuth redirect URL.

**Response (200):**
```json
{ "url": "https://accounts.google.com/o/oauth2/v2/auth?client_id=..." }
```

---

### 2.8 Google Auth — Handle Callback
```
POST /auth/user/google/callback
```
**Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id_token` | string | ✅ | Google ID token from client |

Internal flow: verifies Google JWKS → decodes token → finds or creates User → returns JWT.

**Response (200):**
```json
{
  "success": true,
  "message": "Google login successful.",
  "data": {
    "user": { "id": "uuid", "name": "string", "email": "string", "phone_number": "string|null", "address": "string|null", "status": "active" },
    "access_token": "eyJ...", "token_type": "bearer", "expires_in": 3600
  },
  "status": 200
}
```

---

### 2.9 Send OTP for Forgot Password
```
POST /auth/forgot-password/send-otp
```
**Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `email` | string | ✅ | Must exist in users table |

**Rate limit:** 3 requests per email per 60s.

**Response (200):**
```json
{ "success": true, "message": "Kode OTP telah dikirim ke email Anda.", "data": null, "status": 200 }
```

---

### 2.10 Verify OTP
```
POST /auth/forgot-password/verify-otp
```
**Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `email` | string | ✅ | Same email as above |
| `otp` | string | ✅ | 6-digit code |

**Response (200):**
```json
{
  "success": true,
  "message": "OTP berhasil diverifikasi. Silakan buat password baru.",
  "data": { "email": "john@example.com" },
  "status": 200
}
```

**Error (422):**
```json
{
  "success": false,
  "message": "Verifikasi OTP gagal.",
  "error": { "otp": ["Kode OTP salah atau telah kedaluwarsa."] },
  "status": 422
}
```

---

### 2.11 Reset Password
```
POST /auth/forgot-password/reset
```
**Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `email` | string | ✅ | Same email |
| `otp` | string | ✅ | 6-digit code |
| `password` | string | ✅ | Min 6 chars |
| `password_confirmation` | string | ✅ | Must match `password` |

**Response (200):**
```json
{ "success": true, "message": "Password berhasil diubah. Silakan login dengan password baru.", "data": null, "status": 200 }
```

---

## 3. User Endpoints

> All require middleware `role:user` (JWT with `role: "user"` claim).

---

### 3.1 Get User Profile
```
GET /user/profile
```
**Response (200):**
```json
{
  "success": true,
  "message": null,
  "data": {
    "id": "uuid", "name": "string", "email": "string",
    "phone_number": "string|null", "address": "string|null", "status": "active"
  },
  "status": 200
}
```

---

### 3.2 Update User Profile
```
PUT /user/profile
```
**Body (all fields optional):**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | ❌ | Max 255 |
| `email` | string | ❌ | Unique |
| `phone_number` | string | ❌ | Max 30 |
| `password` | string | ❌ | Min 6 chars (only if changing) |
| `address` | string | ❌ | Free text |

**Response (200):**
```json
{
  "success": true,
  "message": "Profile berhasil diperbarui.",
  "data": { "id": "uuid", "name": "string", "email": "string", "phone_number": "string|null", "address": "string|null", "status": "active" },
  "status": 200
}
```

---

### 3.3 List Brands (dropdown data)
```
GET /user/brands
```
Returns all brands sorted by name.

**Response (200):**
```json
{
  "success": true,
  "data": [
    { "id": "uuid", "name": "IKEA", "description": "string|null", "image": "string|null", "created_at": "...", "updated_at": "..." },
    ...
  ],
  "status": 200
}
```

---

### 3.4 List Categories (dropdown data)
```
GET /user/categories
```
Same format as brands.

---

### 3.5 Browse Products
```
GET /user/products
```
**Query params:**
| Param | Type | Description |
|-------|------|-------------|
| `per_page` | int | Page size, default 15 |
| `search` | string | Filter by product name (LIKE) |
| `category_id` | uuid | Filter by category |
| `brand_id` | uuid | Filter by brand |

Returns only `status = 'available'` products.

**Response (200):**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": "uuid", "name": "string", "description": "string|null",
        "category_id": "uuid", "brand_id": "uuid", "status": "available",
        "price": 99.99,
        "image1": "string|null", "image2": "string|null", ..., "image6": "string|null",
        "images": ["https://...", ...],
        "category": { "id": "uuid", "name": "string", ... },
        "brand": { "id": "uuid", "name": "string", ... },
        "created_at": "2026-08-01T00:00:00Z",
        "updated_at": "2026-08-01T00:00:00Z"
      }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 100, "last_page": 7 }
  },
  "status": 200
}
```

---

### 3.6 Get Product Detail
```
GET /user/products/{id}
```
**Path param:** `id` (uuid)

**Response (200):** Same structure as items in the list endpoint, but wrapped directly in `data`.

---

### 3.7 List User's Furnitures
```
GET /user/furnitures
```
**Query params:**
| Param | Type | Description |
|-------|------|-------------|
| `per_page` | int | Default 15 |
| `status` | string | Filter by status: `available`, `in_transaction`, `swapped`, `rejected` |

**Response (200):**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": "uuid", "user_id": "uuid", "name": "string", "description": "string|null",
        "status": "available",
        "category_id": "uuid|null", "category_text": "string|null",
        "brand_id": "uuid|null", "brand_text": "string|null",
        "admin_price": "99.99|null",
        "category": { ... }|null, "brand": { ... }|null,
        "images": [ { "id": "uuid", "image_url": "string", "sort_order": 0 } ],
        "created_at": "...", "updated_at": "..."
      }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 5, "last_page": 1 }
  },
  "status": 200
}
```

---

### 3.8 Create Furniture
```
POST /user/furnitures
```
**Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | ✅ | Max 255 |
| `category_id` | uuid | ❌ | Must exist in categories |
| `category_text` | string | ❌ | Fallback if no category_id |
| `brand_id` | uuid | ❌ | Must exist in brands |
| `brand_text` | string | ❌ | Fallback if no brand_id |
| `description` | string | ❌ | Free text |
| `images` | array | ❌ | Uploaded files (multipart) |

**Response (201):**
```json
{ "success": true, "message": "Furniture berhasil ditambahkan.", "data": { ...UserFurnitureResource... }, "status": 201 }
```

---

### 3.9 Get Furniture Detail
```
GET /user/furnitures/{id}
```
Returns furniture only if owned by the authenticated user.

---

### 3.10 Update Furniture
```
PUT /user/furnitures/{id}
```
**Body (all fields optional):**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | ❌ | Max 255 |
| `category_id` | uuid | ❌ | |
| `category_text` | string | ❌ | |
| `brand_id` | uuid | ❌ | |
| `brand_text` | string | ❌ | |
| `description` | string | ❌ | |
| `images` | array | ❌ | New image files to add |
| `remove_images` | array | ❌ | Image IDs to remove (JSON string) |

**Response (200):** Updated `UserFurnitureResource`.

---

### 3.11 Delete Furniture
```
DELETE /user/furnitures/{id}
```
Cannot delete furniture with `status = 'in_transaction'`.

**Response (200):**
```json
{ "success": true, "message": "Furniture berhasil dihapus.", "data": null, "status": 200 }
```

---

### 3.12 Add Furniture Image
```
POST /user/furnitures/{id}/images
```
**Body (multipart):**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `image` | file | ⚠️ | One of `image` or `image_url` required |
| `image_url` | string | ⚠️ | URL string alternative |
| `sort_order` | int | ❌ | Default: max existing + 1 |

Accepted formats: `jpeg`, `png`, `jpg`, `webp`, `gif`, max 10MB.

**Response (201):**
```json
{
  "success": true, "message": "Gambar furniture berhasil ditambahkan.",
  "data": { "id": "uuid", "image_url": "string", "sort_order": 0 },
  "status": 201
}
```

---

### 3.13 Delete Furniture Image
```
DELETE /user/furnitures/{id}/images/{imageId}
```
Deletes both DB record and physical file from `/storage/`.

**Response (200):**
```json
{ "success": true, "message": "Gambar furniture berhasil dihapus.", "data": null, "status": 200 }
```

---

### 3.14 Create Transaction
```
POST /user/transactions
```
**Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `user_furniture_id` | uuid | ✅ | Must exist, belong to user, status=`available` |
| `product_id` | uuid | ✅ | Must exist, status=`available` |

Creates transaction with `status = 'pending'`, sets furniture status to `in_transaction`.

**Response (201):**
```json
{
  "success": true, "message": "Pengajuan tukar berhasil, menunggu verifikasi admin.",
  "data": { ...TransactionResource... },
  "status": 201
}
```

---

### 3.15 List User's Transactions
```
GET /user/transactions
```
**Query params:** `per_page` (default 15), `status` filter.

**Response (200):** Paginated `TransactionResource` collection.

---

### 3.16 Get Transaction Detail
```
GET /user/transactions/{id}
```
Returns transaction only if owned by the authenticated user. Includes nested `userFurniture`, `product`, `admin`, and `messages`.

---

### 3.17 List Notifications
```
GET /user/notifications
```
**Query params:** `per_page` (default 15), `unread_only` (`true`/`1` to filter unread).

**Response (200):** Paginated array of notification objects `{ id, user_id, transaction_id, message, read_at, created_at }`.

---

### 3.18 Mark Notification as Read
```
PATCH /user/notifications/{id}/read
```
Sets `read_at` to current timestamp if not already read.

**Response (200):**
```json
{ "success": true, "message": "Notifikasi ditandai terbaca.", "data": { ... }, "status": 200 }
```

---

## 4. Admin Endpoints

> All require middleware `role:admin` (JWT with `role: "admin"` claim).

---

### 4.1 Admin Dashboard Stats
```
GET /systemAdmin/dashboard
```
**Response (200):**
```json
{
  "success": true,
  "data": {
    "total_users": 150,
    "total_products": 200,
    "total_user_furnitures": 80,
    "total_items": 280,
    "total_transactions": 45,
    "transactions_by_status": {
      "pending": 10, "checking": 5, "approved": 25, "rejected": 5
    }
  },
  "status": 200
}
```

---

### 4.2 CRUD Admins
```
GET    /systemAdmin/admins
POST   /systemAdmin/admins
GET    /systemAdmin/admins/{id}
PUT    /systemAdmin/admins/{id}
DELETE /systemAdmin/admins/{id}
```
Standard RESTful endpoints. Admin cannot delete self (422).

**Store body:** `name` (required), `email` (required, unique), `password` (required, min 6), `status` (optional, `active`/`inactive`).

**Update body:** All fields optional. `password` only if changing.

**List query params:** `per_page` (15), `search` (name/email LIKE), `status`.

---

### 4.3 CRUD Brands
```
GET    /systemAdmin/brands
POST   /systemAdmin/brands
GET    /systemAdmin/brands/{id}
PUT    /systemAdmin/brands/{id}
DELETE /systemAdmin/brands/{id}
```
Cannot delete brand if it has associated products (422).

**Store/Update body:** `name` (required, unique), `description` (optional), `image` (optional, URL string).

**List query params:** `per_page` (15), `search` (name LIKE).

---

### 4.4 CRUD Categories
```
GET    /systemAdmin/categories
POST   /systemAdmin/categories
GET    /systemAdmin/categories/{id}
PUT    /systemAdmin/categories/{id}
DELETE /systemAdmin/categories/{id}
```
Cannot delete category if it has associated products (422).

Same structure as brands.

---

### 4.5 CRUD Products
```
GET    /systemAdmin/products
POST   /systemAdmin/products
GET    /systemAdmin/products/{id}
PUT    /systemAdmin/products/{id}
DELETE /systemAdmin/products/{id}
```
**Store body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `name` | string | ✅ | Max 255 |
| `category_id` | uuid | ✅ | Must exist |
| `brand_id` | uuid | ✅ | Must exist |
| `description` | string | ❌ | |
| `price` | numeric | ❌ | Min 0 |
| `status` | enum | ❌ | `available`/`swapped`/`inactive` |
| `image1`~`image6` | file | ❌ | Each max 2MB |
| `images` | array | ❌ | Alternative multi-file upload |

**Update body:** Same fields, all optional. Supports `cleared_image_slots` JSON array to delete specific image slots.

**List query params:** `per_page` (15), `search`, `category_id`, `brand_id`, `status`.

**Response items include:** `image1`–`image6` (URL strings) AND `images` array (from `product_images` table).

---

### 4.6 Add Product Image
```
POST /systemAdmin/products/{id}/images
```
Same body as user furniture image endpoint.

---

### 4.7 Reorder Product Images
```
PATCH /systemAdmin/products/{id}/images/reorder
```
**Body:**
```json
{
  "images": [
    { "id": "uuid", "sort_order": 0 },
    { "id": "uuid", "sort_order": 1 }
  ]
}
```

---

### 4.8 Delete Product Image
```
DELETE /systemAdmin/products/{id}/images/{imageId}
```

---

### 4.9 List Transactions
```
GET /systemAdmin/transactions
```
**Query params:** `per_page` (15), `status`, `user_id`.

---

### 4.10 Get Transaction Detail
```
GET /systemAdmin/transactions/{id}
```
Includes `messages` relation.

---

### 4.11 Start Checking Transaction
```
PATCH /systemAdmin/transactions/{id}/checking
```
Transitions `pending` → `checking`. Sets `admin_id`. Sends notification to user.

---

### 4.12 Approve Transaction
```
PATCH /systemAdmin/transactions/{id}/approve
```
Transitions `checking` → `approved`. Sets both furniture and product status to `swapped`. Sends notification.

---

### 4.13 Reject Transaction
```
PATCH /systemAdmin/transactions/{id}/reject
```
**Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `reject_reason` | string | ✅ | 3–1000 chars |

Transitions to `rejected`. Returns furniture to `available` status. Sends notification.

---

### 4.14 Set User Furniture Price
```
PATCH /systemAdmin/transactions/{id}/set-price
```
**Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `admin_price` | numeric | ✅ | Min 0 |

Sets `admin_price` on the associated `user_furniture`.

---

## 5. Shared Endpoints (admin + user)

### 5.1 List Transaction Messages
```
GET /systemAdmin/transactions/{id}/messages
GET /user/transactions/{id}/messages
```
**Query params:** `per_page` (default 50).

Admin can access any transaction. User can only access their own.

**Response (200):** Paginated `TransactionMessageResource` collection.

---

### 5.2 Send Transaction Message
```
POST /systemAdmin/transactions/{id}/messages
POST /user/transactions/{id}/messages
```
**Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `message` | string | ✅ | 1–2000 chars |

Only allowed when transaction `status = 'checking'`.

**Response (201):**
```json
{
  "success": true, "message": "Pesan terkirim.",
  "data": { "id": "uuid", "transaction_id": "uuid", "sender_id": "uuid", "sender_type": "admin|user", "message": "string", "created_at": "..." },
  "status": 201
}
```

---

## 6. Error Handling

All error responses follow this format:
```json
{
  "success": false,
  "message": "Human-readable error message.",
  "error": { "field": ["validation message"] },
  "status": 422
}
```

### HTTP Status Code Reference

| Code | Meaning | Typical Cause |
|------|---------|---------------|
| 200 | OK | Successful GET/PUT/PATCH |
| 201 | Created | Successful POST |
| 400 | Bad Request | Missing required field |
| 401 | Unauthorized | Invalid/expired/missing JWT |
| 403 | Forbidden | Wrong role, banned user, access denied |
| 404 | Not Found | Resource doesn't exist |
| 422 | Unprocessable | Validation error, business logic violation |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Server Error | Unexpected exception |

### Common Error Messages

| Error | Status |
|-------|--------|
| "Token tidak valid atau tidak ditemukan." | 401 |
| "Token sudah kadaluarsa." | 401 |
| "Token tidak ditemukan." | 401 |
| "Anda tidak memiliki akses ke resource ini." | 403 |
| "Akun dibanned." | 403 |
| "Akun nonaktif." | 403 |
| "Resource tidak ditemukan." | 404 |
| "Furniture sedang dalam transaksi, tidak dapat dihapus." | 422 |
| "Transaksi sudah pada status final, tidak dapat ditolak." | 422 |

---

## 7. Pagination & Filtering

All list endpoints support:
```json
{
  "data": { "items": [...], "meta": { "current_page": 1, "per_page": 15, "total": 100, "last_page": 7 } }
}
```

Standard Laravel pagination query parameters:
- `page` — page number (default 1)
- `per_page` — items per page (default 15)

---

## 8. File Upload

### Acceptable Formats
| Resource | Formats | Max Size |
|----------|---------|----------|
| Product images | jpeg, jpg, png, webp | 2 MB |
| User furniture images | jpeg, png, jpg, webp, gif | 10 MB |

### Storage Location
Files are stored in `storage/app/public/{products,user_furnitures}/` and accessible via `/storage/filename.ext`.

### Upload Methods
1. **Multipart form** — `image` field with file upload
2. **URL string** — `image_url` or `image1`–`image6` fields with pre-existing URLs

---

## 9. Migration Mapping Table (Laravel → Go)

### 9.1 Database Schema

| Table | Laravel Model | Go Struct Suggestion | PK Type | Soft Delete |
|-------|--------------|---------------------|---------|-------------|
| `admins` | `Admin` | `Admin` | `uuid` | No |
| `users` | `User` | `User` | `uuid` | No |
| `brands` | `Brand` | `Brand` | `uuid` | No |
| `categories` | `Category` | `Category` | `uuid` | No |
| `products` | `Product` | `Product` | `uuid` | Yes |
| `product_images` | `ProductImage` | `ProductImage` | `uuid` | No |
| `user_furnitures` | `UserFurniture` | `UserFurniture` | `uuid` | Yes |
| `user_furniture_images` | `UserFurnitureImage` | `UserFurnitureImage` | `uuid` | No |
| `transactions` | `Transaction` | `Transaction` | `uuid` | No |
| `transaction_messages` | `TransactionMessage` | `TransactionMessage` | `uuid` | No |
| `notifications` | `Notification` | `Notification` | `uuid` | No |
| `password_otps` | `PasswordOtp` | `PasswordOtp` | `BIGINT` (auto-increment) | No |
| `personal_access_tokens` | — | — | `BIGINT` | No |

### 9.2 Handler Mapping

| Controller | Route Group | Go Handler Pattern |
|------------|-------------|-------------------|
| `Admin\AuthController` | `/auth/systemAdmin/*` | `handlerAdminAuth` |
| `User\AuthController` | `/auth/user/*` | `handlerUserAuth` |
| `User\ForgotPasswordController` | `/auth/forgot-password/*` | `handlerForgotPassword` |
| `User\GoogleAuthController` | `/auth/user/google/*` | `handlerGoogleAuth` |
| `Admin\DashboardController` | `/systemAdmin/dashboard` | `handlerAdminDashboard` |
| `Admin\AdminController` | `/systemAdmin/admins` | `handlerAdmins` |
| `Admin\BrandController` | `/systemAdmin/brands` | `handlerBrands` |
| `Admin\CategoryController` | `/systemAdmin/categories` | `handlerCategories` |
| `Admin\ProductController` | `/systemAdmin/products` | `handlerProducts` |
| `Admin\TransactionController` | `/systemAdmin/transactions` | `handlerTransactions` |
| `Api\TransactionMessageController` | `/systemAdmin/transactions/{id}/messages` | `handlerTxMessages` |
| `User\ProfileController` | `/user/profile` | `handlerUserProfile` |
| `User\ProductController` | `/user/products`, `/user/brands`, `/user/categories` | `handlerUserProducts` |
| `User\FurnitureController` | `/user/furnitures` | `handlerUserFurniture` |
| `User\TransactionController` | `/user/transactions` | `handlerUserTransactions` |
| `User\NotificationController` | `/user/notifications` | `handlerNotifications` |

### 9.3 Service Mapping

| Laravel Service | Go Service Suggestion |
|----------------|----------------------|
| `App\Services\TransactionService` | `service/transaction.go` — methods: `Submit`, `StartChecking`, `Approve`, `Reject` |

### 9.4 Request Struct Mapping

| Laravel Request Class | Go Struct Field |
|----------------------|-----------------|
| `Admin\LoginRequest` | `Email string`, `Password string` |
| `User\LoginRequest` | `Email string`, `Password string` |
| `User\RegisterRequest` | `Name`, `Email`, `Password`, `PhoneNumber?`, `Address?` |
| `User\SendOtpRequest` | `Email string` |
| `User\VerifyOtpRequest` | `Email string`, `OTP string` |
| `User\ResetPasswordRequest` | `Email`, `Password`, `PasswordConfirmation` |
| `User\UpdateProfileRequest` | All optional: `Name?`, `Email?`, `Phone?`, `Password?`, `Address?` |
| `User\StoreTransactionRequest` | `UserFurnitureID`, `ProductID` |
| `Admin\StoreAdminRequest` | `Name`, `Email`, `Password`, `Status?` |
| `Admin\UpdateAdminRequest` | All optional |
| `Admin\StoreBrandRequest` | `Name`, `Description?`, `Image?` |
| `Admin\UpdateBrandRequest` | All optional |
| `Admin\StoreCategoryRequest` | `Name`, `Description?`, `Image?` |
| `Admin\UpdateCategoryRequest` | All optional |
| `Admin\StoreProductRequest` | `Name`, `CategoryID`, `BrandID`, `Description?`, `Price?`, `Status?`, `Image1–6?`, `Images?` |
| `Admin\UpdateProductRequest` | Same as Store, all optional |
| `Admin\StoreProductImageRequest` | `Image?`, `ImageURL?`, `SortOrder?` |
| `Admin\RejectTransactionRequest` | `RejectReason string` |
| `Admin\UpdateFurniturePriceRequest` | `AdminPrice decimal` |
| `Message\StoreMessageRequest` | `Message string` |
| `User\StoreFurnitureRequest` | `Name`, `CategoryID?`, `CategoryText?`, `BrandID?`, `BrandText?`, `Description?`, `Images?` |
| `User\UpdateFurnitureRequest` | Same as Store, all optional |
| `User\StoreFurnitureImageRequest` | `Image?`, `ImageURL?`, `SortOrder?` |

### 9.5 Response Struct Mapping

| Laravel Resource | Go Struct Field |
|-----------------|-----------------|
| `AdminResource` | `ID`, `Name`, `Email`, `Status`, `CreatedAt`, `UpdatedAt` |
| `BrandResource` | `ID`, `Name`, `Description?`, `Image?`, `CreatedAt`, `UpdatedAt` |
| `CategoryResource` | `ID`, `Name`, `Description?`, `Image?`, `CreatedAt`, `UpdatedAt` |
| `ProductResource` | `ID`, `Name`, `Description?`, `CategoryID`, `BrandID`, `Status`, `Price?`, `Image1–6?`, `Images[]`, `Category?`, `Brand?`, `CreatedAt`, `UpdatedAt` |
| `UserFurnitureResource` | `ID`, `UserID`, `Name`, `Description?`, `Status`, `CategoryID?`, `CategoryText?`, `BrandID?`, `BrandText?`, `AdminPrice?`, `Category?`, `Brand?`, `Images[]`, `CreatedAt`, `UpdatedAt` |
| `TransactionResource` | `ID`, `UserID`, `UserFurnitureID`, `ProductID`, `Status`, `AdminID?`, `RejectReason?`, `UserFurniture?`, `Product?`, `User?`, `Admin?`, `CreatedAt`, `UpdatedAt` |
| `TransactionMessageResource` | `ID`, `TransactionID`, `SenderID`, `SenderType`, `Message`, `CreatedAt` |

### 9.6 Middleware Mapping

| Laravel Middleware | Go Equivalent |
|--------------------|---------------|
| `role:admin` | JWT auth + role claim check (`role == "admin"`) |
| `role:user` | JWT auth + role claim check (`role == "user"`) |

Both check `Authorization: Bearer <token>` header, parse JWT, validate role claim, and set authenticated user.

### 9.7 Dependencies Summary

| Dependency | Purpose | Go Replacement |
|-----------|---------|---------------|
| `tymon/jwt-auth` | JWT auth | `golang-jwt/jwt` |
| `laravel/socialite` | Google OAuth | Google OAuth2 `golang.org/x/oauth2` |
| `firebase/php-jwt` | Google ID token verification | `github.com/golang-jwt/jwt` |
| `illuminate/mail` | OTP email sending | SMTP or SendGrid client |
| `illuminate/redis` | Rate limiting (Redis) | In-memory map or Redis |

---

## 10. Known Issues & Conflicts

### ⚠️ Conflict: `LOG::info` Typo (Admin/ProductController.php:92)
- **Source:** `LOG::info(...)` uses uppercase class name — PHP treats this as a constant lookup, will cause error at runtime unless `LOG` is defined globally.
- **Expected:** `Log::info(...)` (Illuminate\Support\Facades\Log).
- **Impact:** Admin product update debugging may silently fail.

### ⚠️ Conflict: Missing `google_id` in User Model Fillable (User.php)
- **Migration** `2026_08_09_120245` adds `google_id` column to `users` table.
- **User model** `$fillable` array does NOT include `'google_id'`.
- **Impact:** `GoogleAuthController::handleGoogleCallback()` calls `User::firstOrCreate([...])` with `'google_id'` — this field will be silently ignored by mass assignment. The Google user will be created but `google_id` will be NULL, breaking future lookups by `google_id`.
- **Fix needed:** Add `'google_id'` to `$fillable` in `User` model.

### ⚠️ Conflict: Rate Limiter Key Uses `md5()`
- Both login endpoints use `md5($email . $ip)` as rate limiter key.
- `md5()` is deprecated in PHP 8.4+. Consider using `hash('sha256', ...)`.
- **Not a functional bug today**, but a future compatibility issue.

### ⚠️ Conflict: Duplicate Migration Files
- `2026_08_05_104335_add_price_to_products_table.php` and `2026_08_05_104335_add_admin_price_to_user_furnitures_table.php` share the same timestamp prefix `2026_08_05_104335`.
- This can cause migration ordering issues. The `add_admin_price` migration should have a later timestamp.

### ⚠️ Legacy: `image1`–`image6` Columns Are Deprecated
- The `products` table originally had `image1`–`image6` columns (added by migration `2026_07_17_000000`).
- The modern approach uses the `product_images` table with `image_url` and `sort_order`.
- The `ProductResource` still returns both `image1`–`image6` AND the `images` array — this dual-format may confuse API consumers.
- **Recommendation:** Migrate all existing image data to `product_images` rows and deprecate `image1`–`image6` in a future version.

### ⚠️ Not Used: `StoreFurnitureImageRequest` (Redundant)
- `User\FurnitureController@storeImage()` does NOT use `StoreFurnitureImageRequest` — it validates inline with `$request->validate()`.
- The request class exists but is dead code.

### ℹ️ Note: Notification Content Is Plain Text Only
- `Notification` model stores plain `message` string, no structured payload.
- `sender_type` is not tracked in notifications (unlike `TransactionMessage` which has `sender_type`).

---

## Summary

| Metric | Count |
|--------|-------|
| **Total documented endpoints** | 42 |
| Public endpoints | 11 |
| User endpoints | 15 |
| Admin endpoints | 16 |
| Shared endpoints (admin+user) | 2 |
| **Models** | 11 |
| **Request classes** | 17 |
| **Resource classes** | 6 |
| **Middleware** | 1 (role) |
| **Known conflicts** | 4 |
| **Legacy/deprecated patterns** | 2 |

---

*Documentation generated from source code audit — 2026-08-19*
*Source of truth: routes/api.php, app/Http/Controllers/, app/Http/Requests/, app/Http/Resources/, app/Models/, database/migrations/*
