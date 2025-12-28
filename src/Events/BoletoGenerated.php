<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Events;

use Fevinta\CashierAsaas\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a Boleto is generated.
 *
 * Brazilian-specific event for Boleto payment flow.
 */
class BoletoGenerated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly Payment $payment,
        public readonly array $payload,
        public readonly ?string $bankSlipUrl = null,
        public readonly ?string $identificationField = null
    ) {}

    /**
     * Get the Boleto PDF URL.
     */
    public function bankSlipUrl(): ?string
    {
        return $this->bankSlipUrl ?? $this->payment->bank_slip_url;
    }

    /**
     * Get the Boleto barcode/identification field (linha digitavel).
     */
    public function identificationField(): ?string
    {
        return $this->identificationField;
    }
}
