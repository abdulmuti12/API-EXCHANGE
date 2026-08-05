<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_furniture_id' => 'required|uuid|exists:user_furnitures,id',
            'product_id'        => 'required|uuid|exists:products,id',
        ];
    }
}