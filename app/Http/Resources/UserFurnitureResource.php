<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserFurnitureResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'user_id'       => $this->user_id,
            'name'          => $this->name,
            'description'   => $this->description,
            'status'        => $this->status,
            'category_id'   => $this->category_id,
            'category_text' => $this->category_text,
            'category'      => $this->whenLoaded('category', fn () => $this->category ? new CategoryResource($this->category) : null),
            'brand_id'      => $this->brand_id,
            'brand_text'    => $this->brand_text,
            'brand'         => $this->whenLoaded('brand', fn () => $this->brand ? new BrandResource($this->brand) : null),
            'admin_price'   => $this->admin_price,
            'images'        => $this->whenLoaded('images', fn () => $this->images->map(fn ($img) => [
                'id'        => $img->id,
                'image_url' => $img->image_url,
                'sort_order' => $img->sort_order,
            ])),
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}