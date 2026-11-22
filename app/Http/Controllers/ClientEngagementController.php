<?php

namespace App\Http\Controllers;

use App\Mail\VehicleReminderEmail;
use App\Models\Calendar;
use App\Models\Customer;
use App\Models\Promotion;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleReminder;
use App\Models\VehicleReminderAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ClientEngagementController extends Controller
{
    public function index(): Response
    {
        return response()->view('engagement.index', [
            'customers' => Customer::query()->orderBy('name')->get(),
            'vehicles' => Vehicle::query()->with('customer')->orderBy('plate_number')->get(),
            'promotions' => Promotion::query()->latest('id')->paginate(10, ['*'], 'promotions_page')->withQueryString(),
            'reminders' => VehicleReminder::query()->with(['customer', 'vehicle', 'attempts'])
                ->latest('id')->paginate(10, ['*'], 'reminders_page')->withQueryString(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function customer(string $customer): Response
    {
        $customer = Customer::query()->findOrFail($customer);

        return response()->view('engagement.customer', [
            'customer' => $customer,
            'balance' => (int) $customer->loyaltyEntries()->sum('points'),
            'entries' => $customer->loyaltyEntries()->latest('id')->paginate(20),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function points(Request $request, string $customer): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['award', 'redeem'])],
            'points' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        DB::connection('tenant')->transaction(function () use ($request, $customer, $data) {
            $record = Customer::query()->lockForUpdate()->findOrFail($customer);
            $balance = (int) $record->loyaltyEntries()->sum('points');
            $points = (int) $data['points'] * ($data['action'] === 'redeem' ? -1 : 1);
            if ($balance + $points < 0 || $balance + $points > 1000000000) {
                throw ValidationException::withMessages(['points' => 'The resulting balance must be between 0 and 1,000,000,000 points.']);
            }
            $record->loyaltyEntries()->create([
                'points' => $points, 'reason' => $data['reason'], 'recorded_by_user_id' => $request->user()->id,
            ]);
        }, 3);

        return back()->with('success', 'Loyalty entry recorded.');
    }

    public function consent(Request $request, string $customer): RedirectResponse
    {
        $data = $request->validate(['opt_in' => ['required', 'boolean']]);
        Customer::query()->findOrFail($customer)->update(['engagement_reminders_opt_in' => $data['opt_in']]);

        return back()->with('success', 'Reminder preference saved.');
    }

    public function promotion(Request $request, ?string $promotion = null): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'active' => ['required', 'boolean'],
        ]);
        $record = $promotion ? Promotion::query()->findOrFail($promotion) : new Promotion;
        $record->fill($data)->save();

        return redirect()->route('engagement.index')->with('success', 'Promotion saved.');
    }

    public function reminder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'vehicle_id' => ['required', 'integer', Rule::exists(Vehicle::class, 'id')],
            'type' => ['required', Rule::in(['service', 'registration', 'insurance'])],
            'due_on' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        DB::connection('tenant')->transaction(function () use ($data) {
            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($data['vehicle_id']);
            VehicleReminder::create($data + ['customer_id' => $vehicle->customer_id]);
        }, 3);

        return back()->with('success', 'Reminder added.');
    }

    public function complete(string $reminder): RedirectResponse
    {
        VehicleReminder::query()->findOrFail($reminder)->update(['completed_at' => now()]);

        return back()->with('success', 'Reminder closed.');
    }

    public function send(Request $request, string $reminder): RedirectResponse
    {
        $attempt = DB::connection('tenant')->transaction(function () use ($request, $reminder) {
            $record = VehicleReminder::query()->lockForUpdate()->findOrFail($reminder);
            $customer = Customer::query()->lockForUpdate()->findOrFail($record->customer_id);
            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($record->vehicle_id);
            $this->assertSendable($record, $customer, $vehicle);
            if ($record->attempts()->where('status', 'sending')->exists()) {
                throw ValidationException::withMessages(['reminder' => 'A send is already in progress or its outcome is unknown. Check the provider before resolving it.']);
            }

            return $record->attempts()->create([
                'requested_by_user_id' => $request->user()->id, 'recipient_email' => $customer->email, 'status' => 'sending',
            ]);
        }, 3);

        DB::connection('tenant')->transaction(function () use ($reminder, $attempt) {
            $record = VehicleReminder::query()->lockForUpdate()->findOrFail($reminder);
            $customer = Customer::query()->lockForUpdate()->findOrFail($record->customer_id);
            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($record->vehicle_id);
            $attempt = VehicleReminderAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($attempt->status !== 'sending') {
                return;
            }
            if ($record->completed_at || ! $customer->engagement_reminders_opt_in
                || $vehicle->customer_id !== $customer->id || $customer->email !== $attempt->recipient_email) {
                $attempt->update(['status' => 'failed', 'failure_message' => 'Reminder, consent, ownership or recipient changed before sending.']);

                return;
            }
            try {
                Mail::to($attempt->recipient_email)->send(new VehicleReminderEmail($record, $customer, $vehicle, (string) tenant('workshop_name')));
            } catch (Throwable $exception) {
                $attempt->update(['status' => 'failed', 'failure_message' => 'Mail transport failed. Check application logs before retrying.']);
                Log::error('Vehicle reminder email failed.', ['tenant_id' => tenant('id'), 'attempt_id' => $attempt->id, 'exception_class' => $exception::class]);

                return;
            }
            $attempt->update(['status' => 'sent', 'sent_at' => now()]);
        }, 1);
        $attempt->refresh();

        return $attempt->status === 'sent'
            ? back()->with('success', 'Reminder accepted by the mail transport.')
            : back()->withErrors(['reminder' => $attempt->failure_message]);
    }

    public function resolve(Request $request, string $reminder, string $attempt): RedirectResponse
    {
        DB::connection('tenant')->transaction(function () use ($request, $reminder, $attempt) {
            $record = VehicleReminder::query()->lockForUpdate()->findOrFail($reminder);
            $delivery = $record->attempts()->where('status', 'sending')->lockForUpdate()->findOrFail($attempt);
            $delivery->update(['status' => 'unknown', 'resolved_by_user_id' => $request->user()->id,
                'failure_message' => 'Manually resolved as outcome unknown after provider check.']);
        }, 3);

        return back()->with('success', 'Attempt marked outcome unknown. Check the provider before sending again.');
    }

    public function portal(Request $request): Response
    {
        $customer = $this->portalCustomer($request);
        $today = now(Calendar::TIMEZONE)->toDateString();

        return response()->view('engagement.portal', [
            'customer' => $customer,
            'balance' => (int) $customer->loyaltyEntries()->sum('points'),
            'entries' => $customer->loyaltyEntries()->latest('id')->paginate(20, ['*'], 'loyalty_page')->withQueryString(),
            'promotions' => Promotion::query()->where('active', true)->whereDate('starts_on', '<=', $today)
                ->whereDate('ends_on', '>=', $today)->orderBy('ends_on')->paginate(10, ['*'], 'promotions_page')->withQueryString(),
            'reminders' => VehicleReminder::query()->with('vehicle')->where('customer_id', $customer->id)
                ->whereNull('completed_at')->whereHas('vehicle', fn ($query) => $query->where('customer_id', $customer->id))
                ->orderBy('due_on')->paginate(10, ['*'], 'reminders_page')->withQueryString(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function portalConsent(Request $request): RedirectResponse
    {
        $data = $request->validate(['opt_in' => ['required', 'boolean']]);
        $this->portalCustomer($request)->update(['engagement_reminders_opt_in' => $data['opt_in']]);

        return back()->with('success', 'Reminder preference saved.');
    }

    private function portalCustomer(Request $request): Customer
    {
        abort_unless($request->user()->status === User::STATUS_ACTIVE, 403);

        return Customer::query()->where('portal_user_id', $request->user()->id)->firstOrFail();
    }

    private function assertSendable(VehicleReminder $record, Customer $customer, Vehicle $vehicle): void
    {
        if ($record->completed_at || ! $customer->engagement_reminders_opt_in
            || $vehicle->customer_id !== $customer->id || ! filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['reminder' => 'The reminder must be open, the customer opted in with a valid email, and the vehicle still owned by that customer.']);
        }
    }
}
