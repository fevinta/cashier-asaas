<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Contracts;

use Fevinta\CashierAsaas\Payment;
use Fevinta\CashierAsaas\Subscription;
use Fevinta\CashierAsaas\SubscriptionBuilder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Contract for billable models.
 */
interface Billable
{
    /**
     * Get the Asaas customer ID.
     */
    public function asaasId(): ?string;

    /**
     * Determine if the billable has an Asaas ID.
     */
    public function hasAsaasId(): bool;

    /**
     * Create an Asaas customer for the billable model.
     */
    public function createAsAsaasCustomer(array $options = []): array;

    /**
     * Create an Asaas customer if it doesn't exist.
     */
    public function createOrGetAsaasCustomer(array $options = []): array;

    /**
     * Update the Asaas customer information.
     */
    public function updateAsaasCustomer(array $options = []): array;

    /**
     * Get the Asaas customer.
     */
    public function asAsaasCustomer(): array;

    /**
     * Get all subscriptions for the billable model.
     */
    public function subscriptions(): HasMany;

    /**
     * Get a subscription by type.
     */
    public function subscription(string $type = 'default'): ?Subscription;

    /**
     * Begin creating a new subscription.
     */
    public function newSubscription(string $type, string $plan): SubscriptionBuilder;

    /**
     * Determine if the billable is subscribed.
     */
    public function subscribed(string $type = 'default', ?string $plan = null): bool;

    /**
     * Determine if the billable is on trial.
     */
    public function onTrial(?string $type = 'default', ?string $plan = null): bool;

    /**
     * Determine if the billable is on a generic trial.
     */
    public function onGenericTrial(): bool;

    /**
     * Get all payments for the billable model.
     */
    public function payments(): HasMany;

    /**
     * Charge the billable with a specific amount.
     */
    public function charge(float $amount, mixed $billingType, array $options = []): Payment;

    /**
     * Charge the billable with PIX.
     */
    public function chargeWithPix(float $amount, array $options = []): Payment;

    /**
     * Charge the billable with Boleto.
     */
    public function chargeWithBoleto(float $amount, array $options = []): Payment;

    /**
     * Charge the billable with credit card.
     */
    public function chargeWithCreditCard(float $amount, string $creditCardToken, array $options = []): Payment;

    /**
     * Refund a payment.
     */
    public function refund(string $paymentId, ?float $amount = null, ?string $description = null): array;

    /**
     * Tokenize a credit card.
     */
    public function tokenizeCreditCard(array $creditCard, array $holderInfo, ?string $remoteIp = null): string;

    /**
     * Determine if the billable has a payment method.
     */
    public function hasPaymentMethod(): bool;
}
