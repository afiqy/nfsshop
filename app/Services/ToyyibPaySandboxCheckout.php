<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ToyyibPaySandboxCheckout
{
    public const HOST = 'https://dev.toyyibpay.com';

    private const AMOUNT_SEN = 1000;

    public function enabled(): bool
    {
        return config('services.toyyibpay.sandbox_enabled') === true
            && filled(config('services.toyyibpay.test_secret'))
            && filled(config('services.toyyibpay.test_category'));
    }

    public function create(string $returnUrl, string $reference): string
    {
        $result = $this->request('createBill', [
            'categoryCode' => config('services.toyyibpay.test_category'),
            'billName' => 'Workshop sandbox demo',
            'billDescription' => 'Test payment only',
            'billPriceSetting' => 1,
            'billPayorInfo' => 0,
            'billAmount' => self::AMOUNT_SEN,
            'billPaymentChannel' => 0,
            'billReturnUrl' => $returnUrl,
            'billCallbackUrl' => '',
            'billExternalReferenceNo' => $reference,
            'billExpiryDays' => 1,
        ]);
        $code = $result[0]['BillCode'] ?? null;
        abort_unless(is_string($code) && preg_match('/\A[A-Za-z0-9]{1,64}\z/', $code) === 1,
            502, 'toyyibPay did not return a valid sandbox bill code.');

        return $code;
    }

    public function isPaid(string $code, string $reference): bool
    {
        $transactions = $this->request('getBillTransactions', [
            'billCode' => $code,
            'billpaymentStatus' => '1',
        ]);

        foreach ($transactions as $transaction) {
            if (is_array($transaction) && ($transaction['billExternalReferenceNo'] ?? null) === $reference
                && in_array($transaction['billpaymentStatus'] ?? null, [1, '1'], true)
                && in_array($transaction['billpaymentAmount'] ?? null, ['10.00', '10.0', '10', 10, 10.0], true)) {
                return true;
            }
        }

        return false;
    }

    private function request(string $action, array $data): array
    {
        abort_unless($this->enabled(), 404);

        try {
            $response = Http::asForm()->acceptJson()->withoutRedirecting()->connectTimeout(5)->timeout(15)
                ->post(self::HOST.'/index.php/api/'.$action, ['userSecretKey' => config('services.toyyibpay.test_secret')] + $data);
        } catch (ConnectionException) {
            abort(502, 'The toyyibPay sandbox is unavailable. Try again later.');
        }

        $result = $response->json();
        abort_unless($response->successful() && is_array($result) && array_is_list($result),
            502, 'toyyibPay did not return the expected sandbox response.');

        return $result;
    }
}
