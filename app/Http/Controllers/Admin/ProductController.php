<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\StoreProductImageRequest;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProductController extends BaseController
{
    private const IMAGE_FIELDS = ['image1', 'image2', 'image3', 'image4', 'image5', 'image6'];

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);

        $query = Product::query()->with(['category', 'brand', 'images']);

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }
        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }
        if ($brandId = $request->query('brand_id')) {
            $query->where('brand_id', $brandId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $products = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->sendResponse([
            'items' => ProductResource::collection($products->items())->resolve(),
            'meta'  => [
                'current_page' => $products->currentPage(),
                'per_page'     => $products->perPage(),
                'total'        => $products->total(),
                'last_page'    => $products->lastPage(),
            ],
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $data = $this->processImageFields($data, $request);

            $product = Product::create($data);

            $images = $data['images'] ?? [];
            foreach ($images as $i => $image) {
                if (! $image) {
                    continue;
                }
                $path = $image->store('products', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_url'  => '/storage/' . $path,
                    'sort_order' => $i,
                ]);
            }

            return $product->load(['category', 'brand', 'images']);
        });

        return $this->sendResponse(new ProductResource($product), 'Product berhasil dibuat.', 201);
    }

    public function show(string $id): JsonResponse
    {
        $product = Product::withTrashed()->with(['category', 'brand', 'images'])->findOrFail($id);

        return $this->sendResponse(new ProductResource($product));
    }

    public function update(UpdateProductRequest $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $data = $request->validated();
        LOG::info('Updating product', ['product_id' => $id, 'data' => $data]);
        // Merge existing image URLs so processImageFields can detect replacements/deletes
        foreach (self::IMAGE_FIELDS as $field) {
            if (! isset($data[$field])) {
                $data[$field] = $product->$field;
            }
        }
        $data = $this->processImageFields($data, $request);
        $product->update($data);

        return $this->sendResponse(
            new ProductResource($product->fresh()->load(['category', 'brand', 'images'])),
            'Product berhasil diperbarui.'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->delete(); // soft delete

        return $this->sendResponse(null, 'Product berhasil dihapus.');
    }

    public function storeImage(StoreProductImageRequest $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $maxSort = (int) $product->images()->max('sort_order');

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $imageUrl = '/storage/' . $path;
        } else {
            $imageUrl = $request->validated()['image_url'] ?? null;
        }

        if (! $imageUrl) {
            return $this->sendError('Gambar product gagal diunggah.', ['image' => ['File gambar atau URL wajib diisi.']], 422);
        }

        $image = ProductImage::create([
            'product_id' => $product->id,
            'image_url'  => $imageUrl,
            'sort_order' => $request->validated()['sort_order'] ?? ($maxSort + 1),
        ]);

        return $this->sendResponse([
            'id'         => $image->id,
            'image_url'  => $image->image_url,
            'sort_order' => $image->sort_order,
        ], 'Gambar product berhasil ditambahkan.', 201);
    }

    public function reorderImages(Request $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'images' => 'required|array|min:1',
            'images.*.id' => 'required|string|exists:product_images,id',
            'images.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['images'] as $item) {
            $product->images()->where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return $this->sendResponse(null, 'Urutan gambar berhasil diperbarui.');
    }

    public function destroyImage(string $id, string $imageId): JsonResponse
    {
        $product = Product::findOrFail($id);
        $image = ProductImage::where('product_id', $product->id)->where('id', $imageId)->firstOrFail();

        $url = $image->image_url;
        if ($url && str_starts_with($url, '/storage/')) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $url));
        }

        $image->delete();

        return $this->sendResponse(null, 'Gambar product berhasil dihapus.');
    }

    /**
     * Process image1-image6 fields.
     * Upload uploaded files, delete old files on update,
     * strip File objects from validated data so only URL strings go to fillable.
     * Handle cleared image slots from frontend.
     */
    private function processImageFields(array $data, Request $request): array
    {
        // Handle cleared image slots (update mode)
        $cleared = $request->input('cleared_image_slots');
        if (is_string($cleared) && ($decoded = json_decode($cleared, true)) !== null) {
            foreach ($decoded as $index) {
                $index = (int) $index;
                $field = self::IMAGE_FIELDS[$index] ?? null;
                if ($field && isset($data[$field]) && is_string($data[$field])) {
                    // Delete old file
                    $path = str_replace('/storage/', '', $data[$field]);
                    if (str_starts_with($path, 'products/')) {
                        Storage::disk('public')->delete($path);
                    }
                    $data[$field] = null;
                }
            }
        }

        foreach (self::IMAGE_FIELDS as $field) {
            $file = $request->file($field);
            if ($file) {
                // Delete old file if replacing during update
                $oldUrl = $data[$field] ?? null;
                if (is_string($oldUrl) && str_starts_with($oldUrl, '/storage/')) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $oldUrl));
                }
                // Store new file
                $path = $file->store('products', 'public');
                $data[$field] = '/storage/' . $path;
            } elseif (isset($data[$field]) && is_object($data[$field])) {
                // Validator placed a File object but no file is actually uploaded — remove it
                unset($data[$field]);
            }
        }

        return $data;
    }
}
