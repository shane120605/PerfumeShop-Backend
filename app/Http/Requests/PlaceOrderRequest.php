<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // The frontend checkout form uses camelCase (fullName); accept it too.
    protected function prepareForValidation(): void
    {
        if (! $this->has('full_name') && $this->has('fullName')) {
            $this->merge(['full_name' => $this->input('fullName')]);
        }
    }

    /**
     * NOTE: there is intentionally NO price / total field. Prices are always read from the database.
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^[0-9 +()\-]{7,30}$/'],
            'payment_method' => ['required', Rule::in(config('shop.payment_methods'))],
            // optional: if omitted, the server-side cart is used
            'items' => ['sometimes', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid phone number.',
            'payment_method.in' => 'Choose a valid payment method.',
            'items.*.product_id.exists' => 'One of the products no longer exists.',
        ];
    }
}
