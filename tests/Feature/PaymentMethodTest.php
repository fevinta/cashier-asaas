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

test('user can tokenize credit card', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response(
            AsaasApiFixtures::customer(['id' => 'cus_test123']),
            200
        ),
        Asaas::baseUrl().'/creditCard/tokenize' => Http::response(
            AsaasApiFixtures::creditCardToken([
                'creditCardToken' => 'cc_tok_abc123',
                'creditCardNumber' => '4242',
                'creditCardBrand' => 'VISA',
            ]),
            200
        ),
    ]);

    $token = $this->user->tokenizeCreditCard(
        [
            'holderName' => 'John Doe',
            'number' => '4242424242424242',
            'expiryMonth' => '12',
            'expiryYear' => '2030',
            'ccv' => '123',
        ],
        [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'cpfCnpj' => '12345678909',
            'postalCode' => '01310100',
            'addressNumber' => '123',
            'phone' => '11999998888',
        ],
        '192.168.1.1'
    );

    expect($token)->toBe('cc_tok_abc123');
});

test('user has payment method check when subscription has credit card', function () {
    Subscription::create([
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

    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_test123' => Http::response(
            AsaasApiFixtures::subscription([
                'id' => 'sub_test123',
                'billingType' => 'CREDIT_CARD',
                'creditCard' => [
                    'creditCardNumber' => '4242',
                    'creditCardBrand' => 'VISA',
                ],
            ]),
            200
        ),
    ]);

    expect($this->user->hasPaymentMethod())->toBeTrue();
});

test('user does not have payment method when no credit card subscription', function () {
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

    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_test123' => Http::response(
            AsaasApiFixtures::subscription([
                'id' => 'sub_test123',
                'billingType' => 'PIX',
            ]),
            200
        ),
    ]);

    expect($this->user->hasPaymentMethod())->toBeFalse();
});

test('user can get default payment method', function () {
    Subscription::create([
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

    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_test123' => Http::response(
            AsaasApiFixtures::subscription([
                'id' => 'sub_test123',
                'billingType' => 'CREDIT_CARD',
                'creditCard' => [
                    'creditCardNumber' => '4242',
                    'creditCardBrand' => 'VISA',
                ],
            ]),
            200
        ),
    ]);

    $paymentMethod = $this->user->defaultPaymentMethod();

    expect($paymentMethod)->toBeArray();
    expect($paymentMethod['type'])->toBe('credit_card');
    expect($paymentMethod['last4'])->toBe('4242');
    expect($paymentMethod['brand'])->toBe('VISA');
});

test('user can update default payment method from token', function () {
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

    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_test123/creditCard' => Http::response(
            AsaasApiFixtures::subscription([
                'id' => 'sub_test123',
                'billingType' => 'CREDIT_CARD',
            ]),
            200
        ),
    ]);

    $this->user->updateDefaultPaymentMethodFromToken('cc_tok_new123', '192.168.1.1');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/creditCard')
            && $request['creditCardToken'] === 'cc_tok_new123';
    });
});

test('tokenize credit card sends correct data', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response(
            AsaasApiFixtures::customer(['id' => 'cus_test123']),
            200
        ),
        Asaas::baseUrl().'/creditCard/tokenize' => Http::response(
            AsaasApiFixtures::creditCardToken(),
            200
        ),
    ]);

    $this->user->tokenizeCreditCard(
        [
            'holderName' => 'Jane Doe',
            'number' => '5555555555554444',
            'expiryMonth' => '06',
            'expiryYear' => '2028',
            'ccv' => '456',
        ],
        [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'cpfCnpj' => '98765432100',
            'postalCode' => '04567890',
            'addressNumber' => '456',
            'phone' => '11888887777',
        ],
        '10.0.0.1'
    );

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/creditCard/tokenize')
            && $request['creditCard']['holderName'] === 'Jane Doe'
            && $request['creditCardHolderInfo']['name'] === 'Jane Doe'
            && $request['remoteIp'] === '10.0.0.1';
    });
});
