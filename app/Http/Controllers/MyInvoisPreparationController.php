<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\User;
use App\Models\WorkshopTaxProfile;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as InputValidator;

class MyInvoisPreparationController extends Controller
{
    private const ID_TYPES = [
        'BRN' => 'Business registration number',
        'NRIC' => 'MyKad / NRIC',
        'PASSPORT' => 'Passport',
        'ARMY' => 'Army identification',
    ];

    private const STATES = [
        '01' => 'Johor', '02' => 'Kedah', '03' => 'Kelantan', '04' => 'Melaka',
        '05' => 'Negeri Sembilan', '06' => 'Pahang', '07' => 'Pulau Pinang', '08' => 'Perak',
        '09' => 'Perlis', '10' => 'Selangor', '11' => 'Terengganu', '12' => 'Sabah',
        '13' => 'Sarawak', '14' => 'Kuala Lumpur', '15' => 'Labuan', '16' => 'Putrajaya',
    ];

    public function __construct()
    {
        $this->middleware(function (Request $request, Closure $next) {
            abort_unless($request->user()->status === User::STATUS_ACTIVE, 403);

            return $next($request);
        });
    }

    public function editSeller(): Response
    {
        $profile = WorkshopTaxProfile::find(WorkshopTaxProfile::PROFILE_ID);

        return $this->form(null, $profile?->details ?? [], $profile !== null);
    }

    public function updateSeller(Request $request): RedirectResponse
    {
        $validator = $this->validateDetails($request, seller: true);
        $redirect = redirect()->route('myinvois.seller.edit');
        if ($validator->fails()) {
            return $redirect->withErrors($validator);
        }

        WorkshopTaxProfile::updateOrCreate(
            ['id' => WorkshopTaxProfile::PROFILE_ID],
            ['details' => $validator->validated()]
        );

        return $redirect->with('success', 'Workshop tax profile saved.');
    }

    public function editBuyer(string $invoice): Response
    {
        $record = Invoice::findOrFail($invoice);

        return $this->form(
            $record,
            $record->myinvois_buyer ?? ['legal_name' => $record->customer_name],
            WorkshopTaxProfile::whereKey(WorkshopTaxProfile::PROFILE_ID)->exists()
        );
    }

    public function updateBuyer(Request $request, string $invoice): RedirectResponse
    {
        $record = Invoice::findOrFail($invoice);
        $validator = $this->validateDetails($request, seller: false);
        $redirect = redirect()->route('myinvois.buyer.edit', $record);
        if ($validator->fails()) {
            return $redirect->withErrors($validator);
        }

        $record->myinvois_buyer = $validator->validated();
        $record->save();

        return $redirect->with('success', 'Invoice buyer details saved.');
    }

    public function clearBuyer(string $invoice): RedirectResponse
    {
        $record = Invoice::findOrFail($invoice);
        $record->myinvois_buyer = null;
        $record->save();

        return redirect()->route('myinvois.buyer.edit', $record)->with('success', 'Invoice buyer details cleared.');
    }

    private function form(?Invoice $invoice, array $details, bool $sellerSaved): Response
    {
        return response()->view('myinvois.preparation', [
            'invoice' => $invoice,
            'details' => $details,
            'sellerSaved' => $sellerSaved,
            'buyerSaved' => $invoice !== null && $invoice->myinvois_buyer !== null,
            'idTypes' => self::ID_TYPES,
            'states' => self::STATES,
        ])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    private function validateDetails(Request $request, bool $seller): InputValidator
    {
        $rules = [
            'legal_name' => ['required', 'string', 'max:300'],
            'tin' => ['required', 'string', 'regex:/\A[A-Z]{1,2}[0-9]{1,13}\z/', 'max:14'],
            'id_type' => ['required', 'string', Rule::in(array_keys(self::ID_TYPES))],
            'id_number' => ['required', 'string', 'regex:/\A[A-Za-z0-9-]{1,30}\z/'],
            'address_line' => ['required', 'string', 'max:150'],
            'postcode' => ['required', 'string', 'regex:/\A[0-9]{5}\z/'],
            'city' => ['required', 'string', 'max:50'],
            'state' => ['required', 'string', Rule::in(array_keys(self::STATES))],
            'phone' => ['required', 'string', 'regex:/\A\+[1-9][0-9]{7,14}\z/'],
            'sst_number' => ['nullable', 'string', 'regex:/\A[A-Za-z0-9;-]{1,35}\z/'],
        ];

        if ($seller) {
            $rules['msic'] = ['required', 'string', 'regex:/\A[0-9]{5}\z/'];
            $rules['business_activity'] = ['required', 'string', 'max:300'];
        }

        return Validator::make($request->only(array_keys($rules)), $rules);
    }
}
