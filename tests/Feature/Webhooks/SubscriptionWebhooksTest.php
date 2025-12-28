<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Events\SubscriptionCreated;
use Fevinta\CashierAsaas\Events\SubscriptionDeleted;
use Fevinta\CashierAsaas\Events\SubscriptionUpdated;
use Fevinta\CashierAsaas\Subscription;
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

test('subscription created webhook syncs subscription data', function () {
    // Create existing subscription (usually created via the package)
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_test123',
        'asaas_status' => 'PENDING',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addMonth(),
    ]);

    $response = $this->postJson('/asaas/webhook', [
        'event' => 'SUBSCRIPTION_CREATED',
        'subscription' => [
            'id' => 'sub_test123',
            'customer' => 'cus_test123',
            'status' => 'ACTIVE',
            'value' => 99.90,
            'cycle' => 'MONTHLY',
            'billingType' => 'PIX',
            'nextDueDate' => now()->addMonth()->format('Y-m-d'),
        ],
    ]);

    $response->assertStatus(200);

    $subscription->refresh();
    expect($subscription->asaas_status)->toBe('ACTIVE');
});

test('subscription updated webhook syncs data', function () {
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_test123',
        'asaas_status' => 'ACTIVE',
        'plan' => 'basic',
        'value' => 49.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addMonth(),
    ]);

    $response = $this->postJson('/asaas/webhook', [
        'event' => 'SUBSCRIPTION_UPDATED',
        'subscription' => [
            'id' => 'sub_test123',
            'customer' => 'cus_test123',
            'status' => 'ACTIVE',
            'value' => 99.90, // Updated value
            'cycle' => 'MONTHLY',
            'billingType' => 'BOLETO', // Updated billing type
            'nextDueDate' => now()->addMonth()->format('Y-m-d'),
        ],
    ]);

    $response->assertStatus(200);

    $subscription->refresh();
    expect((float) $subscription->value)->toBe(99.90);
    expect($subscription->billing_type)->toBe('BOLETO');
});

test('subscription deleted webhook marks as cancelled', function () {
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_test123',
        'asaas_status' => 'ACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addMonth(),
    ]);

    $response = $this->postJson('/asaas/webhook', [
        'event' => 'SUBSCRIPTION_DELETED',
        'subscription' => [
            'id' => 'sub_test123',
            'customer' => 'cus_test123',
        ],
    ]);

    $response->assertStatus(200);

    $subscription->refresh();
    expect($subscription->asaas_status)->toBe('INACTIVE');
    expect($subscription->ends_at)->not->toBeNull();
    expect($subscription->cancelled())->toBeTrue();
});

test('subscription webhook dispatches SubscriptionCreated event', function () {
    Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_test123',
        'asaas_status' => 'PENDING',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addMonth(),
    ]);

    $this->postJson('/asaas/webhook', [
        'event' => 'SUBSCRIPTION_CREATED',
        'subscription' => [
            'id' => 'sub_test123',
            'status' => 'ACTIVE',
        ],
    ]);

    Event::assertDispatched(SubscriptionCreated::class, function ($event) {
        return $event->subscription->asaas_id === 'sub_test123';
    });
});

test('subscription webhook dispatches SubscriptionUpdated event', function () {
    Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_test123',
        'asaas_status' => 'ACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addMonth(),
    ]);

    $this->postJson('/asaas/webhook', [
        'event' => 'SUBSCRIPTION_UPDATED',
        'subscription' => [
            'id' => 'sub_test123',
            'value' => 149.90,
            'status' => 'ACTIVE',
        ],
    ]);

    Event::assertDispatched(SubscriptionUpdated::class);
});

test('subscription webhook dispatches SubscriptionDeleted event', function () {
    Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_test123',
        'asaas_status' => 'ACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addMonth(),
    ]);

    $this->postJson('/asaas/webhook', [
        'event' => 'SUBSCRIPTION_DELETED',
        'subscription' => [
            'id' => 'sub_test123',
        ],
    ]);

    Event::assertDispatched(SubscriptionDeleted::class);
});

test('subscription webhook with unknown subscription id does nothing', function () {
    $response = $this->postJson('/asaas/webhook', [
        'event' => 'SUBSCRIPTION_UPDATED',
        'subscription' => [
            'id' => 'sub_unknown',
            'status' => 'ACTIVE',
        ],
    ]);

    // Should still return 200 (acknowledge webhook)
    $response->assertStatus(200);

    // No subscription created
    expect(Subscription::where('asaas_id', 'sub_unknown')->exists())->toBeFalse();
});

test('subscription cycle changes are synced', function () {
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_test123',
        'asaas_status' => 'ACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addMonth(),
    ]);

    $this->postJson('/asaas/webhook', [
        'event' => 'SUBSCRIPTION_UPDATED',
        'subscription' => [
            'id' => 'sub_test123',
            'status' => 'ACTIVE',
            'value' => 999.00,
            'cycle' => 'YEARLY',
            'billingType' => 'CREDIT_CARD',
            'nextDueDate' => now()->addYear()->format('Y-m-d'),
        ],
    ]);

    $subscription->refresh();
    expect((float) $subscription->value)->toBe(999.00);
    expect($subscription->cycle)->toBe('YEARLY');
    expect($subscription->billing_type)->toBe('CREDIT_CARD');
});
