<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Asaas;
use Fevinta\CashierAsaas\Enums\BillingType;
use Fevinta\CashierAsaas\Payment;
use Fevinta\CashierAsaas\Subscription;
use Fevinta\CashierAsaas\Tests\Fixtures\User;
use Illuminate\Support\Facades\Http;

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

test('subscription user method returns owner', function () {
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

    expect($subscription->user)->toBeInstanceOf(User::class);
    expect($subscription->user->id)->toBe($this->user->id);
});

test('subscription has many payments', function () {
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

    Payment::create([
        'customer_id' => $this->user->id,
        'subscription_id' => $subscription->id,
        'asaas_id' => 'pay_1',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now()->addDays(3),
    ]);

    expect($subscription->payments)->toHaveCount(1);
});

test('cancel cancels subscription', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_test123' => Http::sequence()
            ->push(['id' => 'sub_test123', 'status' => 'ACTIVE', 'nextDueDate' => now()->addMonth()->format('Y-m-d')], 200)
            ->push(['deleted' => true], 200),
    ]);

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

    $result = $subscription->cancel();

    expect($result)->toBeInstanceOf(Subscription::class);
    expect($subscription->fresh()->ends_at)->not->toBeNull();
    expect($subscription->fresh()->asaas_status)->toBe('INACTIVE');
});

test('cancelNow cancels subscription immediately', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_test123' => Http::response(['deleted' => true], 200),
    ]);

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

    $result = $subscription->cancelNow();

    expect($result)->toBeInstanceOf(Subscription::class);
    expect($subscription->fresh()->ends_at)->not->toBeNull();
    expect($subscription->fresh()->asaas_status)->toBe('INACTIVE');
});

test('cancelAt cancels subscription at specific date', function () {
    $cancelDate = now()->addWeeks(2);

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

    $result = $subscription->cancelAt($cancelDate);

    expect($result)->toBeInstanceOf(Subscription::class);
    expect($subscription->fresh()->ends_at->format('Y-m-d'))->toBe($cancelDate->format('Y-m-d'));
});

test('resume resumes cancelled subscription within grace period', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_test123' => Http::response([
            'id' => 'sub_test123',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_test123',
        'asaas_status' => 'INACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addMonth(),
        'ends_at' => now()->addDays(7),
    ]);

    $result = $subscription->resume();

    expect($result)->toBeInstanceOf(Subscription::class);
    expect($subscription->fresh()->ends_at)->toBeNull();
    expect($subscription->fresh()->asaas_status)->toBe('ACTIVE');
});

test('resume throws exception when not on grace period', function () {
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

    $subscription->resume();
})->throws(LogicException::class);

test('swap changes subscription plan', function () {
    config(['cashier-asaas.plans.pro.price' => 199.90]);

    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_test123' => Http::response([
            'id' => 'sub_test123',
            'value' => 199.90,
        ], 200),
    ]);

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

    $result = $subscription->swap('pro');

    expect($result)->toBeInstanceOf(Subscription::class);
    expect($subscription->fresh()->plan)->toBe('pro');
    expect($subscription->fresh()->value)->toBe('199.90');
});

test('swap with custom price works correctly', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_test123' => Http::response([
            'id' => 'sub_test123',
            'value' => 149.90,
        ], 200),
    ]);

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

    $result = $subscription->swap('pro', 149.90);

    expect($subscription->fresh()->value)->toBe('149.90');
});

test('swap throws exception when price not found', function () {
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

    $subscription->swap('nonexistent');
})->throws(InvalidArgumentException::class);

test('swapAndInvoice swaps plan', function () {
    config(['cashier-asaas.plans.pro.price' => 199.90]);

    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_test123' => Http::response([
            'id' => 'sub_test123',
            'value' => 199.90,
        ], 200),
    ]);

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

    $result = $subscription->swapAndInvoice('pro');

    expect($result)->toBeInstanceOf(Subscription::class);
    expect($subscription->fresh()->plan)->toBe('pro');
});

test('updateValue updates subscription value', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_test123' => Http::response([
            'id' => 'sub_test123',
            'value' => 149.90,
        ], 200),
    ]);

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

    $result = $subscription->updateValue(149.90);

    expect($result)->toBeInstanceOf(Subscription::class);
    expect($subscription->fresh()->value)->toBe('149.90');
});

test('updateValue with updatePending flag works correctly', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_test123' => Http::response([
            'id' => 'sub_test123',
            'value' => 149.90,
        ], 200),
    ]);

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

    $result = $subscription->updateValue(149.90, true);

    expect($subscription->fresh()->value)->toBe('149.90');
});

test('changeBillingType changes billing type', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_test123' => Http::response([
            'id' => 'sub_test123',
            'billingType' => 'BOLETO',
        ], 200),
    ]);

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

    $result = $subscription->changeBillingType(BillingType::BOLETO);

    expect($result)->toBeInstanceOf(Subscription::class);
    expect($subscription->fresh()->billing_type)->toBe('BOLETO');
});

test('updateCreditCard updates credit card', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_test123/creditCard' => Http::response([
            'id' => 'sub_test123',
            'creditCard' => ['creditCardBrand' => 'VISA'],
        ], 200),
    ]);

    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_test123',
        'asaas_status' => 'ACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'CREDIT_CARD',
        'next_due_date' => now()->addMonth(),
    ]);

    $result = $subscription->updateCreditCard(
        ['holderName' => 'Test', 'number' => '4242424242424242', 'expiryMonth' => '12', 'expiryYear' => '2030', 'ccv' => '123'],
        ['name' => 'Test', 'email' => 'test@test.com', 'cpfCnpj' => '12345678909'],
        '127.0.0.1'
    );

    expect($result)->toBeInstanceOf(Subscription::class);
});

test('updateCreditCardToken updates credit card with token', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_test123/creditCard' => Http::response([
            'id' => 'sub_test123',
        ], 200),
    ]);

    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_test123',
        'asaas_status' => 'ACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'CREDIT_CARD',
        'next_due_date' => now()->addMonth(),
    ]);

    $result = $subscription->updateCreditCardToken('cc_tok_123', '127.0.0.1');

    expect($result)->toBeInstanceOf(Subscription::class);
});

test('upcomingPayment returns upcoming payment', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_test123/payments*' => Http::response([
            'data' => [
                ['id' => 'pay_1', 'value' => 99.90, 'status' => 'PENDING'],
            ],
            'totalCount' => 1,
        ], 200),
    ]);

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

    $result = $subscription->upcomingPayment();

    expect($result['id'])->toBe('pay_1');
});

test('upcomingPayment returns null when no pending payments', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_test123/payments*' => Http::response([
            'data' => [],
            'totalCount' => 0,
        ], 200),
    ]);

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

    $result = $subscription->upcomingPayment();

    expect($result)->toBeNull();
});

test('asaasPayments returns payments from Asaas', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_test123/payments*' => Http::response([
            'data' => [
                ['id' => 'pay_1', 'status' => 'CONFIRMED'],
                ['id' => 'pay_2', 'status' => 'PENDING'],
            ],
            'totalCount' => 2,
        ], 200),
    ]);

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

    $result = $subscription->asaasPayments();

    expect($result['data'])->toHaveCount(2);
});

test('syncFromAsaas syncs subscription data', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_test123' => Http::response([
            'id' => 'sub_test123',
            'status' => 'INACTIVE',
            'value' => 199.90,
            'nextDueDate' => now()->addMonths(2)->format('Y-m-d'),
        ], 200),
    ]);

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

    $result = $subscription->syncFromAsaas();

    expect($result)->toBeInstanceOf(Subscription::class);
    expect($subscription->fresh()->asaas_status)->toBe('INACTIVE');
    expect($subscription->fresh()->value)->toBe('199.90');
});

test('asAsaasSubscription returns subscription data from Asaas', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_test123' => Http::response([
            'id' => 'sub_test123',
            'customer' => 'cus_test123',
            'value' => 99.90,
            'status' => 'ACTIVE',
            'billingType' => 'PIX',
        ], 200),
    ]);

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

    $result = $subscription->asAsaasSubscription();

    expect($result['id'])->toBe('sub_test123');
    expect($result['status'])->toBe('ACTIVE');
});
