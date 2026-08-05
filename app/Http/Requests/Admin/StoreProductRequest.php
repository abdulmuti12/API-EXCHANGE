<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'category_id' => 'required|uuid|exists:categories,id',
            'brand_id'    => 'required|uuid|exists:brands,id',
            'description' => 'nullable|string',
            'image1'      => 'nullable|file|image|mimes:jpeg,jpg,png,webp|max:2048',
            'image2'      => 'nullable|file|image|mimes:jpeg,jpg,png,webp|max:2048',
            'image3'      => 'nullable|file|image|mimes:jpeg,jpg,png,webp|max:2048',
            'image4'      => 'nullable|file|image|mimes:jpeg,jpg,png,webp|max:2048',
            'image5'      => 'nullable|file|image|mimes:jpeg,jpg,png,webp|max:2048',
            'image6'      => 'nullable|file|image|mimes:jpeg,jpg,png,webp|max:2048',
            'price'       => 'nullable|numeric|min:0',
            'status'      => ['nullable', Rule::in(['available', 'swapped', 'inactive'])],
            'images'      => 'nullable|array',
            'images.*'    => 'nullable|file|image|mimes:jpeg,jpg,png,webp|max:2048',
        ];
    }
}