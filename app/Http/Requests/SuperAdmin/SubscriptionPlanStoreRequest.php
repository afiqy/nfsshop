<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SubscriptionPlanStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:subscription_plans,name'],
            'price' => ['required', 'numeric', 'min:0'],
            'billing_interval' => ['required', Rule::in(['monthly', 'yearly'])],
            'trial_days' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'features' => ['nullable', 'array'],
            'features.*' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $features = $this->input('features');

        if (is_string($features)) {
            $features = array_filter(array_map('trim', explode(PHP_EOL, $features)));
        }

        if (is_array($features)) {
            $features = array_values(array_filter(array_map('trim', $features)));
        }

        $this->merge([
            'features' => $features,
            'trial_days' => $this->input('trial_days', 0),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
