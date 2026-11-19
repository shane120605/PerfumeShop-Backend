<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('email')) {
            $merge['email'] = strtolower(trim((string) $this->input('email')));
        }
        if ($this->has('role')) {
            $merge['role'] = strtolower(trim((string) $this->input('role'))); // "Admin" -> "admin"
        }
        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ];
    }
}
