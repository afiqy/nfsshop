<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\Invoice;
use App\Models\JobCard;
use App\Models\WorkshopPricingSetting;
use App\Models\WorkshopService;
use App\Services\InvoiceLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        return view('staff-invoices.index', [
            'invoices' => $this->visibleInvoices($request)->withSum('payments as paid_sen', 'amount_sen')
                ->withSum('refunds as refunded_sen', 'amount_sen')->withSum('creditNotes as credited_sen', 'amount_sen')
                ->latest('id')->paginate(15),
        ]);
    }

    public function show(Request $request, string $invoice): View
    {
        $invoice = $this->visibleInvoices($request)->withSum('payments as paid_sen', 'amount_sen')
            ->withSum('refunds as refunded_sen', 'amount_sen')->withSum('creditNotes as credited_sen', 'amount_sen')
            ->with('payments')->findOrFail($invoice);

        return view('staff-invoices.show', compact('invoice'));
    }

    public function create(Request $request, string $job): View
    {
        $job = $this->eligibleJob($request, $job)->with(['customer', 'vehicle'])->firstOrFail();
        $this->assertAvailable($job);

        return view('staff-invoices.create', [
            'job' => $job,
            'services' => WorkshopService::query()->where('is_active', true)->orderBy('name')->get(),
            'defaultTaxRate' => WorkshopPricingSetting::query()->find(1)?->tax_rate_basis_points ?? 0,
            'today' => CarbonImmutable::today(Calendar::TIMEZONE)->toDateString(),
        ]);
    }

    public function store(Request $request, string $job, InvoiceController $invoices, InvoiceLifecycleService $lifecycle): RedirectResponse
    {
        $record = DB::connection('tenant')->transaction(function () use ($request, $job, $invoices, $lifecycle) {
            $job = $this->eligibleJob($request, $job)->lockForUpdate()->firstOrFail();
            $this->assertAvailable($job);
            $request->merge(['customer_id' => $job->customer_id, 'vehicle_id' => $job->vehicle_id]);
            $data = $invoices->validateInvoice($request);
            if (collect($data['items'])->pluck('inventory_item_id')->filter()->isNotEmpty()) {
                throw ValidationException::withMessages(['items' => 'Inventory deductions cannot be added through staff job invoices.']);
            }
            $invoice = $lifecycle->save($data, $request->user());
            $invoice->update(['job_card_id' => $job->id]);

            return $invoice;
        }, 3);

        return redirect()->route('staff-invoices.show', $record)->with('success', 'Invoice created for the completed job.');
    }

    public function recordPayment(Request $request, string $invoice, InvoiceController $invoices): RedirectResponse
    {
        abort_unless($request->user()->can_record_payments, 403);
        $request->validate(['payment_method' => ['required', 'in:cash,pos,transfer']]);

        return $invoices->recordPayment($request, $invoice);
    }

    private function eligibleJob(Request $request, string $job): Builder
    {
        abort_unless($request->user()->can_create_job_invoices, 403);

        return JobCard::query()->whereKey($job)->where('assigned_mechanic_id', $request->user()->id)
            ->where('status', JobCard::COMPLETED);
    }

    private function assertAvailable(JobCard $job): void
    {
        if (! $job->customer_id || ! $job->vehicle_id) {
            throw ValidationException::withMessages(['job' => 'This job needs a customer and vehicle before it can be invoiced.']);
        }
        if (Invoice::query()->where('job_card_id', $job->id)->where('status', '!=', Invoice::STATUS_VOIDED)->exists()) {
            throw ValidationException::withMessages(['job' => 'This job already has an active linked invoice.']);
        }
    }

    private function visibleInvoices(Request $request): Builder
    {
        $user = $request->user();
        abort_unless($user->can_record_payments || $user->can_create_job_invoices, 403);

        return Invoice::query()->when(! $user->can_record_payments, fn ($query) => $query
            ->whereIn('job_card_id', JobCard::query()->where('assigned_mechanic_id', $user->id)->select('id')));
    }
}
