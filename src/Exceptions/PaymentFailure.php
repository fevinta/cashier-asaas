<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Exceptions;

use Fevinta\CashierAsaas\Payment;

/**
 * Exception thrown when a payment is declined or fails.
 */
class PaymentFailure extends IncompletePayment
{
    /**
     * The failure reason.
     */
    public readonly ?string $reason;

    /**
     * Create a new exception instance.
     */
    public function __construct(Payment $payment, string $message, ?string $reason = null)
    {
        $this->reason = $reason;

        parent::__construct($payment, $message);
    }

    /**
     * Create a new exception for an invalid credit card.
     */
    public static function invalidCard(Payment $payment, ?string $reason = null): self
    {
        return new self(
            $payment,
            "Payment [{$payment->asaas_id}] failed due to invalid credit card.",
            $reason
        );
    }

    /**
     * Create a new exception for a declined card.
     */
    public static function cardDeclined(Payment $payment, ?string $reason = null): self
    {
        return new self(
            $payment,
            "Payment [{$payment->asaas_id}] was declined by the card issuer.",
            $reason
        );
    }

    /**
     * Create a new exception for insufficient funds.
     */
    public static function insufficientFunds(Payment $payment): self
    {
        return new self(
            $payment,
            "Payment [{$payment->asaas_id}] failed due to insufficient funds.",
            'insufficient_funds'
        );
    }

    /**
     * Create a new exception for an expired card.
     */
    public static function expiredCard(Payment $payment): self
    {
        return new self(
            $payment,
            "Payment [{$payment->asaas_id}] failed due to expired card.",
            'expired_card'
        );
    }

    /**
     * Create a new exception for a generic failure.
     */
    public static function failed(Payment $payment, ?string $reason = null): self
    {
        return new self(
            $payment,
            "Payment [{$payment->asaas_id}] failed.".($reason ? " Reason: {$reason}" : ''),
            $reason
        );
    }

    /**
     * Get the failure reason.
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }
}
