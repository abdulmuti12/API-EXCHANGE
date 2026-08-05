<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Notification;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserFurniture;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    public function submit(User $user, string $userFurnitureId, string $productId): Transaction
    {
        return DB::transaction(function () use ($user, $userFurnitureId, $productId) {
            $furniture = UserFurniture::lockForUpdate()->find($userFurnitureId);
            $product   = Product::lockForUpdate()->find($productId);

            if (! $furniture) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Furniture tidak ditemukan.',
                    'error'   => ['user_furniture_id' => ['Data tidak ditemukan.']],
                    'status'  => 404,
                ], 404));
            }

            if (! $product) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Product tidak ditemukan.',
                    'error'   => ['product_id' => ['Data tidak ditemukan.']],
                    'status'  => 404,
                ], 404));
            }

            if ($furniture->user_id !== $user->id) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Furniture bukan milik Anda.',
                    'error'   => ['user_furniture_id' => ['Tidak diizinkan.']],
                    'status'  => 403,
                ], 403));
            }

            if ($furniture->status !== 'available') {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Furniture tidak tersedia untuk ditukar.',
                    'error'   => ['user_furniture_id' => ["Status furniture saat ini: {$furniture->status}."]],
                    'status'  => 422,
                ], 422));
            }

            if ($product->status !== 'available') {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Product tidak tersedia untuk ditukar.',
                    'error'   => ['product_id' => ["Status product saat ini: {$product->status}."]],
                    'status'  => 422,
                ], 422));
            }

            $transaction = Transaction::create([
                'user_id'           => $user->id,
                'user_furniture_id' => $furniture->id,
                'product_id'        => $product->id,
                'status'            => Transaction::STATUS_PENDING,
            ]);

            $furniture->update(['status' => 'in_transaction']);

            return $transaction->load(['userFurniture', 'product', 'user']);
        });
    }

    public function startChecking(Admin $admin, Transaction $transaction): Transaction
    {
        if ($transaction->status !== Transaction::STATUS_PENDING) {
            abort(response()->json([
                'success' => false,
                'message' => 'Transaksi tidak dapat dimulai pengecekannya.',
                'error'   => ['status' => ["Status transaksi saat ini: {$transaction->status}."]],
                'status'  => 422,
            ], 422));
        }

        return DB::transaction(function () use ($admin, $transaction) {
            $transaction->update([
                'status'   => Transaction::STATUS_CHECKING,
                'admin_id' => $admin->id,
            ]);

            $this->notify($transaction, "Transaksi Anda sekarang dalam tahap pengecekan oleh admin.");

            return $transaction->fresh()->load(['userFurniture', 'product', 'user', 'admin']);
        });
    }

    public function approve(Admin $admin, Transaction $transaction): Transaction
    {
        if ($transaction->status !== Transaction::STATUS_CHECKING) {
            abort(response()->json([
                'success' => false,
                'message' => 'Transaksi hanya dapat disetujui dari status checking.',
                'error'   => ['status' => ["Status transaksi saat ini: {$transaction->status}."]],
                'status'  => 422,
            ], 422));
        }

        return DB::transaction(function () use ($admin, $transaction) {
            $transaction->update([
                'status'   => Transaction::STATUS_APPROVED,
                'admin_id' => $admin->id,
            ]);

            $furniture = UserFurniture::lockForUpdate()->find($transaction->user_furniture_id);
            $product   = Product::lockForUpdate()->find($transaction->product_id);

            if ($furniture) {
                $furniture->update(['status' => 'swapped']);
            }
            if ($product) {
                $product->update(['status' => 'swapped']);
            }

            $this->notify($transaction, "Transaksi Anda telah disetujui oleh admin.");

            return $transaction->fresh()->load(['userFurniture', 'product', 'user', 'admin']);
        });
    }

    public function reject(Admin $admin, Transaction $transaction, string $reason): Transaction
    {
        if (in_array($transaction->status, [Transaction::STATUS_APPROVED, Transaction::STATUS_REJECTED], true)) {
            abort(response()->json([
                'success' => false,
                'message' => 'Transaksi sudah pada status final, tidak dapat ditolak.',
                'error'   => ['status' => ["Status transaksi saat ini: {$transaction->status}."]],
                'status'  => 422,
            ], 422));
        }

        return DB::transaction(function () use ($admin, $transaction, $reason) {
            $transaction->update([
                'status'        => Transaction::STATUS_REJECTED,
                'admin_id'      => $admin->id,
                'reject_reason' => $reason,
            ]);

            $furniture = UserFurniture::lockForUpdate()->find($transaction->user_furniture_id);
            if ($furniture && $furniture->status === 'in_transaction') {
                $furniture->update(['status' => 'available']);
            }

            $this->notify($transaction, "Transaksi Anda ditolak. Alasan: {$reason}");

            return $transaction->fresh()->load(['userFurniture', 'product', 'user', 'admin']);
        });
    }

    protected function notify(Transaction $transaction, string $message): void
    {
        Notification::create([
            'user_id'       => $transaction->user_id,
            'transaction_id'=> $transaction->id,
            'message'       => $message,
        ]);
    }
}