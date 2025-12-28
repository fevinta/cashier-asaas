<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Subscription;
use Fevinta\CashierAsaas\Tests\Fixtures\User;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'cpf_cnpj' => generateTestCpf(),
        'asaas_id' => 'cus_test123',
    ]);
});

test('subscription is active when status is ACTIVE', function () {
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

    expect($subscription->active())->toBeTrue();
    expect($subscription->valid())->toBeTrue();
});

test('subscription is valid when on trial', function () {
    $subscription = Subscription::create([
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

    expect($subscription->onTrial())->toBeTrue();
    expect($subscription->valid())->toBeTrue();
});

test('subscription trial has expired', function () {
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
        'trial_ends_at' => now()->subDay(),
    ]);

    expect($subscription->onTrial())->toBeFalse();
    expect($subscription->hasExpiredTrial())->toBeTrue();
});

test('subscription is valid when on grace period', function () {
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
        'ends_at' => now()->addDays(7),
    ]);

    expect($subscription->onGracePeriod())->toBeTrue();
    expect($subscription->cancelled())->toBeTrue();
    expect($subscription->valid())->toBeTrue();
});

test('subscription is cancelled when ends_at is set', function () {
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
        'ends_at' => now()->addMonth(),
    ]);

    expect($subscription->cancelled())->toBeTrue();
    expect($subscription->recurring())->toBeFalse();
});

test('subscription has ended when cancelled and past grace period', function () {
    $subscription = Subscription::create([
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

    expect($subscription->cancelled())->toBeTrue();
    expect($subscription->ended())->toBeTrue();
    expect($subscription->active())->toBeFalse();
});

test('subscription is recurring when not cancelled', function () {
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

    expect($subscription->recurring())->toBeTrue();
});

test('subscription plan matching works correctly', function () {
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

    expect($subscription->hasPlan('premium'))->toBeTrue();
    expect($subscription->hasPlan('basic'))->toBeFalse();
});

test('subscription belongs to owner', function () {
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

    expect($subscription->owner)->toBeInstanceOf(User::class);
    expect($subscription->owner->id)->toBe($this->user->id);
});

test('subscription scope filters active subscriptions', function () {
    Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_active',
        'asaas_status' => 'ACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addMonth(),
    ]);

    Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'secondary',
        'asaas_id' => 'sub_inactive',
        'asaas_status' => 'INACTIVE',
        'plan' => 'basic',
        'value' => 49.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->subMonth(),
    ]);

    expect(Subscription::whereActive()->count())->toBe(1);
    expect(Subscription::whereActive()->first()->asaas_id)->toBe('sub_active');
});

test('subscription scope filters by type', function () {
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
        'plan' => 'extra-storage',
        'value' => 19.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addMonth(),
    ]);

    expect(Subscription::ofType('default')->count())->toBe(1);
    expect(Subscription::ofType('addon')->count())->toBe(1);
});

test('subscription casts value as decimal', function () {
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_test123',
        'asaas_status' => 'ACTIVE',
        'plan' => 'premium',
        'value' => '99.90',
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addMonth(),
    ]);

    // decimal:2 cast returns a string with 2 decimal places
    expect($subscription->value)->toBeString();
    expect($subscription->value)->toBe('99.90');
});

test('subscription casts dates correctly', function () {
    $nextDueDate = now()->addMonth();
    $trialEndsAt = now()->addDays(14);

    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_test123',
        'asaas_status' => 'ACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => $nextDueDate,
        'trial_ends_at' => $trialEndsAt,
    ]);

    expect($subscription->next_due_date)->toBeInstanceOf(\Carbon\Carbon::class);
    expect($subscription->trial_ends_at)->toBeInstanceOf(\Carbon\Carbon::class);
});
