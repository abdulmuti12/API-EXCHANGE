<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'user_id'           => $this->user_id,
            'user_furniture_id' => $this->user_furniture_id,
            'product_id'        => $this->product_id,
            'status'            => $this->status,
            'admin_id'          => $this->admin_id,
            'reject_reason'     => $this->reject_reason,
            'user_furniture'    => $this->whenLoaded('userFurniture', fn () => new UserFurnitureResource($this->userFurniture)),
            'product'           => $this->whenLoaded('product', fn () => new ProductResource($this->product)),
            'user'              => $this->whenLoaded('user', fn () => [
                'id'           => $this->user->id,
                'name'         => $this->user->name,
                'email'        => $this->user->email,
                'phone_number' => $this->user->phone_number,
            ]),
            'admin'             => $this->whenLoaded('admin', fn () => $this->admin ? [
                'id'    => $this->admin->id,
                'name'  => $this->admin->name,
                'email' => $this->admin->email,
            ] : null),
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}