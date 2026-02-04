<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class WorkshopUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user !== null && $user->isSuperAdmin();
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
            'subscription_plan_id' => ['nullable', 'exists:subscription_plans,id'],
            'subscription_plan_label' => ['nullable', 'string', 'max:100'],
            'subscription_status' => ['required', 'string', 'max:50'],
            'monthly_fee' => ['nullable', 'numeric', 'min:0'],
            'next_billing_date' => ['nullable', 'date'],
            'subscription_notes' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $planId = $this->input('subscription_plan_id');

        if ($planId === '' || $planId === 'null') {
            $planId = null;
        }

        $planLabel = $this->input('subscription_plan_label');
        $planLabel = is_string($planLabel) ? trim($planLabel) : null;
        if ($planLabel === '') {
            $planLabel = null;
        }

        $this->merge([
            'subscription_plan_id' => $planId,
            'subscription_plan_label' => $planLabel,
        ]);
    }
}
