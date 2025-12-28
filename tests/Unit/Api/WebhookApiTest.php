<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Api\WebhookApi;
use Fevinta\CashierAsaas\Asaas;
use Fevinta\CashierAsaas\Exceptions\AsaasApiException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('list returns webhooks successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/webhooks*' => Http::response([
            'data' => [
                ['id' => 'wh_123', 'url' => 'https://example.com/webhook'],
            ],
            'totalCount' => 1,
        ], 200),
    ]);

    $api = new WebhookApi();
    $result = $api->list();

    expect($result['data'])->toHaveCount(1);
    expect($result['data'][0]['id'])->toBe('wh_123');
});

test('list throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/webhooks*' => Http::response([
            'errors' => [['description' => 'Unauthorized', 'code' => 'UNAUTHORIZED']],
        ], 401),
    ]);

    $api = new WebhookApi();
    $api->list();
})->throws(AsaasApiException::class);

test('create creates webhook successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/webhooks' => Http::response([
            'id' => 'wh_123',
            'url' => 'https://example.com/webhook',
            'enabled' => true,
        ], 200),
    ]);

    $api = new WebhookApi();
    $result = $api->create([
        'url' => 'https://example.com/webhook',
        'email' => 'test@example.com',
        'enabled' => true,
    ]);

    expect($result['id'])->toBe('wh_123');
    expect($result['url'])->toBe('https://example.com/webhook');
});

test('create throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/webhooks' => Http::response([
            'errors' => [['description' => 'Invalid URL', 'code' => 'INVALID_URL']],
        ], 400),
    ]);

    $api = new WebhookApi();
    $api->create(['url' => 'invalid']);
})->throws(AsaasApiException::class);

test('find returns webhook by id', function () {
    Http::fake([
        Asaas::baseUrl().'/webhooks/wh_123' => Http::response([
            'id' => 'wh_123',
            'url' => 'https://example.com/webhook',
            'enabled' => true,
        ], 200),
    ]);

    $api = new WebhookApi();
    $result = $api->find('wh_123');

    expect($result['id'])->toBe('wh_123');
});

test('find throws exception when webhook not found', function () {
    Http::fake([
        Asaas::baseUrl().'/webhooks/wh_nonexistent' => Http::response([
            'errors' => [['description' => 'Not found', 'code' => 'NOT_FOUND']],
        ], 404),
    ]);

    $api = new WebhookApi();
    $api->find('wh_nonexistent');
})->throws(AsaasApiException::class);

test('update updates webhook successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/webhooks/wh_123' => Http::response([
            'id' => 'wh_123',
            'url' => 'https://example.com/webhook-updated',
            'enabled' => false,
        ], 200),
    ]);

    $api = new WebhookApi();
    $result = $api->update('wh_123', [
        'url' => 'https://example.com/webhook-updated',
        'enabled' => false,
    ]);

    expect($result['url'])->toBe('https://example.com/webhook-updated');
    expect($result['enabled'])->toBeFalse();
});

test('update throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/webhooks/wh_123' => Http::response([
            'errors' => [['description' => 'Invalid data', 'code' => 'INVALID_DATA']],
        ], 400),
    ]);

    $api = new WebhookApi();
    $api->update('wh_123', ['url' => 'invalid']);
})->throws(AsaasApiException::class);

test('delete deletes webhook successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/webhooks/wh_123' => Http::response([
            'deleted' => true,
        ], 200),
    ]);

    $api = new WebhookApi();
    $result = $api->delete('wh_123');

    expect($result)->toBeTrue();
});

test('delete throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/webhooks/wh_123' => Http::response([
            'errors' => [['description' => 'Cannot delete', 'code' => 'CANNOT_DELETE']],
        ], 400),
    ]);

    $api = new WebhookApi();
    $api->delete('wh_123');
})->throws(AsaasApiException::class);

test('queue returns webhook queue', function () {
    Http::fake([
        Asaas::baseUrl().'/webhooks/wh_123/queue*' => Http::response([
            'data' => [
                ['id' => 'event_1', 'event' => 'PAYMENT_CREATED'],
                ['id' => 'event_2', 'event' => 'PAYMENT_RECEIVED'],
            ],
            'totalCount' => 2,
        ], 200),
    ]);

    $api = new WebhookApi();
    $result = $api->queue('wh_123');

    expect($result['data'])->toHaveCount(2);
    expect($result['totalCount'])->toBe(2);
});

test('queue throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/webhooks/wh_123/queue*' => Http::response([
            'errors' => [['description' => 'Not found', 'code' => 'NOT_FOUND']],
        ], 404),
    ]);

    $api = new WebhookApi();
    $api->queue('wh_123');
})->throws(AsaasApiException::class);

test('resend resends webhook events successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/webhooks/wh_123/resend' => Http::response([
            'success' => true,
        ], 200),
    ]);

    $api = new WebhookApi();
    $result = $api->resend('wh_123');

    expect($result)->toBeTrue();
});

test('resend throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/webhooks/wh_123/resend' => Http::response([
            'errors' => [['description' => 'No events to resend', 'code' => 'NO_EVENTS']],
        ], 400),
    ]);

    $api = new WebhookApi();
    $api->resend('wh_123');
})->throws(AsaasApiException::class);
