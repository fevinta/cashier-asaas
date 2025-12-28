<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Api;

use Fevinta\CashierAsaas\Asaas;
use Fevinta\CashierAsaas\Exceptions\AsaasApiException;

/**
 * API client for managing Asaas webhooks.
 */
class WebhookApi
{
    /**
     * List configured webhooks.
     *
     * @throws AsaasApiException
     */
    public function list(array $filters = []): array
    {
        $response = Asaas::client()->get('webhooks', $filters);

        if ($response->failed()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Create a new webhook endpoint.
     *
     * @param  array  $data  Webhook configuration
     *                       - url: string (required) - The webhook URL
     *                       - email: string - Email for notifications
     *                       - enabled: bool - Whether the webhook is enabled
     *                       - interrupted: bool - Whether to stop on failure
     *                       - apiVersion: int - API version (3)
     *                       - authToken: string - Authentication token
     *                       - sendType: string - SEQUENTIALLY or NON_SEQUENTIALLY
     *
     * @throws AsaasApiException
     */
    public function create(array $data): array
    {
        $response = Asaas::client()->post('webhooks', $data);

        if ($response->failed()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Get webhook details.
     *
     * @throws AsaasApiException
     */
    public function find(string $id): array
    {
        $response = Asaas::client()->get("webhooks/{$id}");

        if ($response->failed()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Update a webhook.
     *
     * @throws AsaasApiException
     */
    public function update(string $id, array $data): array
    {
        $response = Asaas::client()->post("webhooks/{$id}", $data);

        if ($response->failed()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Delete a webhook.
     *
     * @throws AsaasApiException
     */
    public function delete(string $id): bool
    {
        $response = Asaas::client()->delete("webhooks/{$id}");

        if ($response->failed()) {
            throw AsaasApiException::fromResponse($response);
        }

        return true;
    }

    /**
     * Get webhook queue (pending events).
     *
     * @throws AsaasApiException
     */
    public function queue(string $id, array $filters = []): array
    {
        $response = Asaas::client()->get("webhooks/{$id}/queue", $filters);

        if ($response->failed()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Resend failed webhook events.
     *
     * @throws AsaasApiException
     */
    public function resend(string $id): bool
    {
        $response = Asaas::client()->post("webhooks/{$id}/resend");

        if ($response->failed()) {
            throw AsaasApiException::fromResponse($response);
        }

        return true;
    }
}
