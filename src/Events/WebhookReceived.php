<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when any webhook arrives (before processing).
 */
class WebhookReceived
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly array $payload
    ) {}
}
