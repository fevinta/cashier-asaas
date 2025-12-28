<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Events;

use Fevinta\CashierAsaas\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a payment is received.
 */
class PaymentReceived
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly Payment $payment,
        public readonly array $payload
    ) {}
}
