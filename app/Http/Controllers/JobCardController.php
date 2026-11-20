<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\JobCard;
use App\Models\JobCardUpdate;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JobCardController extends Controller
{
    public function index(Request $request): View
    {
        $staffView = $this->isMechanic($request);
        $jobs = $this->visibleJobs($request)->with(['customer', 'vehicle'])->latest('id')->paginate(15);
        $assigneeNames = $staffView ? collect() : $this->mechanics($request)
            ->whereIn('id', $jobs->pluck('assigned_mechanic_id')->filter()->unique()->all())->pluck('name', 'id');

        return view('job-cards.index', [
            'jobs' => $jobs,
            'staffView' => $staffView,
            'assigneeNames' => $assigneeNames,
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'vehicle_id' => ['required', 'integer', 'min:1'],
            'calendar_id' => ['nullable', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:4000'],
        ]);

        $actor = $request->user();
        $job = DB::connection('tenant')->transaction(function () use ($validated, $actor) {
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
                'description' => $validated['description'],
                'status' => JobCard::OPEN,
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
            ])->findOrFail($jobCard);
        $staffView = $this->isMechanic($request);

        return view('job-cards.show', [
            'job' => $job,
            'staffView' => $staffView,
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
