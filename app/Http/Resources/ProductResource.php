<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'brand_id'    => $this->brand_id,
            'status'      => $this->status,
            'category'    => $this->whenLoaded('category', fn () => new CategoryResource($this->category)),
            'brand'       => $this->whenLoaded('brand', fn () => new BrandResource($this->brand)),
            'price'       => $this->price,
            'image1'      => $this->image1,
            'image2'      => $this->image2,
            'image3'      => $this->image3,
            'image4'      => $this->image4,
            'image5'      => $this->image5,
            'image6'      => $this->image6,

            'images'      => $this->whenLoaded('images', fn () => $this->images->pluck('image_url')->values()),
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ];
    }
}
