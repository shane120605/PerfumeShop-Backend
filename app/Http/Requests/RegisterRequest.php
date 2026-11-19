<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }
        // the frontend form uses "fullName"
        if (! $this->has('name') && $this->has('fullName')) {
            $this->merge(['name' => $this->input('fullName')]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            // 72 = bcrypt limit
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ];
    }
}
