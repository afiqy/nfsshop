<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        return view('suppliers.index', [
            'suppliers' => Supplier::query()->withCount('purchaseOrders')->orderBy('name')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('suppliers.form', ['supplier' => new Supplier(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Supplier::create($this->validateSupplier($request));

        return redirect()->route('suppliers.index')->with('success', 'Supplier added.');
    }

    public function edit(string $supplier): View
    {
        return view('suppliers.form', ['supplier' => Supplier::query()->findOrFail($supplier)]);
    }

    public function update(Request $request, string $supplier): RedirectResponse
    {
        Supplier::query()->findOrFail($supplier)->update($this->validateSupplier($request));

        return redirect()->route('suppliers.index')->with('success', 'Supplier updated.');
    }

    private function validateSupplier(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
