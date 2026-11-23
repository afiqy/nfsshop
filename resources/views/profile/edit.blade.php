@extends('layouts.app')

@section('title', 'Edit Profile')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Edit Profile</h1>
</div>

@if ($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

<form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-3 text-center border-right mb-4 mb-md-0">
                    <div class="avatar-container position-relative d-inline-block">
                        @if($profile->avatar)
                        <img src="{{ route('profile.avatar') }}"
                            alt="Avatar"
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
                    
                    <div class="form-group mt-2 px-3">
                        <label for="avatar" class="small text-muted mb-1">Update Avatar</label>
                        <input type="file" class="form-control-file form-control-sm @error('avatar') is-invalid @enderror" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp">
                        <small>JPEG, PNG or WebP, up to 2 MB.</small>
                        @if ($profile->avatar)
                            <label class="d-block"><input type="checkbox" name="remove_avatar" value="1"> Remove current avatar</label>
                        @endif
                        @error('avatar')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="col-md-9">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="first_name">First Name</label>
                                <input type="text" class="form-control @error('first_name') is-invalid @enderror" id="first_name" name="first_name" value="{{ old('first_name', optional($profile)->first_name) }}" required>
                                @error('first_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="last_name">Last Name</label>
                                <input type="text" class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" value="{{ old('last_name', optional($profile)->last_name) }}" maxlength="120">
                                @error('last_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="email">Email</label>
                                <input type="email" class="form-control" id="email" value="{{ $user->email }}" disabled>
                                <small>Change your account email on the profile page.</small>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="phone">Phone Number</label>
                                <input type="text" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', optional($user)->phone) }}">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-md-4 form-group">
                    <label for="preferred_language">Preferred language</label>
                    <select class="form-control" id="preferred_language" name="preferred_language" required>
                        <option value="en" @selected(old('preferred_language', $profile->preferred_language) === 'en')>English</option>
                        <option value="ms" @selected(old('preferred_language', $profile->preferred_language) === 'ms')>Bahasa Melayu</option>
                    </select>
                </div>
                <div class="col-md-4 form-group">
                    <label for="timezone">Preferred timezone</label>
                    <select class="form-control" id="timezone" name="timezone" required>
                        @foreach (timezone_identifiers_list() as $timezone)
                            <option value="{{ $timezone }}" @selected(old('timezone', $profile->timezone) === $timezone)>{{ $timezone }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 form-group">
                    <label for="preferred_contact_method">Preferred contact method</label>
                    <select class="form-control" id="preferred_contact_method" name="preferred_contact_method">
                        <option value="">Not set</option>
                        @foreach (['email' => 'Email', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('preferred_contact_method', $profile->preferred_contact_method) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="small text-muted">Preferences are saved for future use; the interface remains in English, workshop dates use Malaysia time, and SMS/WhatsApp delivery is not available.</p>
            <div class="form-group">
                <label for="bio">Bio</label>
                <textarea class="form-control" id="bio" name="bio" maxlength="2000">{{ old('bio', $profile->bio) }}</textarea>
            </div>
            <div class="d-flex justify-content-end mb-3">
                <a href="{{ route('profile.index') }}" class="btn btn-secondary mr-2">Cancel</a>
                <button type="submit" class="btn btn-success">Save Changes</button>
            </div>
        </div>
    </div>
</form>
@endsection