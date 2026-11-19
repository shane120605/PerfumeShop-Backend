<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Keys match what the React frontend already uses (name, category, price, stock, size,
 * image, imageUrl, description, notes).
 *
 * @mixin \App\Models\Product
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category?->name,
            'category_id' => $this->category_id,
            'price' => (float) $this->price,
            'stock' => $this->stock,
            'size' => $this->size,
            'description' => $this->description,
            'image' => $this->image_number ?? 1,       // bundled photo number 1-4
            'imageUrl' => $this->image_path             // uploaded photo (or null)
                ? Storage::disk('public')->url($this->image_path)
                : null,
            'notes' => [
                'top' => $this->notes_top,
                'heart' => $this->notes_heart,
                'base' => $this->notes_base,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
