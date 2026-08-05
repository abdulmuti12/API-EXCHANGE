<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TransactionMessage extends Model
{
    use HasUuids;

    protected $fillable = [
        'transaction_id',
        'sender_id',
        'sender_type',
        'message',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}