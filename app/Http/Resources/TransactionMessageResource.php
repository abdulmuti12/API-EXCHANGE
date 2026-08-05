<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionMessageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'transaction_id' => $this->transaction_id,
            'sender_id'      => $this->sender_id,
            'sender_type'    => $this->sender_type,
            'message'        => $this->message,
            'created_at'     => $this->created_at,
        ];
    }
}