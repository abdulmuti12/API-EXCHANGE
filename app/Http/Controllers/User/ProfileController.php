<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\User\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProfileController extends BaseController
{
    public function show(): JsonResponse
    {
        $user = Auth::guard('user')->user();

        return $this->sendResponse([
            'id'           => $user->id,
            'name'         => $user->name,
            'email'        => $user->email,
            'phone_number' => $user->phone_number,
            'address'      => $user->address,
            'status'       => $user->status,
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = Auth::guard('user')->user();
        $data = $request->validated();

        if (! empty($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return $this->sendResponse([
            'id'           => $user->id,
            'name'         => $user->name,
            'email'        => $user->email,
            'phone_number' => $user->phone_number,
            'address'      => $user->address,
            'status'       => $user->status,
        ], 'Profile berhasil diperbarui.');
    }
}