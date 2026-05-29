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
                        @if(optional($profile)->avatar_url)
                        <img src="{{ asset($profile->avatar_url) }}"
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
                        <input type="file" class="form-control-file form-control-sm @error('avatar') is-invalid @enderror" id="avatar" name="avatar">
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
                                <input type="text" class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" value="{{ old('last_name', optional($profile)->last_name) }}" required>
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
                                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', optional($user)->email) }}" required>
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
            
            <div class="d-flex justify-content-end mb-3">
                <a href="{{ route('profile.index') }}" class="btn btn-secondary mr-2">Cancel</a>
                <button type="submit" class="btn btn-success">Save Changes</button>
            </div>
        </div>
    </div>
</form>
@endsection