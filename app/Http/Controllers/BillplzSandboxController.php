<?php

namespace App\Http\Controllers;

use App\Models\PaymentPreference;
use App\Services\BillplzSandboxCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BillplzSandboxController extends Controller
{
    public function start(Request $request, BillplzSandboxCheckout $gateway): RedirectResponse
    {
        abort_unless($gateway->enabled(), 404);
        $preferences = PaymentPreference::where('user_id', $request->user()->id)->first();
        abort_unless($preferences?->usesGateway('billplz'), 403);

        $baseUrl = rtrim(config('app.url'), '/');
        abort_unless(parse_url($baseUrl, PHP_URL_SCHEME) === 'https' && parse_url($baseUrl, PHP_URL_HOST),
            503, 'Billplz sandbox requires an HTTPS staging APP_URL.');
        $bill = $gateway->create(
            $baseUrl.route('billplz-sandbox.callback', absolute: false),
            $baseUrl.route('billplz-sandbox.result', absolute: false)
        );
        $request->session()->put($this->sessionKey($request), $bill['id']);

        return redirect()->away($bill['url'], 303, ['Cache-Control' => 'no-store']);
    }

    public function result(Request $request, BillplzSandboxCheckout $gateway): Response
    {
        abort_unless($gateway->enabled(), 404);
        parse_str($request->server('QUERY_STRING', ''), $query);
        $bill = $query['billplz'] ?? null;
        $id = is_array($bill) ? ($bill['id'] ?? null) : null;
        abort_unless(is_string($id) && $id === $request->session()->get($this->sessionKey($request)), 404);
        abort_unless($gateway->hasValidSignature($bill, 'billplz'), 403);

        return response()->view('payment-preferences.sandbox-result', [
            'provider' => 'Billplz',
            'paid' => $gateway->isPaid($id),
        ])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function callback(Request $request, BillplzSandboxCheckout $gateway): Response
    {
        abort_unless($gateway->enabled(), 404);
        // Verify the form values before Laravel's input trimming can change them.
        parse_str($request->getContent(), $payload);
        abort_unless($gateway->isValidCallback($payload), 403);

        return response('OK')->header('Cache-Control', 'no-store');
    }

    private function sessionKey(Request $request): string
    {
        return 'billplz_sandbox_'.hash('sha256', tenant('id').':'.$request->user()->id);
    }
}
