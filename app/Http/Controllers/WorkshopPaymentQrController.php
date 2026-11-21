<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use App\Models\WorkshopPaymentQr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkshopPaymentQrController extends Controller
{
    public function index(): View
    {
        return view('payment-qrs.index', [
            'paymentQrs' => WorkshopPaymentQr::query()->orderByDesc('is_active')->orderBy('category')
                ->orderBy('label')->get(),
            'maxActive' => WorkshopPaymentQr::MAX_ACTIVE,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', Rule::in([
                WorkshopPaymentQr::CATEGORY_BANK,
                WorkshopPaymentQr::CATEGORY_EWALLET,
            ])],
            'label' => ['required', 'string', 'max:100'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        if (WorkshopPaymentQr::query()->where('is_active', true)->count() >= WorkshopPaymentQr::MAX_ACTIVE) {
            throw ValidationException::withMessages([
                'image' => 'A workshop can have up to '.WorkshopPaymentQr::MAX_ACTIVE.' active payment QR codes.',
            ]);
        }

        $file = $validated['image'];
        $path = $file->store('payment-qrs/'.tenant('id'), 'local');
        if (! is_string($path)) {
            throw new RuntimeException('The payment QR image could not be stored.');
        }

        $created = false;
        try {
            $qr = WorkshopPaymentQr::create([
                'category' => $validated['category'],
                'label' => $validated['label'],
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'created_by_user_id' => $request->user()->id,
            ]);
            $created = true;
        } finally {
            if (! $created && ! Storage::disk('local')->delete($path)) {
                logger()->warning('A payment QR image could not be removed after a failed save.', ['path' => $path]);
            }
        }

        return redirect()->route('payment-qrs.index')->with('success', $qr->label.' QR code added.');
    }

    public function setActive(Request $request, string $qr): RedirectResponse
    {
        $record = WorkshopPaymentQr::query()->findOrFail($qr);
        $active = $request->validate(['active' => ['required', 'boolean']])['active'];
        if ($active && ! $record->is_active
            && WorkshopPaymentQr::query()->where('is_active', true)->count() >= WorkshopPaymentQr::MAX_ACTIVE) {
            throw ValidationException::withMessages([
                'active' => 'A workshop can have up to '.WorkshopPaymentQr::MAX_ACTIVE.' active payment QR codes.',
            ]);
        }

        $record->update(['is_active' => (bool) $active]);

        return redirect()->route('payment-qrs.index')->with('success', 'Payment QR availability updated.');
    }

    public function adminImage(string $qr): StreamedResponse
    {
        return $this->imageResponse(WorkshopPaymentQr::query()->findOrFail($qr));
    }

    public function customerImage(Request $request, string $qr): StreamedResponse
    {
        $this->activeCustomer($request);
        $record = WorkshopPaymentQr::query()->where('is_active', true)->findOrFail($qr);

        return $this->imageResponse($record);
    }

    private function imageResponse(WorkshopPaymentQr $qr): StreamedResponse
    {
        abort_unless(in_array($qr->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true), 404);
        $stream = Storage::disk('local')->readStream($qr->path);
        abort_unless(is_resource($stream), 404);

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'inline; filename="payment-qr"',
            'Content-Type' => $qr->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function activeCustomer(Request $request): Customer
    {
        abort_unless($request->user()->status === User::STATUS_ACTIVE, 403);

        return Customer::query()->where('portal_user_id', $request->user()->id)->firstOrFail();
    }
}
