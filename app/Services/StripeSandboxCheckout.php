<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class StripeSandboxCheckout
{
    public const AMOUNT_SEN = 1000;

    private const API = 'https://api.stripe.com/v1/checkout/sessions';

    public function enabled(): bool
    {
        return config('services.stripe.sandbox_enabled') === true
            && preg_match('/\A(?:sk|rk)_test_[A-Za-z0-9]+\z/', (string) config('services.stripe.test_secret')) === 1;
    }

    public function create(string $successUrl, string $cancelUrl): array
    {
        return $this->request('POST', self::API, [
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'myr',
                    'unit_amount' => self::AMOUNT_SEN,
                    'product_data' => ['name' => 'Workshop checkout sandbox demo'],
                ],
            ]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);
    }

    public function retrieve(string $id): array
    {
        return $this->request('GET', self::API.'/'.rawurlencode($id));
    }

    private function request(string $method, string $url, array $data = []): array
    {
        abort_unless($this->enabled(), 404);

        try {
            $client = Http::asForm()->acceptJson()
                ->withBasicAuth(config('services.stripe.test_secret'), '')
                ->withoutRedirecting()->connectTimeout(5)->timeout(15);
            $response = $method === 'POST' ? $client->post($url, $data) : $client->get($url);
        } catch (ConnectionException) {
            abort(502, 'Stripe sandbox is unavailable. Try again later.');
        }

        $session = $response->json();
        abort_unless($response->successful() && is_array($session)
            && ($session['object'] ?? null) === 'checkout.session'
            && ($session['livemode'] ?? null) === false
            && ($session['mode'] ?? null) === 'payment'
            && ($session['currency'] ?? null) === 'myr'
            && ($session['amount_total'] ?? null) === self::AMOUNT_SEN
            && is_string($session['id'] ?? null)
            && preg_match('/\Acs_test_[A-Za-z0-9]+\z/', $session['id']) === 1,
            502, 'Stripe did not return the expected sandbox checkout session.');

        return $session;
    }
}
