<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoiceAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceAdjustmentController extends Controller
{
    public function storeRefund(
        Request $request,
        string $invoice,
        string $payment,
        InvoiceAdjustmentService $adjustments
    ): RedirectResponse {
        $data = $request->validate([
            'amount' => ['required', 'regex:/\A[0-9]{1,13}(?:\.[0-9]{1,2})?\z/'],
            'reason' => ['required', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);
        $invoice = Invoice::query()->findOrFail($invoice);
        $payment = $invoice->payments()->findOrFail($payment);
        $refund = $adjustments->recordRefund($invoice, $payment, $request->user(), $data);

        return redirect()->route('invoices.refunds.show', [$invoice, $refund])
            ->with('success', 'Refund recorded. No money was transferred by the app.');
    }

    public function showRefund(string $invoice, string $refund): View
    {
        $invoice = Invoice::query()->findOrFail($invoice);
        $refund = $invoice->refunds()->with('payment')->findOrFail($refund);

        return view('invoices.refund', compact('invoice', 'refund'));
    }

    public function storeCreditNote(
        Request $request,
        string $invoice,
        InvoiceAdjustmentService $adjustments
    ): RedirectResponse {
        $data = $request->validate([
            'amount' => ['required', 'regex:/\A[0-9]{1,13}(?:\.[0-9]{1,2})?\z/'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $invoice = Invoice::query()->findOrFail($invoice);
        $creditNote = $adjustments->issueCreditNote($invoice, $request->user(), $data);

        return redirect()->route('invoices.credit-notes.show', [$invoice, $creditNote])
            ->with('success', 'Credit note issued. It does not transfer or refund money.');
    }

    public function showCreditNote(string $invoice, string $creditNote): View
    {
        $invoice = Invoice::query()->findOrFail($invoice);
        $creditNote = $invoice->creditNotes()->findOrFail($creditNote);

        return view('invoices.credit-note', compact('invoice', 'creditNote'));
    }
}
