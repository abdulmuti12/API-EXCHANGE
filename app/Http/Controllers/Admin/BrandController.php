<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\StoreBrandRequest;
use App\Http\Requests\Admin\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);

        $query = Brand::query()->orderBy('name');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $brands = $query->paginate($perPage);

        return $this->sendResponse([
            'items' => BrandResource::collection($brands->items())->resolve(),
            'meta'  => [
                'current_page' => $brands->currentPage(),
                'per_page'     => $brands->perPage(),
                'total'        => $brands->total(),
                'last_page'    => $brands->lastPage(),
            ],
        ]);
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        $brand = Brand::create($request->validated());

        return $this->sendResponse(new BrandResource($brand), 'Brand berhasil dibuat.', 201);
    }

    public function show(string $id): JsonResponse
    {
        return $this->sendResponse(new BrandResource(Brand::findOrFail($id)));
    }

    public function update(UpdateBrandRequest $request, string $id): JsonResponse
    {
        $brand = Brand::findOrFail($id);
        $brand->update($request->validated());

        return $this->sendResponse(new BrandResource($brand->fresh()), 'Brand berhasil diperbarui.');
    }

    public function destroy(string $id): JsonResponse
    {
        $brand = Brand::findOrFail($id);

        if ($brand->products()->exists()) {
            return $this->sendError(
                ['brand' => ['Brand masih digunakan oleh produk.']],
                'Hapus brand gagal.',
                422
            );
        }

        $brand->delete();

        return $this->sendResponse(null, 'Brand berhasil dihapus.');
    }
}