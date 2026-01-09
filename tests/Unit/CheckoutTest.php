<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Checkout;
use Fevinta\CashierAsaas\CheckoutBuilder;
use Fevinta\CashierAsaas\Tests\Fixtures\User;
use Illuminate\Http\RedirectResponse;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'cpf_cnpj' => generateTestCpf(),
        'asaas_id' => 'cus_test123',
    ]);
});

test('constructor sets id, url, and session', function () {
    $checkout = new Checkout(
        'checkout_123',
        'https://asaas.com/checkoutSession/show?id=checkout_123',
        ['status' => 'ACTIVE', 'chargeType' => 'DETACHED']
    );

    expect($checkout->id())->toBe('checkout_123');
    expect($checkout->url())->toBe('https://asaas.com/checkoutSession/show?id=checkout_123');
    expect($checkout->session())->toBe(['status' => 'ACTIVE', 'chargeType' => 'DETACHED']);
});

test('status returns session status', function () {
    $checkout = new Checkout(
        'checkout_123',
        'https://asaas.com/checkoutSession/show?id=checkout_123',
        ['status' => 'ACTIVE']
    );

    expect($checkout->status())->toBe('ACTIVE');
});

test('status returns null when not set', function () {
    $checkout = new Checkout(
        'checkout_123',
        'https://asaas.com/checkoutSession/show?id=checkout_123',
        []
    );

    expect($checkout->status())->toBeNull();
});

test('guest returns CheckoutBuilder', function () {
    $builder = Checkout::guest();

    expect($builder)->toBeInstanceOf(CheckoutBuilder::class);
});

test('customer returns CheckoutBuilder with owner', function () {
    $builder = Checkout::customer($this->user);

    expect($builder)->toBeInstanceOf(CheckoutBuilder::class);
});

test('redirect returns RedirectResponse', function () {
    $checkout = new Checkout(
        'checkout_123',
        'https://asaas.com/checkoutSession/show?id=checkout_123',
        []
    );

    $response = $checkout->redirect();

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->getStatusCode())->toBe(303);
    expect($response->getTargetUrl())->toBe('https://asaas.com/checkoutSession/show?id=checkout_123');
});

test('formatUrl creates correct sandbox URL', function () {
    config(['cashier-asaas.sandbox' => true]);

    $url = Checkout::formatUrl('checkout_123');

    expect($url)->toBe('https://sandbox.asaas.com/checkoutSession/show?id=checkout_123');
});

test('formatUrl creates correct production URL', function () {
    config(['cashier-asaas.sandbox' => false]);

    $url = Checkout::formatUrl('checkout_123');

    expect($url)->toBe('https://asaas.com/checkoutSession/show?id=checkout_123');
});

test('toArray returns correct structure', function () {
    $checkout = new Checkout(
        'checkout_123',
        'https://asaas.com/checkoutSession/show?id=checkout_123',
        ['status' => 'ACTIVE']
    );

    $array = $checkout->toArray();

    expect($array)->toBe([
        'id' => 'checkout_123',
        'url' => 'https://asaas.com/checkoutSession/show?id=checkout_123',
        'session' => ['status' => 'ACTIVE'],
    ]);
});

test('toJson returns JSON string', function () {
    $checkout = new Checkout(
        'checkout_123',
        'https://asaas.com/checkoutSession/show?id=checkout_123',
        ['status' => 'ACTIVE']
    );

    $json = $checkout->toJson();

    expect($json)->toBeString();
    expect(json_decode($json, true)['id'])->toBe('checkout_123');
});

test('jsonSerialize returns array', function () {
    $checkout = new Checkout(
        'checkout_123',
        'https://asaas.com/checkoutSession/show?id=checkout_123',
        ['status' => 'ACTIVE']
    );

    $data = $checkout->jsonSerialize();

    expect($data)->toBeArray();
    expect($data['id'])->toBe('checkout_123');
});

test('toResponse returns redirect response', function () {
    $checkout = new Checkout(
        'checkout_123',
        'https://asaas.com/checkoutSession/show?id=checkout_123',
        []
    );

    $response = $checkout->toResponse(request());

    expect($response)->toBeInstanceOf(RedirectResponse::class);
});

test('magic get returns session property', function () {
    $checkout = new Checkout(
        'checkout_123',
        'https://asaas.com/checkoutSession/show?id=checkout_123',
        ['status' => 'ACTIVE', 'chargeType' => 'DETACHED']
    );

    expect($checkout->status)->toBe('ACTIVE');
    expect($checkout->chargeType)->toBe('DETACHED');
});

test('magic get returns null for missing property', function () {
    $checkout = new Checkout(
        'checkout_123',
        'https://asaas.com/checkoutSession/show?id=checkout_123',
        []
    );

    expect($checkout->nonexistent)->toBeNull();
});

test('magic isset checks session property', function () {
    $checkout = new Checkout(
        'checkout_123',
        'https://asaas.com/checkoutSession/show?id=checkout_123',
        ['status' => 'ACTIVE']
    );

    expect(isset($checkout->status))->toBeTrue();
    expect(isset($checkout->nonexistent))->toBeFalse();
});
