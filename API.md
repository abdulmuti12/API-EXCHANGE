# API Documentation — Exchange Product

> Dokumentasi ini ditulis khusus untuk **tim frontend**. Setiap endpoint diuraikan dengan payload, response, dan catatan UI yang relevan (alur layar, validasi, edge case).

**Base URL** (development): `http://127.0.0.1:8088/api`

**Credentials bawaan (dari seeder)**:
- Admin: `admin@example.com` / `password123`
- User:  `user@example.com` / `password123`

---

## Daftar Isi

1. [Konvensi Response](#1-konvensi-response)
2. [Autentikasi & Token](#2-autentikasi--token)
3. [Alur Layar (User)](#3-alur-layar-user)
4. [Alur Layar (Admin)](#4-alur-layar-admin)
5. [Referensi Endpoint](#5-referensi-endpoint)
   - 5.1 [Auth](#51-auth)
   - 5.2 [User — Profile](#52-user--profile)
   - 5.3 [User — Browse Master Data & Product](#53-user--browse-master-data--product)
   - 5.4 [User — Furnitures (CRUD)](#54-user--furnitures-crud)
   - 5.5 [User — Transactions](#55-user--transactions)
   - 5.6 [User — Notifications](#56-user--notifications)
   - 5.7 [Admin — Dashboard](#57-admin--dashboard)
   - 5.8 [Admin — CRUD Master Data](#58-admin--crud-master-data)
   - 5.9 [Admin — Products (CRUD + Images)](#59-admin--products-crud--images)
   - 5.10 [Admin — Transactions + State Machine](#510-admin--transactions--state-machine)
   - 5.11 [Chat (Messages)](#511-chat-messages)
6. [State Machine Transaksi](#6-state-machine-transaksi)
7. [Snippet Frontend](#7-snippet-frontend)
8. [Postman Collection](#8-postman-collection)

---

## 1. Konvensi Response

Semua response (sukses maupun gagal) mengikuti pola:

```jsonc
{
  "success": true,            // atau false
  "message": "Login berhasil.",// opsional, deskripsi singkat
  "data": { ... },            // payload utama, atau null
  "error": { ... },           // hanya saat success = false, berisi detail error
  "status": 200               // mirror HTTP status code
}
```

### HTTP Status Code yang Dipakai

| Code | Arti | Kapan Muncul |
|------|------|--------------|
| 200  | OK | GET, PUT, PATCH, DELETE sukses |
| 201  | Created | POST sukses membuat resource baru |
| 401  | Unauthenticated | Token tidak ada / tidak valid / kadaluarsa |
| 403  | Forbidden | Role token tidak sesuai, **atau** user mengakses resource milik user lain (IDOR) |
| 404  | Not Found | Resource tidak ada |
| 422  | Unprocessable Entity | Validasi gagal, **atau** transisi state machine ilegal (mis. approve transaksi yang sudah final) |
| 500  | Server Error | Bug / kesalahan tak terduga. Saat `APP_DEBUG=true`, response berisi class exception. |

### Struktur Error Validasi (422)

```json
{
  "success": false,
  "message": "Validasi gagal.",
  "error": {
    "field_name": ["Pesan error untuk field ini."]
  },
  "status": 422
}
```

> **Catatan frontend:** saat menampilkan pesan error, ambil `Object.values(error).flat()` atau render per-field. Untuk error non-validasi (mis. "Status transaksi saat ini: approved."), error hanya berisi satu field generik seperti `status` atau `reject_reason`.

### Pagination

Semua endpoint list mengembalikan envelope:

```jsonc
{
  "success": true,
  "data": {
    "items": [ /* ... */ ],
    "meta": {
      "current_page": 1,
      "per_page": 15,
      "total": 42,
      "last_page": 3
    }
  }
}
```

Query: `?page=1&per_page=15`. Default `per_page=15`.

---

## 2. Autentikasi & Token

API menggunakan **JWT** ([tymon/jwt-auth](https://jwt-auth.readthedocs.io/)). Ada 2 guard terpisah:

- **Admin** (tabel `admins`, klaim `role: "admin"`)
- **User**  (tabel `users`,   klaim `role: "user"`)

Token didapat dari endpoint login/register, lalu dikirim di header:

```
Authorization: Bearer <token>
```

### Alur Saran untuk Frontend

1. Setelah login/register sukses, simpan `access_token` di storage:
   - **Web (SPA)**: `localStorage.setItem('ep_token', token)` (perhatikan XSS — bisa juga `httpOnly` cookie kalau Anda tambahkan endpoint-nya).
   - **Mobile (RN/Flutter)**: `AsyncStorage` / `SharedPreferences` (encrypted storage lebih baik).
2. Simpan **role** (`admin` / `user`) agar bisa route guard.
3. Setiap request, sisipkan header. Jika response `401`, hapus token & redirect ke halaman login.
4. Logout: panggil endpoint logout (server invalidate token), lalu bersihkan storage.

### Middleware `role` (penting!)

Server akan **menolak** (403) jika:
- token tidak ada → `401`
- token role **tidak cocok** dengan prefix route (`/admin/*` butuh `role: admin`, `/user/*` butuh `role: user`) → `403`
- token valid tapi user/admin di DB sudah tidak ada → `401`

Ini otomatis — frontend tidak perlu cek role manual di setiap request, cukup intercept `403` dan redirect ke halaman yang sesuai.

---

## 3. Alur Layar (User)

Saran breakdown layar frontend (bukan keharusan — lihat sebagai referensi):

```
[Login] ──► [Register]
   │             │
   └──► [Home / Browse Product] ◄── browse catalog
              │
              ├── [Detail Product] ──► [Ajukan Tukar] (pilih furniture sendiri)
              │                              │
              │                              ▼
              │                       [My Transactions] ──► [Detail Transaksi]
              │                              │                      │
              │                              │                      ├── status=pending: tunggu admin
              │                              │                      ├── status=checking: [Chat dengan admin]
              │                              │                      ├── status=approved: selesai
              │                              │                      └── status=rejected: lihat alasan
              │                              ▼
              │                       [Notifikasi] (bell icon)
              │
              ├── [My Furnitures] ──► [+ Tambah Furniture] (multi-image)
              │                       [Edit Furniture]
              │                       [Hapus Furniture]
              │
              └── [Profile] ──► [Edit Profile]
                                [Logout]
```

### Penting untuk Form "Ajukan Tukar"

- User harus sudah punya minimal 1 furniture berstatus `available`.
- Furniture yang ditampilkan di dropdown adalah yang berstatus `available` saja.
- Product target harus berstatus `available` (frontend cukup disable button jika product detail `status !== 'available'`).
- Setelah submit berhasil, redirect ke `/user/transactions` atau `/user/transactions/{id}`.

### Penting untuk Form "Tambah Furniture"

- Field `category` dan `brand` punya 2 mode:
  - **Pilih dari master** → kirim `category_id` / `brand_id` (UUID dari GET `/user/categories` & `/user/brands`).
  - **Input manual**     → kirim `category_text` / `brand_text`.
  - Validasi server: minimal salah satu harus diisi.
- `images` adalah array string URL (saat ini **string**, bukan file upload). Untuk upload, frontend perlu endpoint upload file terpisah (belum diimplementasi) atau host gambar di CDN/static URL.

---

## 4. Alur Layar (Admin)

```
[Login Admin]
   │
   ▼
[Dashboard] ── statistik: total user, total barang, breakdown transaksi
   │
   ├── [Kelola Admin] (CRUD)
   ├── [Kelola Brand] (CRUD)
   ├── [Kelola Kategori] (CRUD)
   ├── [Kelola Product] (CRUD + upload gambar)
   └── [Kelola Transaksi] (list + detail)
            │
            ▼
       [Detail Transaksi]
            ├── Tombol "Mulai Cek"     (jika status=pending)
            ├── Tombol "Setujui"       (jika status=checking)
            ├── Tombol "Tolak" + form reject_reason (jika status=pending ATAU checking)
            ├── Tombol "Chat"          (jika status=checking) — modal/panel chat dengan user
            └── (status=approved/rejected) — read-only, tampilkan reject_reason
```

---

## 5. Referensi Endpoint

> **Konvensi penulisan**:
> - **Body** = JSON, contoh payload.
> - **Response** = JSON shape yang dikembalikan (field penting saja, lihat `data.*`).
> - **Error** = kasus gagal yang relevan untuk UI.
> - **Filter** = query string yang didukung.

### 5.1 Auth

#### POST `/api/auth/admin/login`

Body:
```json
{ "email": "admin@example.com", "password": "password123" }
```

Response 200:
```json
{
  "success": true,
  "message": "Login admin berhasil.",
  "data": {
    "admin": { "id": "uuid", "name": "...", "email": "...", "status": "active" },
    "access_token": "eyJ0eXAi...",
    "token_type": "bearer",
    "expires_in": 3600
  }
}
```

Error:
- `401` kredensial salah → tampilkan "Email atau password salah."
- `403` akun nonaktif → tampilkan "Akun admin nonaktif."

#### POST `/api/auth/user/register`

Body:
```json
{
  "name": "Andi",
  "email": "andi@example.com",
  "password": "password123",
  "phone_number": "08123...",   // opsional
  "address": "Jl. ..."          // opsional
}
```

Response 201: sama strukturnya dengan login, `data.user` berisi field profil.

Error:
- `422` email sudah terdaftar / validasi gagal.
- **Catatan UI**: langsung login-kan user setelah register sukses (tidak perlu kembali ke halaman login).

#### POST `/api/auth/user/login`

Body: sama dengan admin login.

Response 200: struktur sama, `data.user`.
Error:
- `401` kredensial salah.
- `403` akun nonaktif / banned → tampilkan `"Akun nonaktif."` atau `"Akun dibanned."`

#### POST `/api/auth/admin/logout` & `/api/auth/user/logout`

Header: `Authorization: Bearer <token>`.

Response 200:
```json
{ "success": true, "message": "Logout berhasil.", "data": null }
```

> Frontend: setelah dapat response 200, hapus token di storage & redirect ke `/login`.

---

### 5.2 User — Profile

#### GET `/api/user/profile`

Header: Bearer user.

Response 200:
```json
{
  "success": true,
  "data": {
    "id": "uuid", "name": "...", "email": "...", "phone_number": "...",
    "address": "...", "status": "active"
  }
}
```

#### PUT `/api/user/profile`

Body (semua field opsional, hanya yang dikirim akan di-update):
```json
{ "name": "...", "phone_number": "...", "address": "...", "password": "newpass123" }
```

> Untuk ganti password, kirim field `password`. Untuk ganti email, sertakan `email` (tidak boleh bentrok dengan user lain).

Response 200: payload profil yang sama.

Error:
- `422` validasi (email sudah dipakai, password < 6 char, dll).

---

### 5.3 User — Browse Master Data & Product

#### GET `/api/user/brands`

Response 200 `data`: array brand `[{ id, name, description, image }]`.

> Untuk dropdown "Pilih Brand" di form furniture.

#### GET `/api/user/categories`

Response 200 `data`: array kategori.

#### GET `/api/user/products`

Filter query:
- `search` (string, partial match name)
- `category_id` (uuid)
- `brand_id` (uuid)
- `page`, `per_page`

Response 200 `data.items`: array product. **Hanya yang `status: "available"`** yang dikembalikan.

Shape per item:
```json
{
  "id": "uuid",
  "name": "Sofa IKEA Friheten",
  "description": "...",
  "status": "available",
  "category": { "id": "...", "name": "Sofa" },
  "brand": { "id": "...", "name": "IKEA" },
  "images": ["/storage/products/...jpg"]
}
```

#### GET `/api/user/products/{id}`

Sama strukturnya, satu product.

---

### 5.4 User — Furnitures (CRUD)

> **IDOR prevention**: server otomatis cek bahwa furniture yang diakses/diubah milik user login. Tidak ada cara bypass dari frontend.

#### GET `/api/user/furnitures`

Filter: `status` (opsional, default semua).

Response 200 `data.items`: array furniture user login.

Shape per item:
```json
{
  "id": "uuid", "user_id": "uuid", "name": "Kursi Kayu Bekas",
  "description": "...", "status": "available",
  "category_id": "uuid|null", "category_text": null,
  "brand_id": "uuid|null", "brand_text": null,
  "category": { "id": "...", "name": "Sofa" } | null,
  "brand": { "id": "...", "name": "IKEA" } | null,
  "images": ["/storage/..."]
}
```

> **Catatan frontend**:
> - Status `available`     → bisa ditawarkan untuk tukar / diedit / dihapus.
> - Status `in_transaction`→ sedang dalam pengajuan, **tidak bisa diedit/dihapus**.
> - Status `swapped`        → sudah selesai ditukar, read-only.
> - Status `rejected`       → pengajuan sebelumnya ditolak, kembali available; bisa ditukar lagi.

#### POST `/api/user/furnitures`

Body:
```json
{
  "name": "Kursi Kayu Jati",
  "category_id": "uuid",         // atau
  "category_text": "Kursi",      // salah satu harus ada
  "brand_id": "uuid",            // atau
  "brand_text": "Custom",        // salah satu harus ada
  "description": "Kondisi 90%",
  "images": ["url1", "url2"]     // opsional, array URL string
}
```

Response 201 `data`: object furniture lengkap.

> **Catatan form**:
> - Radio button "Pilih Kategori/Brand" vs "Tulis Manual" — UI harus enforce minimal salah satu terisi (validasi server tetap memvalidasi).
> - `images` saat ini **bukan file upload**, hanya string URL. Integrasikan dengan CDN/object storage Anda; atau tambahkan endpoint upload terpisah (tidak termasuk scope spec).

#### GET `/api/user/furnitures/{id}`

Response 200 `data`: object furniture.

Error:
- `403` jika furniture bukan milik user login.

#### PUT `/api/user/furnitures/{id}`

Body: sama dengan POST, semua field opsional.

#### DELETE `/api/user/furnitures/{id}`

Soft delete. Response 200.

Error:
- `422` jika status = `in_transaction` → tampilkan "Furniture sedang dalam transaksi, tidak dapat dihapus."

#### POST `/api/user/furnitures/{id}/images`

Body:
```json
{ "image_url": "/storage/...", "sort_order": 0 }
```

Response 201 `data`: `{ id, image_url, sort_order }`.

#### DELETE `/api/user/furnitures/{id}/images/{imageId}`

Response 200.

---

### 5.5 User — Transactions

#### GET `/api/user/transactions`

Filter: `status` (opsional).

Response 200 `data.items`: array transaksi milik user login (beserta relasi furniture & product).

#### POST `/api/user/transactions`

Body:
```json
{
  "user_furniture_id": "uuid",  // harus milik user login + status "available"
  "product_id": "uuid"          // harus status "available"
}
```

Response 201 `data`: object transaction `status: "pending"`.

Error umum (422):
- Furniture bukan milik user.
- Furniture bukan `available` (mis. `in_transaction`, `swapped`).
- Product bukan `available` (mis. `swapped`).

> **Alur UI**: setelah sukses, redirect ke "Transaksi Saya" → detail transaksi. Tampilkan banner: "Pengajuan berhasil, menunggu verifikasi admin."

#### GET `/api/user/transactions/{id}`

Response 200 `data`: object transaction lengkap dengan relasi.

Error:
- `403` jika bukan milik user login.

---

### 5.6 User — Notifications

#### GET `/api/user/notifications`

Filter: `?unread_only=true` untuk hanya yang belum dibaca.

Response 200 `data.items`: array notifikasi user.

Shape per item:
```json
{
  "id": "uuid", "user_id": "uuid", "transaction_id": "uuid|null",
  "message": "Transaksi Anda disetujui oleh admin.",
  "read_at": null,
  "created_at": "2026-07-14T..."
}
```

> **Catatan UI**: badge counter di bell icon = jumlah `read_at IS NULL`.

#### PATCH `/api/user/notifications/{id}/read`

Response 200 `data`: notification yang sudah `read_at = now()`.

---

### 5.7 Admin — Dashboard

#### GET `/api/admin/dashboard`

Response 200 `data`:
```json
{
  "total_users": 12,
  "total_products": 25,
  "total_user_furnitures": 38,
  "total_items": 63,
  "total_transactions": 50,
  "transactions_by_status": {
    "pending": 5, "checking": 3, "approved": 30, "rejected": 12
  }
}
```

> **UI saran**: tampilkan sebagai stat tiles + pie chart breakdown status.

---

### 5.8 Admin — CRUD Master Data

Pattern CRUD yang sama untuk `admins`, `brands`, `categories`:

| Method | Path |
|--------|------|
| GET    | `/api/admin/{resource}`       — list + pagination |
| POST   | `/api/admin/{resource}`       — create |
| GET    | `/api/admin/{resource}/{id}`  — detail |
| PUT/PATCH | `/api/admin/{resource}/{id}` — update |
| DELETE | `/api/admin/{resource}/{id}`  — hapus |

Filter list `brands` & `categories`: `?search=...&page=...&per_page=...`.
Filter list `admins`: `?search=...&status=active|inactive&page=...&per_page=...`.

**Store admin** body:
```json
{ "name": "...", "email": "...", "password": "...", "status": "active" }
```

**Update admin** body: field opsional. `password` opsional (kosongkan/tidak kirim = tidak diubah).

**Store brand** body:
```json
{ "name": "IKEA", "description": "...", "image": "/storage/..." }
```

**Store category** body: sama dengan brand.

Error umum:
- `422` unique violation (email/nama sudah ada).
- `422` brand/category masih digunakan produk → "Brand masih digunakan oleh produk." (saat delete).

---

### 5.9 Admin — Products (CRUD + Images)

#### GET `/api/admin/products`

Filter: `?search=...&category_id=...&brand_id=...&status=...&page=...&per_page=...`.

Response: sama shape dengan user browse, tapi **semua status** dikembalikan (termasuk `swapped`, `inactive`, dan `deleted` via soft-delete).

#### POST `/api/admin/products`

Body:
```json
{
  "name": "Sofa Baru",
  "category_id": "uuid",
  "brand_id": "uuid",
  "description": "...",
  "status": "available",  // opsional, default available
  "images": ["/storage/a.jpg", "/storage/b.jpg"]  // opsional
}
```

Response 201 `data`: product lengkap dengan images.

#### PUT `/api/admin/products/{id}`

Body: field opsional. `images` **tidak** diupdate di sini (pakai endpoint image terpisah).

#### DELETE `/api/admin/products/{id}`

Soft delete. Response 200.

#### POST `/api/admin/products/{id}/images`

Body: `{ "image_url": "...", "sort_order": 0 }` (sort_order opsional, auto-increment jika tidak diisi).

#### DELETE `/api/admin/products/{id}/images/{imageId}`

Response 200.

> **Catatan frontend**: untuk re-order gambar, hapus & tambah ulang dengan `sort_order` yang diinginkan, atau tambahkan endpoint PATCH sort (tidak termasuk spec awal).

---

### 5.10 Admin — Transactions + State Machine

#### GET `/api/admin/transactions`

Filter: `?status=...&user_id=...&page=...&per_page=...`.

Response 200 `data.items`: semua transaksi (semua user).

#### GET `/api/admin/transactions/{id}`

Response 200 `data`: transaction + relasi (user, product, furniture, admin, messages).

#### PATCH `/api/admin/transactions/{id}/checking`

`pending → checking`. Response 200, `data.status = "checking"`.

Error:
- `422` jika status bukan `pending` (mis. sudah di-check).

#### PATCH `/api/admin/transactions/{id}/approve`

`checking → approved`. Response 200, `data.status = "approved"`. Side effect:
- `user_furnitures.status = "swapped"`
- `products.status = "swapped"`
- Notifikasi dibuat.

Error:
- `422` jika status bukan `checking`.

#### PATCH `/api/admin/transactions/{id}/reject`

Body:
```json
{ "reject_reason": "Lemari tidak sesuai spesifikasi." }
```

`* → rejected`. Response 200, `data.status = "rejected"`, `data.reject_reason` terisi.

Error:
- `422` validasi: `reject_reason` wajib (`min:3`).
- `422` jika status sudah `approved` atau `rejected` (status final).

---

### 5.11 Chat (Messages)

#### GET `/{admin|user}/transactions/{id}/messages`

Response 200 `data.items`: array pesan.

Shape:
```json
{
  "id": "uuid", "transaction_id": "uuid",
  "sender_id": "uuid", "sender_type": "admin" | "user",
  "message": "...",
  "created_at": "..."
}
```

> **Authorization**: admin bisa akses chat di transaksi manapun. User hanya bisa akses chat di transaksi miliknya sendiri (`403` jika coba akses transaksi orang lain).

#### POST `/{admin|user}/transactions/{id}/messages`

Body:
```json
{ "message": "Halo, kondisi furniture masih bagus." }
```

Response 201 `data`: object message.

Error:
- `422` jika `transaction.status != "checking"`. Pesan error: "Chat hanya tersedia saat transaksi berstatus checking (saat ini: pending)."

> **Saran UI realtime**: spec awal hanya REST. Untuk realtime, bisa di-upgrade ke:
> - Polling setiap 3-5 detik saat modal chat terbuka.
> - Laravel Echo + Reverb/Pusher (perlu setup broadcaster).

---

## 6. State Machine Transaksi

```
            admin: mulai cek
   pending ─────────────────► checking ─admin: approve─► approved [FINAL]
     │                            │
     │                            └─admin: reject─► rejected [FINAL]
     │
     └──admin: reject (langsung)──────────────────► rejected [FINAL]
```

### Aturan Bisnis (untuk UI hint)

| Status       | User bisa…                                       | Admin bisa…                          |
|--------------|--------------------------------------------------|--------------------------------------|
| `pending`    | Lihat status, menunggu. Tidak bisa chat.         | Mulai cek, atau reject langsung.     |
| `checking`   | Lihat status, **buka chat** dengan admin.        | Chat dengan user, approve, atau reject. |
| `approved`   | Lihat (read-only), furniture & product `swapped`.| Lihat (read-only).                   |
| `rejected`   | Lihat + baca `reject_reason`, furniture `available` lagi. | Lihat (read-only).            |

### Transisi Ilegal (server tolak 422)

- Approve transaksi yang status-nya `pending`, `approved`, atau `rejected` → 422.
- Reject transaksi yang status-nya `approved` atau `rejected` → 422.
- Mulai cek transaksi yang status-nya bukan `pending` → 422.
- Kirim/ambil chat saat status bukan `checking` → 422.

### Notifikasi Otomatis

Setiap perubahan status → 1 row baru di tabel `notifications` untuk user pemilik transaksi. Pesan yang dibuat:

| Transisi | Pesan notifikasi |
|----------|------------------|
| pending → checking | "Transaksi Anda sekarang dalam tahap pengecekan oleh admin." |
| checking → approved | "Transaksi Anda telah disetujui oleh admin." |
| * → rejected | "Transaksi Anda ditolak. Alasan: {reject_reason}" |

---

## 7. Snippet Frontend

### 7.1 Setup Axios Instance (Generic)

```ts
// src/lib/api.ts
import axios from 'axios';

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE ?? 'http://127.0.0.1:8088/api',
  headers: { 'Content-Type': 'application/json' },
});

// Inject token dari storage
api.interceptors.request.use((cfg) => {
  const token = localStorage.getItem('ep_token');
  if (token) cfg.headers.Authorization = `Bearer ${token}`;
  return cfg;
});

// Auto-redirect ke login saat 401
api.interceptors.response.use(
  (r) => r,
  (err) => {
    if (err.response?.status === 401) {
      localStorage.removeItem('ep_token');
      localStorage.removeItem('ep_role');
      window.location.href = '/login';
    }
    return Promise.reject(err);
  }
);
```

### 7.2 Login (React)

```ts
async function login(email: string, password: string) {
  const { data } = await api.post('/auth/user/login', { email, password });
  // data.data.access_token, data.data.user
  localStorage.setItem('ep_token', data.data.access_token);
  localStorage.setItem('ep_role', 'user');
  return data.data.user;
}
```

### 7.3 Render Error Validasi

```ts
function firstError(errorObj?: Record<string, string[]>) {
  if (!errorObj) return null;
  const first = Object.values(errorObj).flat()[0];
  return first ?? null;
}

// di component
const message = firstError(err.response?.data?.error);
toast.error(message ?? 'Terjadi kesalahan.');
```

### 7.4 Submit Transaksi

```ts
async function submitTransaction(userFurnitureId: string, productId: string) {
  try {
    const { data } = await api.post('/user/transactions', {
      user_furniture_id: userFurnitureId,
      product_id: productId,
    });
    toast.success(data.message);
    router.push(`/transactions/${data.data.id}`);
  } catch (err: any) {
    if (err.response?.status === 422) {
      // Validasi field (mis. UUID format salah)
      toast.error(firstError(err.response.data.error));
    } else if (err.response?.status === 403) {
      toast.error('Furniture bukan milik Anda.');
    } else {
      toast.error(err.response?.data?.message ?? 'Gagal mengajukan tukar.');
    }
  }
}
```

### 7.5 Admin: Reject dengan Reason

```tsx
function RejectButton({ transactionId, onDone }: Props) {
  const [reason, setReason] = useState('');
  const [loading, setLoading] = useState(false);

  async function reject() {
    if (reason.trim().length < 3) {
      toast.error('Alasan minimal 3 karakter.');
      return;
    }
    setLoading(true);
    try {
      const { data } = await api.patch(
        `/admin/transactions/${transactionId}/reject`,
        { reject_reason: reason }
      );
      toast.success(data.message);
      onDone();
    } catch (err: any) {
      toast.error(err.response?.data?.error?.reject_reason?.[0] ?? 'Gagal menolak.');
    } finally {
      setLoading(false);
    }
  }

  return ( /* modal/prompt UI */ );
}
```

### 7.6 Polling Chat Sederhana (saat modal chat terbuka)

```ts
useEffect(() => {
  const interval = setInterval(async () => {
    const { data } = await api.get(`/user/transactions/${trxId}/messages`);
    setMessages(data.data.items);
  }, 4000);
  return () => clearInterval(interval);
}, [trxId]);
```

---

## 8. Postman Collection

File `postman_collection.json` di root repo — import ke Postman:

1. Buka Postman → **Import** → drag file `postman_collection.json`.
2. Set environment variable:
   - `base_url` = `http://127.0.0.1:8088/api`
   - `admin_token` (otomatis ter-set dari response login admin)
   - `user_token`  (otomatis ter-set dari response login user)
3. Login admin/user dulu untuk auto-populate token, lalu endpoint lain sudah pre-filled dengan `{{base_url}}` & `{{admin_token}}` / `{{user_token}}`.

---

## Quick Reference Tabel

| Aksi                                             | Method | Path                                                       |
|--------------------------------------------------|--------|------------------------------------------------------------|
| Login admin                                      | POST   | `/api/auth/admin/login`                                    |
| Login user                                       | POST   | `/api/auth/user/login`                                     |
| Register user                                    | POST   | `/api/auth/user/register`                                  |
| Logout                                           | POST   | `/api/auth/{admin\|user}/logout`                           |
| Lihat profile sendiri                            | GET    | `/api/user/profile`                                        |
| Update profile                                   | PUT    | `/api/user/profile`                                        |
| List product (user)                              | GET    | `/api/user/products`                                       |
| Detail product (user)                            | GET    | `/api/user/products/{id}`                                  |
| List dropdown brand                              | GET    | `/api/user/brands`                                         |
| List dropdown kategori                           | GET    | `/api/user/categories`                                     |
| List furniture sendiri                           | GET    | `/api/user/furnitures`                                     |
| Tambah furniture                                 | POST   | `/api/user/furnitures`                                     |
| Edit furniture                                   | PUT    | `/api/user/furnitures/{id}`                                |
| Hapus furniture                                  | DELETE | `/api/user/furnitures/{id}`                                |
| Tambah gambar furniture                           | POST   | `/api/user/furnitures/{id}/images`                         |
| Hapus gambar furniture                           | DELETE | `/api/user/furnitures/{id}/images/{imageId}`               |
| Ajukan transaksi tukar                           | POST   | `/api/user/transactions`                                   |
| List transaksi sendiri                           | GET    | `/api/user/transactions`                                   |
| Detail transaksi sendiri                         | GET    | `/api/user/transactions/{id}`                              |
| Lihat pesan chat                                 | GET    | `/user/transactions/{id}/messages`                         |
| Kirim pesan chat                                 | POST   | `/user/transactions/{id}/messages`                         |
| Lihat notifikasi                                 | GET    | `/api/user/notifications`                                  |
| Tandai notifikasi terbaca                        | PATCH  | `/api/user/notifications/{id}/read`                        |
| Dashboard admin                                  | GET    | `/api/admin/dashboard`                                     |
| CRUD admin                                       | *      | `/api/admin/admins`                                        |
| CRUD brand                                       | *      | `/api/admin/brands`                                        |
| CRUD kategori                                    | *      | `/api/admin/categories`                                    |
| CRUD product                                     | *      | `/api/admin/products`                                      |
| Tambah gambar product                            | POST   | `/api/admin/products/{id}/images`                          |
| Hapus gambar product                             | DELETE | `/api/admin/products/{id}/images/{imageId}`                |
| List semua transaksi                             | GET    | `/api/admin/transactions`                                  |
| Detail transaksi (admin)                         | GET    | `/api/admin/transactions/{id}`                             |
| pending → checking                               | PATCH  | `/api/admin/transactions/{id}/checking`                    |
| checking → approved                              | PATCH  | `/api/admin/transactions/{id}/approve`                     |
| pending/checking → rejected                      | PATCH  | `/api/admin/transactions/{id}/reject`                      |
| Chat admin                                       | GET/POST | `/api/admin/transactions/{id}/messages`                  |
