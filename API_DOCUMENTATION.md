# API Exchange — Backend Documentation

## 1. Authentication

Sistem menggunakan **JWT (JSON Web Token)** dengan library `tymon/jwt-auth`.

### Konfigurasi JWT

| Parameter | Nilai |
|-----------|-------|
| Algorithm | HMAC-SHA256 |
| TTL | 60 menit |
| Refresh TTL | 20160 menit (2 minggu) |
| Blacklist | Enabled |
| Lock Subject | true |

### Header yang Digunakan

```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

### Format Response

**Sukses:**
```json
{
  "success": true,
  "message": "...",
  "data": { ... },
  "status": 200
}
```

**Error:**
```json
{
  "success": false,
  "message": "...",
  "error": { ... },
  "status": 400
}
```

---

## 2. Endpoint Publik (Tanpa Auth)

### Health Check

```
GET /
```

**Response:**
```json
{
  "success": true,
  "message": "Exchange Product API — silakan baca README untuk dokumentasi.",
  "data": null,
  "status": 200
}
```

---

## 3. Auth

### 3.1 Register User

```
POST /auth/user/register
```

**Request Body:**
```json
{
  "name": "John Doe",           // required, string, max 255
  "email": "john@example.com",  // required, email, unique
  "password": "rahasia123",     // required, string, min 6
  "phone_number": "08123456789",// optional, string, max 30
  "address": "Jakarta"          // optional, string
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Registrasi berhasil.",
  "data": {
    "user": {
      "id": "uuid-string",
      "name": "John Doe",
      "email": "john@example.com",
      "phone_number": "08123456789",
      "address": "Jakarta",
      "status": "active"
    },
    "access_token": "eyJ...",
    "token_type": "bearer",
    "expires_in": 3600
  },
  "status": 201
}
```

### 3.2 Login User

```
POST /auth/user/login
```

**Request Body:**
```json
{
  "email": "john@example.com",   // required, email
  "password": "rahasia123"       // required, string, min 6
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Login berhasil.",
  "data": {
    "user": {
      "id": "uuid-string",
      "name": "John Doe",
      "email": "john@example.com",
      "phone_number": "08123456789",
      "address": "Jakarta",
      "status": "active"
    },
    "access_token": "eyJ...",
    "token_type": "bearer",
    "expires_in": 3600
  },
  "status": 200
}
```

**Error (401 — salah email/password):**
```json
{
  "success": false,
  "message": "Login gagal.",
  "error": { "auth": ["Email atau password salah."] },
  "status": 401
}
```

**Error (403 — akun nonaktif/banned):**
```json
{
  "success": false,
  "message": "Login gagal.",
  "error": { "auth": ["Akun dibanned."] },
  "status": 403
}
```

### 3.3 Login Admin

```
POST /auth/admin/login
```

**Request Body:**
```json
{
  "email": "admin@example.com",  // required, email
  "password": "rahasia123"       // required, string, min 6
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Login admin berhasil.",
  "data": {
    "admin": {
      "id": "uuid-string",
      "name": "Admin Name",
      "email": "admin@example.com",
      "status": "active"
    },
    "access_token": "eyJ...",
    "token_type": "bearer",
    "expires_in": 3600
  },
  "status": 200
}
```

### 3.4 Google OAuth (User)

#### Redirect ke Google

```
GET /auth/user/google/redirect
```

**Response:**
```json
{ "url": "https://accounts.google.com/o/oauth2/..." }
```

#### Callback dari Google

```
POST /auth/user/google/callback
```

**Request Body:**
```json
{
  "id_token": "eyJ..."   // required, Google ID Token
}
```

**Response:**
```json
{
  "success": true,
  "message": "...",
  "data": {
    "user": {
      "id": "uuid-string",
      "name": "John Doe",
      "email": "john@example.com",
      "phone_number": null,
      "address": null,
      "status": "active"
    },
    "access_token": "eyJ...",
    "token_type": "bearer",
    "expires_in": 3600
  },
  "status": 200
}
```

### 3.5 忘记密码（Forgot Password）

#### 3.5.1 发送 OTP

```
POST /auth/forgot-password/send-otp
```

**请求参数：**
```json
{
  "email": "john@example.com"  // required, email, 必须存在于系统中
}
```

**响应（200）：**
```json
{
  "success": true,
  "message": "Kode OTP telah dikirim ke email Anda.",
  "data": null,
  "status": 200
}
```

**错误（429 — 请求过于频繁）：**
```json
{
  "success": false,
  "message": "Rate limit terlampaui.",
  "error": { "email": ["Terlalu banyak permintaan. Tunggu beberapa menit lagi."] },
  "status": 429
}
```

---

#### 3.5.2 验证 OTP

```
POST /auth/forgot-password/verify-otp
```

**请求参数：**
```json
{
  "email": "john@example.com",  // required, email
  "otp": "123456"               // required, string, 6位数字
}
```

**响应（200）：**
```json
{
  "success": true,
  "message": "OTP berhasil diverifikasi. Silakan buat password baru.",
  "data": {
    "email": "john@example.com"
  },
  "status": 200
}
```

**错误（422 — OTP 错误或已过期）：**
```json
{
  "success": false,
  "message": "Verifikasi OTP gagal.",
  "error": { "otp": ["Kode OTP salah atau telah kedaluwarsa."] },
  "status": 422
}
```

---

#### 3.5.3 重置密码

```
POST /auth/forgot-password/reset
```

**请求参数：**
```json
{
  "email": "john@example.com",    // required, email
  "otp": "123456",                 // required, string, 6位数字
  "password": "newpassword123",    // required, string, min 6
  "password_confirmation": "newpassword123"  // required, 必须与 password 一致
}
```

**响应（200）：**
```json
{
  "success": true,
  "message": "Password berhasil diubah. Silakan login dengan password baru.",
  "data": null,
  "status": 200
}
```

**错误（422 — OTP 错误或已过期）：**
```json
{
  "success": false,
  "message": "Reset password gagal.",
  "error": { "otp": ["Kode OTP salah atau telah kedaluwarsa."] },
  "status": 422
}
```

---

### 3.6 Logout

```
POST /auth/user/logout            // requires role:user
POST /auth/admin/logout           // requires role:admin
```

**Response:**
```json
{
  "success": true,
  "message": "Logout berhasil.",
  "data": null,
  "status": 200
}
```

---

## 4. Endpoint User (`role:user`)

**Base URL:** `/api/user`
**Middleware:** `role:user`

---

### 4.1 Profile

#### GET Profile

```
GET /profile
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "uuid-string",
    "name": "John Doe",
    "email": "john@example.com",
    "phone_number": "08123456789",
    "address": "Jakarta",
    "status": "active",
    "created_at": "2026-08-07T10:00:00.000000Z",
    "updated_at": "2026-08-07T10:00:00.000000Z"
  }
}
```

#### PUT Update Profile

```
PUT /profile
```

**Request Body:**
```json
{
  "name": "John Doe Updated",          // optional, string, max 255
  "email": "new@example.com",          // optional, email, unique
  "phone_number": "08123456789",       // optional, string, max 30
  "password": "newpassword123",        // optional, string, min 6
  "address": "Bandung"                 // optional, string
}
```

---

### 4.2 Master Data (Read-Only)

#### GET Brands (Dropdown)

```
GET /brands
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid-string",
      "name": "IKEA",
      "description": "Furniture Store",
      "image": "/storage/brands/...",
      "created_at": "...",
      "updated_at": "..."
    }
  ]
}
```

#### GET Categories (Dropdown)

```
GET /categories
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "uuid-string",
      "name": "Kursi",
      "description": "Furniture kategori kursi",
      "image": "/storage/categories/...",
      "created_at": "...",
      "updated_at": "..."
    }
  ]
}
```

---

### 4.3 Products (Browse)

#### GET Products (List)

```
GET /products?per_page=15&status=available
```

**Query Parameters:**
| Param | Type | Default | Description |
|-------|------|---------|-------------|
| per_page | int | 15 | Jumlah item per halaman |
| status | string | - | Filter: available, swapped, inactive |

**Response:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": "uuid-string",
        "name": "Meja Makan Jati",
        "description": "Meja makan 6 kursi",
        "category_id": "uuid-string",
        "brand_id": "uuid-string",
        "status": "available",
        "price": 1500000,
        "image1": "/storage/products/img1.jpg",
        "image2": "/storage/products/img2.jpg",
        "image3": null,
        "image4": null,
        "image5": null,
        "image6": null,
        "images": ["/storage/products/img1.jpg", "/storage/products/img2.jpg"],
        "category": { "id": "...", "name": "...", "description": "...", "image": "...", "created_at": "...", "updated_at": "..." },
        "brand": { "id": "...", "name": "...", "description": "...", "image": "...", "created_at": "...", "updated_at": "..." },
        "created_at": "...",
        "updated_at": "..."
      }
    ],
    "meta": {
      "current_page": 1,
      "per_page": 15,
      "total": 100,
      "last_page": 7
    }
  }
}
```

#### GET Product (Detail)

```
GET /products/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "uuid-string",
    "name": "Meja Makan Jati",
    "description": "Meja makan 6 kursi",
    "category_id": "uuid-string",
    "brand_id": "uuid-string",
    "status": "available",
    "price": 1500000,
    "image1": "/storage/products/img1.jpg",
    "image2": "/storage/products/img2.jpg",
    "image3": null,
    "image4": null,
    "image5": null,
    "image6": null,
    "images": ["/storage/products/img1.jpg", "/storage/products/img2.jpg"],
    "category": { ... },
    "brand": { ... },
    "created_at": "...",
    "updated_at": "..."
  }
}
```

---

### 4.4 Furnitures (CRUD — Milik User Sendiri)

#### GET Furnitures (List)

```
GET /furnitures?per_page=15&status=available
```

**Query Parameters:**
| Param | Type | Default | Description |
|-------|------|---------|-------------|
| per_page | int | 15 | Jumlah item per halaman |
| status | string | - | Filter: available, in_transaction, swapped, rejected |

**Response:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": "uuid-string",
        "user_id": "uuid-string",
        "name": "Kursi Kayu Bekas",
        "description": "Kursi kayu jati, masih kokoh.",
        "status": "available",
        "category_id": "uuid-string",
        "category_text": "Kursi",
        "category": { "id": "...", "name": "...", "description": "...", "image": "...", "created_at": "...", "updated_at": "..." },
        "brand_id": "uuid-string",
        "brand_text": "Custom",
        "brand": { "id": "...", "name": "...", "description": "...", "image": "...", "created_at": "...", "updated_at": "..." },
        "admin_price": null,
        "images": [
          { "id": "uuid-string", "image_url": "/storage/user_furnitures/abc.jpg", "sort_order": 0 }
        ],
        "created_at": "...",
        "updated_at": "..."
      }
    ],
    "meta": {
      "current_page": 1,
      "per_page": 15,
      "total": 50,
      "last_page": 4
    }
  }
}
```

#### POST Create Furniture

```
POST /furnitures
```

**Request Body (multipart/form-data atau JSON):**
```json
{
  "name": "Meja Makan Baru",           // required, string, max 255
  "category_id": "uuid-string",        // optional, UUID exists di categories
  "category_text": "Meja",             // optional, string, max 255 (wajib kalau category_id kosong)
  "brand_id": "uuid-string",           // optional, UUID exists di brands
  "brand_text": "IKEA",                // optional, string, max 255 (wajib kalau brand_id kosong)
  "description": "Meja makan 6 kursi", // optional, string
  "images": [                          // optional, array of files
    {}
  ]
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Furniture berhasil ditambahkan.",
  "data": {
    "id": "uuid-string",
    "user_id": "uuid-string",
    "name": "Meja Makan Baru",
    "description": "Meja makan 6 kursi",
    "status": "available",
    "category_id": "uuid-string",
    "category_text": "Meja",
    "brand_id": null,
    "brand_text": null,
    "admin_price": null,
    "images": [],
    "created_at": "...",
    "updated_at": "..."
  }
}
```

#### GET Furniture (Detail)

```
GET /furnitures/{id}
```

**Response:** Sama seperti struktur di `items` pada list.

#### PUT/PATCH Update Furniture

```
PUT /furnitures/{id}
PATCH /furnitures/{id}
```

**Request Body:**
```json
{
  "name": "Meja Makan Updated",        // optional, string, max 255
  "category_id": "uuid-string",        // optional, UUID exists di categories
  "category_text": "Meja",             // optional, string, max 255
  "brand_id": "uuid-string",           // optional, UUID exists di brands
  "brand_text": "IKEA",                // optional, string, max 255
  "description": "Updated desc",       // optional, string
  "images": [],                        // optional, array of files
  "remove_images": [                   // optional, array of UUIDs gambar yang ingin dihapus
    "uuid-image-id-1",
    "uuid-image-id-2"
  ]
}
```

**Response:**
```json
{
  "success": true,
  "message": "Furniture berhasil diperbarui.",
  "data": { ...UserFurnitureResource... }
}
```

#### DELETE Delete Furniture

```
DELETE /furnitures/{id}
```

**Response:**
```json
{
  "success": true,
  "message": "Furniture berhasil dihapus.",
  "data": null
}
```

**Error (422 — sedang dalam transaksi):**
```json
{
  "success": false,
  "message": "Hapus furniture gagal.",
  "error": { "furniture": ["Furniture sedang dalam transaksi, tidak dapat dihapus."] },
  "status": 422
}
```

#### POST Add Image to Furniture

```
POST /furnitures/{id}/images
```

**Request Body:**
```json
{
  "image": {},          // optional, image file (jpeg/png/jpg/webp/gif, max 10MB)
  "image_url": "https://...",  // optional, URL gambar
  "sort_order": 0       // optional, integer >= 0
}
```

Catatan: Harus mengisi minimal satu dari `image` (file) atau `image_url` (string).

**Response (201):**
```json
{
  "success": true,
  "message": "Gambar furniture berhasil ditambahkan.",
  "data": {
    "id": "uuid-string",
    "image_url": "/storage/user_furnitures/abc.jpg",
    "sort_order": 0
  }
}
```

#### DELETE Remove Image from Furniture

```
DELETE /furnitures/{id}/images/{imageId}
```

**Response:**
```json
{
  "success": true,
  "message": "Gambar furniture berhasil dihapus.",
  "data": null
}
```

---

### 4.5 Transactions

#### GET Transactions (List)

```
GET /transactions?per_page=15
```

**Query Parameters:**
| Param | Type | Default | Description |
|-------|------|---------|-------------|
| per_page | int | 15 | Jumlah item per halaman |

**Response:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": "uuid-string",
        "user_id": "uuid-string",
        "user_furniture_id": "uuid-string",
        "product_id": "uuid-string",
        "status": "pending",
        "admin_id": null,
        "reject_reason": null,
        "user_furniture": { ...UserFurnitureResource },
        "product": { ...ProductResource },
        "user": { "id": "...", "name": "...", "email": "..." },
        "admin": null,
        "created_at": "...",
        "updated_at": "..."
      }
    ],
    "meta": {
      "current_page": 1,
      "per_page": 15,
      "total": 30,
      "last_page": 2
    }
  }
}
```

#### POST Create Transaction

```
POST /transactions
```

**Request Body:**
```json
{
  "user_furniture_id": "uuid-string",  // required, UUID exists di user_furnitures
  "product_id": "uuid-string"          // required, UUID exists di products
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Transaksi berhasil dibuat.",
  "data": { ...TransactionResource }
}
```

#### GET Transaction (Detail)

```
GET /transactions/{id}
```

**Response:** Sama seperti struktur item di list.

#### GET Transaction Messages

```
GET /transactions/{id}/messages?per_page=50
```

**Query Parameters:**
| Param | Type | Default | Description |
|-------|------|---------|-------------|
| per_page | int | 50 | Jumlah pesan per halaman |

**Response:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": "uuid-string",
        "transaction_id": "uuid-string",
        "sender_id": "uuid-string",
        "sender_type": "admin",
        "message": "Baik, saya cek dulu ya",
        "created_at": "..."
      }
    ],
    "meta": {
      "current_page": 1,
      "per_page": 50,
      "total": 10,
      "last_page": 1
    }
  }
}
```

#### POST Send Message

```
POST /transactions/{id}/messages
```

**Request Body:**
```json
{
  "message": "Baik, saya setuju dengan pertukaran ini"  // required, string, 1-2000 karakter
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Pesan terkirim.",
  "data": {
    "id": "uuid-string",
    "transaction_id": "uuid-string",
    "sender_id": "uuid-string",
    "sender_type": "user",
    "message": "Baik, saya setuju dengan pertukaran ini",
    "created_at": "..."
  }
}
```

**Error (422 — chat hanya saat checking):**
```json
{
  "success": false,
  "message": "Tidak dapat mengirim pesan.",
  "error": { "status": ["Chat hanya tersedia saat transaksi berstatus checking (saat ini: pending)."] },
  "status": 422
}
```

---

### 4.6 Notifications

#### GET Notifications (List)

```
GET /notifications?per_page=15
```

**Response:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": "uuid-string",
        "user_id": "uuid-string",
        "type": "App\\Notifications\\TransactionApproved",
        "subject": "Transaksi Disetujui",
        "body": "Transaksi #xxx berhasil disetujui",
        "data": {},
        "read_at": null,
        "created_at": "..."
      }
    ],
    "meta": {
      "current_page": 1,
      "per_page": 15,
      "total": 20,
      "last_page": 2
    }
  }
}
```

#### PATCH Mark as Read

```
PATCH /notifications/{id}/read
```

**Response:**
```json
{
  "success": true,
  "message": "Notifikasi ditandai dibaca.",
  "data": null
}
```

---

## 5. Endpoint System Admin (`role:admin`)

**Base URL:** `/api/systemAdmin`
**Middleware:** `role:admin`

---

### 5.1 Dashboard

#### GET Dashboard Stats

```
GET /dashboard
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total_users": 150,
    "total_admins": 5,
    "total_products": 500,
    "total_furnitures": 300,
    "total_transactions": 120,
    "transactions_by_status": {
      "pending": 30,
      "checking": 10,
      "approved": 70,
      "rejected": 10
    }
  }
}
```

---

### 5.2 Admins (CRUD)

#### GET Admins (List)

```
GET /admins?per_page=15
```

**Response:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": "uuid-string",
        "name": "Admin Name",
        "email": "admin@example.com",
        "status": "active",
        "created_at": "...",
        "updated_at": "..."
      }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 5, "last_page": 1 }
  }
}
```

#### POST Create Admin

```
POST /admins
```

**Request Body:**
```json
{
  "name": "Admin Name",     // required, string, max 255
  "email": "admin@example.com",  // required, email, unique
  "password": "rahasia123",   // required, string, min 6
  "status": "active"          // optional, enum: active, inactive
}
```

#### GET Admin (Detail)

```
GET /admins/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "uuid-string",
    "name": "Admin Name",
    "email": "admin@example.com",
    "status": "active",
    "created_at": "...",
    "updated_at": "..."
  }
}
```

#### PUT/PATCH Update Admin

```
PUT /admins/{id}
PATCH /admins/{id}
```

**Request Body:**
```json
{
  "name": "Admin Name Updated",  // optional, string, max 255
  "email": "admin2@example.com", // optional, email, unique
  "password": "newpass123",      // optional, string, min 6
  "status": "inactive"           // optional, enum: active, inactive
}
```

#### DELETE Delete Admin

```
DELETE /admins/{id}
```

**Response:**
```json
{
  "success": true,
  "message": "Admin berhasil dihapus.",
  "data": null
}
```

---

### 5.3 Brands (CRUD)

#### GET Brands (List)

```
GET /brands?per_page=15
```

**Response:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": "uuid-string",
        "name": "IKEA",
        "description": "Furniture Store",
        "image": "/storage/brands/ikea.jpg",
        "created_at": "...",
        "updated_at": "..."
      }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 10, "last_page": 1 }
  }
}
```

#### POST Create Brand

```
POST /brands
```

**Request Body:**
```json
{
  "name": "IKEA",           // required, string, max 255, unique
  "description": "Furniture Store",  // optional, string
  "image": "/storage/brands/..."     // optional, string URL
}
```

#### GET Brand (Detail)

```
GET /brands/{id}
```

#### PUT/PATCH Update Brand

```
PUT /brands/{id}
PATCH /brands/{id}
```

**Request Body:**
```json
{
  "name": "IKEA Updated",     // optional, string, max 255, unique
  "description": "Updated",   // optional, string
  "image": "/storage/..."     // optional, string URL
}
```

#### DELETE Delete Brand

```
DELETE /brands/{id}
```

---

### 5.4 Categories (CRUD)

Sama struktur dengan Brands.

#### GET Categories (List)

```
GET /categories?per_page=15
```

#### POST Create Category

```
POST /categories
```

**Request Body:**
```json
{
  "name": "Kursi",           // required, string, max 255, unique
  "description": "Furniture kategori kursi",  // optional, string
  "image": "/storage/categories/..."         // optional, string URL
}
```

#### GET Category (Detail)

```
GET /categories/{id}
```

#### PUT/PATCH Update Category

```
PUT /categories/{id}
PATCH /categories/{id}
```

**Request Body:**
```json
{
  "name": "Kursi Updated",   // optional, string, max 255, unique
  "description": "Updated",  // optional, string
  "image": "/storage/..."    // optional, string URL
}
```

#### DELETE Delete Category

```
DELETE /categories/{id}
```

---

### 5.5 Products (CRUD)

#### GET Products (List)

```
GET /products?per_page=15&status=available
```

**Query Parameters:**
| Param | Type | Default | Description |
|-------|------|---------|-------------|
| per_page | int | 15 | Jumlah item per halaman |
| status | string | - | Filter: available, swapped, inactive |

**Response:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": "uuid-string",
        "name": "Meja Makan Jati",
        "description": "Meja makan 6 kursi",
        "category_id": "uuid-string",
        "brand_id": "uuid-string",
        "status": "available",
        "price": 1500000,
        "image1": "/storage/products/img1.jpg",
        "image2": "/storage/products/img2.jpg",
        "image3": null,
        "image4": null,
        "image5": null,
        "image6": null,
        "images": ["/storage/products/img1.jpg", "/storage/products/img2.jpg"],
        "category": { ...CategoryResource },
        "brand": { ...BrandResource },
        "created_at": "...",
        "updated_at": "..."
      }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 100, "last_page": 7 }
  }
}
```

#### POST Create Product

```
POST /products
```

**Request Body:**
```json
{
  "name": "Meja Makan Jati",               // required, string, max 255
  "category_id": "uuid-string",            // required, UUID exists di categories
  "brand_id": "uuid-string",               // required, UUID exists di brands
  "description": "Meja makan 6 kursi",     // optional, string
  "price": 1500000,                        // optional, numeric >= 0
  "status": "available",                   // optional, enum: available, swapped, inactive
  "image1": {},                            // optional, file image (jpeg/jpg/png/webp, max 2MB)
  "image2": {},                            // optional, file image
  "image3": {},                            // optional, file image
  "image4": {},                            // optional, file image
  "image5": {},                            // optional, file image
  "image6": {},                            // optional, file image
  "images": []                             // optional, array of files (tambahan)
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Product berhasil ditambahkan.",
  "data": { ...ProductResource }
}
```

#### GET Product (Detail)

```
GET /products/{id}
```

#### PUT/PATCH Update Product

```
PUT /products/{id}
PATCH /products/{id}
```

**Request Body:**
```json
{
  "name": "Meja Makan Jati Updated",    // optional, string, max 255
  "category_id": "uuid-string",         // optional, required if provided, UUID exists
  "brand_id": "uuid-string",            // optional, required if provided, UUID exists
  "description": "Updated desc",        // optional, string
  "price": 1600000,                     // optional, numeric >= 0
  "status": "swapped",                  // optional, enum: available, swapped, inactive
  "image1": {},                         // optional, file image
  "image2": {},                         // optional, file image
  "image3": {},                         // optional, file image
  "image4": {},                         // optional, file image
  "image5": {},                         // optional, file image
  "image6": {},                         // optional, file image
  "images": []                          // optional, array of files
}
```

#### DELETE Delete Product

```
DELETE /products/{id}
```

**Response:**
```json
{
  "success": true,
  "message": "Product berhasil dihapus.",
  "data": null
}
```

#### POST Add Product Image

```
POST /products/{id}/images
```

**Request Body:**
```json
{
  "image": {},              // optional, file image (jpeg/jpg/png/webp, max 2MB)
  "image_url": "https://...",  // optional, string URL
  "sort_order": 0           // optional, integer >= 0
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Gambar product berhasil ditambahkan.",
  "data": {
    "id": "uuid-string",
    "image_url": "/storage/products/abc.jpg",
    "sort_order": 0
  }
}
```

#### PATCH Reorder Product Images

```
PATCH /products/{id}/images/reorder
```

**Request Body:**
```json
{
  "images": [
    { "id": "uuid-1", "sort_order": 0 },
    { "id": "uuid-2", "sort_order": 1 },
    { "id": "uuid-3", "sort_order": 2 }
  ]
}
```

#### DELETE Remove Product Image

```
DELETE /products/{id}/images/{imageId}
```

---

### 5.6 Transactions

#### GET Transactions (List)

```
GET /transactions?per_page=15&status=pending
```

**Query Parameters:**
| Param | Type | Default | Description |
|-------|------|---------|-------------|
| per_page | int | 15 | Jumlah item per halaman |
| status | string | - | Filter: pending, checking, approved, rejected |

**Response:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": "uuid-string",
        "user_id": "uuid-string",
        "user_furniture_id": "uuid-string",
        "product_id": "uuid-string",
        "status": "pending",
        "admin_id": null,
        "reject_reason": null,
        "user_furniture": { ...UserFurnitureResource },
        "product": { ...ProductResource },
        "user": { "id": "...", "name": "...", "email": "..." },
        "admin": null,
        "created_at": "...",
        "updated_at": "..."
      }
    ],
    "meta": { "current_page": 1, "per_page": 15, "total": 30, "last_page": 2 }
  }
}
```

#### GET Transaction (Detail)

```
GET /transactions/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "uuid-string",
    "user_id": "uuid-string",
    "user_furniture_id": "uuid-string",
    "product_id": "uuid-string",
    "status": "pending",
    "admin_id": null,
    "reject_reason": null,
    "user_furniture": { ...UserFurnitureResource },
    "product": { ...ProductResource },
    "user": { "id": "...", "name": "...", "email": "..." },
    "admin": null,
    "created_at": "...",
    "updated_at": "..."
  }
}
```

#### PATCH Start Checking

```
PATCH /transactions/{id}/checking
```

**Response:**
```json
{
  "success": true,
  "message": "Transaksi mulai diperiksa.",
  "data": { ...TransactionResource }
}
```

#### PATCH Approve Transaction

```
PATCH /transactions/{id}/approve
```

**Response:**
```json
{
  "success": true,
  "message": "Transaksi disetujui.",
  "data": { ...TransactionResource }
}
```

#### PATCH Reject Transaction

```
PATCH /transactions/{id}/reject
```

**Request Body:**
```json
{
  "reject_reason": "Furniture dalam kondisi buruk"  // required, string, 3-1000 karakter
}
```

**Response:**
```json
{
  "success": true,
  "message": "Transaksi ditolak.",
  "data": { ...TransactionResource }
}
```

#### PATCH Set Price

```
PATCH /transactions/{id}/set-price
```

**Request Body:**
```json
{
  "admin_price": 1500000  // required, numeric >= 0
}
```

**Response:**
```json
{
  "success": true,
  "message": "Harga berhasil diatur.",
  "data": { ...TransactionResource }
}
```

#### GET Transaction Messages

```
GET /transactions/{id}/messages?per_page=50
```

**Response:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": "uuid-string",
        "transaction_id": "uuid-string",
        "sender_id": "uuid-string",
        "sender_type": "admin",
        "message": "Baik, saya cek dulu ya",
        "created_at": "..."
      }
    ],
    "meta": { "current_page": 1, "per_page": 50, "total": 10, "last_page": 1 }
  }
}
```

#### POST Send Message

```
POST /transactions/{id}/messages
```

**Request Body:**
```json
{
  "message": "Baik, saya setuju dengan pertukaran ini"  // required, string, 1-2000 karakter
}
```

---

## 6. Error Codes Reference

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request (validation error) |
| 401 | Unauthorized (token invalid/missing) |
| 403 | Forbidden (wrong role / inactive account) |
| 422 | Unprocessable Entity (business rule violation) |

---

## 7. Status Enums

### UserFurniture.status
`available`, `in_transaction`, `swapped`, `rejected`

### Product.status
`available`, `swapped`, `inactive`

### Transaction.status
`pending`, `checking`, `approved`, `rejected`

### Admin.status / User.status
`active`, `inactive`, `banned`
