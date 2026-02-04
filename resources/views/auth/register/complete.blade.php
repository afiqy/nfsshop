@extends('layouts.guest')

@section('title', 'Registration Complete')

@section('content')
<div class="container text-center">
    <h2 class="mb-3">You are all set!</h2>
    <p class="lead">Your trial has started and we have prepared your workshop dashboard.</p>

    <div class="card mx-auto" style="max-width: 480px;">
        <div class="card-body">
            <p class="mb-1">Next steps:</p>
            <ul class="text-left">
                <li>Invite your staff and assign roles.</li>
                <li>Complete your business profile before issuing invoices.</li>
                <li>Explore billing to pick the right package before your trial ends.</li>
            </ul>
            <a href="{{ route('dashboard') }}" class="btn btn-primary">Go to my dashboard</a>
        </div>
    </div>
</div>
@endsection
