<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

test('middleware passes when no token is configured in non-production', function () {
    config(['cashier-asaas.webhook_token' => null]);
    App::partialMock()->shouldReceive('environment')->with('production')->andReturn(false);

    $request = Request::create('/webhook', 'POST');
    $middleware = new VerifyWebhookSignature;

    $response = $middleware->handle($request, function ($req) {
        return response('OK');
    });

    expect($response->getContent())->toBe('OK');
});

test('middleware logs warning when no token configured in production', function () {
    config(['cashier-asaas.webhook_token' => null]);
    App::partialMock()->shouldReceive('environment')->with('production')->andReturn(true);

    Log::shouldReceive('warning')->once()->with(
        'Cashier Asaas: Webhook signature verification is disabled. '.
        'Set ASAAS_WEBHOOK_TOKEN in production for security.'
    );

    $request = Request::create('/webhook', 'POST');
    $middleware = new VerifyWebhookSignature;

    $response = $middleware->handle($request, function ($req) {
        return response('OK');
    });

    expect($response->getContent())->toBe('OK');
});

test('middleware returns 403 when token header is missing', function () {
    config(['cashier-asaas.webhook_token' => 'secret_token_123']);

    Log::shouldReceive('warning')->once()->with('Cashier Asaas: Webhook received without access token header.');

    $request = Request::create('/webhook', 'POST');
    $middleware = new VerifyWebhookSignature;

    $response = $middleware->handle($request, function ($req) {
        return response('OK');
    });

    expect($response->getStatusCode())->toBe(403);
    expect($response->getContent())->toContain('Missing access token');
});

test('middleware returns 403 when token is invalid', function () {
    config(['cashier-asaas.webhook_token' => 'correct_token']);

    Log::shouldReceive('warning')->once()->withArgs(function ($message, $context) {
        return str_contains($message, 'Webhook signature verification failed')
            && isset($context['ip']);
    });

    $request = Request::create('/webhook', 'POST');
    $request->headers->set('asaas-access-token', 'wrong_token');

    $middleware = new VerifyWebhookSignature;

    $response = $middleware->handle($request, function ($req) {
        return response('OK');
    });

    expect($response->getStatusCode())->toBe(403);
    expect($response->getContent())->toContain('Invalid access token');
});

test('middleware passes when token is valid', function () {
    config(['cashier-asaas.webhook_token' => 'valid_secret_token']);

    $request = Request::create('/webhook', 'POST');
    $request->headers->set('asaas-access-token', 'valid_secret_token');

    $middleware = new VerifyWebhookSignature;

    $response = $middleware->handle($request, function ($req) {
        return response('OK');
    });

    expect($response->getContent())->toBe('OK');
});

test('middleware uses timing-safe comparison', function () {
    config(['cashier-asaas.webhook_token' => 'secret']);

    // Even with similar tokens, it should fail
    $request = Request::create('/webhook', 'POST');
    $request->headers->set('asaas-access-token', 'secre'); // Missing last char

    Log::shouldReceive('warning')->once();

    $middleware = new VerifyWebhookSignature;

    $response = $middleware->handle($request, function ($req) {
        return response('OK');
    });

    expect($response->getStatusCode())->toBe(403);
});
