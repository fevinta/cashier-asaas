<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Database\Factories;

use Fevinta\CashierAsaas\Payment;
use Fevinta\CashierAsaas\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for creating Payment models in tests.
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'customer_id' => User::factory(),
            'subscription_id' => null,
            'asaas_id' => 'pay_'.$this->faker->uuid(),
            'billing_type' => 'PIX',
            'value' => 99.90,
            'net_value' => 97.90,
            'status' => 'PENDING',
            'due_date' => now()->addDays(3),
            'payment_date' => null,
            'confirmed_date' => null,
            'refunded_at' => null,
            'invoice_url' => 'https://sandbox.asaas.com/i/'.$this->faker->uuid(),
            'bank_slip_url' => null,
            'pix_qrcode' => null,
            'pix_copy_paste' => null,
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the payment is pending.
     */
    public function pending(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'PENDING',
            'payment_date' => null,
            'confirmed_date' => null,
        ]);
    }

    /**
     * Indicate that the payment is received.
     */
    public function received(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'RECEIVED',
            'payment_date' => now(),
            'confirmed_date' => null,
        ]);
    }

    /**
     * Indicate that the payment is confirmed.
     */
    public function confirmed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'CONFIRMED',
            'payment_date' => now(),
            'confirmed_date' => now(),
        ]);
    }

    /**
     * Indicate that the payment is overdue.
     */
    public function overdue(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'OVERDUE',
            'due_date' => now()->subDays(1),
            'payment_date' => null,
        ]);
    }

    /**
     * Indicate that the payment is refunded.
     */
    public function refunded(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'REFUNDED',
            'payment_date' => now()->subDays(7),
            'confirmed_date' => now()->subDays(7),
            'refunded_at' => now(),
        ]);
    }

    /**
     * Indicate that the payment is deleted.
     */
    public function deleted(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'DELETED',
        ]);
    }

    /**
     * Indicate that the payment was received in cash.
     */
    public function receivedInCash(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'RECEIVED_IN_CASH',
            'payment_date' => now(),
        ]);
    }

    /**
     * Set the payment as PIX.
     */
    public function pix(): self
    {
        return $this->state(fn (array $attributes) => [
            'billing_type' => 'PIX',
            'pix_qrcode' => 'data:image/png;base64,'.base64_encode('fake-qr-code'),
            'pix_copy_paste' => '00020126580014br.gov.bcb.pix0136'.$this->faker->uuid(),
            'bank_slip_url' => null,
        ]);
    }

    /**
     * Set the payment as Boleto.
     */
    public function boleto(): self
    {
        return $this->state(fn (array $attributes) => [
            'billing_type' => 'BOLETO',
            'bank_slip_url' => 'https://sandbox.asaas.com/b/'.$this->faker->uuid(),
            'pix_qrcode' => null,
            'pix_copy_paste' => null,
        ]);
    }

    /**
     * Set the payment as Credit Card.
     */
    public function creditCard(): self
    {
        return $this->state(fn (array $attributes) => [
            'billing_type' => 'CREDIT_CARD',
            'bank_slip_url' => null,
            'pix_qrcode' => null,
            'pix_copy_paste' => null,
        ]);
    }

    /**
     * Set the payment value.
     */
    public function withValue(float $value, ?float $netValue = null): self
    {
        return $this->state(fn (array $attributes) => [
            'value' => $value,
            'net_value' => $netValue ?? ($value * 0.98),
        ]);
    }

    /**
     * Associate with a subscription.
     */
    public function forSubscription($subscription): self
    {
        return $this->state(fn (array $attributes) => [
            'subscription_id' => $subscription->id ?? $subscription,
            'customer_id' => $subscription->user_id ?? null,
        ]);
    }

    /**
     * Add metadata to the payment.
     */
    public function withMetadata(array $metadata): self
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => $metadata,
        ]);
    }
}
