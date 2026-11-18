<?php

namespace App\Http\Controllers;

use App\Models\PaymentPreference;
use App\Services\StripeSandboxCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StripeSandboxController extends Controller
{
    public function start(Request $request, StripeSandboxCheckout $stripe): RedirectResponse
    {
        abort_unless($stripe->enabled(), 404);

        $preferences = PaymentPreference::where('user_id', $request->user()->id)->first();
        abort_unless($preferences?->usesGateway('stripe'), 403);

        $baseUrl = rtrim(config('app.url'), '/');
        $session = $stripe->create(
            $baseUrl.route('stripe-sandbox.result', absolute: false).'?session_id={CHECKOUT_SESSION_ID}',
            $baseUrl.route('payment-preferences.edit', absolute: false)
        );

        $url = $session['url'] ?? null;
        abort_unless(is_string($url) && preg_match('~\Ahttps://checkout\.stripe\.com/c/pay/[^\s\\\\]+\z~', $url) === 1,
            502, 'Stripe did not return a valid hosted checkout link.');

        $request->session()->put($this->sessionKey($request), $session['id']);

        return redirect()->away($url, 303, ['Cache-Control' => 'no-store']);
    }

    public function result(Request $request, StripeSandboxCheckout $stripe): Response
    {
        abort_unless($stripe->enabled(), 404);

        $id = $request->query('session_id');
        abort_unless(is_string($id) && $id === $request->session()->get($this->sessionKey($request)), 404);

        $session = $stripe->retrieve($id);
        abort_unless($session['id'] === $id, 502, 'Stripe returned a different checkout session.');

        return response()->view('payment-preferences.sandbox-result', [
            'provider' => 'Stripe',
            'paid' => ($session['status'] ?? null) === 'complete' && ($session['payment_status'] ?? null) === 'paid',
        ])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    private function sessionKey(Request $request): string
    {
        return 'stripe_sandbox_'.hash('sha256', tenant('id').':'.$request->user()->id);
    }
}
