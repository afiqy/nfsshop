<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePaymentSubmission;
use App\Models\User;
use App\Models\WorkshopPaymentQr;
use App\Services\InvoicePaymentSubmissionService;
use App\Services\PaymentProofStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoicePaymentSubmissionController extends Controller
{
    public function adminIndex(): Response
    {
        return response()->view('invoices.payment-submissions', [
            'submissions' => InvoicePaymentSubmission::query()->where('status', InvoicePaymentSubmission::STATUS_PENDING)
                ->with('invoice')->latest('id')->paginate(20),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function customerIndex(Request $request): Response
    {
        $customer = $this->activeCustomer($request);

        return response()->view('customer-portal.invoices.index', [
            'invoices' => Invoice::query()->where('customer_id', $customer->id)
                ->withSum('payments as paid_sen', 'amount_sen')
                ->withSum('refunds as refunded_sen', 'amount_sen')
                ->withSum('creditNotes as credited_sen', 'amount_sen')
                ->latest('id')->paginate(15),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function customerShow(Request $request, string $invoice): Response
    {
        $customer = $this->activeCustomer($request);
        $invoice = Invoice::query()->where('customer_id', $customer->id)
            ->withSum('payments as paid_sen', 'amount_sen')
            ->withSum('refunds as refunded_sen', 'amount_sen')
            ->withSum('creditNotes as credited_sen', 'amount_sen')
            ->findOrFail($invoice);

        return response()->view('customer-portal.invoices.show', [
            'invoice' => $invoice,
            'paymentQrs' => WorkshopPaymentQr::query()->where('is_active', true)
                ->orderBy('category')->orderBy('label')->get(),
            'submissions' => $invoice->paymentSubmissions()->latest('id')->limit(20)->get(),
            'refunds' => $invoice->refunds()->with('payment')->latest('id')->get(),
            'creditNotes' => $invoice->creditNotes()->latest('id')->get(),
            'pendingAmount' => (int) $invoice->paymentSubmissions()
                ->where('status', InvoicePaymentSubmission::STATUS_PENDING)->sum('amount_sen'),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function customerSubmit(
        Request $request,
        string $invoice,
        InvoicePaymentSubmissionService $submissions
    ): RedirectResponse {
        $customer = $this->activeCustomer($request);
        $invoice = Invoice::query()->where('customer_id', $customer->id)->findOrFail($invoice);
        $data = $request->validate($this->submissionRules());
        $submissions->submitByCustomer($invoice, $customer, $request->user(), $data, $data['proof']);

        return redirect()->route('client.invoices.show', $invoice)
            ->with('success', 'Payment proof sent to the workshop for verification.');
    }

    public function customerProof(
        Request $request,
        string $invoice,
        string $submission,
        PaymentProofStorage $proofStorage
    ): Response {
        $customer = $this->activeCustomer($request);
        $invoice = Invoice::query()->where('customer_id', $customer->id)->findOrFail($invoice);
        $record = $invoice->paymentSubmissions()->findOrFail($submission);

        return $proofStorage->download($record);
    }

    public function adminRecord(
        Request $request,
        string $invoice,
        InvoicePaymentSubmissionService $submissions
    ): RedirectResponse {
        $invoice = Invoice::query()->findOrFail($invoice);
        $data = $request->validate($this->submissionRules());
        $submissions->recordByAdmin($invoice, $request->user(), $data, $data['proof']);

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'QR payment recorded with proof.');
    }

    public function approve(
        Request $request,
        string $invoice,
        string $submission,
        InvoicePaymentSubmissionService $submissions
    ): RedirectResponse {
        $invoice = Invoice::query()->findOrFail($invoice);
        $record = $invoice->paymentSubmissions()->findOrFail($submission);
        $submissions->approve($record, $request->user());

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Payment proof approved and payment recorded.');
    }

    public function reject(
        Request $request,
        string $invoice,
        string $submission,
        InvoicePaymentSubmissionService $submissions
    ): RedirectResponse {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $invoice = Invoice::query()->findOrFail($invoice);
        $record = $invoice->paymentSubmissions()->findOrFail($submission);
        $submissions->reject($record, $request->user(), $data['reason']);

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Payment proof rejected.');
    }

    public function adminProof(
        string $invoice,
        string $submission,
        PaymentProofStorage $proofStorage
    ): Response {
        $invoice = Invoice::query()->findOrFail($invoice);
        $record = $invoice->paymentSubmissions()->findOrFail($submission);

        return $proofStorage->download($record);
    }

    private function activeCustomer(Request $request): Customer
    {
        abort_unless($request->user()->status === User::STATUS_ACTIVE, 403);

        return Customer::query()->where('portal_user_id', $request->user()->id)->firstOrFail();
    }

    private function submissionRules(): array
    {
        return [
            'amount' => ['required', 'regex:/\A[0-9]{1,13}(?:\.[0-9]{1,2})?\z/'],
            'workshop_payment_qr_id' => ['required', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:255'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }
}
