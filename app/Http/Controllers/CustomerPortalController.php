<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerVehicleRequest;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerPortalController extends Controller
{
    public const MAX_SELF_SERVICE_VEHICLES = 5;

    public function index(Request $request): View
    {
        $customer = $request->user()->status === User::STATUS_ACTIVE
            ? Customer::query()->where('portal_user_id', $request->user()->id)->first()
            : null;

        return response()->view('customer-portal.index', [
            'customer' => $customer,
            'vehicles' => $customer?->vehicles()->orderBy('plate_number')->get() ?? collect(),
            'vehicleRequests' => $customer?->vehicleRequests()->latest('id')->get() ?? collect(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function createVehicle(Request $request): View|RedirectResponse
    {
        $customer = $this->activeCustomer($request);
        if ($customer->vehicles()->count() >= self::MAX_SELF_SERVICE_VEHICLES) {
            return redirect()->route('client.portal')->withErrors([
                'vehicles' => 'You have reached the five-vehicle limit. Request additional vehicles for workshop approval.',
            ]);
        }

        return view('customer-portal.vehicle-form', ['isRequest' => false]);
    }

    public function storeVehicle(Request $request): RedirectResponse
    {
        $customer = $this->activeCustomer($request);
        $validated = $request->validate($this->vehicleRules());

        DB::connection('tenant')->transaction(function () use ($customer, $validated) {
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            if ($lockedCustomer->vehicles()->count() >= self::MAX_SELF_SERVICE_VEHICLES) {
                throw ValidationException::withMessages([
                    'vehicles' => 'You have reached the five-vehicle limit. Request additional vehicles for workshop approval.',
                ]);
            }

            $lockedCustomer->vehicles()->create($validated);
        });

        return redirect()->route('client.portal')->with('success', 'Vehicle added to your account.');
    }

    public function createVehicleRequest(Request $request): View|RedirectResponse
    {
        $customer = $this->activeCustomer($request);
        if ($customer->vehicles()->count() < self::MAX_SELF_SERVICE_VEHICLES) {
            return redirect()->route('client.vehicle.create');
        }

        return view('customer-portal.vehicle-form', ['isRequest' => true]);
    }

    public function storeVehicleRequest(Request $request): RedirectResponse
    {
        $customer = $this->activeCustomer($request);
        $validated = $request->validate($this->vehicleRules(forRequest: true));

        DB::connection('tenant')->transaction(function () use ($customer, $request, $validated) {
            $lockedCustomer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            if ($lockedCustomer->vehicles()->count() < self::MAX_SELF_SERVICE_VEHICLES) {
                throw ValidationException::withMessages([
                    'vehicles' => 'You can add vehicles yourself until you reach the five-vehicle limit.',
                ]);
            }

            $lockedCustomer->vehicleRequests()->create($validated + [
                'requested_by_user_id' => $request->user()->id,
                'status' => CustomerVehicleRequest::PENDING,
            ]);
        });

        return redirect()->route('client.portal')->with('success', 'Additional vehicle request sent to the workshop.');
    }

    public function history(Request $request, string $vehicle): View
    {
        $customer = $this->activeCustomer($request);
        $vehicle = $customer->vehicles()->findOrFail($vehicle);

        return response()->view('customer-portal.history', [
            'vehicle' => $vehicle,
            'appointments' => $vehicle->appointments()->latest('start')->latest('id')
                ->paginate(10, ['*'], 'appointments_page')->withQueryString(),
            'jobs' => $vehicle->jobCards()->latest('id')
                ->paginate(10, ['*'], 'jobs_page')->withQueryString(),
            'invoices' => $vehicle->invoices()->withSum('payments as paid_sen', 'amount_sen')->latest('id')
                ->paginate(10, ['*'], 'invoices_page')->withQueryString(),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function activeCustomer(Request $request): Customer
    {
        abort_unless($request->user()->status === User::STATUS_ACTIVE, 403);

        return Customer::query()->where('portal_user_id', $request->user()->id)->firstOrFail();
    }

    private function vehicleRules(bool $forRequest = false): array
    {
        $rules = [
            'brand' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'year' => ['nullable', 'digits:4'],
            'color' => ['nullable', 'string', 'max:255'],
        ];

        foreach (['plate_number', 'chassis_number', 'engine_number'] as $field) {
            $rules[$field] = [
                'bail',
                'required',
                'string',
                'max:255',
                Rule::unique(Vehicle::class, $field),
            ];
            if ($forRequest) {
                $rules[$field][] = Rule::unique(CustomerVehicleRequest::class, $field)
                    ->where('status', CustomerVehicleRequest::PENDING);
            }
        }

        return $rules;
    }
}
