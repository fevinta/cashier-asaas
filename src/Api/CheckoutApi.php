<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Api;

use Fevinta\CashierAsaas\Asaas;
use Fevinta\CashierAsaas\Exceptions\AsaasApiException;

class CheckoutApi
{
    /**
     * Create a new checkout session.
     */
    public function create(array $data): array
    {
        $response = Asaas::client()->post('/checkouts', $data);

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Find a checkout session by ID.
     */
    public function find(string $id): array
    {
        $response = Asaas::client()->get("/checkouts/{$id}");

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Cancel a checkout session.
     */
    public function cancel(string $id): bool
    {
        $response = Asaas::client()->delete("/checkouts/{$id}");

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return true;
    }

    /**
     * List checkout sessions.
     */
    public function list(array $filters = []): array
    {
        $response = Asaas::client()->get('/checkouts', $filters);

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Get checkout status.
     */
    public function status(string $id): string
    {
        $checkout = $this->find($id);

        return $checkout['status'];
    }
}
