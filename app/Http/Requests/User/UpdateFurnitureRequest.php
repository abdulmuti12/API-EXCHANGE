<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFurnitureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => 'sometimes|required|string|max:255',
            'category_id'   => 'sometimes|nullable|uuid|exists:categories,id',
            'category_text' => 'sometimes|nullable|string|max:255',
            'brand_id'      => 'sometimes|nullable|uuid|exists:brands,id',
            'brand_text'    => 'sometimes|nullable|string|max:255',
            'description'   => 'sometimes|nullable|string',
            'images'        => 'nullable|array',
            'images.*'      => 'nullable|image|mimes:jpeg,png,jpg,webp,gif,heic|max:10240',
        ];
    }
}