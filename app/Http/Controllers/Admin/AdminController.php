<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\StoreAdminRequest;
use App\Http\Requests\Admin\UpdateAdminRequest;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);

        $query = Admin::query()->orderBy('created_at', 'desc');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $admins = $query->paginate($perPage);

        return $this->sendResponse([
            'items'     => AdminResource::collection($admins->items())->resolve(),
            'meta'      => [
                'current_page' => $admins->currentPage(),
                'per_page'     => $admins->perPage(),
                'total'        => $admins->total(),
                'last_page'    => $admins->lastPage(),
            ],
        ]);
    }

    public function store(StoreAdminRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['password'] = bcrypt($data['password']);
        $data['status']   = $data['status'] ?? 'active';

        $admin = Admin::create($data);

        return $this->sendResponse(new AdminResource($admin), 'Admin berhasil dibuat.', 201);
    }

    public function show(string $id): JsonResponse
    {
        $admin = Admin::findOrFail($id);

        return $this->sendResponse(new AdminResource($admin));
    }

    public function update(UpdateAdminRequest $request, string $id): JsonResponse
    {
        $admin = Admin::findOrFail($id);
        $data = $request->validated();

        if (! empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }

        $admin->update($data);

        return $this->sendResponse(new AdminResource($admin->fresh()), 'Admin berhasil diperbarui.');
    }

    public function destroy(string $id): JsonResponse
    {
        $admin = Admin::findOrFail($id);

        if ($admin->id === auth('admin')->id()) {
            return $this->sendError(
                ['admin' => ['Tidak dapat menghapus akun sendiri.']],
                'Hapus admin gagal.',
                422
            );
        }

        $admin->delete();

        return $this->sendResponse(null, 'Admin berhasil dihapus.');
    }
}