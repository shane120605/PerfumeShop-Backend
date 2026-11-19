<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is already protected by auth:sanctum + role:admin
    }

    protected function prepareForValidation(): void
    {
        // The frontend form sends the category NAME ("Floral"); convert it to category_id.
        if (! $this->filled('category_id') && $this->filled('category')) {
            $id = Category::whereRaw('LOWER(name) = ?', [strtolower((string) $this->input('category'))])->value('id');
            if ($id) {
                $this->merge(['category_id' => $id]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'price' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'size' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'array'],
            'notes.top' => ['nullable', 'string', 'max:100'],
            'notes.heart' => ['nullable', 'string', 'max:100'],
            'notes.base' => ['nullable', 'string', 'max:100'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], // 2 MB
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'Please choose a category.',
            'category_id.exists' => 'The selected category does not exist.',
        ];
    }
}
