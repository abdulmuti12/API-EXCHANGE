<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserFurniture extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'user_furnitures';

    protected $fillable = [
        'user_id',
        'name',
        'category_id',
        'category_text',
        'brand_id',
        'brand_text',
        'description',
        'admin_price',
        'status',
    ];

    protected $casts = [
        'admin_price' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function images()
    {
        return $this->hasMany(UserFurnitureImage::class)->orderBy('sort_order');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}