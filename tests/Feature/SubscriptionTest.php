<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Asaas;
use Fevinta\CashierAsaas\Subscription;
use Fevinta\CashierAsaas\Tests\Concerns\MocksAsaasApi;
use Fevinta\CashierAsaas\Tests\Fixtures\AsaasApiFixtures;
use Fevinta\CashierAsaas\Tests\Fixtures\User;
use Illuminate\Support\Facades\Http;

uses(MocksAsaasApi::class);

beforeEach(function () {
    $this->mockAsaasApi();

    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'cpf_cnpj' => generateTestCpf(),
        'asaas_id' => 'cus_test123',
    ]);
});

test('user can create subscription', function () {
    $subscriptionId = 'sub_'.uniqid();

    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response(
            AsaasApiFixtures::customer(['id' => 'cus_test123']),
            200
        ),
        Asaas::baseUrl().'/subscriptions' => Http::response(
            AsaasApiFixtures::subscription([
                'id' => $subscriptionId,
                'customer' => 'cus_test123',
            ]),
            200
        ),
    ]);

    $subscription = $this->user->newSubscription('default', 'premium')
        ->price(99.90)
        ->monthly()
        ->withPix()
        ->create();

    expect($subscription)->toBeInstanceOf(Subscription::class);
    expect($subscription->type)->toBe('default');
    expect($subscription->plan)->toBe('premium');
    expect($subscription->user_id)->toBe($this->user->id);
});

test('user can create subscription with trial', function () {
    $subscriptionId = 'sub_'.uniqid();

    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response(
            AsaasApiFixtures::customer(['id' => 'cus_test123']),
            200
        ),
        Asaas::baseUrl().'/subscriptions' => Http::response(
            AsaasApiFixtures::subscription([
                'id' => $subscriptionId,
                'customer' => 'cus_test123',
            ]),
            200
        ),
    ]);

    $subscription = $this->user->newSubscription('default', 'premium')
        ->price(99.90)
        ->monthly()
        ->withPix()
        ->trialDays(14)
        ->create();

    expect($subscription->trial_ends_at)->not->toBeNull();
    expect($subscription->onTrial())->toBeTrue();
});

test('user can create subscription with pix', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response(
            AsaasApiFixtures::customer(['id' => 'cus_test123']),
            200
        ),
        Asaas::baseUrl().'/subscriptions' => Http::response(
            AsaasApiFixtures::subscription(['billingType' => 'PIX']),
            200
        ),
    ]);

    $subscription = $this->user->newSubscription('default', 'premium')
        ->price(99.90)
        ->monthly()
        ->withPix()
        ->create();

    expect($subscription->billing_type)->toBe('PIX');
});

test('user can create subscription with boleto', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response(
            AsaasApiFixtures::customer(['id' => 'cus_test123']),
            200
        ),
        Asaas::baseUrl().'/subscriptions' => Http::response(
            AsaasApiFixtures::subscription(['billingType' => 'BOLETO']),
            200
        ),
    ]);

    $subscription = $this->user->newSubscription('default', 'premium')
        ->price(99.90)
        ->monthly()
        ->withBoleto()
        ->create();

    expect($subscription->billing_type)->toBe('BOLETO');
});

test('subscribed check returns true for active subscription', function () {
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

    expect($this->user->subscribed('default'))->toBeTrue();
    expect($this->user->subscribed('default', 'premium'))->toBeTrue();
});

test('subscribed check returns false for cancelled subscription', function () {
    Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_test123',
        'asaas_status' => 'INACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->subMonth(),
        'ends_at' => now()->subDay(),
    ]);

    expect($this->user->subscribed('default'))->toBeFalse();
});

test('on trial check returns correct value', function () {
    Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_test123',
        'asaas_status' => 'ACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addDays(14),
        'trial_ends_at' => now()->addDays(14),
    ]);

    expect($this->user->onTrial('default'))->toBeTrue();
});

test('on grace period check returns correct value', function () {
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
        'ends_at' => now()->addDays(7),
    ]);

    $subscription = $this->user->subscription('default');
    expect($subscription->onGracePeriod())->toBeTrue();
    expect($subscription->cancelled())->toBeTrue();
});

test('user can get subscription by type', function () {
    Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_default',
        'asaas_status' => 'ACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addMonth(),
    ]);

    Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'addon',
        'asaas_id' => 'sub_addon',
        'asaas_status' => 'ACTIVE',
        'plan' => 'storage',
        'value' => 19.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addMonth(),
    ]);

    expect($this->user->subscription('default')->asaas_id)->toBe('sub_default');
    expect($this->user->subscription('addon')->asaas_id)->toBe('sub_addon');
});

test('user subscriptions relationship returns all subscriptions', function () {
    Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_1',
        'asaas_status' => 'ACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addMonth(),
    ]);

    Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'addon',
        'asaas_id' => 'sub_2',
        'asaas_status' => 'ACTIVE',
        'plan' => 'storage',
        'value' => 19.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addMonth(),
    ]);

    expect($this->user->subscriptions)->toHaveCount(2);
});

test('subscribed to plan check works correctly', function () {
    Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_test',
        'asaas_status' => 'ACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addMonth(),
    ]);

    expect($this->user->subscribedToPlan('premium', 'default'))->toBeTrue();
    expect($this->user->subscribedToPlan('basic', 'default'))->toBeFalse();
    expect($this->user->subscribedToPlan(['premium', 'enterprise'], 'default'))->toBeTrue();
});

test('on plan check works correctly', function () {
    Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_test',
        'asaas_status' => 'ACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addMonth(),
    ]);

    expect($this->user->onPlan('premium'))->toBeTrue();
    expect($this->user->onPlan('basic'))->toBeFalse();
});

test('generic trial check works', function () {
    $user = User::create([
        'name' => 'Trial User',
        'email' => 'trial@example.com',
        'cpf_cnpj' => generateTestCpf(),
        'trial_ends_at' => now()->addDays(14),
    ]);

    expect($user->onGenericTrial())->toBeTrue();
});

test('generic trial check returns false when expired', function () {
    $user = User::create([
        'name' => 'Expired Trial User',
        'email' => 'expired@example.com',
        'cpf_cnpj' => generateTestCpf(),
        'trial_ends_at' => now()->subDay(),
    ]);

    expect($user->onGenericTrial())->toBeFalse();
});
