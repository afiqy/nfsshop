@extends('layouts.app')
@section('title', __('Service package'))
@section('content')
<a href="{{ route('services.index') }}">{{ __('Back to services') }}</a>
<h1 class="h3 mt-2">{{ __($package->exists ? 'Edit package' : 'Add package') }}</h1>
@if ($errors->any())<div class="alert alert-danger"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form action="{{ $package->exists ? route('service-packages.update', $package->id) : route('service-packages.store') }}" method="POST" class="card p-3">
    @csrf @if ($package->exists) @method('PUT') @endif
    <div class="form-group"><label for="name">{{ __('Package name') }}</label><input id="name" name="name" class="form-control" maxlength="255" required value="{{ old('name', $package->name) }}"></div>
    <p>{{ __('Packages retain the selected service names and prices as they were when saved. Saving again uses current active services and prices.') }}</p>
    @if ($package->exists && collect($package->services)->pluck('id')->diff($services->pluck('id'))->isNotEmpty())
        <p class="alert alert-warning">{{ __('Some previously selected services are inactive and will be removed if you save this package.') }}</p>
    @endif
    <fieldset><legend class="h5">{{ __('Select services') }}</legend>
        @php $selected = old('service_ids', collect($package->services ?? [])->pluck('id')->all()); @endphp
        @forelse ($services as $service)
            <div class="form-check"><input class="form-check-input package-service" id="service-{{ $service->id }}" type="checkbox" name="service_ids[]" value="{{ $service->id }}" data-price="{{ $service->price_sen }}" @checked(in_array((string) $service->id, array_map('strval', is_array($selected) ? $selected : []), true))>
                <label class="form-check-label" for="service-{{ $service->id }}">{{ $service->name }} &middot; {{ \App\Models\Invoice::formatAmount($service->price_sen) }}</label></div>
        @empty <p>{{ __('Add an active service first.') }}</p> @endforelse
    </fieldset>
    <p class="mt-2">{{ __('Selected service total:') }} <strong id="service-total">{{ __('Calculated when saved') }}</strong></p>
    <noscript><p>{{ __('Enable JavaScript to preview the total; leaving the price blank still uses the selected service total when saved.') }}</p></noscript>
    <div class="form-group"><label for="price">{{ __('Package price (RM; leave blank to use selected service total)') }}</label><input id="price" name="price" type="number" min="0" max="99999999.99" step="0.01" class="form-control" value="{{ old('price', $package->exists ? number_format($package->price_sen / 100, 2, '.', '') : '') }}"></div>
    <div class="form-check mb-3"><input id="is_active" name="is_active" value="1" type="checkbox" class="form-check-input" @checked(old('is_active', $package->exists ? $package->is_active : true))><label for="is_active" class="form-check-label">{{ __('Active') }}</label></div>
    <button class="btn btn-primary">{{ __('Save package') }}</button>
</form>
<script>
    const choices = document.querySelectorAll('.package-service');
    function updateTotal() {
        const total = [...choices].filter(choice => choice.checked).reduce((sum, choice) => sum + Number(choice.dataset.price), 0);
        document.getElementById('service-total').textContent = 'RM ' + (total / 100).toFixed(2);
    }
    choices.forEach(choice => choice.addEventListener('change', updateTotal));
    updateTotal();
</script>
@endsection
