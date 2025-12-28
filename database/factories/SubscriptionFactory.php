<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Database\Factories;

use Fevinta\CashierAsaas\Subscription;
use Fevinta\CashierAsaas\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for creating Subscription models in tests.
 *
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'default',
            'asaas_id' => 'sub_'.$this->faker->uuid(),
            'asaas_status' => 'ACTIVE',
            'plan' => 'premium',
            'value' => 99.90,
            'cycle' => 'MONTHLY',
            'billing_type' => 'PIX',
            'next_due_date' => now()->addMonth(),
            'trial_ends_at' => null,
            'ends_at' => null,
        ];
    }

    /**
     * Indicate that the subscription is active.
     */
    public function active(): self
    {
        return $this->state(fn (array $attributes) => [
            'asaas_status' => 'ACTIVE',
            'ends_at' => null,
        ]);
    }

    /**
     * Indicate that the subscription is on trial.
     */
    public function onTrial(int $days = 14): self
    {
        return $this->state(fn (array $attributes) => [
            'asaas_status' => 'ACTIVE',
            'trial_ends_at' => now()->addDays($days),
            'ends_at' => null,
        ]);
    }

    /**
     * Indicate that the subscription has an expired trial.
     */
    public function expiredTrial(): self
    {
        return $this->state(fn (array $attributes) => [
            'asaas_status' => 'ACTIVE',
            'trial_ends_at' => now()->subDays(1),
            'ends_at' => null,
        ]);
    }

    /**
     * Indicate that the subscription is cancelled.
     */
    public function cancelled(): self
    {
        return $this->state(fn (array $attributes) => [
            'asaas_status' => 'ACTIVE',
            'ends_at' => now()->addMonth(),
        ]);
    }

    /**
     * Indicate that the subscription has ended.
     */
    public function ended(): self
    {
        return $this->state(fn (array $attributes) => [
            'asaas_status' => 'INACTIVE',
            'ends_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the subscription is expired.
     */
    public function expired(): self
    {
        return $this->state(fn (array $attributes) => [
            'asaas_status' => 'EXPIRED',
            'ends_at' => now()->subMonth(),
        ]);
    }

    /**
     * Indicate that the subscription is on grace period.
     */
    public function onGracePeriod(): self
    {
        return $this->state(fn (array $attributes) => [
            'asaas_status' => 'ACTIVE',
            'ends_at' => now()->addDays(7),
        ]);
    }

    /**
     * Set the subscription plan.
     */
    public function withPlan(string $plan): self
    {
        return $this->state(fn (array $attributes) => [
            'plan' => $plan,
        ]);
    }

    /**
     * Set the subscription to use credit card billing.
     */
    public function withCreditCard(): self
    {
        return $this->state(fn (array $attributes) => [
            'billing_type' => 'CREDIT_CARD',
        ]);
    }

    /**
     * Set the subscription to use boleto billing.
     */
    public function withBoleto(): self
    {
        return $this->state(fn (array $attributes) => [
            'billing_type' => 'BOLETO',
        ]);
    }

    /**
     * Set the subscription to use PIX billing.
     */
    public function withPix(): self
    {
        return $this->state(fn (array $attributes) => [
            'billing_type' => 'PIX',
        ]);
    }

    /**
     * Set the subscription cycle.
     */
    public function withCycle(string $cycle): self
    {
        return $this->state(fn (array $attributes) => [
            'cycle' => $cycle,
        ]);
    }

    /**
     * Set the subscription value.
     */
    public function withValue(float $value): self
    {
        return $this->state(fn (array $attributes) => [
            'value' => $value,
        ]);
    }
}
