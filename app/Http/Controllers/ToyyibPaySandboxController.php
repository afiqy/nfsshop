<?php

namespace App\Http\Controllers;

use App\Models\PaymentPreference;
use App\Services\ToyyibPaySandboxCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ToyyibPaySandboxController extends Controller
{
    public function start(Request $request, ToyyibPaySandboxCheckout $gateway): RedirectResponse
    {
        abort_unless($gateway->enabled(), 404);
        $preferences = PaymentPreference::where('user_id', $request->user()->id)->first();
        abort_unless($preferences?->usesGateway('toyyibpay'), 403);

        $reference = Str::random(24);
        $returnUrl = rtrim(config('app.url'), '/').route('toyyibpay-sandbox.result', absolute: false);
        $code = $gateway->create($returnUrl, $reference);
        $request->session()->put($this->sessionKey($request), ['code' => $code, 'reference' => $reference]);

        return redirect()->away(ToyyibPaySandboxCheckout::HOST.'/'.$code, 303, ['Cache-Control' => 'no-store']);
    }

    public function result(Request $request, ToyyibPaySandboxCheckout $gateway): Response
    {
        abort_unless($gateway->enabled(), 404);

        $bill = $request->session()->get($this->sessionKey($request));
        $code = $request->query('billcode');
        abort_unless(is_string($code) && $code === ($bill['code'] ?? null), 404);

        return response()->view('payment-preferences.sandbox-result', [
            'provider' => 'toyyibPay',
            'paid' => $gateway->isPaid($code, $bill['reference']),
        ])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    private function sessionKey(Request $request): string
    {
        return 'toyyibpay_sandbox_'.hash('sha256', tenant('id').':'.$request->user()->id);
    }
}
