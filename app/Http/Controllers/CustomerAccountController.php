<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerVehicleRequest;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerAccountController extends Controller
{
    public function index(): View
    {
        $tenantId = tenant('id');
        $users = User::query()->where('tenant_id', $tenantId)
            ->where('role', User::ROLE_CLIENT)->orderBy('name')->get();
        $linkedUserIds = Customer::query()->whereNotNull('portal_user_id')
            ->pluck('portal_user_id')->map(fn ($id) => (int) $id)->all();
        $customersByEmail = Customer::query()->get()->keyBy(
            fn (Customer $customer) => strtolower(trim($customer->email))
        );

        $unlinkedUsers = $users->reject(fn (User $user) => in_array($user->id, $linkedUserIds, true))
            ->map(fn (User $user) => [
                'user' => $user,
                'customer' => $customersByEmail->get(strtolower(trim($user->email))),
            ]);

        return view('customer-accounts.index', [
            'unlinkedUsers' => $unlinkedUsers,
            'vehicleRequests' => CustomerVehicleRequest::query()->with('customer')
                ->where('status', CustomerVehicleRequest::PENDING)->orderBy('id')->get(),
        ]);
    }

    public function activate(string $user): RedirectResponse
    {
        $user = User::query()->where('tenant_id', tenant('id'))
            ->where('role', User::ROLE_CLIENT)->findOrFail($user);
        $email = strtolower(trim($user->email));

        $customer = DB::connection('tenant')->transaction(function () use ($email, $user) {
            $customer = Customer::query()->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                ->lockForUpdate()->first();
            if (! $customer) {
                throw ValidationException::withMessages([
                    'email' => 'Create a workshop customer record with this exact email before confirming the account.',
                ]);
            }
            if ($customer->portal_user_id !== null && (int) $customer->portal_user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'email' => 'This customer record is already linked to another portal account.',
                ]);
            }

            $customer->portal_user_id = $user->id;
            $customer->save();

            return $customer;
        });

        $user->forceFill([
            'status' => User::STATUS_ACTIVE,
            'suspended_at' => null,
            'suspension_reason' => null,
        ])->save();

        return redirect()->route('client-accounts.index')
            ->with('success', "Confirmed {$user->name} as the portal account for {$customer->name}.");
    }

    public function approveVehicleRequest(string $vehicleRequest): RedirectResponse
    {
        $vehicleRequest = CustomerVehicleRequest::query()->findOrFail($vehicleRequest);
        if ($vehicleRequest->status !== CustomerVehicleRequest::PENDING) {
            abort(409, 'This vehicle request has already been reviewed.');
        }

        DB::connection('tenant')->transaction(function () use ($vehicleRequest) {
            $request = CustomerVehicleRequest::query()->lockForUpdate()->findOrFail($vehicleRequest->id);
            if ($request->status !== CustomerVehicleRequest::PENDING) {
                abort(409, 'This vehicle request has already been reviewed.');
            }

            $customer = Customer::query()->lockForUpdate()->findOrFail($request->customer_id);
            abort_unless((int) $customer->portal_user_id === (int) $request->requested_by_user_id, 409);
            $vehicleDetails = $request->only([
                'plate_number', 'chassis_number', 'engine_number', 'brand', 'model', 'year', 'color',
            ]);
            foreach (['plate_number', 'chassis_number', 'engine_number'] as $field) {
                $exists = Vehicle::query()->where($field, $vehicleDetails[$field])->exists();
                if ($exists) {
                    throw ValidationException::withMessages([
                        $field => 'A vehicle with this value has already been added to the workshop.',
                    ]);
                }
            }

            $customer->vehicles()->create($vehicleDetails);
            $request->update([
                'status' => CustomerVehicleRequest::APPROVED,
                'decided_by_user_id' => auth()->id(),
                'decided_at' => now(),
            ]);
        });

        return redirect()->route('client-accounts.index')->with('success', 'Additional vehicle approved and added.');
    }

    public function rejectVehicleRequest(string $vehicleRequest): RedirectResponse
    {
        DB::connection('tenant')->transaction(function () use ($vehicleRequest) {
            $vehicleRequest = CustomerVehicleRequest::query()->lockForUpdate()->findOrFail($vehicleRequest);
            abort_unless(
                $vehicleRequest->status === CustomerVehicleRequest::PENDING,
                409,
                'This vehicle request has already been reviewed.'
            );
            $vehicleRequest->update([
                'status' => CustomerVehicleRequest::REJECTED,
                'decided_by_user_id' => auth()->id(),
                'decided_at' => now(),
            ]);
        });

        return redirect()->route('client-accounts.index')->with('success', 'Additional vehicle request rejected.');
    }
}
