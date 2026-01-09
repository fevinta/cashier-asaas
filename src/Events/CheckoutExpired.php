<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a checkout session expires.
 */
class CheckoutExpired
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $checkoutId,
        public readonly array $payload
    ) {}
}
