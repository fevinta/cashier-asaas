<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Asaas;
use Fevinta\CashierAsaas\Checkout;
use Fevinta\CashierAsaas\CheckoutBuilder;
use Fevinta\CashierAsaas\Tests\Fixtures\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'cpf_cnpj' => generateTestCpf(),
        'asaas_id' => 'cus_test123',
    ]);
});

test('newCheckout returns CheckoutBuilder with user as owner', function () {
    $builder = $this->user->newCheckout();

    expect($builder)->toBeInstanceOf(CheckoutBuilder::class);

    // Verify the owner is set correctly via reflection
    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('owner');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe($this->user);
});

test('checkout creates checkout session with items', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response([
            'id' => 'cus_test123',
            'name' => 'Test User',
        ], 200),
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_123',
            'status' => 'ACTIVE',
            'chargeType' => 'DETACHED',
        ], 200),
    ]);

    $checkout = $this->user->checkout([
        ['name' => 'Product 1', 'value' => 50.00, 'quantity' => 2],
        ['name' => 'Product 2', 'value' => 100.00],
    ]);

    expect($checkout)->toBeInstanceOf(Checkout::class);
    expect($checkout->id())->toBe('checkout_123');
    expect($checkout->status())->toBe('ACTIVE');

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/checkouts')) {
            $body = json_decode($request->body(), true);

            return $body['customer'] === 'cus_test123'
                && count($body['items']) === 2
                && $body['items'][0]['name'] === 'Product 1'
                && $body['items'][0]['value'] === 50.00
                && $body['items'][0]['quantity'] === 2
                && $body['items'][1]['name'] === 'Product 2'
                && $body['items'][1]['value'] === 100.00
                && $body['items'][1]['quantity'] === 1;
        }

        return true;
    });
});

test('checkout passes session options to builder', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response([
            'id' => 'cus_test123',
        ], 200),
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_456',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    $checkout = $this->user->checkout(
        [['name' => 'Product', 'value' => 99.90]],
        ['expirationMinutes' => 30]
    );

    expect($checkout->id())->toBe('checkout_456');

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/checkouts')) {
            $body = json_decode($request->body(), true);

            return ($body['expirationMinutes'] ?? null) === 30;
        }

        return true;
    });
});

test('checkoutCharge creates checkout with single charge', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response([
            'id' => 'cus_test123',
        ], 200),
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_789',
            'status' => 'ACTIVE',
            'chargeType' => 'DETACHED',
        ], 200),
    ]);

    $checkout = $this->user->checkoutCharge(199.90, 'Premium Upgrade');

    expect($checkout)->toBeInstanceOf(Checkout::class);
    expect($checkout->id())->toBe('checkout_789');

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/checkouts')) {
            $body = json_decode($request->body(), true);

            return $body['customer'] === 'cus_test123'
                && count($body['items']) === 1
                && $body['items'][0]['name'] === 'Premium Upgrade'
                && $body['items'][0]['value'] === 199.90
                && $body['items'][0]['quantity'] === 1;
        }

        return true;
    });
});

test('checkoutCharge with quantity creates correct item', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response([
            'id' => 'cus_test123',
        ], 200),
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_qty',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    $checkout = $this->user->checkoutCharge(25.00, 'Widget', 5);

    expect($checkout->id())->toBe('checkout_qty');

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/checkouts')) {
            $body = json_decode($request->body(), true);

            return $body['items'][0]['quantity'] === 5
                && $body['items'][0]['value'] === 25.00;
        }

        return true;
    });
});

test('checkoutCharge passes session options', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response([
            'id' => 'cus_test123',
        ], 200),
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_opts',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    $checkout = $this->user->checkoutCharge(
        100.00,
        'Product',
        1,
        ['externalReference' => 'order_123']
    );

    expect($checkout->id())->toBe('checkout_opts');

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/checkouts')) {
            $body = json_decode($request->body(), true);

            return ($body['externalReference'] ?? null) === 'order_123';
        }

        return true;
    });
});

test('checkout uses customer asaas_id', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response([
            'id' => 'cus_test123',
        ], 200),
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_cust',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    $this->user->checkout([['name' => 'Test', 'value' => 10.00]]);

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/checkouts')) {
            $body = json_decode($request->body(), true);

            return $body['customer'] === 'cus_test123';
        }

        return true;
    });
});

test('checkout creates customer if not exists', function () {
    $userWithoutAsaasId = User::create([
        'name' => 'New User',
        'email' => 'new@example.com',
        'cpf_cnpj' => generateTestCpf(),
        'asaas_id' => null,
    ]);

    Http::fake([
        Asaas::baseUrl().'/customers' => Http::response([
            'id' => 'cus_new123',
            'name' => 'New User',
        ], 200),
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_new',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    $checkout = $userWithoutAsaasId->checkout([['name' => 'Test', 'value' => 10.00]]);

    expect($checkout->id())->toBe('checkout_new');

    // Verify customer was created
    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/customers')
            && $request->method() === 'POST';
    });
});

test('newCheckout allows fluent API building', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response([
            'id' => 'cus_test123',
        ], 200),
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_fluent',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    $checkout = $this->user->newCheckout()
        ->addItem('Product 1', 50.00)
        ->addItem('Product 2', 75.00, 2)
        ->onlyPix()
        ->successUrl('https://example.com/success')
        ->expiresIn(60)
        ->create();

    expect($checkout)->toBeInstanceOf(Checkout::class);
    expect($checkout->id())->toBe('checkout_fluent');

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/checkouts')) {
            $body = json_decode($request->body(), true);

            return count($body['items']) === 2
                && $body['billingTypes'] === ['PIX']
                && $body['callback']['successUrl'] === 'https://example.com/success'
                && $body['expirationMinutes'] === 60;
        }

        return true;
    });
});

test('checkout returns Checkout instance with correct url', function () {
    config(['cashier-asaas.sandbox' => true]);

    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response([
            'id' => 'cus_test123',
        ], 200),
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_url_test',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    $checkout = $this->user->checkout([['name' => 'Test', 'value' => 10.00]]);

    expect($checkout->url())->toBe('https://sandbox.asaas.com/checkoutSession/show?id=checkout_url_test');
});

test('checkout can redirect to checkout page', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response([
            'id' => 'cus_test123',
        ], 200),
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_redirect',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    $checkout = $this->user->checkout([['name' => 'Test', 'value' => 10.00]]);
    $redirect = $checkout->redirect();

    expect($redirect)->toBeInstanceOf(\Illuminate\Http\RedirectResponse::class);
    expect($redirect->getStatusCode())->toBe(303);
});
