<?php

use App\Http\Controllers\Admin\AdminController as AdminAdminController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\BrandController as AdminBrandController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\TransactionController as AdminTransactionController;
use App\Http\Controllers\Api\TransactionMessageController;
use App\Http\Controllers\User\AuthController as UserAuthController;
use App\Http\Controllers\User\FurnitureController;
use App\Http\Controllers\User\GoogleAuthController;
use App\Http\Controllers\User\NotificationController;
use App\Http\Controllers\User\ProductController as UserProductController;
use App\Http\Controllers\User\ProfileController;
use App\Http\Controllers\User\TransactionController as UserTransactionController;
use Illuminate\Support\Facades\Route;

// ============================================================================
// AUTH (public)
// ============================================================================
Route::prefix('auth')->group(function () {
    Route::post('systemAdmin/login', [AdminAuthController::class, 'login']);

    Route::post('user/register', [UserAuthController::class, 'register']);
    Route::post('user/login',    [UserAuthController::class, 'login']);

    Route::post('systemAdmin/logout', [AdminAuthController::class, 'logout'])->middleware('role:admin');
    Route::post('user/logout',  [UserAuthController::class, 'logout'])->middleware('role:user');

    Route::get('user/google/redirect', [GoogleAuthController::class, 'redirectToGoogle']);
    Route::post('user/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);
});

// ============================================================================
// ADMIN ROUTES (token admin only)
// ============================================================================
Route::prefix('systemAdmin')->middleware('role:admin')->group(function () {
    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index']);

    // CRUD admins
    Route::apiResource('admins', AdminAdminController::class);

    // CRUD brands & categories
    Route::apiResource('brands', AdminBrandController::class);
    Route::apiResource('categories', AdminCategoryController::class);

    // CRUD products + images
    Route::apiResource('products', AdminProductController::class);
    Route::post('products/{id}/images',       [AdminProductController::class, 'storeImage']);
    Route::patch('products/{id}/images/reorder', [AdminProductController::class, 'reorderImages']);
    Route::delete('products/{id}/images/{imageId}', [AdminProductController::class, 'destroyImage']);

    // Transactions
    Route::get('transactions',                 [AdminTransactionController::class, 'index']);
    Route::get('transactions/{id}',            [AdminTransactionController::class, 'show']);
    Route::patch('transactions/{id}/checking', [AdminTransactionController::class, 'startChecking']);
    Route::patch('transactions/{id}/approve',  [AdminTransactionController::class, 'approve']);
    Route::patch('transactions/{id}/reject',   [AdminTransactionController::class, 'reject']);
    Route::patch('transactions/{id}/set-price',[AdminTransactionController::class, 'setPrice']);

    // Transaction messages (chat)
    Route::get('transactions/{id}/messages',   [TransactionMessageController::class, 'index']);
    Route::post('transactions/{id}/messages',  [TransactionMessageController::class, 'store']);
});

// ============================================================================
// USER ROUTES (token user only)
// ============================================================================
Route::prefix('user')->middleware('role:user')->group(function () {
    // Profile
    Route::get('profile',  [ProfileController::class, 'show']);
    Route::put('profile',  [ProfileController::class, 'update']);

    // Read-only master data (for dropdowns)
    Route::get('brands',     [UserProductController::class, 'brands']);
    Route::get('categories', [UserProductController::class, 'categories']);

    // Browse products
    Route::get('products',      [UserProductController::class, 'index']);
    Route::get('products/{id}', [UserProductController::class, 'show']);

    // User furnitures (own only, via policy)
    Route::apiResource('furnitures', FurnitureController::class);
    Route::post('furnitures/{id}/images',          [FurnitureController::class, 'storeImage']);
    Route::delete('furnitures/{id}/images/{imageId}', [FurnitureController::class, 'destroyImage']);

    // Transactions
    Route::get('transactions',      [UserTransactionController::class, 'index']);
    Route::post('transactions',     [UserTransactionController::class, 'store']);
    Route::get('transactions/{id}', [UserTransactionController::class, 'show']);

    // Transaction messages (chat)
    Route::get('transactions/{id}/messages',  [TransactionMessageController::class, 'index']);
    Route::post('transactions/{id}/messages', [TransactionMessageController::class, 'store']);

    // Notifications
    Route::get('notifications',                  [NotificationController::class, 'index']);
    Route::patch('notifications/{id}/read',     [NotificationController::class, 'markAsRead']);
});

// ============================================================================
// Health / root
// ============================================================================
Route::get('/', fn () => response()->json([
    'success' => true,
    'message' => 'Exchange Product API — silakan baca README untuk dokumentasi.',
    'data'    => null,
    'status'  => 200,
], 200));