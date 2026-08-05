<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\User\StoreFurnitureImageRequest;
use App\Http\Requests\User\StoreFurnitureRequest;
use App\Http\Requests\User\UpdateFurnitureRequest;
use App\Http\Resources\UserFurnitureResource;
use App\Models\UserFurniture;
use App\Models\UserFurnitureImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class FurnitureController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);

        $query = UserFurniture::query()
            ->where('user_id', Auth::guard('user')->id())
            ->with(['category', 'brand', 'images'])
            ->orderBy('created_at', 'desc');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $items = $query->paginate($perPage);

        return $this->sendResponse([
            'items' => UserFurnitureResource::collection($items->items())->resolve(),
            'meta'  => [
                'current_page' => $items->currentPage(),
                'per_page'     => $items->perPage(),
                'total'        => $items->total(),
                'last_page'    => $items->lastPage(),
            ],
        ]);
    }

    public function store(StoreFurnitureRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = Auth::guard('user')->id();
        $data['status']  = 'available';

        $furniture = DB::transaction(function () use ($data) {
            $images = $data['images'] ?? [];
            unset($data['images']);

            $furniture = UserFurniture::create($data);

            foreach ($images as $i => $url) {
                if (! $url) {
                    continue;
                }
                UserFurnitureImage::create([
                    'user_furniture_id' => $furniture->id,
                    'image_url'         => $url,
                    'sort_order'        => $i,
                ]);
            }

            return $furniture->load(['category', 'brand', 'images']);
        });

        return $this->sendResponse(new UserFurnitureResource($furniture), 'Furniture berhasil ditambahkan.', 201);
    }

    public function show(string $id): JsonResponse
    {
        $furniture = $this->findOwned($id);

        return $this->sendResponse(new UserFurnitureResource($furniture->load(['category', 'brand', 'images'])));
    }

    public function update(UpdateFurnitureRequest $request, string $id): JsonResponse
    {
        $furniture = $this->findOwned($id);
        $furniture->update($request->validated());

        return $this->sendResponse(
            new UserFurnitureResource($furniture->fresh()->load(['category', 'brand', 'images'])),
            'Furniture berhasil diperbarui.'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $furniture = $this->findOwned($id);

        if ($furniture->status === 'in_transaction') {
            return $this->sendError(
                ['furniture' => ['Furniture sedang dalam transaksi, tidak dapat dihapus.']],
                'Hapus furniture gagal.',
                422
            );
        }

        $furniture->delete(); // soft delete

        return $this->sendResponse(null, 'Furniture berhasil dihapus.');
    }

    public function storeImage(StoreFurnitureImageRequest $request, string $id): JsonResponse
    {
        $furniture = $this->findOwned($id);

        $maxSort = (int) $furniture->images()->max('sort_order');

        $image = UserFurnitureImage::create([
            'user_furniture_id' => $furniture->id,
            'image_url'         => $request->validated()['image_url'],
            'sort_order'        => $request->validated()['sort_order'] ?? ($maxSort + 1),
        ]);

        return $this->sendResponse([
            'id'         => $image->id,
            'image_url'  => $image->image_url,
            'sort_order' => $image->sort_order,
        ], 'Gambar furniture berhasil ditambahkan.', 201);
    }

    public function destroyImage(string $id, string $imageId): JsonResponse
    {
        $furniture = $this->findOwned($id);
        $image = UserFurnitureImage::where('user_furniture_id', $furniture->id)
            ->where('id', $imageId)
            ->firstOrFail();
        $image->delete();

        return $this->sendResponse(null, 'Gambar furniture berhasil dihapus.');
    }

    protected function findOwned(string $id): UserFurniture
    {
        $furniture = UserFurniture::withTrashed()->findOrFail($id);

        if ($furniture->user_id !== Auth::guard('user')->id()) {
            throw new AccessDeniedHttpException('Furniture ini bukan milik Anda.');
        }

        return $furniture;
    }
}