<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a checkout session is paid.
 */
class CheckoutPaid
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
        public readonly array $payload,
        public readonly ?string $paymentId = null,
        public readonly ?string $subscriptionId = null
    ) {}
}
