<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CHECKING = 'checking';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'user_furniture_id',
        'product_id',
        'status',
        'admin_id',
        'reject_reason',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function userFurniture()
    {
        return $this->belongsTo(UserFurniture::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function messages()
    {
        return $this->hasMany(TransactionMessage::class)->orderBy('created_at');
    }
}