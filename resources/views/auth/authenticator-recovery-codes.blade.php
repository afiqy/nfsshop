@extends('layouts.app')
@section('title', 'Authenticator recovery codes')
@section('content')
    <h1 class="h3">Save your recovery codes</h1>
    <p>Each code can be used once. Store them securely; they will not be shown again.</p>
    <ul>@foreach ($codes as $code)<li><code>{{ $code }}</code></li>@endforeach</ul>
    <a href="{{ route('authenticator.show') }}">Continue</a>
@endsection
