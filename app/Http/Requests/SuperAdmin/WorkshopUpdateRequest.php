<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class WorkshopUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user !== null && ($user->isSuperAdmin() || $user->isAccountSuperAdmin());
    }

    public function rules(): array
    {
        return [
            'workshop_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'services' => ['nullable', 'string'],
            'staff_notes' => ['nullable', 'string'],
            'currency' => ['required', 'string', 'max:3'],
            'tax_rate' => ['nullable', 'numeric', 'min:0'],
            'labor_rate' => ['nullable', 'numeric', 'min:0'],
            'callout_fee' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
