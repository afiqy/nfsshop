@extends('layouts.app')

@section('title', 'Payment settings')

@section('content')
    <h1 class="h3 mb-3">Payment settings</h1>
    <p>These preferences apply to your workshop admin account only. Saving them does not connect a gateway, enable checkout, or change invoices.</p>

    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $offlineMethods = old('offline_methods', $errors->any() ? [] : ($preferences?->offline_methods ?? []));
    @endphp
    <form method="POST" action="{{ route('payment-preferences.update') }}" class="card shadow-sm p-3">
        @csrf
        @method('PUT')

        <fieldset class="mb-3">
            <legend class="h5">Online gateways (up to two)</legend>
            <p>Choose “Other provider” if yours is not listed. Enter provider names only, never API keys or account details.</p>
            @for ($slot = 0; $slot < \App\Models\PaymentPreference::MAX_GATEWAYS; $slot++)
                @php
                    $saved = $preferences?->gateways[$slot] ?? [];
                    $provider = old("gateways.$slot.provider", $saved['provider'] ?? null);
                    $name = old("gateways.$slot.name", $saved['name'] ?? '');
                @endphp
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="provider-{{ $slot }}">Gateway {{ $slot + 1 }}</label>
                        <select id="provider-{{ $slot }}" name="gateways[{{ $slot }}][provider]" class="form-control">
                            <option value="">None</option>
                            @foreach ($gatewayGroups as $group => $providers)
                                <optgroup label="{{ $group }}">
                                    @foreach ($providers as $key => $label)
                                        <option value="{{ $key }}" @selected($provider === $key)>{{ $label }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                            <option value="custom" @selected($provider === 'custom')>Other provider</option>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="name-{{ $slot }}">Provider name (for Other provider only)</label>
                        <input id="name-{{ $slot }}" name="gateways[{{ $slot }}][name]" class="form-control"
                            maxlength="100" value="{{ is_string($name) ? $name : '' }}">
                    </div>
                </div>
            @endfor
        </fieldset>

        <fieldset class="mb-3">
            <legend class="h5">Offline payment methods</legend>
            <p>These do not count toward your gateway limit.</p>
            @foreach ($offlineOptions as $key => $label)
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="offline-{{ $key }}" name="offline_methods[]" value="{{ $key }}"
                        @checked(is_array($offlineMethods) && in_array($key, $offlineMethods, true))>
                    <label class="form-check-label" for="offline-{{ $key }}">{{ $label }}</label>
                </div>
            @endforeach
        </fieldset>
        <div><button class="btn btn-primary" type="submit">Save payment preferences</button></div>
    </form>

    @if ($stripeSandboxEnabled && $preferences?->usesGateway('stripe'))
        <section class="card shadow-sm p-3 mt-4" aria-labelledby="stripe-sandbox">
            <h2 class="h5" id="stripe-sandbox">Stripe sandbox</h2>
            <p>Try a RM 10.00 demo payment using Stripe test card details only. No money is charged and no invoice is updated.</p>
            <form method="POST" action="{{ route('stripe-sandbox.start') }}">
                @csrf
                <button class="btn btn-outline-primary" type="submit">Open Stripe test checkout</button>
            </form>
        </section>
    @endif
@endsection
