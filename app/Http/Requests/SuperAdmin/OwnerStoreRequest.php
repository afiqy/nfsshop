<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class OwnerStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user !== null && $user->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'workshop_name' => ['required', 'string', 'max:255'],
            'tenant_id' => ['nullable', 'string', 'max:255', Rule::unique('users', 'tenant_id')->where('role', User::ROLE_ADMIN)],
            'workshop_address' => ['nullable', 'string', 'max:255'],
            'workshop_phone' => ['nullable', 'string', 'max:50'],
        ];
    }
}
