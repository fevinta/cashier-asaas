<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Events\CheckoutCanceled;
use Fevinta\CashierAsaas\Events\CheckoutCreated;
use Fevinta\CashierAsaas\Events\CheckoutExpired;
use Fevinta\CashierAsaas\Events\CheckoutPaid;
use Fevinta\CashierAsaas\Tests\Fixtures\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake();

    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'cpf_cnpj' => generateTestCpf(),
        'asaas_id' => 'cus_test123',
    ]);
});

test('handles CHECKOUT_CREATED event', function () {
    $payload = [
        'event' => 'CHECKOUT_CREATED',
        'checkout' => [
            'id' => 'checkout_123',
            'status' => 'ACTIVE',
            'chargeType' => 'DETACHED',
            'customer' => 'cus_test123',
        ],
    ];

    $response = $this->postJson('/asaas/webhook', $payload);

    $response->assertStatus(200);

    Event::assertDispatched(CheckoutCreated::class, function ($event) {
        return $event->checkoutId === 'checkout_123';
    });
});

test('handles CHECKOUT_PAID event', function () {
    $payload = [
        'event' => 'CHECKOUT_PAID',
        'checkout' => [
            'id' => 'checkout_123',
            'status' => 'PAID',
            'chargeType' => 'DETACHED',
            'customer' => 'cus_test123',
            'payment' => 'pay_123',
            'value' => 99.90,
            'billingType' => 'PIX',
            'dueDate' => now()->format('Y-m-d'),
        ],
    ];

    $response = $this->postJson('/asaas/webhook', $payload);

    $response->assertStatus(200);

    Event::assertDispatched(CheckoutPaid::class, function ($event) {
        return $event->checkoutId === 'checkout_123'
            && $event->paymentId === 'pay_123';
    });
});

test('handles CHECKOUT_PAID event with subscription', function () {
    $payload = [
        'event' => 'CHECKOUT_PAID',
        'checkout' => [
            'id' => 'checkout_123',
            'status' => 'PAID',
            'chargeType' => 'RECURRENT',
            'customer' => 'cus_test123',
            'payment' => 'pay_123',
            'value' => 99.90,
            'billingType' => 'CREDIT_CARD',
            'dueDate' => now()->format('Y-m-d'),
            'subscription' => [
                'id' => 'sub_123',
                'status' => 'ACTIVE',
                'cycle' => 'MONTHLY',
                'nextDueDate' => '2025-02-01',
            ],
            'externalReference' => json_encode([
                'type' => 'default',
                'plan' => 'premium',
                'owner_id' => $this->user->id,
            ]),
        ],
    ];

    $response = $this->postJson('/asaas/webhook', $payload);

    $response->assertStatus(200);

    Event::assertDispatched(CheckoutPaid::class, function ($event) {
        return $event->checkoutId === 'checkout_123'
            && $event->subscriptionId === 'sub_123';
    });
});

test('handles CHECKOUT_CANCELED event', function () {
    $payload = [
        'event' => 'CHECKOUT_CANCELED',
        'checkout' => [
            'id' => 'checkout_123',
            'status' => 'CANCELED',
            'customer' => 'cus_test123',
        ],
    ];

    $response = $this->postJson('/asaas/webhook', $payload);

    $response->assertStatus(200);

    Event::assertDispatched(CheckoutCanceled::class, function ($event) {
        return $event->checkoutId === 'checkout_123';
    });
});

test('handles CHECKOUT_EXPIRED event', function () {
    $payload = [
        'event' => 'CHECKOUT_EXPIRED',
        'checkout' => [
            'id' => 'checkout_123',
            'status' => 'EXPIRED',
            'customer' => 'cus_test123',
        ],
    ];

    $response = $this->postJson('/asaas/webhook', $payload);

    $response->assertStatus(200);

    Event::assertDispatched(CheckoutExpired::class, function ($event) {
        return $event->checkoutId === 'checkout_123';
    });
});

test('returns success even with missing checkout id', function () {
    $payload = [
        'event' => 'CHECKOUT_CREATED',
        'checkout' => [],
    ];

    $response = $this->postJson('/asaas/webhook', $payload);

    $response->assertStatus(200);

    Event::assertNotDispatched(CheckoutCreated::class);
});

test('handles CHECKOUT_PAID with subscription using existing subscription', function () {
    // Create an existing subscription first
    $subscription = $this->user->subscriptions()->create([
        'type' => 'default',
        'asaas_id' => 'sub_existing_123',
        'asaas_status' => 'ACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'CREDIT_CARD',
        'next_due_date' => now()->addMonth(),
    ]);

    $payload = [
        'event' => 'CHECKOUT_PAID',
        'checkout' => [
            'id' => 'checkout_existing_sub',
            'status' => 'PAID',
            'chargeType' => 'RECURRENT',
            'customer' => 'cus_test123',
            'payment' => 'pay_existing_sub',
            'value' => 99.90,
            'billingType' => 'CREDIT_CARD',
            'dueDate' => now()->format('Y-m-d'),
            'subscription' => [
                'id' => 'sub_existing_123',
                'status' => 'ACTIVE',
                'cycle' => 'MONTHLY',
                'nextDueDate' => now()->addMonth()->format('Y-m-d'),
            ],
        ],
    ];

    $response = $this->postJson('/asaas/webhook', $payload);

    $response->assertStatus(200);

    Event::assertDispatched(CheckoutPaid::class, function ($event) {
        return $event->checkoutId === 'checkout_existing_sub'
            && $event->subscriptionId === 'sub_existing_123';
    });

    // Verify no new subscription was created (still only 1)
    expect($this->user->subscriptions()->count())->toBe(1);
});

test('handles CHECKOUT_PAID with subscription fallback to customer lookup', function () {
    // Payload with no external reference, should fall back to customer lookup
    $payload = [
        'event' => 'CHECKOUT_PAID',
        'checkout' => [
            'id' => 'checkout_fallback',
            'status' => 'PAID',
            'chargeType' => 'RECURRENT',
            'customer' => 'cus_test123',
            'payment' => 'pay_fallback',
            'value' => 49.90,
            'billingType' => 'PIX',
            'dueDate' => now()->format('Y-m-d'),
            'subscription' => [
                'id' => 'sub_fallback_123',
                'status' => 'ACTIVE',
                'cycle' => 'MONTHLY',
                'nextDueDate' => now()->addMonth()->format('Y-m-d'),
            ],
        ],
    ];

    $response = $this->postJson('/asaas/webhook', $payload);

    $response->assertStatus(200);

    Event::assertDispatched(CheckoutPaid::class, function ($event) {
        return $event->checkoutId === 'checkout_fallback'
            && $event->subscriptionId === 'sub_fallback_123';
    });

    // Verify subscription was created with default values
    $subscription = $this->user->subscriptions()->where('asaas_id', 'sub_fallback_123')->first();
    expect($subscription)->not->toBeNull();
    expect($subscription->type)->toBe('default');
    expect($subscription->plan)->toBe('default');
});

test('handles CHECKOUT_PAID with subscription and invalid external reference', function () {
    // Payload with invalid (non-JSON) external reference
    $payload = [
        'event' => 'CHECKOUT_PAID',
        'checkout' => [
            'id' => 'checkout_invalid_ref',
            'status' => 'PAID',
            'chargeType' => 'RECURRENT',
            'customer' => 'cus_test123',
            'payment' => 'pay_invalid_ref',
            'value' => 49.90,
            'billingType' => 'PIX',
            'dueDate' => now()->format('Y-m-d'),
            'subscription' => [
                'id' => 'sub_invalid_ref_123',
                'status' => 'ACTIVE',
                'cycle' => 'MONTHLY',
                'nextDueDate' => now()->addMonth()->format('Y-m-d'),
            ],
            'externalReference' => 'not_valid_json',
        ],
    ];

    $response = $this->postJson('/asaas/webhook', $payload);

    $response->assertStatus(200);

    // Should still find customer via asaas_id lookup
    $subscription = $this->user->subscriptions()->where('asaas_id', 'sub_invalid_ref_123')->first();
    expect($subscription)->not->toBeNull();
});

test('handles CHECKOUT_PAID with subscription but no customer found', function () {
    // Payload with unknown customer ID and no external reference
    $payload = [
        'event' => 'CHECKOUT_PAID',
        'checkout' => [
            'id' => 'checkout_no_customer',
            'status' => 'PAID',
            'chargeType' => 'RECURRENT',
            'customer' => 'cus_unknown_999',
            'payment' => 'pay_no_customer',
            'value' => 49.90,
            'billingType' => 'PIX',
            'dueDate' => now()->format('Y-m-d'),
            'subscription' => [
                'id' => 'sub_no_customer_123',
                'status' => 'ACTIVE',
                'cycle' => 'MONTHLY',
                'nextDueDate' => now()->addMonth()->format('Y-m-d'),
            ],
        ],
    ];

    $response = $this->postJson('/asaas/webhook', $payload);

    // Should succeed but not create subscription since customer not found
    $response->assertStatus(200);

    Event::assertDispatched(CheckoutPaid::class);

    // No subscription should be created since customer wasn't found
    expect(\Fevinta\CashierAsaas\Subscription::where('asaas_id', 'sub_no_customer_123')->exists())->toBeFalse();
});

test('handles CHECKOUT_PAID with subscription and empty subscription ID', function () {
    $payload = [
        'event' => 'CHECKOUT_PAID',
        'checkout' => [
            'id' => 'checkout_empty_sub_id',
            'status' => 'PAID',
            'chargeType' => 'RECURRENT',
            'customer' => 'cus_test123',
            'payment' => 'pay_empty_sub_id',
            'value' => 49.90,
            'billingType' => 'PIX',
            'dueDate' => now()->format('Y-m-d'),
            'subscription' => [
                'id' => '',
                'status' => 'ACTIVE',
                'cycle' => 'MONTHLY',
            ],
        ],
    ];

    $response = $this->postJson('/asaas/webhook', $payload);

    $response->assertStatus(200);

    // Should dispatch event but with null subscriptionId
    Event::assertDispatched(CheckoutPaid::class, function ($event) {
        return $event->checkoutId === 'checkout_empty_sub_id';
    });
});
