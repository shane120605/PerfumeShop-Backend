<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = ['quantity' => ['required', 'integer', 'min:1', 'max:99']];

        // when adding, the product id is in the body; when updating it is in the URL
        if (! $this->route('product')) {
            $rules['product_id'] = ['required', 'integer', 'exists:products,id'];
        }

        return $rules;
    }
}
