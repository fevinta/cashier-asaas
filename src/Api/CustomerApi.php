<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Api;

use Fevinta\CashierAsaas\Asaas;
use Fevinta\CashierAsaas\Exceptions\AsaasApiException;

class CustomerApi
{
    /**
     * Create a new customer.
     */
    public function create(array $data): array
    {
        $response = Asaas::client()->post('/customers', $data);

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Find a customer by ID.
     */
    public function find(string $id): array
    {
        $response = Asaas::client()->get("/customers/{$id}");

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Update a customer.
     */
    public function update(string $id, array $data): array
    {
        $response = Asaas::client()->post("/customers/{$id}", $data);

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Delete a customer.
     */
    public function delete(string $id): bool
    {
        $response = Asaas::client()->delete("/customers/{$id}");

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return true;
    }

    /**
     * List customers.
     */
    public function list(array $filters = []): array
    {
        $response = Asaas::client()->get('/customers', $filters);

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Find customer by CPF/CNPJ.
     */
    public function findByCpfCnpj(string $cpfCnpj): ?array
    {
        $result = $this->list(['cpfCnpj' => $cpfCnpj]);

        return $result['data'][0] ?? null;
    }

    /**
     * Find customer by external reference.
     */
    public function findByExternalReference(string $reference): ?array
    {
        $result = $this->list(['externalReference' => $reference]);

        return $result['data'][0] ?? null;
    }

    /**
     * Restore a deleted customer.
     */
    public function restore(string $id): array
    {
        $response = Asaas::client()->post("/customers/{$id}/restore");

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Get customer notifications.
     */
    public function notifications(string $id): array
    {
        $response = Asaas::client()->get("/customers/{$id}/notifications");

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }
}
