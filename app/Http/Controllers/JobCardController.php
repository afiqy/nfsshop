<?php

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\JobCard;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JobCardController extends Controller
{
    public function index(): View
    {
        return view('job-cards.index', [
            'jobs' => JobCard::with(['customer', 'vehicle'])->latest('id')->paginate(15),
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

        $job = DB::connection('tenant')->transaction(function () use ($validated) {
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

            return JobCard::create([
                'vehicle_id' => $vehicle->id,
                'customer_id' => $vehicle->customer_id,
                'calendar_id' => $appointment?->id,
                'description' => $validated['description'],
                'status' => JobCard::OPEN,
            ]);
        }, 3);

        return redirect()->route('job-cards.show', $job)->with('success', 'Job card opened.');
    }

    public function show(string $jobCard): View
    {
        return view('job-cards.show', [
            'job' => JobCard::with(['customer', 'vehicle', 'appointment'])->findOrFail($jobCard),
        ]);
    }

    public function advance(Request $request, string $jobCard): RedirectResponse
    {
        $job = JobCard::findOrFail($jobCard);
        $validated = $request->validate(['status' => ['required', Rule::in([JobCard::IN_PROGRESS, JobCard::COMPLETED])]]);
        $status = $validated['status'];
        $updated = $status === $job->nextStatus() && JobCard::query()
            ->whereKey($job->id)->where('status', $job->status)
            ->update(['status' => $status, 'completed_at' => $status === JobCard::COMPLETED ? now() : null]);

        if (! $updated) {
            throw ValidationException::withMessages(['status' => 'The job status changed or the requested step is not available.']);
        }

        return redirect()->route('job-cards.show', $job)->with('success', 'Job status updated.');
    }
}
