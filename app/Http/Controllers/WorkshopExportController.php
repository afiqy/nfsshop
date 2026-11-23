<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\JobCard;
use App\Models\User;
use App\Services\WorkshopExport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkshopExportController extends Controller
{
    public function index(Request $request, string $type, string $format, WorkshopExport $export): Response|StreamedResponse
    {
        $validated = $request->validate([
            'batch' => $format === 'pdf' ? ['nullable', 'integer', 'min:1', 'max:1000000'] : ['prohibited'],
            'status' => $type === 'invoices' ? ['nullable', 'in:overdue'] : ['prohibited'],
            'filter' => $type === 'jobs' ? ['nullable', 'in:overdue'] : ['prohibited'],
        ]);
        $batch = (int) ($validated['batch'] ?? 1);
        $today = CarbonImmutable::today(Calendar::TIMEZONE)->toDateString();
        $date = fn ($value) => $value?->copy()->timezone(Calendar::TIMEZONE)->format('Y-m-d H:i') ?? '';

        if ($type === 'invoices') {
            $overdue = ($validated['status'] ?? null) === 'overdue';
            $rows = Invoice::withPaymentTotals()->when($overdue, fn ($query) => $query->overdue($today)->orderBy('due_date'))
                ->latest('id')->cursor()->map(function ($invoice) use ($date) {
                    $paid = $invoice->paid_sen ?? 0;
                    $refunded = $invoice->refunded_sen ?? 0;
                    $credited = $invoice->credited_sen ?? 0;

                    return [$invoice->number(), $date($invoice->created_at), $invoice->due_date?->format('Y-m-d') ?? '',
                        $invoice->customer_name, $invoice->vehicle_plate ?? '', $invoice->total_sen,
                        $paid, $refunded, $credited, $invoice->balanceDue($paid, $refunded, $credited),
                        $invoice->paymentStatus($paid, $refunded, $credited)];
                });

            return $export->download($format, 'Invoices', ['Invoice', 'Created (MYT)', 'Due date', 'Customer', 'Vehicle', 'Total (sen)', 'Paid (sen)', 'Refunded (sen)', 'Credited (sen)', 'Balance (sen)', 'Payment status'],
                $rows, $batch, $overdue ? 'Overdue invoices only.' : 'All invoices, including voided invoices.');
        }
        if ($type === 'payments') {
            $rows = InvoicePayment::query()->join('invoices', 'invoices.id', '=', 'invoice_payments.invoice_id')
                ->select('invoice_payments.*', 'invoices.customer_name')
                ->withSum('refunds as refunded_sen', 'amount_sen')->orderByDesc('invoice_payments.id')->cursor()
                ->map(fn ($payment) => [$payment->id, sprintf('INV-%06d', $payment->invoice_id), $payment->customer_name,
                    $date($payment->created_at), $payment->payment_method ?? '', $payment->reference ?? '',
                    $payment->amount_sen, $payment->refunded_sen ?? 0, $payment->recorded_by_user_id]);

            return $export->download($format, 'Payments', ['Payment ID', 'Invoice', 'Customer', 'Recorded (MYT)', 'Method', 'Reference', 'Amount (sen)', 'Refunded (sen)', 'Recorded by user ID'],
                $rows, $batch, 'Recorded payments and their refunds; not payment processing or settlement.');
        }
        $overdue = ($validated['filter'] ?? null) === 'overdue';
        $names = User::on(config('tenancy.database.central_connection'))->where('tenant_id', tenant('id'))->pluck('name', 'id');
        $rows = JobCard::query()->leftJoin('customers', 'customers.id', '=', 'job_cards.customer_id')
            ->leftJoin('vehicles', 'vehicles.id', '=', 'job_cards.vehicle_id')
            ->select('job_cards.*', 'customers.name as customer_name', 'vehicles.plate_number as vehicle_plate')
            ->when($overdue, fn ($query) => $query->overdue($today))
            ->orderByDesc('job_cards.id')->cursor()
            ->map(fn ($job) => [$job->id, $date($job->created_at), $job->customer_name ?? 'Unavailable',
                $job->vehicle_plate ?? 'Unavailable', JobCard::LABELS[$job->status] ?? $job->status,
                $job->due_date?->format('Y-m-d') ?? '', $job->assigned_mechanic_id
                    ? ($names[$job->assigned_mechanic_id] ?? 'Unavailable #'.$job->assigned_mechanic_id) : 'Unassigned',
                $job->description]);

        return $export->download($format, 'Job cards', ['Job ID', 'Created (MYT)', 'Customer', 'Vehicle', 'Status', 'Target date', 'Assigned mechanic', 'Work description'],
            $rows, $batch, $overdue ? 'Overdue jobs only.' : 'All job cards.');
    }
}
