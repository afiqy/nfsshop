@extends('layouts.app')

@section('title', 'Pending payment proofs')

@section('content')
    <a href="{{ route('invoices.index') }}">Back to invoices</a>
    <h1 class="h3 mt-2 mb-3">Pending payment proofs</h1>
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr><th scope="col">Invoice</th><th scope="col">Submitted</th><th scope="col">Method</th><th scope="col" class="text-right">Amount</th><th scope="col">Reference</th><th scope="col">Proof</th><th scope="col">Review</th></tr>
                </thead>
                <tbody>
                    @forelse ($submissions as $submission)
                        <tr>
                            <td><a href="{{ route('invoices.show', $submission->invoice) }}">{{ $submission->invoice->number() }}</a></td>
                            <td>{{ $submission->created_at->timezone(\App\Models\Calendar::TIMEZONE)->format('d M Y H:i') }}</td>
                            <td>{{ $submission->payment_method }}</td>
                            <td class="text-right">{{ \App\Models\Invoice::formatAmount($submission->amount_sen) }}</td>
                            <td>{{ $submission->reference ?? '—' }}</td>
                            <td><a href="{{ route('invoices.payment-submissions.proof', [$submission->invoice, $submission]) }}">Download</a></td>
                            <td>
                                <form action="{{ route('invoices.payment-submissions.approve', [$submission->invoice, $submission]) }}" method="POST" class="mb-2">
                                    @csrf
                                    <button class="btn btn-sm btn-success" type="submit">Approve and record</button>
                                </form>
                                <form action="{{ route('invoices.payment-submissions.reject', [$submission->invoice, $submission]) }}" method="POST">
                                    @csrf
                                    <label class="sr-only" for="reason-{{ $submission->id }}">Reason to reject</label>
                                    <input class="form-control form-control-sm mb-1" id="reason-{{ $submission->id }}"
                                        name="reason" maxlength="1000" placeholder="Reason to reject" required>
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Reject</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center">No payment proofs are waiting for review.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $submissions->links() }}</div>
@endsection
