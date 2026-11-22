<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoicePaymentReminderAttempt;
use App\Models\PaymentReminderSetting;
use App\Services\InvoicePaymentReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentReminderController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        PaymentReminderSetting::query()->findOrFail(1)->update(['enabled' => $data['enabled']]);

        return redirect()->route('invoices.index')->with('success', 'Payment reminder settings saved.');
    }

    public function retry(
        Request $request,
        string $invoice,
        string $reminder,
        InvoicePaymentReminderService $reminders
    ): RedirectResponse {
        $record = Invoice::query()->findOrFail($invoice);
        $stage = $record->paymentReminders()->findOrFail($reminder);
        $attempt = $reminders->retry($record, $stage, $request->user());

        return redirect()->route('invoices.show', $record)->with(
            $attempt->status === InvoicePaymentReminderAttempt::SENT ? 'success' : 'error',
            $attempt->status === InvoicePaymentReminderAttempt::SENT
                ? 'Payment reminder accepted by the configured mail transport.'
                : 'Payment reminder failed. Review the reminder history before retrying.'
        );
    }
}
