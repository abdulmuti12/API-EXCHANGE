<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Api\BaseController;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);

        $query = Notification::query()
            ->where('user_id', Auth::guard('user')->id())
            ->orderBy('created_at', 'desc');

        if ($request->query('unread_only') === 'true' || $request->query('unread_only') === '1') {
            $query->whereNull('read_at');
        }

        $items = $query->paginate($perPage);

        return $this->sendResponse([
            'items' => $items->items(),
            'meta'  => [
                'current_page' => $items->currentPage(),
                'per_page'     => $items->perPage(),
                'total'        => $items->total(),
                'last_page'    => $items->lastPage(),
            ],
        ]);
    }

    public function markAsRead(string $id): JsonResponse
    {
        $notification = Notification::where('user_id', Auth::guard('user')->id())
            ->where('id', $id)
            ->firstOrFail();

        if (is_null($notification->read_at)) {
            $notification->update(['read_at' => now()]);
        }

        return $this->sendResponse($notification, 'Notifikasi ditandai terbaca.');
    }
}