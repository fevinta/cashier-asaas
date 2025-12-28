<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Events\WebhookHandled;
use Fevinta\CashierAsaas\Events\WebhookReceived;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake();
});

test('webhook endpoint returns 200', function () {
    $response = $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_CREATED',
        'payment' => [
            'id' => 'pay_test123',
            'customer' => 'cus_test123',
            'value' => 99.90,
            'status' => 'PENDING',
        ],
    ]);

    $response->assertStatus(200);
    $response->assertSee('Webhook handled');
});

test('webhook dispatches received event', function () {
    $payload = [
        'event' => 'PAYMENT_CREATED',
        'payment' => [
            'id' => 'pay_test123',
        ],
    ];

    $this->postJson('/asaas/webhook', $payload);

    Event::assertDispatched(WebhookReceived::class, function ($event) use ($payload) {
        return $event->payload['event'] === $payload['event'];
    });
});

test('webhook dispatches handled event after processing', function () {
    $payload = [
        'event' => 'PAYMENT_RECEIVED',
        'payment' => [
            'id' => 'pay_test123',
            'status' => 'RECEIVED',
        ],
    ];

    $this->postJson('/asaas/webhook', $payload);

    Event::assertDispatched(WebhookHandled::class, function ($event) use ($payload) {
        return $event->payload['event'] === $payload['event'];
    });
});

test('unknown event type returns 200', function () {
    $response = $this->postJson('/asaas/webhook', [
        'event' => 'UNKNOWN_EVENT_TYPE',
        'data' => ['foo' => 'bar'],
    ]);

    $response->assertStatus(200);
});

test('malformed payload returns 400', function () {
    $response = $this->postJson('/asaas/webhook', [
        // Missing 'event' key
        'data' => ['foo' => 'bar'],
    ]);

    $response->assertStatus(400);
    $response->assertSee('Missing event type');
});

test('webhook signature verification passes with correct token', function () {
    config(['cashier-asaas.webhook_token' => 'secret_token_123']);

    $response = $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_CREATED',
        'payment' => ['id' => 'pay_test'],
    ], [
        'asaas-access-token' => 'secret_token_123',
    ]);

    $response->assertStatus(200);
});

test('webhook signature verification fails with incorrect token', function () {
    config(['cashier-asaas.webhook_token' => 'secret_token_123']);

    $response = $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_CREATED',
        'payment' => ['id' => 'pay_test'],
    ], [
        'asaas-access-token' => 'wrong_token',
    ]);

    $response->assertStatus(403);
});

test('webhook signature verification fails with missing token', function () {
    config(['cashier-asaas.webhook_token' => 'secret_token_123']);

    $response = $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_CREATED',
        'payment' => ['id' => 'pay_test'],
    ]);

    $response->assertStatus(403);
});

test('webhook verification is skipped when no token configured', function () {
    config(['cashier-asaas.webhook_token' => null]);

    $response = $this->postJson('/asaas/webhook', [
        'event' => 'PAYMENT_CREATED',
        'payment' => ['id' => 'pay_test'],
    ]);

    $response->assertStatus(200);
});
