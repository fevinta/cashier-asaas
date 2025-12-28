<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Contracts;

use Carbon\Carbon;
use Fevinta\CashierAsaas\Enums\BillingType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Contract for subscription models.
 */
interface Subscription
{
    /**
     * Get the owner of the subscription.
     */
    public function owner(): BelongsTo;

    /**
     * Get the payments for the subscription.
     */
    public function payments(): HasMany;

    /**
     * Determine if the subscription is active.
     */
    public function active(): bool;

    /**
     * Determine if the subscription is valid.
     */
    public function valid(): bool;

    /**
     * Determine if the subscription is cancelled.
     */
    public function cancelled(): bool;

    /**
     * Determine if the subscription has ended.
     */
    public function ended(): bool;

    /**
     * Determine if the subscription is recurring.
     */
    public function recurring(): bool;

    /**
     * Determine if the subscription is on trial.
     */
    public function onTrial(): bool;

    /**
     * Determine if the subscription has an expired trial.
     */
    public function hasExpiredTrial(): bool;

    /**
     * Determine if the subscription is within the grace period.
     */
    public function onGracePeriod(): bool;

    /**
     * Determine if the subscription has a specific plan.
     */
    public function hasPlan(string $plan): bool;

    /**
     * Cancel the subscription at the end of the billing period.
     */
    public function cancel(): self;

    /**
     * Cancel the subscription immediately.
     */
    public function cancelNow(): self;

    /**
     * Cancel the subscription at a specific date.
     */
    public function cancelAt(Carbon $date): self;

    /**
     * Resume the cancelled subscription.
     */
    public function resume(): self;

    /**
     * Swap the subscription to a new plan.
     */
    public function swap(string $plan, ?float $newPrice = null): self;

    /**
     * Update the subscription value.
     */
    public function updateValue(float $value, bool $updatePending = false): self;

    /**
     * Change the billing type.
     */
    public function changeBillingType(BillingType $type): self;

    /**
     * Update the credit card for the subscription.
     */
    public function updateCreditCard(array $creditCard, array $holderInfo, ?string $remoteIp = null): self;

    /**
     * Update the credit card using a token.
     */
    public function updateCreditCardToken(string $token, ?string $remoteIp = null): self;

    /**
     * Sync the local subscription with Asaas.
     */
    public function syncFromAsaas(): self;

    /**
     * Get the Asaas subscription data.
     */
    public function asAsaasSubscription(): array;

    /**
     * Get payments from Asaas.
     */
    public function asaasPayments(array $filters = []): array;

    /**
     * Get the upcoming payment.
     */
    public function upcomingPayment(): ?array;
}
