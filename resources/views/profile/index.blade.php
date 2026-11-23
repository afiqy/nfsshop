@extends('layouts.app')

@section('title', __('User Profile'))

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">{{ __('User Profile') }}</h1>
</div>
<p><a href="{{ route('authenticator.show') }}">{{ __('Authenticator settings and verification') }}</a></p>
@unless (session()->has('impersonator_id')) <p><a href="{{ route('devices.index') }}">{{ __('Manage device sessions') }}</a></p> @endunless

@if ($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

@if (session('success'))
<div class="alert alert-success">
    {{ session('success') }}
</div>
@endif
@if ($user->force_password_reset)
<div class="alert alert-warning">{{ __('Change your temporary password before using the rest of the system.') }}</div>
@endif
<div class="card shadow">
    <div class="card-body">
        <div class="d-flex justify-content-end">
            <a href="{{ route('profile.edit') }}" class="btn btn-primary">{{ __('Edit') }}</a>
        </div>

        <div class="row align-items-center">
            <!-- avatar -->
            <div class="col-md-3 text-center border-right mb-4 mb-md-0">
                <div class="avatar-container position-relative d-inline-block">
                    @if($profile->avatar)
                    <img src="{{ route('profile.avatar') }}"
                        alt="{{ __('Avatar') }}"
                        class="rounded-circle img-thumbnail shadow-sm"
                        style="width: 150px; height: 150px; object-fit: cover;">
                    @else
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto shadow-sm"
                        style="width: 150px; height: 150px; border: 3px solid #eaecf4;">
                        <i class="fas fa-user fa-4x text-gray-300"></i>
                    </div>
                    @endif
                </div>
                <h5 class="mt-3 font-weight-bold text-gray-800">
                    {{ optional($profile)->first_name }} {{ optional($profile)->last_name }}
                </h5>
            </div>

            <!-- profile details -->
            <div class="col-md-9">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="first_name">{{ __('First Name') }}</label>
                            <input type="text" class="form-control @error('first_name') is-invalid @enderror" id="first_name" name="first_name" value="{{ old('first_name', optional($profile)->first_name) }}" disabled>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="last_name">{{ __('Last Name') }}</label>
                            <input type="text" class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" value="{{ old('last_name', optional($profile)->last_name) }}" disabled>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email">{{ __('Email') }}</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', optional($user)->email) }}" disabled>
                            @if ($user->email_verified === 0)
                                <span class="badge bg-danger-light text-danger font-weight-bold px-2 py-1" style="font-size: 0.75rem; border-radius: 4px;">
                                    {{ __('Has not been verified') }}
                                </span>
                            @else
                                <span class="badge bg-success-light text-success font-weight-bold px-2 py-1" style="font-size: 0.75rem; border-radius: 4px;">
                                    {{ __('Verified') }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="phone">{{ __('Phone Number') }}</label>
                            <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', optional($user)->phone) }}" disabled>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<hr class="my-4">

<div class="card shadow mb-4">
    <div class="card-body">
        <h2 class="h5">{{ __('Account preferences') }}</h2>
        <p>{{ __('Language: :language; timezone: :timezone; preferred contact: :contact.', [
            'language' => $profile->preferred_language === 'ms' ? __('Bahasa Melayu') : __('English'),
            'timezone' => $profile->timezone,
            'contact' => $profile->preferred_contact_method ? __($profile->preferred_contact_method) : __('Not set'),
        ]) }}</p>
        <p>{{ __('Your language and timezone apply to your account. Workshop scheduling remains in Malaysia time. Contact channel selection does not enable SMS or WhatsApp delivery.') }}</p>
        @if ($user->role === \App\Models\User::ROLE_CLIENT)
            <p>{{ __('Email notifications: appointments :appointments, invoices :invoices, payment reminders :reminders.', [
                'appointments' => $profile->allowsEmail(\App\Models\UserProfile::EMAIL_APPOINTMENTS) ? __('on') : __('off'),
                'invoices' => $profile->allowsEmail(\App\Models\UserProfile::EMAIL_INVOICES) ? __('on') : __('off'),
                'reminders' => $profile->allowsEmail(\App\Models\UserProfile::EMAIL_PAYMENT_REMINDERS) ? __('on') : __('off'),
            ]) }}</p>
        @endif
        @if ($workshop && $workshop->workshop_logo)
            <p>{{ __('Workshop logo:') }}</p>
            <img src="{{ route('profile.workshop-logo') }}" alt="{{ __('Workshop logo') }}" class="img-thumbnail mb-3"
                style="max-width: 240px; max-height: 120px;">
        @endif
        @if ($user->role === \App\Models\User::ROLE_CLIENT)
            <p>{{ __('Vehicle reminder consent is managed separately in') }} <a href="{{ route('client.portal') }}">{{ __('your customer portal') }}</a>.</p>
        @endif
    </div>
</div>
@unless (session()->has('impersonator_id'))
<div class="card shadow mb-4">
    <div class="card-body">
        <h2 class="h5">{{ __('Change account email') }}</h2>
        <p>{{ __('A confirmation link will be sent to the new address. Your workshop customer record and its reminder email are separate.') }}</p>
        <form action="{{ route('profile.email.request') }}" method="POST">
            @csrf
            <div class="form-group"><label for="new_email">{{ __('New email') }}</label><input id="new_email" type="email" name="new_email" class="form-control" value="{{ old('new_email') }}" maxlength="255" required></div>
            <div class="form-group"><label for="email_password">{{ __('Current password') }}</label><input id="email_password" type="password" name="current_password" class="form-control" autocomplete="current-password" required></div>
            <button type="submit" class="btn btn-primary">{{ __('Send confirmation link') }}</button>
        </form>
    </div>
</div>
@endunless
@unless (session()->has('impersonator_id'))
<div class="card shadow mb-4">
    <div class="card-body">
        <h2 class="h5">{{ __('Linked sign-in methods') }}</h2>
        <p>{{ __('External identities are linked only after provider authentication. An email match never links accounts automatically.') }}</p>
        <p>{{ __('If you signed up with SSO and have not set a password, sign out and use') }} <a href="{{ route('password.request') }}">{{ __('password recovery') }}</a>, {{ __('then sign in again before linking or unlinking an identity.') }}</p>
        @foreach ($identities as $identity)
            <form action="{{ route('profile.identities.unlink', $identity->id) }}" method="POST" class="border rounded p-3 mb-3">
                @csrf
                @method('DELETE')
                <strong>{{ ucfirst($identity->provider) }}</strong>
                <label class="d-block mt-2" for="unlink-password-{{ $identity->id }}">{{ __('Current password') }}</label>
                <input id="unlink-password-{{ $identity->id }}" type="password" name="current_password" class="form-control mb-2" autocomplete="current-password" required>
                <button type="submit" class="btn btn-outline-danger">{{ __('Unlink :provider', ['provider' => ucfirst($identity->provider)]) }}</button>
            </form>
        @endforeach
        @foreach ($ssoProviders as $provider => $label)
            @unless ($identities->contains('provider', $provider))
                <form action="{{ route('profile.identities.link', $provider) }}" method="POST" class="border rounded p-3 mb-3">
                    @csrf
                    <strong>{{ __('Link :provider', ['provider' => $label]) }}</strong>
                    <label class="d-block mt-2" for="link-password-{{ $provider }}">{{ __('Current password') }}</label>
                    <input id="link-password-{{ $provider }}" type="password" name="current_password" class="form-control mb-2" autocomplete="current-password" required>
                    <button type="submit" class="btn btn-outline-primary">{{ __('Link :provider', ['provider' => $label]) }}</button>
                </form>
            @endunless
        @endforeach
        @if (! $ssoProviders)
            <p class="mb-0">{{ __('Google and Microsoft sign-in are not configured.') }}</p>
        @endif
    </div>
</div>
@endunless
<div class="card shadow mb-4">
    <a href="#collapsePasswordCard" class="d-block card-header py-3 collapsed" data-bs-toggle="collapse" data-toggle="collapse" role="button" aria-expanded="false" aria-controls="collapsePasswordCard" data-bs-target="#collapsePasswordCard">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-lock fa-sm mr-2"></i>{{ __('Update Password') }}
        </h6>
    </a>
    
    <div class="collapse @if($user->force_password_reset || $errors->has('current_password') || $errors->has('new_password') || $errors->has('new_password_confirmation')) show @endif" id="collapsePasswordCard">
        <div class="card-body">
            <form action="{{ route('profile.password.update') }}" method="POST">
                @csrf
                @method('PATCH')
                <div class="form-group">
                    <label for="current_password">{{ __('Current password') }}</label>
                    <input type="password" class="form-control @error('current_password') is-invalid @enderror" id="current_password" name="current_password" required>
                    @error('current_password')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="new_password">{{ __('New Password (at least 8 characters, up to 72 bytes)') }}</label>
                    <input type="password" class="form-control @error('new_password') is-invalid @enderror" id="new_password" name="new_password" autocomplete="new-password" minlength="8" required>
                    @error('new_password')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="new_password_confirmation">{{ __('Confirm New Password') }}</label>
                    <input type="password" class="form-control @error('new_password_confirmation') is-invalid @enderror" id="new_password_confirmation" name="new_password_confirmation" required>
                    @error('new_password_confirmation')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <button type="submit" class="btn btn-primary">{{ __('Update Password') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection