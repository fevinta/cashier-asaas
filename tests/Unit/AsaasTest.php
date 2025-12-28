<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Api\CustomerApi;
use Fevinta\CashierAsaas\Api\PaymentApi;
use Fevinta\CashierAsaas\Api\SubscriptionApi;
use Fevinta\CashierAsaas\Api\WebhookApi;
use Fevinta\CashierAsaas\Asaas;

beforeEach(function () {
    // Reset static properties
    $reflection = new ReflectionClass(Asaas::class);
    $apiKeyProp = $reflection->getProperty('apiKey');
    $apiKeyProp->setAccessible(true);
    $apiKeyProp->setValue(null, null);

    $sandboxProp = $reflection->getProperty('sandbox');
    $sandboxProp->setAccessible(true);
    $sandboxProp->setValue(null, false);
});

test('setApiKey sets the API key', function () {
    Asaas::setApiKey('test_key_123');

    expect(Asaas::apiKey())->toBe('test_key_123');
});

test('useSandbox enables sandbox mode', function () {
    config(['cashier-asaas.sandbox' => false]);

    Asaas::useSandbox(true);

    expect(Asaas::baseUrl())->toBe('https://sandbox.asaas.com/api/v3');
});

test('useSandbox disables sandbox mode', function () {
    Asaas::useSandbox(true);
    Asaas::useSandbox(false);

    config(['cashier-asaas.sandbox' => false]);

    expect(Asaas::baseUrl())->toBe('https://api.asaas.com/v3');
});

test('baseUrl returns sandbox URL when sandbox config is true', function () {
    config(['cashier-asaas.sandbox' => true]);

    expect(Asaas::baseUrl())->toBe('https://sandbox.asaas.com/api/v3');
});

test('baseUrl returns production URL when sandbox is false', function () {
    config(['cashier-asaas.sandbox' => false]);

    expect(Asaas::baseUrl())->toBe('https://api.asaas.com/v3');
});

test('apiKey returns config value when not set manually', function () {
    config(['cashier-asaas.api_key' => 'config_key_456']);

    expect(Asaas::apiKey())->toBe('config_key_456');
});

test('customer returns CustomerApi instance', function () {
    expect(Asaas::customer())->toBeInstanceOf(CustomerApi::class);
});

test('subscription returns SubscriptionApi instance', function () {
    expect(Asaas::subscription())->toBeInstanceOf(SubscriptionApi::class);
});

test('payment returns PaymentApi instance', function () {
    expect(Asaas::payment())->toBeInstanceOf(PaymentApi::class);
});

test('webhook returns WebhookApi instance', function () {
    expect(Asaas::webhook())->toBeInstanceOf(WebhookApi::class);
});

test('client returns configured HTTP client', function () {
    $client = Asaas::client();

    expect($client)->toBeInstanceOf(\Illuminate\Http\Client\PendingRequest::class);
});
