<?php
namespace Joytekmotion\Paystack\Classes;

use Illuminate\Support\Facades\Http;

class PaystackApi {
    private string $secretKey;
    private string $baseUrl = 'https://api.paystack.co';

    public function __construct(string $secretKey) {
        $this->secretKey = $secretKey;
    }

    public function initializeTransaction(array $data) {
        $response = $this->initalizeClient()
            ->post('/transaction/initialize', $data);

        $responseData = $response->json();
        if($response->failed()) {
            throw new \Exception($responseData['message'] ?? 'Failed to initialize transaction');
        }

        return $responseData;
    }

    public function verifyTransaction(string $reference) {
        $response = $this->initalizeClient()
            ->get("/transaction/verify/$reference");

        $responseData = $response->json();
        if($response->failed()) {
            throw new \Exception($responseData['message'] ?? 'Failed to verify transaction');
        }

        return $responseData;
    }

    public function chargeAuthorization(array $data) {
        $response = $this->initalizeClient()
            ->post('/transaction/charge_authorization', $data);

        $responseData = $response->json();
        if($response->failed()) {
            throw new \Exception($responseData['message'] ?? 'Failed to charge authorization');
        }

        return $responseData;
    }

    public function createRefund(string $transactionId, int $amount) {
        $response = $this->initalizeClient()
            ->post('/refund', [
                'transaction' => $transactionId,
                'amount' => $amount
            ]);

        $responseData = $response->json();
        if($response->failed()) {
            throw new \Exception($responseData['message'] ?? 'Failed to refund transaction');
        }

        return $responseData;
    }

    private function initalizeClient() {
        return Http::withToken($this->secretKey)
            ->acceptJson()
            ->baseUrl($this->baseUrl);
    }
}
