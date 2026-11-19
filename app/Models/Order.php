<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_DELIVERED, self::STATUS_CANCELLED];

    protected $fillable = [
        'order_number', 'user_id', 'customer_name', 'customer_email', 'phone',
        'shipping_address', 'city', 'payment_method', 'status',
        'subtotal', 'shipping_fee', 'total',
    ];

    protected function casts(): array
    {
        return [
            'order_number' => 'integer',
            'subtotal' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    // URLs use the order number:  /api/admin/orders/12345
    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
