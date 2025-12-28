<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Exceptions;

use Throwable;

/**
 * Exception thrown when webhook processing fails.
 */
class WebhookFailed extends CashierException
{
    /**
     * The event type.
     */
    public readonly string $eventType;

    /**
     * The webhook payload.
     */
    public readonly array $payload;

    /**
     * Create a new exception instance.
     */
    public function __construct(string $eventType, array $payload, string $message, ?Throwable $previous = null)
    {
        $this->eventType = $eventType;
        $this->payload = $payload;

        parent::__construct($message, 0, $previous);
    }

    /**
     * Create a new exception for a processing error.
     */
    public static function processingError(string $eventType, array $payload, Throwable $exception): self
    {
        return new self(
            $eventType,
            $payload,
            "Webhook [{$eventType}] processing failed: {$exception->getMessage()}",
            $exception
        );
    }

    /**
     * Create a new exception for an unsupported event type.
     */
    public static function unsupportedEvent(string $eventType, array $payload): self
    {
        return new self(
            $eventType,
            $payload,
            "Webhook event type [{$eventType}] is not supported."
        );
    }

    /**
     * Create a new exception for a handler not found.
     */
    public static function handlerNotFound(string $eventType, array $payload): self
    {
        return new self(
            $eventType,
            $payload,
            "No handler found for webhook event type [{$eventType}]."
        );
    }

    /**
     * Create a new exception for a database error.
     */
    public static function databaseError(string $eventType, array $payload, Throwable $exception): self
    {
        return new self(
            $eventType,
            $payload,
            "Database error while processing webhook [{$eventType}]: {$exception->getMessage()}",
            $exception
        );
    }

    /**
     * Get the event type.
     */
    public function getEventType(): string
    {
        return $this->eventType;
    }

    /**
     * Get the webhook payload.
     */
    public function getPayload(): array
    {
        return $this->payload;
    }
}
