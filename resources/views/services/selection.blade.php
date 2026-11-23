<div class="form-group">
    <label for="selection_type">{{ __('Service selection') }}</label>
    <select id="selection_type" name="selection_type" class="form-control">
        <option value="">{{ __($allowLegacy ? 'Enter a service description instead' : 'Select package or custom service') }}</option>
        <option value="package" @selected(old('selection_type') === 'package')>{{ __('Package') }}</option>
        <option value="custom" @selected(old('selection_type') === 'custom')>{{ __('Custom service selection') }}</option>
    </select>
</div>
<div class="form-group">
    <label for="package_id">{{ __('Package') }}</label>
    <select id="package_id" name="package_id" class="form-control">
        <option value="">{{ __('Select a package') }}</option>
        @foreach ($packages as $package)
            <option value="{{ $package->id }}" data-price="{{ $package->price_sen }}" @selected((string) old('package_id') === (string) $package->id)>{{ $package->name }} — {{ \App\Models\Invoice::formatAmount($package->price_sen) }} ({{ collect($package->services)->pluck('name')->join(', ') }})</option>
        @endforeach
    </select>
</div>
<fieldset class="mb-3"><legend class="h6">{{ __('Custom service choices') }}</legend>
    @forelse ($services as $service)
        <div class="form-check">
            <input id="choice-{{ $service->id }}" type="checkbox" class="form-check-input quote-choice" name="service_ids[]" value="{{ $service->id }}" data-price="{{ $service->price_sen }}" @checked(in_array((string) $service->id, array_map('strval', is_array(old('service_ids')) ? old('service_ids') : []), true))>
            <label class="form-check-label" for="choice-{{ $service->id }}">{{ $service->name }} — {{ \App\Models\Invoice::formatAmount($service->price_sen) }}</label>
        </div>
    @empty <p>{{ __('No active services.') }}</p> @endforelse
</fieldset>
<div class="form-group"><label for="notes">{{ __('Notes (optional)') }}</label><textarea id="notes" name="notes" class="form-control" maxlength="2000">{{ old('notes') }}</textarea></div>
<p>{{ __('Estimate:') }} <strong id="quote-total">{{ __('Calculated when submitted') }}</strong>. {{ __('Prices are before invoice discount or tax and are not payments.') }}</p>
<noscript><p>{{ __('Enable JavaScript for the selection total. The workshop calculates the estimate when you submit.') }}</p></noscript>
<script>
    const selectionType = document.getElementById('selection_type');
    const packageChoice = document.getElementById('package_id');
    const serviceChoices = document.querySelectorAll('.quote-choice');
    function updateQuote() {
        let total = 0;
        if (selectionType.value === 'package') total = Number(packageChoice.selectedOptions[0]?.dataset.price || 0);
        if (selectionType.value === 'custom') total = [...serviceChoices].filter(choice => choice.checked).reduce((sum, choice) => sum + Number(choice.dataset.price), 0);
        document.getElementById('quote-total').textContent = 'RM ' + (total / 100).toFixed(2);
    }
    selectionType.addEventListener('change', updateQuote);
    packageChoice.addEventListener('change', updateQuote);
    serviceChoices.forEach(choice => choice.addEventListener('change', updateQuote));
    updateQuote();
</script>
