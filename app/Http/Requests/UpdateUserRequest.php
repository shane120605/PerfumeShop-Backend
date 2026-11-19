<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends StoreUserRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'role' => ['sometimes', 'required', Rule::in(User::ROLES)],
            // leave empty to keep the current password
            'password' => ['nullable', 'string', 'min:8', 'max:72'],
        ];
    }
}
