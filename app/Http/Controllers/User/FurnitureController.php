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
use Illuminate\Support\Facades\Storage;
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

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'category_id'   => 'nullable|uuid|exists:categories,id',
            'category_text' => 'nullable|string|max:255|required_without:category_id',
            'brand_id'      => 'nullable|uuid|exists:brands,id',
            'brand_text'    => 'nullable|string|max:255|required_without:brand_id',
            'description'   => 'nullable|string',
            'images'        => 'nullable|array',
            'images.*'      => 'nullable|image|mimes:jpeg,png,jpg,webp,gif|max:10240',
        ]);

        $data = $request->only(['name', 'description', 'category_id', 'category_text', 'brand_id', 'brand_text']);
        $data['user_id'] = Auth::guard('user')->id();
        $data['status']  = 'available';

        $furniture = DB::transaction(function () use ($request, $data) {
            $furniture = UserFurniture::create($data);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $i => $image) {
                    $path = $image->store('user_furnitures', 'public');
                    UserFurnitureImage::create([
                        'user_furniture_id' => $furniture->id,
                        'image_url'         => '/storage/' . $path,
                        'sort_order'        => $i,
                    ]);
                }
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

    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'category_id'   => 'sometimes|nullable|uuid|exists:categories,id',
            'category_text' => 'sometimes|nullable|string|max:255',
            'brand_id'      => 'sometimes|nullable|uuid|exists:brands,id',
            'brand_text'    => 'sometimes|nullable|string|max:255',
            'description'   => 'sometimes|nullable|string',
            'images'        => 'nullable|array',
            'images.*'      => 'nullable|image|mimes:jpeg,png,jpg,webp,gif|max:10240',
            'remove_images' => 'nullable|array',
            'remove_images.*' => 'string|exists:user_furniture_images,id',
        ]);

        $furniture = $this->findOwned($id);

        $updateData = $request->only(['name', 'description', 'category_id', 'category_text', 'brand_id', 'brand_text']);
        $furniture->update($updateData);

        // Remove deleted images
        if ($request->filled('remove_images')) {
            foreach ($request->input('remove_images') as $imageId) {
                $image = $furniture->images()->where('id', $imageId)->first();
                if ($image) {
                    Storage::disk('public')->delete(ltrim($image->image_url, '/'));
                    $image->delete();
                }
            }
        }

        // Add new uploaded images
        if ($request->hasFile('images')) {
            $maxSort = (int) $furniture->images()->max('sort_order');
            foreach ($request->file('images') as $i => $image) {
                if ($image) {
                    $path = $image->store('user_furnitures', 'public');
                    $furniture->images()->create([
                        'image_url' => '/storage/' . $path,
                        'sort_order' => $maxSort + 1 + $i,
                    ]);
                }
            }
        }

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

    public function storeImage(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'image_url' => 'nullable|string|max:255',
            'image'     => 'nullable|image|mimes:jpeg,png,jpg,webp,gif|max:10240',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $furniture = $this->findOwned($id);
        $maxSort = (int) $furniture->images()->max('sort_order');

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('user_furnitures', 'public');
            $imageUrl = '/storage/' . $path;
        } else {
            $imageUrl = $request->input('image_url');
        }

        if (! $imageUrl) {
            return $this->sendError('Gambar gagal diunggah.', ['image' => ['File gambar wajib diisi.']], 422);
        }

        $image = UserFurnitureImage::create([
            'user_furniture_id' => $furniture->id,
            'image_url'         => $imageUrl,
            'sort_order'        => $request->input('sort_order', $maxSort + 1),
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

        if (str_starts_with($image->image_url, '/storage/')) {
            Storage::disk('public')->delete(ltrim($image->image_url, '/storage/'));
        }

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