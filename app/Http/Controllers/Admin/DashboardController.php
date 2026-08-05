<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserFurniture;
use Illuminate\Http\JsonResponse;

class DashboardController extends BaseController
{
    public function index(): JsonResponse
    {
        $totalUsers    = User::count();
        $totalProducts = Product::count();
        $totalUserFurnitures = UserFurniture::count();

        $transactionBreakdown = Transaction::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return $this->sendResponse([
            'total_users'             => $totalUsers,
            'total_products'          => $totalProducts,
            'total_user_furnitures'   => $totalUserFurnitures,
            'total_items'             => $totalProducts + $totalUserFurnitures,
            'total_transactions'      => (int) $transactionBreakdown->sum(),
            'transactions_by_status'  => [
                'pending'   => (int) ($transactionBreakdown['pending']   ?? 0),
                'checking'  => (int) ($transactionBreakdown['checking']  ?? 0),
                'approved'  => (int) ($transactionBreakdown['approved']  ?? 0),
                'rejected'  => (int) ($transactionBreakdown['rejected']  ?? 0),
            ],
        ]);
    }
}