<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreFurnitureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => 'required|string|max:255',
            'category_id'   => 'nullable|uuid|exists:categories,id',
            'category_text' => 'nullable|string|max:255|required_without:category_id',
            'brand_id'      => 'nullable|uuid|exists:brands,id',
            'brand_text'    => 'nullable|string|max:255|required_without:brand_id',
            'description'   => 'nullable|string',
            'images'        => 'nullable|array',
            'images.*'      => 'nullable|string|max:255',
        ];
    }
}