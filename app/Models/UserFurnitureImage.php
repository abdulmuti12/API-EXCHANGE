<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UserFurnitureImage extends Model
{
    use HasUuids;

    protected $fillable = ['user_furniture_id', 'image_url', 'sort_order'];

    public function userFurniture()
    {
        return $this->belongsTo(UserFurniture::class);
    }
}