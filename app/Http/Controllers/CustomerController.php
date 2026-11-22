<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim($filters['search'] ?? '');

        return view('customers.index', [
            'customers' => Customer::query()
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                    $like = '%'.$search.'%';
                    $query->where('name', 'like', $like)
                        ->orWhere('short_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                }))
                ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString(),
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('customers.form', ['customer' => new Customer]);
    }

    public function store(Request $request): RedirectResponse
    {
        Customer::create($this->validateCustomer($request));

        return redirect()->route('customers.index')->with('success', 'Customer added successfully.');
    }

    public function edit(string $customer): View
    {
        return view('customers.form', ['customer' => Customer::query()->findOrFail($customer)]);
    }

    public function update(Request $request, string $customer): RedirectResponse
    {
        $customer = Customer::query()->findOrFail($customer);
        $data = $this->validateCustomer($request, $customer);
        DB::connection('tenant')->transaction(function () use ($customer, $data) {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            if ($customer->email !== $data['email']) {
                $data['engagement_reminders_opt_in'] = false;
            }
            $customer->update($data);
        }, 3);

        return redirect()->route('customers.index')->with('success', 'Customer updated successfully.');
    }

    private function validateCustomer(Request $request, ?Customer $customer = null): array
    {
        $uniqueEmail = Rule::unique(Customer::class, 'email');
        if ($customer) {
            $uniqueEmail->ignore($customer);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', $uniqueEmail],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
