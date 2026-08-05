<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $adminId = $this->route('admin');

        return [
            'name'     => 'sometimes|required|string|max:255',
            'email'    => ['sometimes', 'required', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($adminId)],
            'password' => 'sometimes|nullable|string|min:6',
            'status'   => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}