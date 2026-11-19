<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('status')) {
            $this->merge(['status' => strtolower(trim((string) $this->input('status')))]); // "Delivered" -> "delivered"
        }
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Order::STATUSES)],
        ];
    }
}
