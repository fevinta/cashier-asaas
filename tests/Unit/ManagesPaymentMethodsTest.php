<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Asaas;
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

test('updateDefaultPaymentMethod updates active credit card subscriptions', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_cc_1/creditCard' => Http::response([
            'id' => 'sub_cc_1',
        ], 200),
        Asaas::baseUrl().'/subscriptions/sub_cc_2/creditCard' => Http::response([
            'id' => 'sub_cc_2',
        ], 200),
    ]);

    // Create credit card subscriptions
    Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_cc_1',
        'asaas_status' => 'ACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'CREDIT_CARD',
        'next_due_date' => now()->addMonth(),
    ]);

    Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'addon',
        'asaas_id' => 'sub_cc_2',
        'asaas_status' => 'ACTIVE',
        'plan' => 'storage',
        'value' => 19.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'CREDIT_CARD',
        'next_due_date' => now()->addMonth(),
    ]);

    // Create a PIX subscription (should not be updated)
    Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'secondary',
        'asaas_id' => 'sub_pix',
        'asaas_status' => 'ACTIVE',
        'plan' => 'basic',
        'value' => 29.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addMonth(),
    ]);

    $result = $this->user->updateDefaultPaymentMethod(
        ['holderName' => 'New Card', 'number' => '5555555555554444', 'expiryMonth' => '12', 'expiryYear' => '2030', 'ccv' => '456'],
        ['name' => 'Test User', 'email' => 'test@test.com', 'cpfCnpj' => '12345678909'],
        '127.0.0.1'
    );

    expect($result)->toBe($this->user);

    // Assert only credit card subscriptions were updated
    Http::assertSentCount(2);
});

test('updateDefaultPaymentMethodFromToken updates active credit card subscriptions', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_cc_1/creditCard' => Http::response([
            'id' => 'sub_cc_1',
        ], 200),
    ]);

    Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_cc_1',
        'asaas_status' => 'ACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'CREDIT_CARD',
        'next_due_date' => now()->addMonth(),
    ]);

    $result = $this->user->updateDefaultPaymentMethodFromToken('cc_tok_new_123', '127.0.0.1');

    expect($result)->toBe($this->user);
});

test('updateDefaultPaymentMethod does not update cancelled subscriptions', function () {
    Http::fake();

    // Create a cancelled credit card subscription (has ends_at set)
    Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_cancelled',
        'asaas_status' => 'ACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'CREDIT_CARD',
        'next_due_date' => now()->addMonth(),
        'ends_at' => now()->addWeek(),
    ]);

    $result = $this->user->updateDefaultPaymentMethod(
        ['holderName' => 'Test', 'number' => '5555555555554444', 'expiryMonth' => '12', 'expiryYear' => '2030', 'ccv' => '456'],
        ['name' => 'Test User', 'email' => 'test@test.com', 'cpfCnpj' => '12345678909']
    );

    expect($result)->toBe($this->user);
    Http::assertNothingSent();
});
