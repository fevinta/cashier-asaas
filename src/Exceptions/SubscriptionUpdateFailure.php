<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Exceptions;

use Fevinta\CashierAsaas\Subscription;

/**
 * Exception thrown when subscription modifications fail.
 */
class SubscriptionUpdateFailure extends CashierException
{
    /**
     * The subscription instance.
     */
    public readonly Subscription $subscription;

    /**
     * Create a new exception instance.
     */
    public function __construct(Subscription $subscription, string $message)
    {
        $this->subscription = $subscription;

        parent::__construct($message);
    }

    /**
     * Create a new exception for an incomplete subscription.
     */
    public static function incompleteSubscription(Subscription $subscription): self
    {
        return new self(
            $subscription,
            "Subscription [{$subscription->asaas_id}] cannot be modified because it has an incomplete payment."
        );
    }

    /**
     * Create a new exception for a cancelled subscription.
     */
    public static function cannotSwapCancelled(Subscription $subscription): self
    {
        return new self(
            $subscription,
            "Subscription [{$subscription->asaas_id}] cannot be swapped because it is cancelled."
        );
    }

    /**
     * Create a new exception for a subscription that cannot be resumed.
     */
    public static function cannotResume(Subscription $subscription): self
    {
        return new self(
            $subscription,
            "Subscription [{$subscription->asaas_id}] cannot be resumed. ".
            'Only subscriptions within their grace period can be resumed.'
        );
    }

    /**
     * Create a new exception for a generic update failure.
     */
    public static function failed(Subscription $subscription, ?string $reason = null): self
    {
        return new self(
            $subscription,
            "Subscription [{$subscription->asaas_id}] update failed.".($reason ? " Reason: {$reason}" : '')
        );
    }

    /**
     * Get the subscription instance.
     */
    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }
}
