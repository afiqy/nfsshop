@extends('layouts.app')

@section('title', __('Workshop payment QR codes'))

@section('content')
    <a href="{{ route('payment-preferences.edit') }}">{{ __('Back to payment settings') }}</a>
    <h1 class="h3 mt-2 mb-3">{{ __('Workshop payment QR codes') }}</h1>
    <p>{{ __('Upload bank or e-wallet QR images for linked customers. Payment proof uploaded by a customer stays pending until an admin verifies it.') }}</p>

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

    <section class="card shadow-sm p-3 mb-4" aria-labelledby="add-payment-qr">
        <h2 class="h5" id="add-payment-qr">{{ __('Add payment QR') }}</h2>
        <p>{{ __('Up to :count active QRs. Use PNG, JPEG or WebP images no larger than 2 MB.', ['count' => $maxActive]) }}</p>
        <form action="{{ route('payment-qrs.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="category">{{ __('Type') }}</label>
                    <select class="form-control" id="category" name="category" required>
                        <option value="bank" @selected(old('category') === 'bank')>{{ __('Bank') }}</option>
                        <option value="ewallet" @selected(old('category') === 'ewallet')>{{ __('E-wallet') }}</option>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label for="label">{{ __('Label') }}</label>
                    <input class="form-control" id="label" name="label" maxlength="100" value="{{ old('label') }}" required>
                </div>
                <div class="form-group col-md-5">
                    <label for="image">{{ __('QR image') }}</label>
                    <input class="form-control-file" id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" required>
                </div>
            </div>
            <button class="btn btn-primary" type="submit">{{ __('Upload QR') }}</button>
        </form>
    </section>

    <h2 class="h5">{{ __('Saved payment QRs') }}</h2>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr><th scope="col">{{ __('Type') }}</th><th scope="col">{{ __('Label') }}</th><th scope="col">{{ __('QR') }}</th><th scope="col">{{ __('Availability') }}</th><th scope="col">{{ __('Action') }}</th></tr>
                </thead>
                <tbody>
                    @forelse ($paymentQrs as $qr)
                        <tr>
                            <td>{{ __($qr->category === \App\Models\WorkshopPaymentQr::CATEGORY_BANK ? 'Bank' : 'E-wallet') }}</td>
                            <td>{{ $qr->label }}</td>
                            <td>
                                <img src="{{ route('payment-qrs.image', $qr) }}" alt="{{ __(':label payment QR', ['label' => $qr->label]) }}"
                                    style="max-width: 9rem; max-height: 9rem">
                            </td>
                            <td>{{ __($qr->is_active ? 'Shown to customers' : 'Hidden from customers') }}</td>
                            <td>
                                <form action="{{ route('payment-qrs.active', $qr) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="active" value="{{ $qr->is_active ? '0' : '1' }}">
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">
                                        {{ __($qr->is_active ? 'Hide' : 'Show') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center">{{ __('No payment QR codes uploaded.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
