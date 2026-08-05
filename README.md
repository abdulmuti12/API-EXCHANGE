# Exchange Product — REST API

Sistem tukar barang furniture dengan 2 role terpisah (**admin** & **user**), JWT auth, MySQL, response pattern konsisten. Implementasi mengikuti spesifikasi di [system-flow.md](./system-flow.md).

## Stack

- **Laravel** 11
- **tymon/jwt-auth** ^2.3
- **MySQL** 10.4+ (MariaDB OK)
- **UUID** primary key di semua tabel

## Setup

### 1. Install dependencies

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

### 2. Konfigurasi database (`.env`)

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=exchange_product
DB_USERNAME=root
DB_PASSWORD=
DB_SOCKET=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock
```

Buat database: `CREATE DATABASE exchange_product CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`

### 3. Migrasi + seed

```bash
php artisan migrate:fresh --seed
```

Seeder akan menambahkan:
- Admin: `admin@example.com` / `password123`
- User: `user@example.com` / `password123`
- 2 brand (IKEA, Informa), 2 kategori (Sofa, Meja), 2 product contoh, 1 furniture contoh.

### 4. Jalankan server

```bash
php artisan serve --host=127.0.0.1 --port=8088
```

API tersedia di `http://127.0.0.1:8088/api`.

### 5. Test

```bash
php artisan test
```

19 test lulus (1.0s), mencakup:
- Login & register (admin/user)
- Role-separation (admin↔user 403, tanpa token 401)
- Submit transaksi (validasi furniture milik sendiri & berstatus available)
- State machine lengkap (pending → checking → approved/rejected)
- Approve/reject dari status final ditolak
- `reject_reason` wajib divalidasi
- Chat hanya saat status `checking`
- IDOR prevention (user tidak bisa akses furniture/transaksi user lain)

## Response Pattern

```json
{
  "success": true|false,
  "message": "string|null",
  "data":    object|array|null,
  "error":   object|null,
  "status":  200
}
```

HTTP status code yang dipakai:

| Code | Kapan |
|------|-------|
| 200  | OK (GET, PUT, PATCH) |
| 201  | Created (POST) |
| 401  | Token tidak ada / tidak valid / kadaluarsa |
| 403  | Role tidak sesuai, atau akses resource orang lain (IDOR) |
| 404  | Resource tidak ditemukan |
| 422  | Validasi gagal, atau transisi state machine ilegal |
| 500  | Server error (debug message hanya saat `APP_DEBUG=true`) |

## Endpoints

### Auth (public)

| Method | Path | Body |
|--------|------|------|
| POST | `/api/auth/admin/login` | `email`, `password` |
| POST | `/api/auth/user/register` | `name`, `email`, `password`, `phone_number?`, `address?` |
| POST | `/api/auth/user/login` | `email`, `password` |
| POST | `/api/auth/admin/logout` | header `Authorization: Bearer <admin token>` |
| POST | `/api/auth/user/logout` | header `Authorization: Bearer <user token>` |

### Admin (header `Authorization: Bearer <admin token>`)

| Method | Path | Keterangan |
|--------|------|------------|
| GET    | `/api/admin/dashboard` | Statistik agregat |
| GET/POST | `/api/admin/admins` | CRUD admin |
| GET/PUT/DELETE | `/api/admin/admins/{id}` | |
| GET/POST | `/api/admin/brands` | CRUD brand |
| GET/PUT/DELETE | `/api/admin/brands/{id}` | |
| GET/POST | `/api/admin/categories` | CRUD kategori |
| GET/PUT/DELETE | `/api/admin/categories/{id}` | |
| GET/POST | `/api/admin/products` | CRUD product katalog |
| GET/PUT/DELETE | `/api/admin/products/{id}` | |
| POST | `/api/admin/products/{id}/images` | Tambah gambar product |
| DELETE | `/api/admin/products/{id}/images/{imageId}` | Hapus gambar |
| GET    | `/api/admin/transactions` | List semua transaksi |
| GET    | `/api/admin/transactions/{id}` | Detail + messages |
| PATCH  | `/api/admin/transactions/{id}/checking` | pending → checking |
| PATCH  | `/api/admin/transactions/{id}/approve` | checking → approved |
| PATCH  | `/api/admin/transactions/{id}/reject` | → rejected (body: `reject_reason`) |
| GET/POST | `/api/admin/transactions/{id}/messages` | Chat (hanya saat checking) |

### User (header `Authorization: Bearer <user token>`)

| Method | Path | Keterangan |
|--------|------|------------|
| GET/PUT | `/api/user/profile` | Lihat & update profile sendiri |
| GET    | `/api/user/brands` | Dropdown brand |
| GET    | `/api/user/categories` | Dropdown kategori |
| GET    | `/api/user/products` | Browse katalog (hanya status `available`) |
| GET    | `/api/user/products/{id}` | Detail product |
| GET/POST | `/api/user/furnitures` | List & tambah furniture sendiri |
| GET/PUT/DELETE | `/api/user/furnitures/{id}` | Update/hapus (policy: milik sendiri) |
| POST   | `/api/user/furnitures/{id}/images` | Tambah gambar furniture |
| DELETE | `/api/user/furnitures/{id}/images/{imageId}` | Hapus gambar |
| GET    | `/api/user/transactions` | List transaksi sendiri |
| POST   | `/api/user/transactions` | Ajukan tukar (body: `user_furniture_id`, `product_id`) |
| GET    | `/api/user/transactions/{id}` | Detail transaksi sendiri |
| GET/POST | `/api/user/transactions/{id}/messages` | Chat (hanya saat checking) |
| GET    | `/api/user/notifications` | List notifikasi (`?unread_only=true`) |
| PATCH  | `/api/user/notifications/{id}/read` | Tandai terbaca |

## State Machine Transaksi

```
              admin: mulai cek
   pending ────────────────────────► checking ───admin: approve──► approved [FINAL]
     │                                  │
     │                                  └──admin: reject──► rejected [FINAL]
     └──admin: reject (langsung)───────────────────────────► rejected [FINAL]
```

Semua transisi ditangani di `app/Services/TransactionService.php` dalam `DB::transaction()`:

- `pending → checking` — buka chat, set `admin_id`.
- `checking → approved` — `user_furnitures.status = swapped`, `products.status = swapped`.
- `* → rejected` — `reject_reason` wajib (divalidasi di Form Request), `user_furnitures.status` kembali ke `available`.
- Status `approved` & `rejected` **final** — endpoint update menolak (422).

Notifikasi dibuat otomatis setiap perubahan status.

## Struktur Folder

```
app/
  Http/
    Controllers/
      Api/BaseController.php         ← response pattern konsisten
      Admin/                          ← semua controller admin
      User/                           ← semua controller user
    Middleware/EnsureRole.php         ← cek klaim role di JWT
    Requests/{Admin,User,Message}/    ← Form Request (validasi)
    Resources/                        ← JSON Resource
  Models/                             ← Eloquent + relasi
  Policies/                           ← UserFurniturePolicy, TransactionPolicy
  Services/TransactionService.php     ← state machine + DB::transaction
database/migrations/                  ← urutan: admins → users → brands → categories → products → images → user_furnitures → images → transactions → messages → notifications
routes/api.php                        ← grup /auth, /admin/*, /user/*
tests/Feature/                        ← 19 test lulus
```

## Postman Collection (opsional)

Untuk generate Postman collection otomatis dari route, pasang:

```bash
composer require --dev knuckleswtf/scribe
php artisan vendor:publish --tag=scribe-config
php artisan scribe:generate
```

Output HTML di `public/docs`, Postman collection di `public/docs/collection.json`.

## Catatan Implementasi

- **Validasi `reject_reason`:** divalidasi server-side di `Form Request`, bukan hanya UI.
- **IDOR prevention:** furniture & transaksi user dicek via `Policy` + filter query `where user_id = auth()->id()`.
- **Role separation:** `EnsureRole` middleware membandingkan klaim `role` di JWT payload dengan role yang diharapkan route; token admin ditolak di route user dan sebaliknya.
- **Soft delete:** `products` & `user_furnitures` pakai `SoftDeletes` agar referensi transaksi lama tidak hilang.
- **Pagination:** semua endpoint list menerima `?page=N&per_page=M` (default 15).
- **Auto notification:** setiap perubahan status transaksi otomatis membuat record di tabel `notifications` (saat ini hanya insert — pengiriman real-time/WebSocket belum diimplementasi sesuai spek "opsional").
