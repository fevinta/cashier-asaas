<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Concerns;

use Carbon\Carbon;
use Fevinta\CashierAsaas\Subscription;
use Fevinta\CashierAsaas\SubscriptionBuilder;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait ManagesSubscriptions
{
    /**
     * Begin creating a new subscription.
     * 
     * @example
     * $user->newSubscription('default', 'monthly')
     *      ->withCreditCard($cardToken)
     *      ->create();
     */
    public function newSubscription(string $type, string $plan): SubscriptionBuilder
    {
        return new SubscriptionBuilder($this, $type, $plan);
    }

    /**
     * Determine if the billable model is on trial.
     */
    public function onTrial(string $type = 'default', ?string $plan = null): bool
    {
        if (func_num_args() === 0 && $this->onGenericTrial()) {
            return true;
        }

        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->onTrial()) {
            return false;
        }

        return $plan ? $subscription->hasPlan($plan) : true;
    }

    /**
     * Determine if the billable model is on a generic trial.
     */
    public function onGenericTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Get the ending date of the trial.
     */
    public function trialEndsAt(?string $type = 'default'): ?Carbon
    {
        if ($subscription = $this->subscription($type)) {
            return $subscription->trial_ends_at;
        }

        return $this->trial_ends_at;
    }

    /**
     * Determine if the billable model has a given subscription.
     */
    public function subscribed(string $type = 'default', ?string $plan = null): bool
    {
        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        return $plan ? $subscription->hasPlan($plan) : true;
    }

    /**
     * Get a subscription instance by type.
     */
    public function subscription(string $type = 'default'): ?Subscription
    {
        return $this->subscriptions
            ->where('type', $type)
            ->first(fn (Subscription $subscription) => $subscription->valid());
    }

    /**
     * Get all of the subscriptions for the billable model.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, $this->getForeignKey())
            ->orderBy('created_at', 'desc');
    }

    /**
     * Determine if the billable model is actively subscribed to one of the given plans.
     */
    public function subscribedToPlan(string|array $plans, string $type = 'default'): bool
    {
        $subscription = $this->subscription($type);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        foreach ((array) $plans as $plan) {
            if ($subscription->hasPlan($plan)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the billable model has an active subscription on a given plan.
     */
    public function onPlan(string $plan): bool
    {
        return $this->subscriptions
            ->filter(fn (Subscription $subscription) => $subscription->valid())
            ->contains(fn (Subscription $subscription) => $subscription->hasPlan($plan));
    }

    /**
     * Get the billable model's upcoming payment for a subscription.
     */
    public function upcomingPayment(string $type = 'default'): ?array
    {
        $subscription = $this->subscription($type);

        if (! $subscription) {
            return null;
        }

        return $subscription->upcomingPayment();
    }
}
