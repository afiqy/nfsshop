<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\Invoice;
use App\Models\JobCard;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class WorkshopReportController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'period' => ['nullable', 'regex:/\A20[0-9]{2}(?:-(?:0[1-9]|1[0-2]))?\z/'],
        ]);
        $period = $validated['period'] ?? CarbonImmutable::now(Calendar::TIMEZONE)->format('Y-m');
        $isYear = strlen($period) === 4;
        $startLocal = CarbonImmutable::createFromFormat($isYear ? '!Y' : '!Y-m', $period, Calendar::TIMEZONE);
        $endLocal = $isYear ? $startLocal->addYear() : $startLocal->addMonth();
        $start = $startLocal->utc()->toDateTimeString();
        $end = $endLocal->utc()->toDateTimeString();
        $db = DB::connection('tenant');

        return $db->transaction(function () use ($db, $start, $end, $period, $request) {
            $invoices = $db->table('invoices')->where('status', Invoice::STATUS_ISSUED)
                ->where('created_at', '>=', $start)->where('created_at', '<', $end);
            $credits = $db->table('invoice_credit_notes as c')->join('invoices as i', 'i.id', '=', 'c.invoice_id')
                ->where('i.status', Invoice::STATUS_ISSUED)->where('c.created_at', '>=', $start)->where('c.created_at', '<', $end);
            $payments = $db->table('invoice_payments as p')->join('invoices as i', 'i.id', '=', 'p.invoice_id')
                ->where('i.status', Invoice::STATUS_ISSUED)->where('p.created_at', '>=', $start)->where('p.created_at', '<', $end);
            $refunds = $db->table('invoice_refunds as r')->join('invoice_payments as p', 'p.id', '=', 'r.invoice_payment_id')
                ->join('invoices as i', 'i.id', '=', 'p.invoice_id')->where('i.status', Invoice::STATUS_ISSUED)
                ->where('r.created_at', '>=', $start)->where('r.created_at', '<', $end);
            $invoiced = (int) (clone $invoices)->sum('total_sen');
            $credited = (int) (clone $credits)->sum('c.amount_sen');
            $received = (int) (clone $payments)->sum('p.amount_sen');
            $refunded = (int) (clone $refunds)->sum('r.amount_sen');

            $cashMovements = (clone $payments)->selectRaw('i.customer_id, CAST(p.amount_sen AS SIGNED) AS amount_sen')
                ->unionAll((clone $refunds)->selectRaw('i.customer_id, -CAST(r.amount_sen AS SIGNED) AS amount_sen'));
            $customers = $db->query()->fromSub($cashMovements, 'movement')
                ->leftJoin('customers as c', 'c.id', '=', 'movement.customer_id')
                ->selectRaw('movement.customer_id, c.name, SUM(movement.amount_sen) AS net_sen')
                ->groupBy('movement.customer_id', 'c.name')->orderByDesc('net_sen')->orderBy('movement.customer_id')
                ->paginate(25, ['*'], 'customers_page')->withQueryString();

            $staff = JobCard::query()->where('status', JobCard::COMPLETED)
                ->where('completed_at', '>=', $start)->where('completed_at', '<', $end)
                ->selectRaw('assigned_mechanic_id, COUNT(*) AS completed_count')
                ->groupBy('assigned_mechanic_id')->orderByDesc('completed_count')->orderBy('assigned_mechanic_id')
                ->paginate(25, ['*'], 'staff_page')->withQueryString();
            $staffNames = User::on(config('tenancy.database.central_connection'))
                ->where('tenant_id', tenant('id'))->whereIn('id', $staff->pluck('assigned_mechanic_id')->filter()->all())
                ->pluck('name', 'id');

            $services = [];
            $unclassified = 0;
            foreach (Invoice::query()->where('status', Invoice::STATUS_ISSUED)
                ->where('created_at', '>=', $start)->where('created_at', '<', $end)
                ->select(['id', 'items', 'pricing'])->lazyById(500) as $invoice) {
                foreach ($invoice->items as $item) {
                    $gross = (int) $item['total_sen'];
                    if (($invoice->pricing['tax_mode'] ?? null) === 'inclusive') {
                        $rate = (int) $invoice->pricing['tax_rate'];
                        $gross -= intdiv($gross * $rate + intdiv(10000 + $rate, 2), 10000 + $rate);
                    }
                    if (! isset($item['service_id'], $item['service_name'])) {
                        $unclassified += $gross;

                        continue;
                    }
                    $id = (int) $item['service_id'];
                    if (! isset($services[$id])) {
                        $services[$id] = ['id' => $id, 'name' => $item['service_name'], 'gross_sen' => 0, 'quantity' => 0];
                    }
                    $services[$id]['gross_sen'] += $gross;
                    $services[$id]['quantity'] += (int) $item['quantity'];
                }
            }
            $sortedServices = collect($services)->sortByDesc('gross_sen')->values();
            $servicePage = LengthAwarePaginator::resolveCurrentPage('services_page');
            $serviceRows = (new LengthAwarePaginator(
                $sortedServices->forPage($servicePage, 25)->values(), $sortedServices->count(), 25, $servicePage,
                ['path' => $request->url(), 'pageName' => 'services_page']
            ))->withQueryString();

            return response()->view('reports.index', [
                'period' => $period,
                'invoiced' => $invoiced, 'credited' => $credited, 'received' => $received, 'refunded' => $refunded,
                'invoiceCount' => (clone $invoices)->count(),
                'customers' => $customers, 'staff' => $staff, 'staffNames' => $staffNames,
                'services' => $serviceRows, 'unclassified' => $unclassified,
            ])->header('Cache-Control', 'private, no-store');
        });
    }
}
