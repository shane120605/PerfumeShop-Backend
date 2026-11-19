<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ignore = $this->route('category'); // null when creating

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($ignore)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
