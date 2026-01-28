<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Events;

use Fevinta\CashierAsaas\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when an invoice (nota fiscal) is created.
 */
class InvoiceCreated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly Invoice $invoice,
        public readonly array $payload
    ) {}
}
