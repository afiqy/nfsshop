<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class BillplzSandboxCheckout
{
    private const HOST = 'https://www.billplz-sandbox.com';

    private const AMOUNT_SEN = 1000;

    public function enabled(): bool
    {
        return config('services.billplz.sandbox_enabled') === true
            && filled(config('services.billplz.test_secret'))
            && filled(config('services.billplz.test_collection'))
            && filled(config('services.billplz.test_signature_key'));
    }

    public function create(string $callbackUrl, string $redirectUrl): array
    {
        $bill = $this->request('POST', '/bills', [
            'collection_id' => config('services.billplz.test_collection'),
            'email' => 'sandbox@example.com',
            'name' => 'Workshop sandbox demo',
            'description' => 'Test payment only',
            'amount' => self::AMOUNT_SEN,
            'callback_url' => $callbackUrl,
            'redirect_url' => $redirectUrl,
        ]);

        abort_unless(($bill['url'] ?? null) === self::HOST.'/bills/'.$bill['id'],
            502, 'Billplz did not return a valid sandbox checkout link.');

        return $bill;
    }

    public function isPaid(string $id): bool
    {
        $bill = $this->request('GET', '/bills/'.rawurlencode($id));
        abort_unless($bill['id'] === $id, 502, 'Billplz returned a different sandbox bill.');

        return ($bill['paid'] ?? null) === true && ($bill['state'] ?? null) === 'paid'
            && in_array($bill['paid_amount'] ?? null, [self::AMOUNT_SEN, (string) self::AMOUNT_SEN], true);
    }

    public function isValidCallback(array $payload): bool
    {
        return $this->hasValidSignature($payload)
            && ($payload['collection_id'] ?? null) === config('services.billplz.test_collection')
            && ($payload['amount'] ?? null) === (string) self::AMOUNT_SEN
            && $this->validId($payload['id'] ?? null);
    }

    public function hasValidSignature(array $payload, string $prefix = ''): bool
    {
        $signature = $payload['x_signature'] ?? null;
        if (! $this->enabled() || ! is_string($signature) || preg_match('/\A[a-f0-9]{64}\z/i', $signature) !== 1) {
            return false;
        }

        unset($payload['x_signature']);
        $parts = [];
        foreach ($payload as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                return false;
            }
            $parts[] = $prefix.$key.$value;
        }
        sort($parts, SORT_STRING | SORT_FLAG_CASE);

        return hash_equals(
            hash_hmac('sha256', implode('|', $parts), config('services.billplz.test_signature_key')),
            strtolower($signature)
        );
    }

    private function request(string $method, string $path, array $data = []): array
    {
        abort_unless($this->enabled(), 404);

        try {
            $client = Http::asForm()->acceptJson()
                ->withBasicAuth(config('services.billplz.test_secret'), '')
                ->withoutRedirecting()->connectTimeout(5)->timeout(15);
            $url = self::HOST.'/api/v3'.$path;
            $response = $method === 'POST' ? $client->post($url, $data) : $client->get($url);
        } catch (ConnectionException) {
            abort(502, 'The Billplz sandbox is unavailable. Try again later.');
        }

        $bill = $response->json();
        abort_unless($response->successful() && is_array($bill)
            && $this->validId($bill['id'] ?? null)
            && ($bill['collection_id'] ?? null) === config('services.billplz.test_collection')
            && in_array($bill['amount'] ?? null, [self::AMOUNT_SEN, (string) self::AMOUNT_SEN], true),
            502, 'Billplz did not return the expected sandbox bill.');

        return $bill;
    }

    private function validId(mixed $id): bool
    {
        return is_string($id) && preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $id) === 1;
    }
}
