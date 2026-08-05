<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name'         => 'sometimes|required|string|max:255',
            'email'        => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone_number' => 'sometimes|nullable|string|max:30',
            'password'     => 'sometimes|nullable|string|min:6',
            'address'      => 'sometimes|nullable|string',
        ];
    }
}