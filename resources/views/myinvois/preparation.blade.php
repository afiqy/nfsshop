@extends('layouts.app')

@section('title', 'MyInvois preparation')

@section('content')
    <div class="container-fluid">
        <a href="{{ $invoice ? route('invoices.show', $invoice) : route('invoices.index') }}">
            {{ $invoice ? 'Back to invoice' : 'Back to invoices' }}
        </a>
        <h1 class="h3 mt-2">MyInvois preparation: {{ $invoice ? $invoice->number() : 'Workshop tax profile' }}</h1>
        <p>Preparation only. No e-invoice is generated or sent to LHDN and no taxpayer details are verified.</p>
        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <p>Nothing was saved. For privacy, unsaved entries are not restored.</p>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($invoice)
            <section class="mb-3" aria-labelledby="preparation-checklist">
                <h2 class="h5" id="preparation-checklist">Preparation checklist</h2>
                <ul>
                    <li><a href="{{ route('myinvois.seller.edit') }}">Workshop tax profile</a>: {{ $sellerSaved ? 'Saved' : 'Missing' }}</li>
                    <li>Buyer details: {{ $buyerSaved ? 'Saved' : 'Missing' }}</li>
                    <li>Not available yet: line classification and tax mapping, signed document generation, submission and validation.</li>
                </ul>
            </section>
        @endif
        <form class="card shadow-sm p-3" method="POST" action="{{ $invoice ? route('myinvois.buyer.update', $invoice) : route('myinvois.seller.update') }}" autocomplete="off">
            @csrf
            @method('PUT')
            <h2 class="h5">{{ $invoice ? 'Named buyer details (this invoice only)' : 'Supplier details (this workshop only)' }}</h2>
            <p>Use a Malaysian address. Only enter taxpayer details you are authorized to collect. Consolidated e-invoices are not supported.</p>
            @unless ($invoice)
                <p>Find your business activity in the <a href="https://sdk.myinvois.hasil.gov.my/codes/msic-codes/">official MyInvois MSIC code list</a>.</p>
            @endunless
            @php
                $fields = [
                    'legal_name' => ['Registered name', 300],
                    'tin' => ['Tax identification number (TIN, uppercase)', 14],
                    'id_number' => ['Registration or identification number', 30],
                    'address_line' => ['Address line', 150],
                    'postcode' => ['Postcode (5 digits)', 5],
                    'city' => ['City', 50],
                    'phone' => ['Contact number (international format, e.g. +60123456789)', 16],
                    'sst_number' => ['SST registration number (if applicable)', 35],
                ];
                if (! $invoice) {
                    $fields['msic'] = ['MSIC code (5 digits) from the official code list', 5];
                    $fields['business_activity'] = ['Business activity description', 300];
                }
            @endphp
            <div class="form-row">
                @foreach ($fields as $field => [$label, $length])
                    @if (in_array($field, ['id_number', 'phone'], true))
                        @php
                            $isId = $field === 'id_number';
                            $select = $isId ? 'id_type' : 'state';
                        @endphp
                        <div class="form-group col-md-6">
                            <label for="{{ $select }}">{{ $isId ? 'Identification type' : 'State' }}</label>
                            <select class="form-control" id="{{ $select }}" name="{{ $select }}" required>
                                <option value="">Select {{ $isId ? 'identification type' : 'state' }}</option>
                                @foreach (($isId ? $idTypes : $states) as $code => $description)
                                    <option value="{{ $code }}" @selected((string) ($details[$select] ?? '') === (string) $code)>{{ $description }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="form-group col-md-6">
                        <label for="{{ $field }}">{{ $label }}</label>
                        <input class="form-control" id="{{ $field }}" name="{{ $field }}" maxlength="{{ $length }}"
                            value="{{ $details[$field] ?? '' }}" @if ($field !== 'sst_number') required @endif>
                    </div>
                @endforeach
            </div>
            <div><button class="btn btn-primary" type="submit">{{ $invoice ? 'Save buyer details' : 'Save workshop tax profile' }}</button></div>
        </form>

        @if ($buyerSaved)
            <form class="mt-3" method="POST" action="{{ route('myinvois.buyer.clear', $invoice) }}">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger" type="submit">Clear saved buyer details</button>
            </form>
        @endif
    </div>
@endsection
