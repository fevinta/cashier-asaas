<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Api\SubscriptionApi;
use Fevinta\CashierAsaas\Asaas;
use Fevinta\CashierAsaas\Exceptions\AsaasApiException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('create creates subscription successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions' => Http::response([
            'id' => 'sub_123',
            'customer' => 'cus_123',
            'billingType' => 'PIX',
            'value' => 99.90,
            'cycle' => 'MONTHLY',
            'status' => 'ACTIVE',
            'nextDueDate' => '2024-12-31',
        ], 200),
    ]);

    $api = new SubscriptionApi();
    $result = $api->create([
        'customer' => 'cus_123',
        'billingType' => 'PIX',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'nextDueDate' => '2024-12-31',
    ]);

    expect($result['id'])->toBe('sub_123');
    expect($result['status'])->toBe('ACTIVE');
});

test('create throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions' => Http::response([
            'errors' => [['description' => 'Invalid customer', 'code' => 'INVALID_CUSTOMER']],
        ], 400),
    ]);

    $api = new SubscriptionApi();
    $api->create(['customer' => 'invalid']);
})->throws(AsaasApiException::class);

test('find returns subscription by id', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_123' => Http::response([
            'id' => 'sub_123',
            'customer' => 'cus_123',
            'value' => 99.90,
            'status' => 'ACTIVE',
        ], 200),
    ]);

    $api = new SubscriptionApi();
    $result = $api->find('sub_123');

    expect($result['id'])->toBe('sub_123');
    expect($result['status'])->toBe('ACTIVE');
});

test('find throws exception when subscription not found', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_nonexistent' => Http::response([
            'errors' => [['description' => 'Subscription not found', 'code' => 'NOT_FOUND']],
        ], 404),
    ]);

    $api = new SubscriptionApi();
    $api->find('sub_nonexistent');
})->throws(AsaasApiException::class);

test('update updates subscription successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_123' => Http::response([
            'id' => 'sub_123',
            'value' => 150.00,
            'description' => 'Updated subscription',
        ], 200),
    ]);

    $api = new SubscriptionApi();
    $result = $api->update('sub_123', [
        'value' => 150.00,
        'description' => 'Updated subscription',
    ]);

    expect($result['value'])->toBe(150);
});

test('update throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_123' => Http::response([
            'errors' => [['description' => 'Cannot update cancelled subscription', 'code' => 'CANNOT_UPDATE']],
        ], 400),
    ]);

    $api = new SubscriptionApi();
    $api->update('sub_123', ['value' => 100]);
})->throws(AsaasApiException::class);

test('delete cancels subscription successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_123' => Http::response([
            'deleted' => true,
        ], 200),
    ]);

    $api = new SubscriptionApi();
    $result = $api->delete('sub_123');

    expect($result)->toBeTrue();
});

test('delete throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_123' => Http::response([
            'errors' => [['description' => 'Subscription already cancelled', 'code' => 'ALREADY_CANCELLED']],
        ], 400),
    ]);

    $api = new SubscriptionApi();
    $api->delete('sub_123');
})->throws(AsaasApiException::class);

test('list returns subscriptions with pagination', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions*' => Http::response([
            'data' => [
                ['id' => 'sub_1', 'value' => 99.90, 'status' => 'ACTIVE'],
                ['id' => 'sub_2', 'value' => 150.00, 'status' => 'INACTIVE'],
            ],
            'totalCount' => 2,
            'limit' => 10,
            'offset' => 0,
            'hasMore' => false,
        ], 200),
    ]);

    $api = new SubscriptionApi();
    $result = $api->list();

    expect($result['data'])->toHaveCount(2);
    expect($result['totalCount'])->toBe(2);
});

test('list throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions*' => Http::response([
            'errors' => [['description' => 'Unauthorized', 'code' => 'UNAUTHORIZED']],
        ], 401),
    ]);

    $api = new SubscriptionApi();
    $api->list();
})->throws(AsaasApiException::class);

test('payments returns subscription payments', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_123/payments*' => Http::response([
            'data' => [
                ['id' => 'pay_1', 'value' => 99.90, 'status' => 'PENDING'],
                ['id' => 'pay_2', 'value' => 99.90, 'status' => 'CONFIRMED'],
            ],
            'totalCount' => 2,
        ], 200),
    ]);

    $api = new SubscriptionApi();
    $result = $api->payments('sub_123');

    expect($result['data'])->toHaveCount(2);
});

test('payments with filters works correctly', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_123/payments*' => Http::response([
            'data' => [
                ['id' => 'pay_1', 'value' => 99.90, 'status' => 'PENDING'],
            ],
            'totalCount' => 1,
        ], 200),
    ]);

    $api = new SubscriptionApi();
    $result = $api->payments('sub_123', ['status' => 'PENDING']);

    expect($result['data'])->toHaveCount(1);
});

test('payments throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_123/payments*' => Http::response([
            'errors' => [['description' => 'Subscription not found', 'code' => 'NOT_FOUND']],
        ], 404),
    ]);

    $api = new SubscriptionApi();
    $api->payments('sub_123');
})->throws(AsaasApiException::class);

test('updateCreditCard updates subscription credit card', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_123/creditCard' => Http::response([
            'id' => 'sub_123',
            'creditCard' => [
                'creditCardBrand' => 'VISA',
                'creditCardNumber' => '4242',
            ],
        ], 200),
    ]);

    $api = new SubscriptionApi();
    $result = $api->updateCreditCard('sub_123', [
        'creditCard' => [
            'holderName' => 'Test User',
            'number' => '4242424242424242',
            'expiryMonth' => '12',
            'expiryYear' => '2030',
            'ccv' => '123',
        ],
        'creditCardHolderInfo' => [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'cpfCnpj' => '12345678909',
        ],
        'remoteIp' => '127.0.0.1',
    ]);

    expect($result['creditCard']['creditCardBrand'])->toBe('VISA');
});

test('updateCreditCard throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_123/creditCard' => Http::response([
            'errors' => [['description' => 'Invalid card', 'code' => 'INVALID_CARD']],
        ], 400),
    ]);

    $api = new SubscriptionApi();
    $api->updateCreditCard('sub_123', ['creditCard' => ['number' => 'invalid']]);
})->throws(AsaasApiException::class);

test('invoices returns subscription invoices (alias for payments)', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_123/payments*' => Http::response([
            'data' => [
                ['id' => 'pay_1', 'value' => 99.90],
            ],
            'totalCount' => 1,
        ], 200),
    ]);

    $api = new SubscriptionApi();
    $result = $api->invoices('sub_123');

    expect($result['data'])->toHaveCount(1);
});

test('findByCustomer returns subscriptions for a customer', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions*' => Http::response([
            'data' => [
                ['id' => 'sub_1', 'customer' => 'cus_123', 'status' => 'ACTIVE'],
                ['id' => 'sub_2', 'customer' => 'cus_123', 'status' => 'INACTIVE'],
            ],
            'totalCount' => 2,
        ], 200),
    ]);

    $api = new SubscriptionApi();
    $result = $api->findByCustomer('cus_123');

    expect($result['data'])->toHaveCount(2);
    expect($result['data'][0]['customer'])->toBe('cus_123');
});

test('findByCustomer returns empty when customer has no subscriptions', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions*' => Http::response([
            'data' => [],
            'totalCount' => 0,
        ], 200),
    ]);

    $api = new SubscriptionApi();
    $result = $api->findByCustomer('cus_new');

    expect($result['data'])->toBeEmpty();
    expect($result['totalCount'])->toBe(0);
});
