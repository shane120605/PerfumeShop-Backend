<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // accept "email" as an alias of "identifier"
        if (! $this->filled('identifier') && $this->filled('email')) {
            $this->merge(['identifier' => $this->input('email')]);
        }
    }

    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
