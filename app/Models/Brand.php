<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'description', 'image'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function userFurnitures()
    {
        return $this->hasMany(UserFurniture::class);
    }
}