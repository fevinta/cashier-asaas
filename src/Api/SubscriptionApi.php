<?php

declare(strict_types=1);

namespace FernandoHS\CashierAsaas\Api;

use FernandoHS\CashierAsaas\Asaas;
use FernandoHS\CashierAsaas\Exceptions\AsaasApiException;

class SubscriptionApi
{
    /**
     * Create a new subscription.
     */
    public function create(array $data): array
    {
        $response = Asaas::client()->post('/subscriptions', $data);

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Find a subscription by ID.
     */
    public function find(string $id): array
    {
        $response = Asaas::client()->get("/subscriptions/{$id}");

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Update a subscription.
     */
    public function update(string $id, array $data): array
    {
        $response = Asaas::client()->post("/subscriptions/{$id}", $data);

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Delete (cancel) a subscription.
     */
    public function delete(string $id): bool
    {
        $response = Asaas::client()->delete("/subscriptions/{$id}");

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return true;
    }

    /**
     * List subscriptions.
     */
    public function list(array $filters = []): array
    {
        $response = Asaas::client()->get('/subscriptions', $filters);

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Get payments for a subscription.
     */
    public function payments(string $id, array $filters = []): array
    {
        $response = Asaas::client()->get("/subscriptions/{$id}/payments", $filters);

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Update credit card for a subscription.
     */
    public function updateCreditCard(string $id, array $data): array
    {
        $response = Asaas::client()->put("/subscriptions/{$id}/creditCard", $data);

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Get invoices/payments book for a subscription.
     */
    public function invoices(string $id): array
    {
        return $this->payments($id);
    }

    /**
     * Find by customer.
     */
    public function findByCustomer(string $customerId): array
    {
        return $this->list(['customer' => $customerId]);
    }
}
