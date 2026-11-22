<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\InventoryItem;
use App\Models\JobCard;
use App\Models\JobCardPart;
use App\Models\JobCardUpdate;
use App\Models\ServicePackage;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkshopService;
use App\Services\ServiceQuoteService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JobCardController extends Controller
{
    public function __construct(private ServiceQuoteService $quotes) {}

    public function index(Request $request): View
    {
        $staffView = $this->isMechanic($request);
        $validated = $request->validate(['filter' => ['nullable', Rule::in(['overdue'])]]);
        $overdueOnly = ($validated['filter'] ?? null) === 'overdue';
        $today = CarbonImmutable::today(Calendar::TIMEZONE)->toDateString();
        $jobsQuery = $this->visibleJobs($request)->with(['customer', 'vehicle']);
        if ($overdueOnly) {
            $jobsQuery->whereIn('status', [JobCard::OPEN, JobCard::IN_PROGRESS])
                ->whereNotNull('due_date')->where('due_date', '<', $today);
        }
        $jobs = $jobsQuery->latest('id')->paginate(15)->withQueryString();
        $assigneeNames = $staffView ? collect() : $this->mechanics($request)
            ->whereIn('id', $jobs->pluck('assigned_mechanic_id')->filter()->unique()->all())->pluck('name', 'id');

        return view('job-cards.index', [
            'jobs' => $jobs,
            'staffView' => $staffView,
            'assigneeNames' => $assigneeNames,
            'overdueOnly' => $overdueOnly,
            'today' => $today,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $validated = $request->validate(['appointment' => ['nullable', 'integer', 'min:1']]);
        $appointment = isset($validated['appointment'])
            ? Calendar::with(['customer', 'vehicle', 'jobCard'])->findOrFail($validated['appointment'])
            : null;

        if ($appointment?->jobCard) {
            return redirect()->route('job-cards.show', $appointment->jobCard);
        }

        if ($appointment) {
            abort_unless($appointment->status === Calendar::STATUS_SCHEDULED
                && $appointment->vehicle
                && $appointment->vehicle->customer_id === $appointment->customer_id, 409,
                'This appointment is no longer available for a job card.');
        }

        return view('job-cards.create', [
            'appointment' => $appointment,
            'vehicles' => $appointment ? collect() : Vehicle::with('customer')->orderBy('plate_number')->get(),
            'today' => CarbonImmutable::today(Calendar::TIMEZONE)->toDateString(),
            'services' => $appointment ? collect() : WorkshopService::query()->where('is_active', true)->orderBy('name')->get(),
            'packages' => $appointment ? collect() : ServicePackage::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'vehicle_id' => ['required', 'integer', 'min:1'],
            'calendar_id' => ['nullable', 'integer', 'min:1'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.CarbonImmutable::today(Calendar::TIMEZONE)->toDateString()],
            'description' => ['required', 'string', 'max:4000'],
        ]);

        $actor = $request->user();
        $job = DB::connection('tenant')->transaction(function () use ($validated, $actor, $request) {
            $description = $validated['description'];
            if ($request->filled('selection_type')) {
                if (isset($validated['calendar_id'])) {
                    throw ValidationException::withMessages(['selection_type' => 'The appointment already has its service selection.']);
                }
                $quote = $this->quotes->fromRequest($request);
                $description = $quote['name'].': '.collect($quote['services'])->pluck('name')->join(', ')
                    .($quote['notes'] ? "\n".$quote['notes'] : '')."\n".$description;
                if (mb_strlen($description) > 4000) {
                    throw ValidationException::withMessages(['description' => 'The service selection and work description together must not exceed 4000 characters.']);
                }
            }
            $vehicle = Vehicle::query()->lockForUpdate()->find($validated['vehicle_id']);
            if (! $vehicle) {
                throw ValidationException::withMessages(['vehicle_id' => 'Select a vehicle from this workshop.']);
            }

            $appointment = null;
            if (isset($validated['calendar_id'])) {
                $appointment = Calendar::query()->lockForUpdate()->find($validated['calendar_id']);
                if (! $appointment || $appointment->status !== Calendar::STATUS_SCHEDULED
                    || $appointment->vehicle_id !== $vehicle->id || $appointment->customer_id !== $vehicle->customer_id
                    || $appointment->jobCard()->exists()) {
                    throw ValidationException::withMessages(['calendar_id' => 'This appointment is not available for a new job card for this vehicle.']);
                }
            }

            $job = JobCard::create([
                'vehicle_id' => $vehicle->id,
                'customer_id' => $vehicle->customer_id,
                'calendar_id' => $appointment?->id,
                'description' => $description,
                'status' => JobCard::OPEN,
                'due_date' => $validated['due_date'] ?? null,
            ]);

            $job->updates()->create([
                'actor_user_id' => $actor->id,
                'actor_name' => $actor->name,
                'type' => JobCardUpdate::TYPE_OPENED,
                'status' => JobCard::OPEN,
            ]);

            return $job;
        }, 3);

        return redirect()->route('job-cards.show', $job)->with('success', 'Job card opened.');
    }

    public function show(Request $request, string $jobCard): View
    {
        $job = $this->visibleJobs($request)
            ->with([
                'customer',
                'vehicle',
                'appointment',
                'updates' => fn ($query) => $query->orderBy('created_at')->orderBy('id'),
                'parts' => fn ($query) => $query->whereNull('return_for_id')->with('returns')->orderBy('id'),
            ])->findOrFail($jobCard);
        $staffView = $this->isMechanic($request);
        $canManageInventory = $request->attributes->get('account_subscription')?->canManageInventory() ?? false;

        return view('job-cards.show', [
            'job' => $job,
            'staffView' => $staffView,
            'canManageInventory' => $canManageInventory,
            'inventoryItems' => $canManageInventory && $job->nextStatus()
                ? InventoryItem::where('quantity', '>', 0)->orderBy('sku')->get(['id', 'sku', 'name', 'quantity'])
                : collect(),
            'today' => CarbonImmutable::today(Calendar::TIMEZONE)->toDateString(),
            'mechanics' => ! $staffView && $job->nextStatus()
                ? $this->mechanics($request)->where('status', User::STATUS_ACTIVE)->orderBy('name')->orderBy('id')->get(['id', 'name'])
                : collect(),
            'assigneeName' => ! $staffView && $job->assigned_mechanic_id
                ? $this->mechanics($request)->whereKey($job->assigned_mechanic_id)->value('name')
                : null,
        ]);
    }

    public function addNote(Request $request, string $jobCard): RedirectResponse
    {
        $validated = $request->validate(['note' => ['required', 'string', 'max:4000']]);

        DB::connection('tenant')->transaction(function () use ($request, $jobCard, $validated) {
            $job = $this->visibleJobs($request)->lockForUpdate()->findOrFail($jobCard);
            $job->updates()->create([
                'actor_user_id' => $request->user()->id,
                'actor_name' => $request->user()->name,
                'type' => JobCardUpdate::TYPE_NOTE,
                'note' => $validated['note'],
            ]);
        }, 3);

        return redirect()->route($this->isMechanic($request) ? 'my-jobs.show' : 'job-cards.show', $jobCard)
            ->with('success', 'Service note added.');
    }

    public function addVehicleUpdate(Request $request, string $jobCard): RedirectResponse
    {
        $validated = $request->validate([
            'mileage_km' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'vehicle_condition' => ['nullable', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:4000'],
        ]);
        if (blank($validated['mileage_km'] ?? null)
            && blank($validated['vehicle_condition'] ?? null)
            && blank($validated['note'] ?? null)) {
            throw ValidationException::withMessages([
                'vehicle_update' => 'Enter mileage, vehicle condition, or a service note.',
            ]);
        }

        $job = DB::connection('tenant')->transaction(function () use ($request, $jobCard, $validated) {
            $job = $this->visibleJobs($request)->lockForUpdate()->findOrFail($jobCard);
            if (! $job->nextStatus() || ! $job->vehicle_id) {
                throw ValidationException::withMessages([
                    'vehicle_update' => 'Vehicle updates can only be recorded on an active job with a linked vehicle.',
                ]);
            }

            $job->updates()->create([
                'actor_user_id' => $request->user()->id,
                'actor_name' => $request->user()->name,
                'type' => JobCardUpdate::TYPE_VEHICLE_UPDATE,
                'mileage_km' => $validated['mileage_km'] ?? null,
                'vehicle_condition' => $validated['vehicle_condition'] ?? null,
                'note' => $validated['note'] ?? null,
            ]);

            return $job;
        }, 3);

        return redirect()->route('my-jobs.show', $job)->with('success', 'Vehicle update added to its service history.');
    }

    public function usePart(Request $request, string $jobCard): RedirectResponse
    {
        $validated = $request->validate([
            'inventory_item_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.InventoryItem::MAX_QUANTITY],
        ]);
        $actor = $request->user();
        $job = DB::connection('tenant')->transaction(function () use ($request, $jobCard, $validated, $actor) {
            $job = $this->visibleJobs($request)->lockForUpdate()->findOrFail($jobCard);
            if (! $job->nextStatus()) {
                throw ValidationException::withMessages(['quantity' => 'Parts can only be recorded on an open or in-progress job.']);
            }

            $item = InventoryItem::query()->lockForUpdate()->find($validated['inventory_item_id']);
            if (! $item) {
                throw ValidationException::withMessages(['inventory_item_id' => 'Select an item from this workshop.']);
            }

            $quantity = (int) $validated['quantity'];
            if ($quantity > $item->quantity) {
                throw ValidationException::withMessages(['quantity' => 'There is not enough stock for this job.']);
            }

            $item->quantity -= $quantity;
            $item->save();
            $movement = $item->movements()->create([
                'quantity_change' => -$quantity,
                'balance_after' => $item->quantity,
                'recorded_by_user_id' => $actor->id,
                'note' => "Used for job #{$job->id}",
            ]);
            $part = $job->parts()->create([
                'inventory_item_id' => $item->id,
                'inventory_movement_id' => $movement->id,
                'recorded_by_user_id' => $actor->id,
                'type' => JobCardPart::TYPE_USED,
                'item_sku' => $item->sku,
                'item_name' => $item->name,
                'quantity' => $quantity,
            ]);
            $job->updates()->create([
                'actor_user_id' => $actor->id,
                'actor_name' => $actor->name,
                'type' => JobCardUpdate::TYPE_PART_USED,
                'note' => "Used {$quantity} x {$part->item_sku} {$part->item_name}",
            ]);

            return $job;
        }, 3);

        return redirect()->route($this->isMechanic($request) ? 'my-jobs.show' : 'job-cards.show', $job)
            ->with('success', 'Part usage recorded and stock updated.');
    }

    public function returnPart(Request $request, string $jobCard, string $part): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:'.InventoryItem::MAX_QUANTITY],
        ]);
        $actor = $request->user();
        $job = DB::connection('tenant')->transaction(function () use ($request, $jobCard, $part, $validated, $actor) {
            $job = $this->visibleJobs($request)->lockForUpdate()->findOrFail($jobCard);
            if (! $job->nextStatus()) {
                throw ValidationException::withMessages(['quantity' => 'Parts can only be returned on an open or in-progress job.']);
            }

            $usage = $job->parts()->whereNull('return_for_id')
                ->where('type', JobCardPart::TYPE_USED)->lockForUpdate()->findOrFail($part);
            $returned = (int) $usage->returns()->sum('quantity');
            $quantity = (int) $validated['quantity'];
            if ($quantity > $usage->quantity - $returned) {
                throw ValidationException::withMessages(['quantity' => 'The return exceeds the unused quantity for this job.']);
            }

            $item = InventoryItem::query()->lockForUpdate()->findOrFail($usage->inventory_item_id);
            if ($item->quantity + $quantity > InventoryItem::MAX_QUANTITY) {
                throw ValidationException::withMessages(['quantity' => 'Returning this quantity would exceed the stock limit.']);
            }

            $item->quantity += $quantity;
            $item->save();
            $movement = $item->movements()->create([
                'quantity_change' => $quantity,
                'balance_after' => $item->quantity,
                'recorded_by_user_id' => $actor->id,
                'note' => "Returned for job #{$job->id}",
            ]);
            $job->parts()->create([
                'inventory_item_id' => $item->id,
                'inventory_movement_id' => $movement->id,
                'return_for_id' => $usage->id,
                'recorded_by_user_id' => $actor->id,
                'type' => JobCardPart::TYPE_RETURNED,
                'item_sku' => $usage->item_sku,
                'item_name' => $usage->item_name,
                'quantity' => $quantity,
            ]);
            $job->updates()->create([
                'actor_user_id' => $actor->id,
                'actor_name' => $actor->name,
                'type' => JobCardUpdate::TYPE_PART_RETURNED,
                'note' => "Returned {$quantity} x {$usage->item_sku} {$usage->item_name}",
            ]);

            return $job;
        }, 3);

        return redirect()->route($this->isMechanic($request) ? 'my-jobs.show' : 'job-cards.show', $job)
            ->with('success', 'Unused part returned to stock.');
    }

    public function assign(Request $request, string $jobCard): RedirectResponse
    {
        $validated = $request->validate(['assigned_mechanic_id' => ['present', 'nullable', 'integer', 'min:1']]);
        $assignee = $validated['assigned_mechanic_id'];

        $job = DB::connection('tenant')->transaction(function () use ($request, $jobCard, $assignee) {
            $job = JobCard::query()->lockForUpdate()->findOrFail($jobCard);
            if ($job->status === JobCard::COMPLETED) {
                throw ValidationException::withMessages(['assigned_mechanic_id' => 'Completed jobs cannot be reassigned.']);
            }

            if ($assignee !== null && ! $this->mechanics($request)
                ->where('status', User::STATUS_ACTIVE)->whereKey($assignee)->exists()) {
                throw ValidationException::withMessages(['assigned_mechanic_id' => 'Select an active mechanic from this workshop.']);
            }

            $job->update(['assigned_mechanic_id' => $assignee]);

            return $job;
        }, 3);

        return redirect()->route('job-cards.show', $job)->with('success', 'Job assignment updated.');
    }

    public function advance(Request $request, string $jobCard): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::in([JobCard::IN_PROGRESS, JobCard::COMPLETED])]]);
        $status = $validated['status'];
        $job = DB::connection('tenant')->transaction(function () use ($request, $jobCard, $status) {
            $job = $this->visibleJobs($request)->lockForUpdate()->findOrFail($jobCard);
            if ($status !== $job->nextStatus()) {
                throw ValidationException::withMessages(['status' => 'The job status changed or the requested step is not available.']);
            }

            $job->update([
                'status' => $status,
                'completed_at' => $status === JobCard::COMPLETED ? now() : null,
            ]);
            $job->updates()->create([
                'actor_user_id' => $request->user()->id,
                'actor_name' => $request->user()->name,
                'type' => JobCardUpdate::TYPE_STATUS,
                'status' => $status,
            ]);

            return $job;
        }, 3);

        return redirect()->route($this->isMechanic($request) ? 'my-jobs.show' : 'job-cards.show', $job)
            ->with('success', 'Job status updated.');
    }

    private function mechanics(Request $request): Builder
    {
        return User::on(config('tenancy.database.central_connection'))
            ->where('tenant_id', $request->user()->tenant_id)->where('role', User::ROLE_MECHANIC);
    }

    private function visibleJobs(Request $request): Builder
    {
        $jobs = JobCard::query();
        if ($this->isMechanic($request)) {
            abort_unless($request->user()->status === User::STATUS_ACTIVE, 403);
            $jobs->where('assigned_mechanic_id', $request->user()->id);
        }

        return $jobs;
    }

    private function isMechanic(Request $request): bool
    {
        return $request->user()->role === User::ROLE_MECHANIC;
    }
}
