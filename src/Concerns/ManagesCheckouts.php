<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Concerns;

use Fevinta\CashierAsaas\Checkout;
use Fevinta\CashierAsaas\CheckoutBuilder;

trait ManagesCheckouts
{
    /**
     * Create a checkout session for items.
     *
     * @param  array<int, array{name: string, value: float, quantity?: int, description?: string}>  $items
     * @param  array<string, mixed>  $sessionOptions
     */
    public function checkout(array $items, array $sessionOptions = []): Checkout
    {
        return $this->newCheckout()
            ->items($items)
            ->create($sessionOptions);
    }

    /**
     * Create a checkout session for a single charge.
     *
     * @param  float  $amount  Amount in BRL
     * @param  string  $name  Item/charge name
     * @param  int  $quantity  Quantity
     * @param  array<string, mixed>  $sessionOptions
     */
    public function checkoutCharge(
        float $amount,
        string $name,
        int $quantity = 1,
        array $sessionOptions = []
    ): Checkout {
        return $this->newCheckout()
            ->charge($amount, $name, $quantity)
            ->create($sessionOptions);
    }

    /**
     * Start building a checkout session (fluent API).
     */
    public function newCheckout(): CheckoutBuilder
    {
        return CheckoutBuilder::customer($this);
    }
}
