<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Events;

use Fevinta\CashierAsaas\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a Boleto is registered at the bank.
 *
 * Brazilian-specific event for Boleto payment flow.
 * This indicates the Boleto is now valid and can be paid at any bank.
 */
class BoletoRegistered
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
