<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\OrderItem */
class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'productId' => $this->product_id,
            'name' => $this->product_name,
            'qty' => $this->quantity,
            'price' => (float) $this->unit_price,
            'lineTotal' => (float) $this->line_total,
        ];
    }
}
