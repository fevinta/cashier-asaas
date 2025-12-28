<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Tests\Concerns;

use Fevinta\CashierAsaas\Asaas;
use Fevinta\CashierAsaas\Tests\Fixtures\AsaasApiFixtures;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Trait for mocking Asaas API responses in tests.
 */
trait MocksAsaasApi
{
    /**
     * Set up HTTP fakes for Asaas API.
     */
    protected function mockAsaasApi(): void
    {
        Http::preventStrayRequests();
    }

    /**
     * Mock customer creation.
     */
    protected function mockCreateCustomer(array $customerData = []): void
    {
        $response = AsaasApiFixtures::customer($customerData);

        Http::fake([
            Asaas::baseUrl().'/customers' => Http::response($response, 200),
        ]);
    }

    /**
     * Mock customer retrieval.
     */
    protected function mockGetCustomer(string $customerId, array $customerData = []): void
    {
        $response = AsaasApiFixtures::customer(array_merge(['id' => $customerId], $customerData));

        Http::fake([
            Asaas::baseUrl()."/customers/{$customerId}" => Http::response($response, 200),
        ]);
    }

    /**
     * Mock customer update.
     */
    protected function mockUpdateCustomer(string $customerId, array $customerData = []): void
    {
        $response = AsaasApiFixtures::customer(array_merge(['id' => $customerId], $customerData));

        Http::fake([
            Asaas::baseUrl()."/customers/{$customerId}" => Http::response($response, 200),
        ]);
    }

    /**
     * Mock subscription creation.
     */
    protected function mockCreateSubscription(array $subscriptionData = []): void
    {
        $response = AsaasApiFixtures::subscription($subscriptionData);

        Http::fake([
            Asaas::baseUrl().'/subscriptions' => Http::response($response, 200),
        ]);
    }

    /**
     * Mock subscription retrieval.
     */
    protected function mockGetSubscription(string $subscriptionId, array $subscriptionData = []): void
    {
        $response = AsaasApiFixtures::subscription(array_merge(['id' => $subscriptionId], $subscriptionData));

        Http::fake([
            Asaas::baseUrl()."/subscriptions/{$subscriptionId}" => Http::response($response, 200),
        ]);
    }

    /**
     * Mock subscription update.
     */
    protected function mockUpdateSubscription(string $subscriptionId, array $subscriptionData = []): void
    {
        $response = AsaasApiFixtures::subscription(array_merge(['id' => $subscriptionId], $subscriptionData));

        Http::fake([
            Asaas::baseUrl()."/subscriptions/{$subscriptionId}" => Http::response($response, 200),
        ]);
    }

    /**
     * Mock subscription cancellation.
     */
    protected function mockDeleteSubscription(string $subscriptionId): void
    {
        Http::fake([
            Asaas::baseUrl()."/subscriptions/{$subscriptionId}" => Http::response(['deleted' => true], 200),
        ]);
    }

    /**
     * Mock payment creation.
     */
    protected function mockCreatePayment(array $paymentData = []): void
    {
        $response = AsaasApiFixtures::payment($paymentData);

        Http::fake([
            Asaas::baseUrl().'/payments' => Http::response($response, 200),
        ]);
    }

    /**
     * Mock PIX payment creation.
     */
    protected function mockCreatePixPayment(array $paymentData = []): void
    {
        $response = AsaasApiFixtures::pixPayment($paymentData);

        Http::fake([
            Asaas::baseUrl().'/payments' => Http::response($response, 200),
        ]);
    }

    /**
     * Mock Boleto payment creation.
     */
    protected function mockCreateBoletoPayment(array $paymentData = []): void
    {
        $response = AsaasApiFixtures::boletoPayment($paymentData);

        Http::fake([
            Asaas::baseUrl().'/payments' => Http::response($response, 200),
        ]);
    }

    /**
     * Mock credit card payment creation.
     */
    protected function mockCreateCreditCardPayment(array $paymentData = []): void
    {
        $response = AsaasApiFixtures::creditCardPayment($paymentData);

        Http::fake([
            Asaas::baseUrl().'/payments' => Http::response($response, 200),
        ]);
    }

    /**
     * Mock payment retrieval.
     */
    protected function mockGetPayment(string $paymentId, array $paymentData = []): void
    {
        $response = AsaasApiFixtures::payment(array_merge(['id' => $paymentId], $paymentData));

        Http::fake([
            Asaas::baseUrl()."/payments/{$paymentId}" => Http::response($response, 200),
        ]);
    }

    /**
     * Mock payment refund.
     */
    protected function mockRefundPayment(string $paymentId): void
    {
        $response = AsaasApiFixtures::payment(['id' => $paymentId, 'status' => 'REFUNDED']);

        Http::fake([
            Asaas::baseUrl()."/payments/{$paymentId}/refund" => Http::response($response, 200),
        ]);
    }

    /**
     * Mock credit card tokenization.
     */
    protected function mockTokenizeCreditCard(array $tokenData = []): void
    {
        $response = AsaasApiFixtures::creditCardToken($tokenData);

        Http::fake([
            Asaas::baseUrl().'/creditCard/tokenize' => Http::response($response, 200),
        ]);
    }

    /**
     * Mock PIX QR code retrieval.
     */
    protected function mockGetPixQrCode(string $paymentId, array $qrCodeData = []): void
    {
        $response = AsaasApiFixtures::pixQrCode($qrCodeData);

        Http::fake([
            Asaas::baseUrl()."/payments/{$paymentId}/pixQrCode" => Http::response($response, 200),
        ]);
    }

    /**
     * Mock an API error response.
     */
    protected function mockApiError(string $endpoint, string $message = 'An error occurred', int $status = 400): void
    {
        $response = AsaasApiFixtures::error($message);

        Http::fake([
            Asaas::baseUrl().$endpoint => Http::response($response, $status),
        ]);
    }

    /**
     * Mock multiple endpoints at once.
     */
    protected function mockMultipleEndpoints(array $mocks): void
    {
        $fakes = [];

        foreach ($mocks as $endpoint => $response) {
            $fakes[Asaas::baseUrl().$endpoint] = Http::response($response, 200);
        }

        Http::fake($fakes);
    }

    /**
     * Assert that the Asaas API was called with specific data.
     */
    protected function assertAsaasApiCalled(string $method, string $endpoint, array $expectedData = []): void
    {
        Http::assertSent(function (Request $request) use ($method, $endpoint, $expectedData) {
            if ($request->method() !== strtoupper($method)) {
                return false;
            }

            if (! str_contains($request->url(), $endpoint)) {
                return false;
            }

            if (! empty($expectedData)) {
                foreach ($expectedData as $key => $value) {
                    if ($request[$key] !== $value) {
                        return false;
                    }
                }
            }

            return true;
        });
    }

    /**
     * Assert that the Asaas API was called a specific number of times.
     */
    protected function assertAsaasApiCalledTimes(int $times): void
    {
        Http::assertSentCount($times);
    }

    /**
     * Assert that the Asaas API was not called.
     */
    protected function assertAsaasApiNotCalled(): void
    {
        Http::assertNothingSent();
    }
}
