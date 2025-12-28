<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Exceptions;

use Fevinta\CashierAsaas\Payment;

/**
 * Base exception for payment failures.
 *
 * Contains the Payment model for retry logic.
 */
class IncompletePayment extends CashierException
{
    /**
     * The payment instance.
     */
    public readonly Payment $payment;

    /**
     * Create a new exception instance.
     */
    public function __construct(Payment $payment, string $message = '')
    {
        $this->payment = $payment;

        parent::__construct($message ?: "Payment [{$payment->asaas_id}] is incomplete.");
    }

    /**
     * Create a new exception for a payment.
     */
    public static function forPayment(Payment $payment): self
    {
        return new self($payment);
    }

    /**
     * Get the payment instance.
     */
    public function getPayment(): Payment
    {
        return $this->payment;
    }
}
