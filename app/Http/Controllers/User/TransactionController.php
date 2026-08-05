<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\User\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class TransactionController extends BaseController
{
    public function __construct(protected TransactionService $service) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);

        $query = Transaction::query()
            ->where('user_id', Auth::guard('user')->id())
            ->with(['userFurniture.images', 'product.images', 'product.category', 'product.brand', 'admin'])
            ->orderBy('created_at', 'desc');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
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

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $user = Auth::guard('user')->user();
        $data = $request->validated();

        $transaction = $this->service->submit($user, $data['user_furniture_id'], $data['product_id']);

        return $this->sendResponse(
            new TransactionResource($transaction),
            'Pengajuan tukar berhasil, menunggu verifikasi admin.',
            201
        );
    }

    public function show(string $id): JsonResponse
    {
        $transaction = Transaction::with([
            'userFurniture.images',
            'product.images',
            'product.category',
            'product.brand',
            'admin',
        ])->findOrFail($id);

        if ($transaction->user_id !== Auth::guard('user')->id()) {
            throw new AccessDeniedHttpException('Transaksi ini bukan milik Anda.');
        }

        return $this->sendResponse(new TransactionResource($transaction));
    }
}