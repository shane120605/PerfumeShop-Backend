<?php

namespace App\Http\Requests;

class UpdateProductRequest extends StoreProductRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0.01', 'max:999999.99'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000000'],
            'size' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'array'],
            'notes.top' => ['nullable', 'string', 'max:100'],
            'notes.heart' => ['nullable', 'string', 'max:100'],
            'notes.base' => ['nullable', 'string', 'max:100'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }
}
