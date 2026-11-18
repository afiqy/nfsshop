<?php

namespace App\Http\Controllers;

use App\Models\PaymentPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentPreferenceController extends Controller
{
    public function edit(Request $request): View
    {
        return view('payment-preferences.edit', [
            'preferences' => PaymentPreference::where('user_id', $request->user()->id)->first(),
            'gatewayGroups' => config('payment_methods.gateways'),
            'offlineOptions' => config('payment_methods.offline'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $catalog = array_merge(...array_values(config('payment_methods.gateways')));
        $offlineOptions = config('payment_methods.offline');
        $validated = $request->validate([
            'gateways' => ['sometimes', 'array', 'max:'.PaymentPreference::MAX_GATEWAYS],
            'gateways.*' => ['required', 'array:provider,name'],
            'gateways.*.provider' => ['nullable', 'string', Rule::in([...array_keys($catalog), 'custom'])],
            'gateways.*.name' => ['nullable', 'string', 'max:100'],
            'offline_methods' => ['sometimes', 'array', 'max:'.count($offlineOptions)],
            'offline_methods.*' => ['required', 'string', 'distinct', Rule::in(array_keys($offlineOptions))],
        ]);

        $gateways = [];
        $seen = [];
        foreach ($validated['gateways'] ?? [] as $index => $gateway) {
            $provider = $gateway['provider'] ?? null;
            if ($provider === null) {
                continue;
            }

            $name = $provider === 'custom' ? ($gateway['name'] ?? null) : $catalog[$provider];
            if ($name === null) {
                throw ValidationException::withMessages(["gateways.$index.name" => 'Enter the custom gateway name.']);
            }

            $key = mb_strtolower($name);
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(["gateways.$index.provider" => 'Select two different gateways.']);
            }
            $seen[$key] = true;
            $gateways[] = $provider === 'custom' ? ['provider' => $provider, 'name' => $name] : ['provider' => $provider];
        }

        PaymentPreference::updateOrCreate(['user_id' => $request->user()->id], [
            'gateways' => $gateways,
            'offline_methods' => array_values($validated['offline_methods'] ?? []),
        ]);

        return redirect()->route('payment-preferences.edit')->with('success', 'Payment preferences saved.');
    }
}
