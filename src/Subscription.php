<?php

declare(strict_types=1);

namespace FernandoHS\CashierAsaas;

use Carbon\Carbon;
use FernandoHS\CashierAsaas\Enums\BillingType;
use FernandoHS\CashierAsaas\Enums\SubscriptionCycle;
use FernandoHS\CashierAsaas\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    protected $table = 'asaas_subscriptions';

    protected $fillable = [
        'type',
        'asaas_id',
        'asaas_status',
        'plan',
        'value',
        'cycle',
        'billing_type',
        'next_due_date',
        'trial_ends_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'next_due_date' => 'datetime',
            'trial_ends_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->owner();
    }

    /**
     * Get the model that owns the subscription.
     */
    public function owner(): BelongsTo
    {
        $model = config('cashier-asaas.model');

        return $this->belongsTo($model, (new $model)->getForeignKey());
    }

    /**
     * Get the payments for this subscription.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'subscription_id');
    }

    /**
     * Determine if the subscription is active.
     */
    public function active(): bool
    {
        return $this->asaas_status === SubscriptionStatus::ACTIVE->value
            || $this->onTrial()
            || $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is valid.
     */
    public function valid(): bool
    {
        return $this->active() || $this->onTrial() || $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is on trial.
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Determine if the subscription has ended its trial.
     */
    public function hasExpiredTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Determine if the subscription is within its grace period after cancellation.
     */
    public function onGracePeriod(): bool
    {
        return $this->ends_at && $this->ends_at->isFuture();
    }

    /**
     * Determine if the subscription is recurring (not cancelled).
     */
    public function recurring(): bool
    {
        return ! $this->cancelled();
    }

    /**
     * Determine if the subscription is cancelled.
     */
    public function cancelled(): bool
    {
        return ! is_null($this->ends_at);
    }

    /**
     * Determine if the subscription has ended.
     */
    public function ended(): bool
    {
        return $this->cancelled() && ! $this->onGracePeriod();
    }

    /**
     * Determine if the subscription has a specific plan.
     */
    public function hasPlan(string $plan): bool
    {
        return $this->plan === $plan;
    }

    /**
     * Cancel the subscription.
     */
    public function cancel(): self
    {
        $asaasSubscription = Asaas::subscription()->find($this->asaas_id);
        
        // Cancel in Asaas
        Asaas::subscription()->delete($this->asaas_id);

        // Set grace period until next due date
        $this->ends_at = $this->next_due_date ?? Carbon::now();
        $this->asaas_status = SubscriptionStatus::INACTIVE->value;
        $this->save();

        return $this;
    }

    /**
     * Cancel the subscription immediately.
     */
    public function cancelNow(): self
    {
        Asaas::subscription()->delete($this->asaas_id);

        $this->ends_at = Carbon::now();
        $this->asaas_status = SubscriptionStatus::INACTIVE->value;
        $this->save();

        return $this;
    }

    /**
     * Cancel the subscription at a specific date.
     */
    public function cancelAt(Carbon $date): self
    {
        $this->ends_at = $date;
        $this->save();

        return $this;
    }

    /**
     * Resume the subscription.
     */
    public function resume(): self
    {
        if (! $this->onGracePeriod()) {
            throw new \LogicException('Unable to resume subscription that is not within grace period.');
        }

        // Reactivate in Asaas by updating the subscription
        Asaas::subscription()->update($this->asaas_id, [
            'status' => SubscriptionStatus::ACTIVE->value,
        ]);

        $this->ends_at = null;
        $this->asaas_status = SubscriptionStatus::ACTIVE->value;
        $this->save();

        return $this;
    }

    /**
     * Swap the subscription to a new plan.
     */
    public function swap(string $plan, ?float $newPrice = null): self
    {
        $price = $newPrice ?? config("cashier-asaas.plans.{$plan}.price");

        if ($price === null) {
            throw new \InvalidArgumentException("Price for plan '{$plan}' not found.");
        }

        Asaas::subscription()->update($this->asaas_id, [
            'value' => $price,
            'updatePendingPayments' => true,
        ]);

        $this->plan = $plan;
        $this->value = $price;
        $this->save();

        return $this;
    }

    /**
     * Swap the subscription to a new plan and invoice immediately.
     */
    public function swapAndInvoice(string $plan, ?float $newPrice = null): self
    {
        return $this->swap($plan, $newPrice);
    }

    /**
     * Update the subscription's value.
     */
    public function updateValue(float $value, bool $updatePending = false): self
    {
        Asaas::subscription()->update($this->asaas_id, [
            'value' => $value,
            'updatePendingPayments' => $updatePending,
        ]);

        $this->value = $value;
        $this->save();

        return $this;
    }

    /**
     * Change the billing type.
     */
    public function changeBillingType(BillingType $type): self
    {
        Asaas::subscription()->update($this->asaas_id, [
            'billingType' => $type->value,
        ]);

        $this->billing_type = $type->value;
        $this->save();

        return $this;
    }

    /**
     * Update the credit card for this subscription.
     */
    public function updateCreditCard(
        array $creditCard,
        array $holderInfo,
        ?string $remoteIp = null
    ): self {
        Asaas::subscription()->updateCreditCard($this->asaas_id, [
            'creditCard' => $creditCard,
            'creditCardHolderInfo' => $holderInfo,
            'remoteIp' => $remoteIp ?? request()->ip(),
        ]);

        return $this;
    }

    /**
     * Update the credit card using a token.
     */
    public function updateCreditCardToken(string $token, ?string $remoteIp = null): self
    {
        Asaas::subscription()->updateCreditCard($this->asaas_id, [
            'creditCardToken' => $token,
            'remoteIp' => $remoteIp ?? request()->ip(),
        ]);

        return $this;
    }

    /**
     * Get the upcoming payment for this subscription.
     */
    public function upcomingPayment(): ?array
    {
        $payments = Asaas::subscription()->payments($this->asaas_id, [
            'status' => 'PENDING',
        ]);

        return $payments['data'][0] ?? null;
    }

    /**
     * Get all payments for this subscription from Asaas.
     */
    public function asaasPayments(array $filters = []): array
    {
        return Asaas::subscription()->payments($this->asaas_id, $filters);
    }

    /**
     * Sync with Asaas data.
     */
    public function syncFromAsaas(): self
    {
        $asaasSubscription = Asaas::subscription()->find($this->asaas_id);

        $this->asaas_status = $asaasSubscription['status'];
        $this->next_due_date = Carbon::parse($asaasSubscription['nextDueDate']);
        $this->value = $asaasSubscription['value'];
        $this->save();

        return $this;
    }

    /**
     * Get the Asaas subscription data.
     */
    public function asAsaasSubscription(): array
    {
        return Asaas::subscription()->find($this->asaas_id);
    }

    /**
     * Scope to get active subscriptions.
     */
    public function scopeActive($query)
    {
        return $query->where('asaas_status', SubscriptionStatus::ACTIVE->value);
    }

    /**
     * Scope to get subscriptions by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
