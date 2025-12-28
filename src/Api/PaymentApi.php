<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Api;

use Fevinta\CashierAsaas\Asaas;
use Fevinta\CashierAsaas\Exceptions\AsaasApiException;

class PaymentApi
{
    /**
     * Create a new payment (charge).
     */
    public function create(array $data): array
    {
        $response = Asaas::client()->post('/payments', $data);

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Find a payment by ID.
     */
    public function find(string $id): array
    {
        $response = Asaas::client()->get("/payments/{$id}");

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Update a payment.
     */
    public function update(string $id, array $data): array
    {
        $response = Asaas::client()->post("/payments/{$id}", $data);

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Delete a payment.
     */
    public function delete(string $id): bool
    {
        $response = Asaas::client()->delete("/payments/{$id}");

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return true;
    }

    /**
     * List payments.
     */
    public function list(array $filters = []): array
    {
        $response = Asaas::client()->get('/payments', $filters);

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Refund a payment.
     */
    public function refund(string $id, array $data = []): array
    {
        $response = Asaas::client()->post("/payments/{$id}/refund", $data);

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Get payment status.
     */
    public function status(string $id): string
    {
        $payment = $this->find($id);

        return $payment['status'];
    }

    /**
     * Get bank slip (boleto) identification field.
     */
    public function identificationField(string $id): array
    {
        $response = Asaas::client()->get("/payments/{$id}/identificationField");

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Get PIX QR Code.
     */
    public function pixQrCode(string $id): array
    {
        $response = Asaas::client()->get("/payments/{$id}/pixQrCode");

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Confirm payment received in cash.
     */
    public function receiveInCash(string $id, array $data = []): array
    {
        $response = Asaas::client()->post("/payments/{$id}/receiveInCash", $data);

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Tokenize credit card.
     */
    public function tokenize(array $data): array
    {
        $response = Asaas::client()->post('/creditCard/tokenize', $data);

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }
}
