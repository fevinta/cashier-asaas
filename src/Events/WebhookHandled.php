<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched after successful webhook processing.
 */
class WebhookHandled
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
