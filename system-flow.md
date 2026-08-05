Spesifikasi Implementasi — Sistem Tukar Barang Furniture


Versi tersusun ulang dari spek awal, disusun per-fase supaya bisa langsung dieksekusi (misal oleh Claude Code) tanpa perlu menebak urutan kerja.



0. Ringkasan Proyek

REST API Laravel untuk sistem tukar barang furniture dengan 2 role terpisah (admin & user), JWT auth, MySQL, response pattern konsisten, plus dokumentasi API & Postman collection.

Stack: Laravel (terbaru stabil) · tymon/jwt-auth · MySQL · UUID sebagai primary key semua tabel.


1. Struktur Folder yang Disarankan

app/
  Http/
    Controllers/
      Api/
        BaseController.php        <- semua controller extend ini
      Admin/
        AuthController.php
        AdminController.php       <- CRUD data admin
        BrandController.php
        CategoryController.php
        ProductController.php
        TransactionController.php
        DashboardController.php
      User/
        AuthController.php
        ProfileController.php
        FurnitureController.php
        ProductController.php     <- browse katalog (read-only)
        TransactionController.php
    Requests/
      Admin/...
      User/...
    Resources/
      ProductResource.php, UserFurnitureResource.php, TransactionResource.php, dst.
    Middleware/
      EnsureRole.php               <- cek klaim role di JWT
  Models/
  Services/
    TransactionService.php         <- logic state machine + DB transaction wrapping
routes/
  api.php                          <- grup: /auth, /admin/*, /user/*
database/
  migrations/
  seeders/


2. Urutan Migrasi (penting — urutan menentukan FK yang valid)


admins
users
brands
categories
products (FK → brands, categories)
product_images (FK → products)
user_furnitures (FK → users; category_id & brand_id nullable → categories/brands)
user_furniture_images (FK → user_furnitures)
transactions (FK → users, user_furnitures, products, admins nullable)
transaction_messages (FK → transactions)
notifications (opsional, FK → users)


Skema tabel (ringkas)

TabelKolom kunciCatatanadminsid(uuid), name, email(unique), password(hash), status(active/inactive)usersid(uuid), name, email(unique), phone_number, password(hash), address, status(active/inactive/banned)brandsid(uuid), name(unique), description?, image?categoriesid(uuid), name(unique), description?, image?productsid(uuid), name, category_id(FK), brand_id(FK), description, status(available/swapped/inactive)+ deleted_at (soft delete)product_imagesid(uuid), product_id(FK), image_url, sort_orderuser_furnituresid(uuid), user_id(FK, wajib), name, category_id(FK)?, category_text?, brand_id(FK)?, brand_text?, description, status(available/in_transaction/swapped/rejected)+ deleted_at; category/brand pakai salah satu: id (dari master) atau text (manual)user_furniture_imagesid(uuid), user_furniture_id(FK), image_url, sort_ordertransactionsid(uuid), user_id(FK), user_furniture_id(FK), product_id(FK), status(pending/checking/rejected/approved), admin_id(FK)?, reject_reason?reject_reason wajib jika status rejectedtransaction_messagesid(uuid), transaction_id(FK), sender_id, sender_type(admin/user), messageaktif hanya saat transaction.status = checkingnotifications (opsional)id(uuid), user_id(FK), transaction_id(FK)?, message, read_at?dikirim tiap status transaksi berubah

Index & constraint wajib: index semua kolom FK, unique index pada email (admins & users), deleted_at untuk soft delete pada products & user_furnitures.


3. Autentikasi & Otorisasi


Guard terpisah di config/auth.php: guard admin dan guard user, masing-masing JWT dengan provider ke tabel admins / users.
Endpoint:

POST /api/auth/admin/login
POST /api/auth/user/register
POST /api/auth/user/login
POST /api/auth/{admin|user}/logout



JWT payload minimal: { sub: id, role: "admin" | "user", exp }.
Middleware EnsureRole:admin / EnsureRole:user mengecek klaim role dari token — token admin tidak boleh tembus ke route user dan sebaliknya. Ini wajib diuji secara eksplisit (test: token admin hit endpoint user → harus 403).
Password: Hash::make() (bcrypt default Laravel), jangan simpan plain text.



4. Response Pattern — Base Controller (dengan koreksi)

Pola dari spek awal sudah bagus untuk konsistensi, tapi sendError selalu mengembalikan HTTP 401 untuk semua jenis error, itu salah secara semantik (401 seharusnya khusus "unauthenticated"). Validasi harus 422, not found 404, forbidden 403, server error 500. Perbaikannya: jadikan status code parameter dengan default yang masuk akal.

php<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class BaseController extends Controller
{
    public function sendResponse($result, $message = null, $code = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $result,
            'status'  => $code,
        ], $code);
    }

    public function sendError($error, $message = null, $code = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error'   => $error,
            'status'  => $code,
        ], $code);
    }
}

Semua controller (Admin & User) extend BaseController ini, supaya bentuk response konsisten di seluruh endpoint.


5. Endpoint API per Modul

Semua endpoint list di bawah wajib pagination (mis. ?page=1&per_page=15).

5.1 Auth

MethodPathRolePOST/auth/admin/loginpublicPOST/auth/user/registerpublicPOST/auth/user/loginpublicPOST/auth/admin/logoutadminPOST/auth/user/logoutuser

5.2 Admin Management (admin only)

CRUD /admin/admins (index, store, show, update, destroy)

5.3 Brand & Category (admin CRUD, user read-only)


Admin: CRUD penuh /admin/brands, /admin/categories
User: GET /user/brands, GET /user/categories (untuk dropdown pilihan)


5.4 Product + Product Images (katalog admin)


Admin: CRUD /admin/products, plus kelola gambar: POST /admin/products/{id}/images, DELETE /admin/products/{id}/images/{imageId}
User: GET /user/products (list+filter+pagination), GET /user/products/{id} (detail)


5.5 User Furniture + Images (milik user sendiri)


GET/POST /user/furnitures, GET/PUT/DELETE /user/furnitures/{id} — hanya furniture milik user login (policy check)
Gambar: POST /user/furnitures/{id}/images, DELETE /user/furnitures/{id}/images/{imageId}


5.6 Transactions


User: POST /user/transactions (ajukan tukar: pilih furniture sendiri + product target), GET /user/transactions (hanya milik sendiri), GET /user/transactions/{id}
Admin: GET /admin/transactions (semua transaksi), GET /admin/transactions/{id}, PATCH /admin/transactions/{id}/checking, PATCH /admin/transactions/{id}/approve, PATCH /admin/transactions/{id}/reject (body: reject_reason wajib)


5.7 Transaction Messages (chat)


GET /{admin|user}/transactions/{id}/messages, POST /{admin|user}/transactions/{id}/messages
Validasi di endpoint POST: tolak jika transaction.status != checking


5.8 Profile (user)

GET /user/profile, PUT /user/profile

5.9 Dashboard (admin)

GET /admin/dashboard → total user, total transaksi (breakdown per status), total barang (product + user_furniture)

5.10 Notifications (opsional)

GET /user/notifications, PATCH /user/notifications/{id}/read


6. State Machine Transaksi — Detail Aturan

pending --(admin: mulai cek)--> checking --(admin: approve)--> approved   [FINAL]
   |                                |
   |                                '--(admin: reject, reject_reason wajib)--> rejected [FINAL]
   '--(admin: reject langsung, reject_reason wajib)-----------------------> rejected [FINAL]

Aturan implementasi (di TransactionService, jalankan dalam DB::transaction()):


Saat submit (pending): validasi user_furniture milik user yang login & berstatus available; validasi product berstatus available.
pending → checking: buka chat (transaction_messages aktif).
checking → approved: side effect atomik — transactions.status = approved, user_furnitures.status = swapped, products.status = swapped.
→ rejected (dari pending atau checking): reject_reason wajib divalidasi ada isinya (Form Request); side effect — user_furnitures.status kembali ke available.
approved / rejected adalah final — endpoint update harus menolak (422/409) kalau transaksi sudah di status final.



7. Checklist Business Rules (jangan sampai kelewat)


 reject_reason wajib diisi setiap kali status → rejected (validasi di Form Request, bukan cuma di frontend)
 Approve transaksi = 3 update sekaligus, wajib dibungkus DB::transaction() supaya atomik
 Chat (transaction_messages) hanya bisa diakses/dikirim saat transaction.status == checking
 User hanya boleh lihat/edit furniture & transaksi miliknya sendiri (Policy/Gate, bukan cuma filter query — cegah IDOR)
 Middleware role memastikan token admin ≠ bisa akses route user, dan sebaliknya
 Semua endpoint list pakai pagination
 Soft delete (deleted_at) di products & user_furnitures supaya transaksi lama tidak kehilangan referensi
 Index di semua FK + unique index email



8. Dokumentasi API & Postman


Struktur folder Postman collection mengikuti modul di atas (Auth, Admin - Brand/Category/Product/Transaction/Dashboard, User - Profile/Furniture/Product/Transaction).
Environment variables: {{base_url}}, {{admin_token}}, {{user_token}} (auto-set via script "Tests" pada request login, simpan token ke environment).
Opsional: pakai package knuckleswtf/scribe untuk generate dokumentasi API otomatis dari route + docblock Laravel, sekaligus bisa export ke format Postman collection — lebih hemat waktu daripada tulis manual.



9. Roadmap Implementasi Bertahap


Setup project Laravel + install tymon/jwt-auth, konfigurasi guard admin/user
Migrasi seluruh tabel (urutan di bagian 2) + Models + relasi Eloquent
Auth endpoints (admin login, user register/login) + middleware EnsureRole
CRUD master data: brands, categories (admin)
CRUD products + product_images (admin) + endpoint browse (user, read-only)
CRUD user_furnitures + user_furniture_images (user, dengan Policy kepemilikan)
Transaksi: submit (user) + kelola status/state machine (admin) via TransactionService + DB::transaction()
transaction_messages (chat) — REST dulu; upgrade ke WebSocket (Laravel Reverb/Pusher) belakangan kalau perlu real-time
Dashboard admin (agregat statistik)
Notifications (opsional)
Postman collection + dokumentasi API (atau setup Scribe)
Feature test: fokus ke middleware role-separation dan seluruh transisi state machine (termasuk percobaan transisi ilegal dari status final)

