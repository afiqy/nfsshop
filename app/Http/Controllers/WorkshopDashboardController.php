<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\JobCard;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WorkshopDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $isWorkshopAdmin = $user->role === User::ROLE_ADMIN;
        $isMechanic = $user->role === User::ROLE_MECHANIC;
        $hasWorkshop = $isWorkshopAdmin && tenancy()->initialized && $user->tenant_id === tenant('id');
        $hasMechanicWorkshop = $isMechanic && $user->status === User::STATUS_ACTIVE
            && tenancy()->initialized && $user->tenant_id === tenant('id');
        $subscriptionUsable = $request->attributes->get('subscription_usable', false);
        $today = CarbonImmutable::today(Calendar::TIMEZONE);

        $data = [
            'isWorkshopAdmin' => $isWorkshopAdmin,
            'subscriptionUsable' => $subscriptionUsable,
            'hasWorkshop' => $hasWorkshop,
            'staffDashboard' => $hasMechanicWorkshop && $subscriptionUsable,
            'workshopName' => $hasWorkshop ? (tenant('workshop_name') ?: $user->workshop_name ?: tenant('id')) : null,
            'planName' => $request->attributes->get('account_subscription')?->plan?->name,
            'today' => $today,
        ];

        if ($hasWorkshop && $subscriptionUsable) {
            $appointments = Calendar::query()
                ->where('status', Calendar::STATUS_SCHEDULED)
                ->where('start', '<', $today->addDay()->toDateTimeString())
                ->where(fn ($query) => $query
                    ->where('start', '>=', $today->toDateTimeString())
                    ->orWhere('end', '>', $today->toDateTimeString()))
                ->orderBy('start')->orderBy('id');

            $payments = InvoicePayment::query()
                ->selectRaw('invoice_id, SUM(amount_sen) AS paid_sen')
                ->groupBy('invoice_id');
            $balance = 'invoices.total_sen - COALESCE(payments.paid_sen, 0)';
            $outstanding = Invoice::query()
                ->leftJoinSub($payments, 'payments', 'payments.invoice_id', '=', 'invoices.id')
                ->whereRaw("$balance > 0")
                ->selectRaw("COUNT(*) AS invoice_count, COALESCE(SUM($balance), 0) AS balance_sen")
                ->selectRaw('COUNT(CASE WHEN invoices.due_date < ? THEN 1 END) AS overdue_count', [$today->toDateString()])
                ->selectRaw("COALESCE(SUM(CASE WHEN invoices.due_date < ? THEN $balance ELSE 0 END), 0) AS overdue_sen", [$today->toDateString()])
                ->first();

            $data += [
                'appointmentCount' => $appointments->count(),
                'appointments' => $appointments->with(['vehicle:id,plate_number'])->limit(10)->get(),
                'openJobCount' => JobCard::whereIn('status', [JobCard::OPEN, JobCard::IN_PROGRESS])->count(),
                'overdueJobCount' => JobCard::whereIn('status', [JobCard::OPEN, JobCard::IN_PROGRESS])
                    ->whereNotNull('due_date')->where('due_date', '<', $today->toDateString())->count(),
                'outstandingCount' => (int) $outstanding->invoice_count,
                'outstandingSen' => (int) $outstanding->balance_sen,
                'overdueCount' => (int) $outstanding->overdue_count,
                'overdueSen' => (int) $outstanding->overdue_sen,
            ];
        } elseif ($hasMechanicWorkshop && $subscriptionUsable) {
            $assignedJobs = JobCard::query()
                ->where('assigned_mechanic_id', $user->id)
                ->whereIn('status', [JobCard::OPEN, JobCard::IN_PROGRESS]);
            $todayJobsQuery = (clone $assignedJobs)->whereHas('appointment', fn ($query) => $query
                ->where('status', Calendar::STATUS_SCHEDULED)
                ->where('start', '>=', $today->toDateTimeString())
                ->where('start', '<', $today->addDay()->toDateTimeString()));

            $data += [
                'assignedJobCount' => (clone $assignedJobs)->count(),
                'overdueJobCount' => (clone $assignedJobs)->whereNotNull('due_date')
                    ->where('due_date', '<', $today->toDateString())->count(),
                'todayJobCount' => (clone $todayJobsQuery)->count(),
                'todayJobs' => $todayJobsQuery->with(['customer', 'vehicle', 'appointment'])
                    ->orderByRaw('(SELECT start FROM calendars WHERE calendars.id = job_cards.calendar_id)')
                    ->orderBy('id')->limit(10)->get(),
            ];
        }

        return response()->view('dashboard', $data)->header('Cache-Control', 'private, no-store');
    }
}
