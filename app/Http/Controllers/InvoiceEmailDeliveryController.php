<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceEmailDelivery;
use App\Services\InvoiceEmailDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvoiceEmailDeliveryController extends Controller
{
    public function store(
        Request $request,
        string $invoice,
        InvoiceEmailDeliveryService $deliveries
    ): RedirectResponse {
        $record = Invoice::query()->findOrFail($invoice);
        $delivery = $deliveries->send($record, $request->user());

        return $this->response($record, $delivery);
    }

    public function retry(
        Request $request,
        string $invoice,
        string $delivery,
        InvoiceEmailDeliveryService $deliveries
    ): RedirectResponse {
        $record = Invoice::query()->findOrFail($invoice);
        $previousAttempt = $record->emailDeliveries()->findOrFail($delivery);
        $attempt = $deliveries->retry($record, $previousAttempt, $request->user());

        return $this->response($record, $attempt);
    }

    private function response(Invoice $invoice, InvoiceEmailDelivery $delivery): RedirectResponse
    {
        if ($delivery->status === InvoiceEmailDelivery::STATUS_SENT) {
            return redirect()->route('invoices.show', $invoice)
                ->with('success', 'Invoice email accepted by the configured mail transport.');
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('error', 'Invoice email failed. Check the delivery history and application logs before retrying.');
    }
}
