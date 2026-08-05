<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreFurnitureImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image_url'  => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}