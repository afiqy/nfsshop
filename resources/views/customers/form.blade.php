@extends('layouts.app')

@section('title', $customer->exists ? 'Edit customer' : 'Add customer')

@section('content')
    <a href="{{ route('customers.index') }}">Back to customers</a>
    <h1 class="h3 mt-2 mb-3">{{ $customer->exists ? 'Edit customer' : 'Add customer' }}</h1>

    @php
        $fields = [
            'name' => ['Name', 'text', 255, true],
            'email' => ['Email', 'email', 255, true],
            'phone' => ['Phone (optional)', 'tel', 255, false],
            'address' => ['Address (optional)', 'textarea', 1000, false],
        ];
    @endphp

    <form method="POST" action="{{ $customer->exists ? route('customers.update', $customer) : route('customers.store') }}" class="card shadow-sm p-3">
        @csrf
        @if ($customer->exists)
            @method('PUT')
        @endif

        @foreach ($fields as $field => [$label, $type, $maxLength, $required])
            @php
                $value = old($field, $customer->{$field});
            @endphp
            <div class="form-group">
                <label for="{{ $field }}">{{ $label }}</label>
                @if ($type === 'textarea')
                    <textarea id="{{ $field }}" name="{{ $field }}" maxlength="{{ $maxLength }}" rows="3"
                        class="form-control @error($field) is-invalid @enderror"
                        @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>{{ is_string($value) ? $value : '' }}</textarea>
                @else
                    <input id="{{ $field }}" type="{{ $type }}" name="{{ $field }}" maxlength="{{ $maxLength }}"
                        value="{{ is_string($value) ? $value : '' }}" class="form-control @error($field) is-invalid @enderror"
                        @required($required) @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
                @endif
                @error($field)
                    <div class="invalid-feedback" id="{{ $field }}-error">{{ $message }}</div>
                @enderror
            </div>
        @endforeach

        <div>
            <button type="submit" class="btn btn-primary">{{ $customer->exists ? 'Save changes' : 'Add customer' }}</button>
        </div>
    </form>
@endsection
