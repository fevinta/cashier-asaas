<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Api\CheckoutApi;
use Fevinta\CashierAsaas\Asaas;
use Fevinta\CashierAsaas\Exceptions\AsaasApiException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('create creates checkout successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_123',
            'status' => 'ACTIVE',
            'chargeType' => 'DETACHED',
            'billingTypes' => ['PIX', 'CREDIT_CARD', 'BOLETO'],
        ], 200),
    ]);

    $api = new CheckoutApi;
    $result = $api->create([
        'chargeType' => 'DETACHED',
        'billingTypes' => ['PIX', 'CREDIT_CARD', 'BOLETO'],
        'items' => [
            ['name' => 'Product', 'value' => 99.90, 'quantity' => 1],
        ],
    ]);

    expect($result['id'])->toBe('checkout_123');
    expect($result['status'])->toBe('ACTIVE');
});

test('create throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/checkouts' => Http::response([
            'errors' => [['description' => 'Invalid charge type', 'code' => 'INVALID_CHARGE_TYPE']],
        ], 400),
    ]);

    $api = new CheckoutApi;
    $api->create(['chargeType' => 'invalid']);
})->throws(AsaasApiException::class);

test('find returns checkout by id', function () {
    Http::fake([
        Asaas::baseUrl().'/checkouts/checkout_123' => Http::response([
            'id' => 'checkout_123',
            'status' => 'ACTIVE',
            'chargeType' => 'DETACHED',
        ], 200),
    ]);

    $api = new CheckoutApi;
    $result = $api->find('checkout_123');

    expect($result['id'])->toBe('checkout_123');
    expect($result['status'])->toBe('ACTIVE');
});

test('find throws exception when checkout not found', function () {
    Http::fake([
        Asaas::baseUrl().'/checkouts/checkout_nonexistent' => Http::response([
            'errors' => [['description' => 'Checkout not found', 'code' => 'NOT_FOUND']],
        ], 404),
    ]);

    $api = new CheckoutApi;
    $api->find('checkout_nonexistent');
})->throws(AsaasApiException::class);

test('cancel cancels checkout successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/checkouts/checkout_123' => Http::response([
            'deleted' => true,
        ], 200),
    ]);

    $api = new CheckoutApi;
    $result = $api->cancel('checkout_123');

    expect($result)->toBeTrue();
});

test('cancel throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/checkouts/checkout_123' => Http::response([
            'errors' => [['description' => 'Cannot cancel checkout', 'code' => 'CANNOT_CANCEL']],
        ], 400),
    ]);

    $api = new CheckoutApi;
    $api->cancel('checkout_123');
})->throws(AsaasApiException::class);

test('list returns checkouts with pagination', function () {
    Http::fake([
        Asaas::baseUrl().'/checkouts*' => Http::response([
            'data' => [
                ['id' => 'checkout_1', 'status' => 'ACTIVE'],
                ['id' => 'checkout_2', 'status' => 'PAID'],
            ],
            'totalCount' => 2,
            'limit' => 10,
            'offset' => 0,
            'hasMore' => false,
        ], 200),
    ]);

    $api = new CheckoutApi;
    $result = $api->list();

    expect($result['data'])->toHaveCount(2);
    expect($result['totalCount'])->toBe(2);
});

test('list throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/checkouts*' => Http::response([
            'errors' => [['description' => 'Unauthorized', 'code' => 'UNAUTHORIZED']],
        ], 401),
    ]);

    $api = new CheckoutApi;
    $api->list();
})->throws(AsaasApiException::class);

test('status returns checkout status', function () {
    Http::fake([
        Asaas::baseUrl().'/checkouts/checkout_123' => Http::response([
            'id' => 'checkout_123',
            'status' => 'PAID',
        ], 200),
    ]);

    $api = new CheckoutApi;
    $result = $api->status('checkout_123');

    expect($result)->toBe('PAID');
});
