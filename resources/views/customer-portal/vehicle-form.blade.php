@extends('layouts.app')

@section('title', $isRequest ? __('Request another vehicle') : __('Add vehicle'))

@section('content')
    <a href="{{ route('client.portal') }}">{{ __('Back to my account') }}</a>
    <h1 class="h3 mt-2 mb-3">{{ $isRequest ? __('Request another vehicle') : __('Add a vehicle') }}</h1>
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif
    @if ($isRequest)
        <p>{{ __('The workshop must approve this request before the vehicle is added to your account.') }}</p>
    @endif

    <form method="POST" action="{{ $isRequest ? route('client.vehicle-requests.store') : route('client.vehicles.store') }}" class="card shadow-sm p-3">
        @csrf
        @foreach ([
            'plate_number' => [__('Registration number'), true],
            'brand' => [__('Brand'), true],
            'model' => [__('Model'), true],
            'chassis_number' => [__('Chassis number'), true],
            'engine_number' => [__('Engine number'), true],
            'year' => [__('Year (optional)'), false],
            'color' => [__('Colour (optional)'), false],
            'mileage_km' => [__('Mileage in km (optional)'), false],
            'fuel_type' => [__('Fuel type (optional)'), false],
        ] as $field => [$label, $required])
            <div class="form-group">
                <label for="{{ $field }}">{{ $label }}</label>
                <input id="{{ $field }}" name="{{ $field }}" type="text" value="{{ old($field) }}"
                    class="form-control @error($field) is-invalid @enderror"
                    @if ($field === 'year') inputmode="numeric" pattern="[0-9]{4}" maxlength="4"
                    @elseif ($field === 'mileage_km') inputmode="numeric" pattern="[0-9]+" maxlength="10"
                    @elseif ($field === 'fuel_type') maxlength="100"
                    @else maxlength="255" @endif
                    @required($required) @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
                @error($field)
                    <div class="invalid-feedback" id="{{ $field }}-error">{{ $message }}</div>
                @enderror
            </div>
        @endforeach
        <div>
            <button type="submit" class="btn btn-primary">{{ $isRequest ? __('Send request') : __('Add vehicle') }}</button>
        </div>
    </form>
@endsection
