<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Asaas;
use Fevinta\CashierAsaas\Checkout;
use Fevinta\CashierAsaas\CheckoutBuilder;
use Fevinta\CashierAsaas\Enums\BillingType;
use Fevinta\CashierAsaas\Enums\ChargeType;
use Fevinta\CashierAsaas\Enums\SubscriptionCycle;
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

test('guest creates builder without owner', function () {
    $builder = CheckoutBuilder::guest();

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('owner');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBeNull();
});

test('customer creates builder with owner', function () {
    $builder = CheckoutBuilder::customer($this->user);

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('owner');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe($this->user);
});

test('addItem adds item to checkout', function () {
    $builder = CheckoutBuilder::guest()
        ->addItem('Product', 99.90, 2, 'Description');

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('items');
    $property->setAccessible(true);

    $items = $property->getValue($builder);
    expect($items)->toHaveCount(1);
    expect($items[0]['name'])->toBe('Product');
    expect($items[0]['value'])->toBe(99.90);
    expect($items[0]['quantity'])->toBe(2);
    expect($items[0]['description'])->toBe('Description');
});

test('items sets multiple items', function () {
    $builder = CheckoutBuilder::guest()
        ->items([
            ['name' => 'Product 1', 'value' => 50.00],
            ['name' => 'Product 2', 'value' => 100.00, 'quantity' => 2],
        ]);

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('items');
    $property->setAccessible(true);

    $items = $property->getValue($builder);
    expect($items)->toHaveCount(2);
    expect($items[1]['quantity'])->toBe(2);
});

test('charge adds single item', function () {
    $builder = CheckoutBuilder::guest()
        ->charge(150.00, 'Premium Upgrade', 1);

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('items');
    $property->setAccessible(true);

    $items = $property->getValue($builder);
    expect($items)->toHaveCount(1);
    expect($items[0]['name'])->toBe('Premium Upgrade');
    expect($items[0]['value'])->toBe(150.00);
});

test('allowAllPaymentMethods sets all billing types', function () {
    $builder = CheckoutBuilder::guest()->allowAllPaymentMethods();

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('billingTypes');
    $property->setAccessible(true);

    $types = $property->getValue($builder);
    expect($types)->toContain(BillingType::PIX);
    expect($types)->toContain(BillingType::CREDIT_CARD);
    expect($types)->toContain(BillingType::BOLETO);
});

test('onlyPix sets only PIX billing type', function () {
    $builder = CheckoutBuilder::guest()->onlyPix();

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('billingTypes');
    $property->setAccessible(true);

    $types = $property->getValue($builder);
    expect($types)->toHaveCount(1);
    expect($types[0])->toBe(BillingType::PIX);
});

test('onlyBoleto sets only BOLETO billing type', function () {
    $builder = CheckoutBuilder::guest()->onlyBoleto();

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('billingTypes');
    $property->setAccessible(true);

    $types = $property->getValue($builder);
    expect($types)->toHaveCount(1);
    expect($types[0])->toBe(BillingType::BOLETO);
});

test('onlyCreditCard sets only CREDIT_CARD billing type', function () {
    $builder = CheckoutBuilder::guest()->onlyCreditCard();

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('billingTypes');
    $property->setAccessible(true);

    $types = $property->getValue($builder);
    expect($types)->toHaveCount(1);
    expect($types[0])->toBe(BillingType::CREDIT_CARD);
});

test('withPix adds PIX to billing types', function () {
    $builder = CheckoutBuilder::guest()
        ->onlyCreditCard()
        ->withPix();

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('billingTypes');
    $property->setAccessible(true);

    $types = $property->getValue($builder);
    expect($types)->toHaveCount(2);
    expect($types)->toContain(BillingType::PIX);
    expect($types)->toContain(BillingType::CREDIT_CARD);
});

test('withPix does not duplicate', function () {
    $builder = CheckoutBuilder::guest()
        ->withPix()
        ->withPix();

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('billingTypes');
    $property->setAccessible(true);

    $types = $property->getValue($builder);
    expect($types)->toHaveCount(1);
});

test('oneTime sets DETACHED charge type', function () {
    $builder = CheckoutBuilder::guest()->oneTime();

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('chargeType');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe(ChargeType::DETACHED);
});

test('installments sets INSTALLMENT charge type and count', function () {
    $builder = CheckoutBuilder::guest()->installments(12, 100.00);

    $reflection = new ReflectionClass($builder);

    $chargeType = $reflection->getProperty('chargeType');
    $chargeType->setAccessible(true);
    expect($chargeType->getValue($builder))->toBe(ChargeType::INSTALLMENT);

    $count = $reflection->getProperty('installmentCount');
    $count->setAccessible(true);
    expect($count->getValue($builder))->toBe(12);

    $value = $reflection->getProperty('installmentValue');
    $value->setAccessible(true);
    expect($value->getValue($builder))->toBe(100.00);
});

test('maxInstallments sets max installment count', function () {
    $builder = CheckoutBuilder::guest()->maxInstallments(6);

    $reflection = new ReflectionClass($builder);

    $chargeType = $reflection->getProperty('chargeType');
    $chargeType->setAccessible(true);
    expect($chargeType->getValue($builder))->toBe(ChargeType::INSTALLMENT);

    $maxCount = $reflection->getProperty('maxInstallmentCount');
    $maxCount->setAccessible(true);
    expect($maxCount->getValue($builder))->toBe(6);
});

test('recurring sets RECURRENT charge type and cycle', function () {
    $builder = CheckoutBuilder::guest()->recurring(SubscriptionCycle::MONTHLY);

    $reflection = new ReflectionClass($builder);

    $chargeType = $reflection->getProperty('chargeType');
    $chargeType->setAccessible(true);
    expect($chargeType->getValue($builder))->toBe(ChargeType::RECURRENT);

    $cycle = $reflection->getProperty('subscriptionCycle');
    $cycle->setAccessible(true);
    expect($cycle->getValue($builder))->toBe(SubscriptionCycle::MONTHLY);
});

test('monthly shortcut sets RECURRENT with MONTHLY cycle', function () {
    $builder = CheckoutBuilder::guest()->monthly();

    $reflection = new ReflectionClass($builder);

    $chargeType = $reflection->getProperty('chargeType');
    $chargeType->setAccessible(true);
    expect($chargeType->getValue($builder))->toBe(ChargeType::RECURRENT);

    $cycle = $reflection->getProperty('subscriptionCycle');
    $cycle->setAccessible(true);
    expect($cycle->getValue($builder))->toBe(SubscriptionCycle::MONTHLY);
});

test('yearly shortcut sets RECURRENT with YEARLY cycle', function () {
    $builder = CheckoutBuilder::guest()->yearly();

    $reflection = new ReflectionClass($builder);

    $cycle = $reflection->getProperty('subscriptionCycle');
    $cycle->setAccessible(true);
    expect($cycle->getValue($builder))->toBe(SubscriptionCycle::YEARLY);
});

test('successUrl sets success URL', function () {
    $builder = CheckoutBuilder::guest()->successUrl('https://example.com/success');

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('successUrl');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe('https://example.com/success');
});

test('cancelUrl sets cancel URL', function () {
    $builder = CheckoutBuilder::guest()->cancelUrl('https://example.com/cancel');

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('cancelUrl');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe('https://example.com/cancel');
});

test('expiredUrl sets expired URL', function () {
    $builder = CheckoutBuilder::guest()->expiredUrl('https://example.com/expired');

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('expiredUrl');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe('https://example.com/expired');
});

test('expiresIn sets expiration minutes', function () {
    $builder = CheckoutBuilder::guest()->expiresIn(60);

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('expirationMinutes');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe(60);
});

test('customerData sets customer data', function () {
    $builder = CheckoutBuilder::guest()
        ->customerName('John Doe')
        ->customerCpfCnpj('12345678909')
        ->customerEmail('john@example.com')
        ->customerPhone('11999999999');

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('customerData');
    $property->setAccessible(true);

    $data = $property->getValue($builder);
    expect($data['name'])->toBe('John Doe');
    expect($data['cpfCnpj'])->toBe('12345678909');
    expect($data['email'])->toBe('john@example.com');
    expect($data['phone'])->toBe('11999999999');
});

test('customerAddress sets address data', function () {
    $builder = CheckoutBuilder::guest()
        ->customerAddress('Rua Test', '123', '01234-567', 'Centro', 'São Paulo', 'Apt 1');

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('customerData');
    $property->setAccessible(true);

    $data = $property->getValue($builder);
    expect($data['address'])->toBe('Rua Test');
    expect($data['addressNumber'])->toBe('123');
    expect($data['postalCode'])->toBe('01234-567');
    expect($data['province'])->toBe('Centro');
    expect($data['city'])->toBe('São Paulo');
    expect($data['complement'])->toBe('Apt 1');
});

test('split adds split configuration', function () {
    $builder = CheckoutBuilder::guest()
        ->split('wallet_123', 10.00, null)
        ->split('wallet_456', null, 5.0);

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('split');
    $property->setAccessible(true);

    $split = $property->getValue($builder);
    expect($split)->toHaveCount(2);
    expect($split[0]['walletId'])->toBe('wallet_123');
    expect($split[0]['fixedValue'])->toBe(10.00);
    expect($split[1]['walletId'])->toBe('wallet_456');
    expect($split[1]['percentualValue'])->toBe(5.0);
});

test('externalReference sets reference', function () {
    $builder = CheckoutBuilder::guest()->externalReference('order_123');

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('externalReference');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe('order_123');
});

test('create throws exception when no items', function () {
    CheckoutBuilder::guest()
        ->allowAllPaymentMethods()
        ->create();
})->throws(InvalidArgumentException::class, 'At least one item is required');

test('create throws exception for installments without credit card', function () {
    CheckoutBuilder::guest()
        ->addItem('Product', 100.00)
        ->installments(6)
        ->onlyPix()
        ->create();
})->throws(InvalidArgumentException::class, 'Installment payments require credit card');

test('create throws exception for recurring without cycle', function () {
    $builder = CheckoutBuilder::guest()
        ->addItem('Product', 100.00)
        ->chargeType(ChargeType::RECURRENT);

    $builder->create();
})->throws(InvalidArgumentException::class, 'Subscription cycle is required');

test('create creates checkout with mocked API', function () {
    Http::fake([
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_123',
            'status' => 'ACTIVE',
            'chargeType' => 'DETACHED',
        ], 200),
    ]);

    $checkout = CheckoutBuilder::guest()
        ->addItem('Product', 99.90)
        ->customerName('John Doe')
        ->customerCpfCnpj('12345678909')
        ->allowAllPaymentMethods()
        ->create();

    expect($checkout)->toBeInstanceOf(Checkout::class);
    expect($checkout->id())->toBe('checkout_123');
});

test('create with customer uses customer ID', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response([
            'id' => 'cus_test123',
            'name' => 'Test User',
        ], 200),
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_123',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    $checkout = CheckoutBuilder::customer($this->user)
        ->addItem('Product', 99.90)
        ->create();

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/checkouts')) {
            $body = json_decode($request->body(), true);

            return $body['customer'] === 'cus_test123';
        }

        return true;
    });

    expect($checkout->id())->toBe('checkout_123');
});

test('fluent interface works correctly', function () {
    $builder = CheckoutBuilder::guest()
        ->addItem('Product', 99.90)
        ->onlyPix()
        ->successUrl('https://example.com/success')
        ->cancelUrl('https://example.com/cancel')
        ->expiresIn(30)
        ->customerName('John Doe');

    expect($builder)->toBeInstanceOf(CheckoutBuilder::class);
});

test('allowPaymentMethods sets specific billing types', function () {
    $builder = CheckoutBuilder::guest()
        ->allowPaymentMethods([BillingType::PIX, BillingType::BOLETO]);

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('billingTypes');
    $property->setAccessible(true);

    $types = $property->getValue($builder);
    expect($types)->toHaveCount(2);
    expect($types)->toContain(BillingType::PIX);
    expect($types)->toContain(BillingType::BOLETO);
    expect($types)->not->toContain(BillingType::CREDIT_CARD);
});

test('withBoleto adds BOLETO to billing types', function () {
    $builder = CheckoutBuilder::guest()
        ->onlyCreditCard()
        ->withBoleto();

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('billingTypes');
    $property->setAccessible(true);

    $types = $property->getValue($builder);
    expect($types)->toHaveCount(2);
    expect($types)->toContain(BillingType::BOLETO);
    expect($types)->toContain(BillingType::CREDIT_CARD);
});

test('withBoleto does not duplicate', function () {
    $builder = CheckoutBuilder::guest()
        ->withBoleto()
        ->withBoleto();

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('billingTypes');
    $property->setAccessible(true);

    $types = $property->getValue($builder);
    expect($types)->toHaveCount(1);
    expect($types[0])->toBe(BillingType::BOLETO);
});

test('withCreditCard adds CREDIT_CARD to billing types', function () {
    $builder = CheckoutBuilder::guest()
        ->onlyPix()
        ->withCreditCard();

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('billingTypes');
    $property->setAccessible(true);

    $types = $property->getValue($builder);
    expect($types)->toHaveCount(2);
    expect($types)->toContain(BillingType::CREDIT_CARD);
    expect($types)->toContain(BillingType::PIX);
});

test('withCreditCard does not duplicate', function () {
    $builder = CheckoutBuilder::guest()
        ->withCreditCard()
        ->withCreditCard();

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('billingTypes');
    $property->setAccessible(true);

    $types = $property->getValue($builder);
    expect($types)->toHaveCount(1);
    expect($types[0])->toBe(BillingType::CREDIT_CARD);
});

test('cycle sets subscription cycle', function () {
    $builder = CheckoutBuilder::guest()->cycle(SubscriptionCycle::QUARTERLY);

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('subscriptionCycle');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe(SubscriptionCycle::QUARTERLY);
});

test('weekly sets RECURRENT with WEEKLY cycle', function () {
    $builder = CheckoutBuilder::guest()->weekly();

    $reflection = new ReflectionClass($builder);

    $chargeType = $reflection->getProperty('chargeType');
    $chargeType->setAccessible(true);
    expect($chargeType->getValue($builder))->toBe(ChargeType::RECURRENT);

    $cycle = $reflection->getProperty('subscriptionCycle');
    $cycle->setAccessible(true);
    expect($cycle->getValue($builder))->toBe(SubscriptionCycle::WEEKLY);
});

test('biweekly sets RECURRENT with BIWEEKLY cycle', function () {
    $builder = CheckoutBuilder::guest()->biweekly();

    $reflection = new ReflectionClass($builder);

    $chargeType = $reflection->getProperty('chargeType');
    $chargeType->setAccessible(true);
    expect($chargeType->getValue($builder))->toBe(ChargeType::RECURRENT);

    $cycle = $reflection->getProperty('subscriptionCycle');
    $cycle->setAccessible(true);
    expect($cycle->getValue($builder))->toBe(SubscriptionCycle::BIWEEKLY);
});

test('quarterly sets RECURRENT with QUARTERLY cycle', function () {
    $builder = CheckoutBuilder::guest()->quarterly();

    $reflection = new ReflectionClass($builder);

    $chargeType = $reflection->getProperty('chargeType');
    $chargeType->setAccessible(true);
    expect($chargeType->getValue($builder))->toBe(ChargeType::RECURRENT);

    $cycle = $reflection->getProperty('subscriptionCycle');
    $cycle->setAccessible(true);
    expect($cycle->getValue($builder))->toBe(SubscriptionCycle::QUARTERLY);
});

test('semiannually sets RECURRENT with SEMIANNUALLY cycle', function () {
    $builder = CheckoutBuilder::guest()->semiannually();

    $reflection = new ReflectionClass($builder);

    $chargeType = $reflection->getProperty('chargeType');
    $chargeType->setAccessible(true);
    expect($chargeType->getValue($builder))->toBe(ChargeType::RECURRENT);

    $cycle = $reflection->getProperty('subscriptionCycle');
    $cycle->setAccessible(true);
    expect($cycle->getValue($builder))->toBe(SubscriptionCycle::SEMIANNUALLY);
});

test('dueDateLimitDays sets due date limit', function () {
    $builder = CheckoutBuilder::guest()->dueDateLimitDays(5);

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('dueDateLimitDays');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe(5);
});

test('description sets description', function () {
    $builder = CheckoutBuilder::guest()->description('Test checkout description');

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('description');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe('Test checkout description');
});

test('customerData merges customer data', function () {
    $builder = CheckoutBuilder::guest()
        ->customerName('John Doe')
        ->customerData(['customField' => 'value', 'anotherField' => 123]);

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('customerData');
    $property->setAccessible(true);

    $data = $property->getValue($builder);
    expect($data['name'])->toBe('John Doe');
    expect($data['customField'])->toBe('value');
    expect($data['anotherField'])->toBe(123);
});

test('customerMobilePhone sets mobile phone', function () {
    $builder = CheckoutBuilder::guest()->customerMobilePhone('11999998888');

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('customerData');
    $property->setAccessible(true);

    $data = $property->getValue($builder);
    expect($data['mobilePhone'])->toBe('11999998888');
});

test('create includes callback URLs in payload', function () {
    Http::fake([
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_callback',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    CheckoutBuilder::guest()
        ->addItem('Product', 99.90)
        ->customerName('John Doe')
        ->customerCpfCnpj('12345678909')
        ->successUrl('https://example.com/success')
        ->cancelUrl('https://example.com/cancel')
        ->expiredUrl('https://example.com/expired')
        ->create();

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/checkouts')) {
            $body = json_decode($request->body(), true);

            return isset($body['callback'])
                && $body['callback']['successUrl'] === 'https://example.com/success'
                && $body['callback']['cancelUrl'] === 'https://example.com/cancel'
                && $body['callback']['expiredUrl'] === 'https://example.com/expired';
        }

        return true;
    });
});

test('create includes installment details in payload', function () {
    Http::fake([
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_installment',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    CheckoutBuilder::guest()
        ->addItem('Product', 1200.00)
        ->customerName('John Doe')
        ->customerCpfCnpj('12345678909')
        ->installments(12, 100.00)
        ->onlyCreditCard()
        ->create();

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/checkouts')) {
            $body = json_decode($request->body(), true);

            return $body['chargeTypes'] === ['INSTALLMENT']
                && $body['installmentCount'] === 12
                && (float) $body['installmentValue'] === 100.00;
        }

        return true;
    });
});

test('create includes max installment count in payload', function () {
    Http::fake([
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_max_installment',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    CheckoutBuilder::guest()
        ->addItem('Product', 600.00)
        ->customerName('John Doe')
        ->customerCpfCnpj('12345678909')
        ->maxInstallments(6)
        ->onlyCreditCard()
        ->create();

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/checkouts')) {
            $body = json_decode($request->body(), true);

            return $body['chargeTypes'] === ['INSTALLMENT']
                && $body['maxInstallmentCount'] === 6;
        }

        return true;
    });
});

test('create includes subscription cycle in payload', function () {
    Http::fake([
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_recurring',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    CheckoutBuilder::guest()
        ->addItem('Subscription', 99.90)
        ->customerName('John Doe')
        ->customerCpfCnpj('12345678909')
        ->monthly()
        ->create();

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/checkouts')) {
            $body = json_decode($request->body(), true);

            return $body['chargeTypes'] === ['RECURRENT']
                && isset($body['subscription'])
                && $body['subscription']['cycle'] === 'MONTHLY'
                && isset($body['subscription']['nextDueDate']);
        }

        return true;
    });
});

test('create includes subscription dates in payload', function () {
    Http::fake([
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_subscription_dates',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    CheckoutBuilder::guest()
        ->addItem('Subscription', 99.90)
        ->customerName('John Doe')
        ->customerCpfCnpj('12345678909')
        ->monthly()
        ->subscriptionNextDueDate('2024-10-31 15:02:38')
        ->subscriptionEndDate('2025-10-31 15:02:38')
        ->create();

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/checkouts')) {
            $body = json_decode($request->body(), true);

            return $body['chargeTypes'] === ['RECURRENT']
                && isset($body['subscription'])
                && $body['subscription']['cycle'] === 'MONTHLY'
                && $body['subscription']['nextDueDate'] === '2024-10-31 15:02:38'
                && $body['subscription']['endDate'] === '2025-10-31 15:02:38';
        }

        return true;
    });
});

test('create includes expiration minutes in payload', function () {
    Http::fake([
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_expiration',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    CheckoutBuilder::guest()
        ->addItem('Product', 99.90)
        ->customerName('John Doe')
        ->customerCpfCnpj('12345678909')
        ->expiresIn(120)
        ->create();

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/checkouts')) {
            $body = json_decode($request->body(), true);

            return $body['expirationMinutes'] === 120;
        }

        return true;
    });
});

test('create includes due date limit days in payload', function () {
    Http::fake([
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_due_date',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    CheckoutBuilder::guest()
        ->addItem('Product', 99.90)
        ->customerName('John Doe')
        ->customerCpfCnpj('12345678909')
        ->dueDateLimitDays(10)
        ->create();

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/checkouts')) {
            $body = json_decode($request->body(), true);

            return $body['dueDateLimitDays'] === 10;
        }

        return true;
    });
});

test('create includes external reference in payload', function () {
    Http::fake([
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_ref',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    CheckoutBuilder::guest()
        ->addItem('Product', 99.90)
        ->customerName('John Doe')
        ->customerCpfCnpj('12345678909')
        ->externalReference('order_12345')
        ->create();

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/checkouts')) {
            $body = json_decode($request->body(), true);

            return $body['externalReference'] === 'order_12345';
        }

        return true;
    });
});

test('create includes description in payload', function () {
    Http::fake([
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_desc',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    CheckoutBuilder::guest()
        ->addItem('Product', 99.90)
        ->customerName('John Doe')
        ->customerCpfCnpj('12345678909')
        ->description('Test checkout description')
        ->create();

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/checkouts')) {
            $body = json_decode($request->body(), true);

            return $body['description'] === 'Test checkout description';
        }

        return true;
    });
});

test('create includes split in payload', function () {
    Http::fake([
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_split',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    CheckoutBuilder::guest()
        ->addItem('Product', 100.00)
        ->customerName('John Doe')
        ->customerCpfCnpj('12345678909')
        ->split('wallet_123', 10.00, null)
        ->split('wallet_456', null, 5.0)
        ->create();

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/checkouts')) {
            $body = json_decode($request->body(), true);

            return isset($body['split'])
                && count($body['split']) === 2
                && $body['split'][0]['walletId'] === 'wallet_123'
                && (float) $body['split'][0]['fixedValue'] === 10.00
                && $body['split'][1]['walletId'] === 'wallet_456'
                && (float) $body['split'][1]['percentualValue'] === 5.0;
        }

        return true;
    });
});

test('create uses config default URLs when not set', function () {
    config([
        'cashier-asaas.checkout.success_url' => 'https://config.example.com/success',
        'cashier-asaas.checkout.cancel_url' => 'https://config.example.com/cancel',
        'cashier-asaas.checkout.expired_url' => 'https://config.example.com/expired',
    ]);

    Http::fake([
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_config',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    CheckoutBuilder::guest()
        ->addItem('Product', 99.90)
        ->customerName('John Doe')
        ->customerCpfCnpj('12345678909')
        ->create();

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/checkouts')) {
            $body = json_decode($request->body(), true);

            return isset($body['callback'])
                && $body['callback']['successUrl'] === 'https://config.example.com/success'
                && $body['callback']['cancelUrl'] === 'https://config.example.com/cancel'
                && $body['callback']['expiredUrl'] === 'https://config.example.com/expired';
        }

        return true;
    });
});

test('create uses config default expiration when not set', function () {
    config(['cashier-asaas.checkout.expiration_minutes' => 45]);

    Http::fake([
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_exp_config',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    CheckoutBuilder::guest()
        ->addItem('Product', 99.90)
        ->customerName('John Doe')
        ->customerCpfCnpj('12345678909')
        ->create();

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/checkouts')) {
            $body = json_decode($request->body(), true);

            return $body['expirationMinutes'] === 45;
        }

        return true;
    });
});

test('create includes customer data for guest checkout', function () {
    Http::fake([
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_guest_data',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    CheckoutBuilder::guest()
        ->addItem('Product', 99.90)
        ->customerName('John Doe')
        ->customerCpfCnpj('12345678909')
        ->customerEmail('john@example.com')
        ->customerPhone('11999999999')
        ->customerMobilePhone('11888888888')
        ->create();

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/checkouts')) {
            $body = json_decode($request->body(), true);

            return isset($body['customerData'])
                && $body['customerData']['name'] === 'John Doe'
                && $body['customerData']['cpfCnpj'] === '12345678909'
                && $body['customerData']['email'] === 'john@example.com'
                && $body['customerData']['phone'] === '11999999999'
                && $body['customerData']['mobilePhone'] === '11888888888';
        }

        return true;
    });
});

test('asGuest allows customer to fill data on checkout page', function () {
    Http::fake([
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_as_guest',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    CheckoutBuilder::customer($this->user)
        ->addItem('Product', 99.90)
        ->asGuest()
        ->create();

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/checkouts')) {
            $body = json_decode($request->body(), true);

            // Should not include customer ID
            return ! isset($body['customer'])
                && ! isset($body['customerData']);
        }

        return true;
    });
});

test('asGuest with customerData pre-fills fields', function () {
    Http::fake([
        Asaas::baseUrl().'/checkouts' => Http::response([
            'id' => 'checkout_as_guest_prefill',
            'status' => 'ACTIVE',
        ], 200),
    ]);

    CheckoutBuilder::customer($this->user)
        ->addItem('Product', 99.90)
        ->asGuest()
        ->customerEmail('prefilled@example.com')
        ->customerName('Prefilled Name')
        ->create();

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/checkouts')) {
            $body = json_decode($request->body(), true);

            // Should not include customer ID but should include customerData
            return ! isset($body['customer'])
                && isset($body['customerData'])
                && $body['customerData']['email'] === 'prefilled@example.com'
                && $body['customerData']['name'] === 'Prefilled Name';
        }

        return true;
    });
});
