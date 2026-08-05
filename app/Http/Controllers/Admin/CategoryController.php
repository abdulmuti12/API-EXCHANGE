<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);

        $query = Category::query()->orderBy('name');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $categories = $query->paginate($perPage);

        return $this->sendResponse([
            'items' => CategoryResource::collection($categories->items())->resolve(),
            'meta'  => [
                'current_page' => $categories->currentPage(),
                'per_page'     => $categories->perPage(),
                'total'        => $categories->total(),
                'last_page'    => $categories->lastPage(),
            ],
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return $this->sendResponse(new CategoryResource($category), 'Kategori berhasil dibuat.', 201);
    }

    public function show(string $id): JsonResponse
    {
        return $this->sendResponse(new CategoryResource(Category::findOrFail($id)));
    }

    public function update(UpdateCategoryRequest $request, string $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $category->update($request->validated());

        return $this->sendResponse(new CategoryResource($category->fresh()), 'Kategori berhasil diperbarui.');
    }

    public function destroy(string $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        if ($category->products()->exists()) {
            return $this->sendError(
                ['category' => ['Kategori masih digunakan oleh produk.']],
                'Hapus kategori gagal.',
                422
            );
        }

        $category->delete();

        return $this->sendResponse(null, 'Kategori berhasil dihapus.');
    }
}