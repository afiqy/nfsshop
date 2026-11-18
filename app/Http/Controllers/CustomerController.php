<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(): View
    {
        return view('customers.index', [
            'customers' => Customer::query()->orderBy('name')->orderBy('id')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('customers.form', ['customer' => new Customer()]);
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
        $customer->update($this->validateCustomer($request, $customer));

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
            'email' => ['required', 'string', 'email', 'max:255', $uniqueEmail],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
