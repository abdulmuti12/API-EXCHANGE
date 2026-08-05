<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\RejectTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends BaseController
{
    public function __construct(protected TransactionService $service) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);

        $query = Transaction::query()
            ->with(['userFurniture.images', 'product.images', 'product.category', 'product.brand', 'user', 'admin'])
            ->orderBy('created_at', 'desc');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        $items = $query->paginate($perPage);

        return $this->sendResponse([
            'items' => TransactionResource::collection($items->items())->resolve(),
            'meta'  => [
                'current_page' => $items->currentPage(),
                'per_page'     => $items->perPage(),
                'total'        => $items->total(),
                'last_page'    => $items->lastPage(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $transaction = Transaction::with([
            'userFurniture.images',
            'product.images',
            'product.category',
            'product.brand',
            'user',
            'admin',
            'messages',
        ])->findOrFail($id);

        return $this->sendResponse(new TransactionResource($transaction));
    }

    public function startChecking(string $id): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        $transaction = Transaction::findOrFail($id);
        $transaction = $this->service->startChecking($admin, $transaction);

        return $this->sendResponse(
            new TransactionResource($transaction),
            'Transaksi masuk tahap pengecekan.'
        );
    }

    public function approve(string $id): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        $transaction = Transaction::findOrFail($id);
        $transaction = $this->service->approve($admin, $transaction);

        return $this->sendResponse(
            new TransactionResource($transaction),
            'Transaksi disetujui. Product & furniture ditandai swapped.'
        );
    }

    public function reject(RejectTransactionRequest $request, string $id): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        $transaction = Transaction::findOrFail($id);
        $transaction = $this->service->reject($admin, $transaction, $request->validated()['reject_reason']);

        return $this->sendResponse(
            new TransactionResource($transaction),
            'Transaksi ditolak.'
        );
    }
}