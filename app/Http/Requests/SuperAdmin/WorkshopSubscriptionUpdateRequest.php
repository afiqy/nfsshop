<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\WorkshopSubscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class WorkshopSubscriptionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'subscription_plan_id' => ['nullable', 'exists:subscription_plans,id'],
            'status' => ['required', Rule::in([
                WorkshopSubscription::STATUS_ACTIVE,
                WorkshopSubscription::STATUS_TRIALING,
                WorkshopSubscription::STATUS_PAST_DUE,
                WorkshopSubscription::STATUS_CANCELED,
            ])],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'overdue_amount' => ['nullable', 'numeric', 'min:0'],
            'next_billing_date' => ['nullable', 'date'],
            'last_paid_at' => ['nullable', 'date'],
            'payment_gateway' => ['nullable', 'string', 'max:100'],
            'external_subscription_id' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
