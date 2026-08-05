<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Message\StoreMessageRequest;
use App\Http\Resources\TransactionMessageResource;
use App\Models\Transaction;
use App\Models\TransactionMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionMessageController extends BaseController
{
    public function index(Request $request, string $id): JsonResponse
    {
        $transaction = Transaction::findOrFail($id);
        $this->authorizeAccess($transaction);

        $perPage = (int) $request->query('per_page', 50);

        $messages = $transaction->messages()->paginate($perPage);

        return $this->sendResponse([
            'items' => TransactionMessageResource::collection($messages->items())->resolve(),
            'meta'  => [
                'current_page' => $messages->currentPage(),
                'per_page'     => $messages->perPage(),
                'total'        => $messages->total(),
                'last_page'    => $messages->lastPage(),
            ],
        ]);
    }

    public function store(StoreMessageRequest $request, string $id): JsonResponse
    {
        $transaction = Transaction::findOrFail($id);
        $this->authorizeAccess($transaction);

        if ($transaction->status !== Transaction::STATUS_CHECKING) {
            return $this->sendError(
                ['status' => ["Chat hanya tersedia saat transaksi berstatus checking (saat ini: {$transaction->status})."]],
                'Tidak dapat mengirim pesan.',
                422
            );
        }

        [$senderId, $senderType] = $this->resolveSender();

        $message = TransactionMessage::create([
            'transaction_id' => $transaction->id,
            'sender_id'      => $senderId,
            'sender_type'    => $senderType,
            'message'        => $request->validated()['message'],
        ]);

        return $this->sendResponse(
            new TransactionMessageResource($message),
            'Pesan terkirim.',
            201
        );
    }

    protected function authorizeAccess(Transaction $transaction): void
    {
        if (Auth::guard('admin')->check()) {
            return; // admin can access any transaction
        }

        $userId = Auth::guard('user')->id();
        if ($transaction->user_id !== $userId) {
            abort(response()->json([
                'success' => false,
                'message' => 'Transaksi ini bukan milik Anda.',
                'error'   => ['transaction_id' => ['Tidak diizinkan.']],
                'status'  => 403,
            ], 403));
        }
    }

    protected function resolveSender(): array
    {
        if (Auth::guard('admin')->check()) {
            return [Auth::guard('admin')->id(), 'admin'];
        }
        return [Auth::guard('user')->id(), 'user'];
    }
}