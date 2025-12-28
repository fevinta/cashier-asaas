<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Events;

use Fevinta\CashierAsaas\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a PIX QR code is generated.
 *
 * Brazilian-specific event for PIX payment flow.
 */
class PixGenerated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly Payment $payment,
        public readonly array $payload,
        public readonly ?string $qrCode = null,
        public readonly ?string $copyPaste = null
    ) {}

    /**
     * Get the PIX QR code image (base64).
     */
    public function qrCode(): ?string
    {
        return $this->qrCode ?? $this->payment->pix_qrcode;
    }

    /**
     * Get the PIX copy-paste code.
     */
    public function copyPaste(): ?string
    {
        return $this->copyPaste ?? $this->payment->pix_copy_paste;
    }
}
