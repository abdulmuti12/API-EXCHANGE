<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasUuids;

    protected $fillable = ['product_id', 'image_url', 'sort_order'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}