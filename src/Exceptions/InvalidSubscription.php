<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Exceptions;

/**
 * Exception thrown for operations on invalid or non-existent subscriptions.
 */
class InvalidSubscription extends CashierException
{
    /**
     * The subscription type.
     */
    public readonly ?string $type;

    /**
     * Create a new exception instance.
     */
    public function __construct(string $message, ?string $type = null)
    {
        $this->type = $type;

        parent::__construct($message);
    }

    /**
     * Create a new exception for a subscription that was not found.
     */
    public static function notFound(?string $type = null): self
    {
        $typeInfo = $type ? " of type [{$type}]" : '';

        return new self(
            "No subscription{$typeInfo} was found.",
            $type
        );
    }

    /**
     * Create a new exception for an inactive subscription.
     */
    public static function inactive(string $type): self
    {
        return new self(
            "Subscription of type [{$type}] is not active.",
            $type
        );
    }

    /**
     * Create a new exception for a subscription that already exists.
     */
    public static function alreadyExists(string $type): self
    {
        return new self(
            "A subscription of type [{$type}] already exists.",
            $type
        );
    }

    /**
     * Get the subscription type.
     */
    public function getType(): ?string
    {
        return $this->type;
    }
}
