<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Exceptions;

/**
 * Exception thrown for malformed webhook payloads.
 */
class InvalidWebhookPayload extends CashierException
{
    /**
     * The webhook payload.
     */
    public readonly array $payload;

    /**
     * Create a new exception instance.
     */
    public function __construct(string $message, array $payload = [])
    {
        $this->payload = $payload;

        parent::__construct($message);
    }

    /**
     * Create a new exception for a missing event type.
     */
    public static function missingEvent(array $payload = []): self
    {
        return new self(
            'The webhook payload does not contain an event type.',
            $payload
        );
    }

    /**
     * Create a new exception for a missing payment ID.
     */
    public static function missingPaymentId(array $payload = []): self
    {
        return new self(
            'The webhook payload does not contain a payment ID.',
            $payload
        );
    }

    /**
     * Create a new exception for a missing subscription ID.
     */
    public static function missingSubscriptionId(array $payload = []): self
    {
        return new self(
            'The webhook payload does not contain a subscription ID.',
            $payload
        );
    }

    /**
     * Create a new exception for a missing customer ID.
     */
    public static function missingCustomerId(array $payload = []): self
    {
        return new self(
            'The webhook payload does not contain a customer ID.',
            $payload
        );
    }

    /**
     * Create a new exception for invalid JSON.
     */
    public static function invalidJson(): self
    {
        return new self('The webhook payload is not valid JSON.');
    }

    /**
     * Get the webhook payload.
     */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
