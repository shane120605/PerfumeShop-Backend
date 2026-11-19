<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'name', 'description', 'price', 'stock', 'size',
        'image_number', 'image_path', 'notes_top', 'notes_heart', 'notes_base',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
            'image_number' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
