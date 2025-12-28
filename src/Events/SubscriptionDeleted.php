<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Events;

use Fevinta\CashierAsaas\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a subscription is deleted/cancelled.
 */
class SubscriptionDeleted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly Subscription $subscription,
        public readonly array $payload
    ) {}
}
