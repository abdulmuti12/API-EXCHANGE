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
            'image_url'  => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->hasFile('image') && !$this->filled('image_url')) {
                $validator->errors()->add('image', 'File gambar wajib diisi.');
            }
        });
    }
}