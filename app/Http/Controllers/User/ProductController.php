<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\BrandResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);

        $query = Product::query()
            ->with(['category', 'brand', 'images'])
            ->where('status', 'available');

        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%");
        }
        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }
        if ($brandId = $request->query('brand_id')) {
            $query->where('brand_id', $brandId);
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

    public function show(string $id): JsonResponse
    {
        $product = Product::with(['category', 'brand', 'images'])->findOrFail($id);

        return $this->sendResponse(new ProductResource($product));
    }

    public function brands(): JsonResponse
    {
        return $this->sendResponse(BrandResource::collection(Brand::orderBy('name')->get())->resolve());
    }

    public function categories(): JsonResponse
    {
        return $this->sendResponse(CategoryResource::collection(Category::orderBy('name')->get())->resolve());
    }
}