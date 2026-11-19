<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Keys match the frontend order object: no, customer, email, date, status, payment, address, phone, items, total.
 *
 * @mixin \App\Models\Order
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'no' => $this->order_number,
            'customer' => $this->customer_name,
            'email' => $this->customer_email,
            'phone' => $this->phone,
            'address' => $this->shipping_address,
            'city' => $this->city,
            'date' => $this->created_at?->toDateString(),
            'status' => ucfirst($this->status), // Pending | Delivered | Cancelled
            'payment' => $this->payment_method,
            'subtotal' => (float) $this->subtotal,
            'shipping_fee' => (float) $this->shipping_fee,
            'total' => (float) $this->total,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
