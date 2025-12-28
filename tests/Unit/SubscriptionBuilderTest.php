<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Asaas;
use Fevinta\CashierAsaas\Enums\BillingType;
use Fevinta\CashierAsaas\Enums\SubscriptionCycle;
use Fevinta\CashierAsaas\SubscriptionBuilder;
use Fevinta\CashierAsaas\Tests\Concerns\MocksAsaasApi;
use Fevinta\CashierAsaas\Tests\Fixtures\AsaasApiFixtures;
use Fevinta\CashierAsaas\Tests\Fixtures\User;
use Illuminate\Support\Facades\Http;

uses(MocksAsaasApi::class);

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'cpf_cnpj' => generateTestCpf(),
        'asaas_id' => 'cus_test123',
    ]);
});

test('builder sets price', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->price(149.90);

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('value');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe(149.90);
});

test('builder sets cycle with enum', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->cycle(SubscriptionCycle::QUARTERLY);

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('cycle');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe(SubscriptionCycle::QUARTERLY);
});

test('builder monthly shortcut', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->monthly();

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('cycle');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe(SubscriptionCycle::MONTHLY);
});

test('builder yearly shortcut', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->yearly();

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('cycle');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe(SubscriptionCycle::YEARLY);
});

test('builder weekly shortcut', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->weekly();

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('cycle');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe(SubscriptionCycle::WEEKLY);
});

test('builder quarterly shortcut', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->quarterly();

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('cycle');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe(SubscriptionCycle::QUARTERLY);
});

test('builder with pix', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->withPix();

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('billingType');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe(BillingType::PIX);
});

test('builder with boleto', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->withBoleto();

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('billingType');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe(BillingType::BOLETO);
});

test('builder ask customer sets undefined billing type', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->askCustomer();

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('billingType');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe(BillingType::UNDEFINED);
});

test('builder trial days', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->trialDays(14);

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('trialEndsAt');
    $property->setAccessible(true);

    $trialEndsAt = $property->getValue($builder);
    expect($trialEndsAt)->toBeInstanceOf(\Carbon\Carbon::class);
    expect((int) round(now()->diffInDays($trialEndsAt, false)))->toBe(14);
});

test('builder trial until date', function () {
    $trialEnd = now()->addDays(30);
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->trialUntil($trialEnd);

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('trialEndsAt');
    $property->setAccessible(true);

    expect($property->getValue($builder)->toDateString())->toBe($trialEnd->toDateString());
});

test('builder skip trial', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->trialDays(14)->skipTrial();

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('trialEndsAt');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBeNull();
});

test('builder with discount', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->withDiscount(10.00, 5);

    $reflection = new ReflectionClass($builder);
    $discountProp = $reflection->getProperty('discount');
    $discountProp->setAccessible(true);

    expect($discountProp->getValue($builder))->toBe(10.00);
});

test('builder with interest', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->withInterest(2.0);

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('interest');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe(2.0);
});

test('builder with fine', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->withFine(5.0);

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('fine');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe(5.0);
});

test('builder with split', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->split('wallet_123', 10.00, null);

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('split');
    $property->setAccessible(true);

    $split = $property->getValue($builder);
    expect($split)->toBeArray();
    expect($split[0]['walletId'])->toBe('wallet_123');
    expect($split[0]['fixedValue'])->toBe(10.00);
});

test('builder with metadata', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->withMetadata(['order_id' => 123, 'source' => 'website']);

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('metadata');
    $property->setAccessible(true);

    $metadata = $property->getValue($builder);
    expect($metadata)->toBeArray();
    expect($metadata['order_id'])->toBe(123);
    expect($metadata['source'])->toBe('website');
});

test('builder description', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->description('Premium subscription for Test User');

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('description');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe('Premium subscription for Test User');
});

test('builder starts at', function () {
    $startDate = now()->addDays(7);
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->startsAt($startDate);

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('nextDueDate');
    $property->setAccessible(true);

    expect($property->getValue($builder)->toDateString())->toBe($startDate->toDateString());
});

test('builder start immediately', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->startImmediately();

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('nextDueDate');
    $property->setAccessible(true);

    expect($property->getValue($builder)->toDateString())->toBe(now()->toDateString());
});

test('builder max payments', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->maxPayments(12);

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('maxPayments');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe(12);
});

test('builder ends at', function () {
    $endDate = now()->addYear();
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->endsAt($endDate);

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('endDate');
    $property->setAccessible(true);

    expect($property->getValue($builder)->toDateString())->toBe($endDate->toDateString());
});

test('builder fluent interface', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');

    $result = $builder
        ->price(99.90)
        ->monthly()
        ->withPix()
        ->trialDays(7)
        ->description('Test subscription');

    expect($result)->toBeInstanceOf(SubscriptionBuilder::class);
});

test('builder creates subscription with mocked api', function () {
    $subscriptionId = 'sub_'.uniqid();

    Http::fake([
        // Mock customer retrieval (since user already has asaas_id)
        Asaas::baseUrl().'/customers/cus_test123' => Http::response(
            AsaasApiFixtures::customer(['id' => 'cus_test123']),
            200
        ),
        // Mock subscription creation
        Asaas::baseUrl().'/subscriptions' => Http::response(
            AsaasApiFixtures::subscription(['id' => $subscriptionId]),
            200
        ),
    ]);

    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $subscription = $builder
        ->price(99.90)
        ->monthly()
        ->withPix()
        ->create();

    expect($subscription)->toBeInstanceOf(\Fevinta\CashierAsaas\Subscription::class);
    expect($subscription->user_id)->toBe($this->user->id);
    expect($subscription->type)->toBe('default');
    expect($subscription->plan)->toBe('premium');
});

test('builder with credit card', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->withCreditCard(
        ['holderName' => 'Test', 'number' => '4242424242424242', 'expiryMonth' => '12', 'expiryYear' => '2030', 'ccv' => '123'],
        ['name' => 'Test User', 'email' => 'test@test.com', 'cpfCnpj' => '12345678909'],
        '127.0.0.1'
    );

    $reflection = new ReflectionClass($builder);

    $billingType = $reflection->getProperty('billingType');
    $billingType->setAccessible(true);
    expect($billingType->getValue($builder))->toBe(BillingType::CREDIT_CARD);

    $creditCard = $reflection->getProperty('creditCard');
    $creditCard->setAccessible(true);
    expect($creditCard->getValue($builder)['holderName'])->toBe('Test');

    $holderInfo = $reflection->getProperty('creditCardHolderInfo');
    $holderInfo->setAccessible(true);
    expect($holderInfo->getValue($builder)['name'])->toBe('Test User');

    $remoteIp = $reflection->getProperty('remoteIp');
    $remoteIp->setAccessible(true);
    expect($remoteIp->getValue($builder))->toBe('127.0.0.1');
});

test('builder with credit card token', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->withCreditCardToken('cc_tok_123', '127.0.0.1');

    $reflection = new ReflectionClass($builder);

    $billingType = $reflection->getProperty('billingType');
    $billingType->setAccessible(true);
    expect($billingType->getValue($builder))->toBe(BillingType::CREDIT_CARD);

    $token = $reflection->getProperty('creditCardToken');
    $token->setAccessible(true);
    expect($token->getValue($builder))->toBe('cc_tok_123');
});

test('builder billing type setter', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->billingType(BillingType::CREDIT_CARD);

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('billingType');
    $property->setAccessible(true);

    expect($property->getValue($builder))->toBe(BillingType::CREDIT_CARD);
});

test('builder with split using percentual value', function () {
    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $builder->split('wallet_123', null, 10.0);

    $reflection = new ReflectionClass($builder);
    $property = $reflection->getProperty('split');
    $property->setAccessible(true);

    $split = $property->getValue($builder);
    expect($split[0]['walletId'])->toBe('wallet_123');
    expect($split[0]['percentualValue'])->toBe(10.0);
});

test('builder creates subscription with credit card token', function () {
    $subscriptionId = 'sub_'.uniqid();

    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response(
            AsaasApiFixtures::customer(['id' => 'cus_test123']),
            200
        ),
        Asaas::baseUrl().'/subscriptions' => Http::response(
            AsaasApiFixtures::subscription([
                'id' => $subscriptionId,
                'billingType' => 'CREDIT_CARD',
            ]),
            200
        ),
    ]);

    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $subscription = $builder
        ->price(99.90)
        ->monthly()
        ->withCreditCardToken('cc_tok_123')
        ->create();

    expect($subscription->billing_type)->toBe('CREDIT_CARD');
});

test('builder creates subscription with credit card data', function () {
    $subscriptionId = 'sub_'.uniqid();

    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response(
            AsaasApiFixtures::customer(['id' => 'cus_test123']),
            200
        ),
        Asaas::baseUrl().'/subscriptions' => Http::response(
            AsaasApiFixtures::subscription([
                'id' => $subscriptionId,
                'billingType' => 'CREDIT_CARD',
            ]),
            200
        ),
    ]);

    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $subscription = $builder
        ->price(99.90)
        ->monthly()
        ->withCreditCard(
            ['holderName' => 'Test', 'number' => '4242424242424242', 'expiryMonth' => '12', 'expiryYear' => '2030', 'ccv' => '123'],
            ['name' => 'Test User', 'email' => 'test@test.com', 'cpfCnpj' => '12345678909']
        )
        ->create();

    expect($subscription->billing_type)->toBe('CREDIT_CARD');
});

test('builder creates subscription with all optional fields', function () {
    $subscriptionId = 'sub_'.uniqid();

    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response(
            AsaasApiFixtures::customer(['id' => 'cus_test123']),
            200
        ),
        Asaas::baseUrl().'/subscriptions' => Http::response(
            AsaasApiFixtures::subscription(['id' => $subscriptionId]),
            200
        ),
    ]);

    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $subscription = $builder
        ->price(99.90)
        ->monthly()
        ->withPix()
        ->maxPayments(12)
        ->endsAt(now()->addYear())
        ->withDiscount(10.00)
        ->withInterest(2.0)
        ->withFine(5.0)
        ->split('wallet_123', 10.00)
        ->create();

    expect($subscription)->toBeInstanceOf(\Fevinta\CashierAsaas\Subscription::class);
});

test('builder throws exception when price not set and not in config', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response(
            AsaasApiFixtures::customer(['id' => 'cus_test123']),
            200
        ),
    ]);

    $builder = new SubscriptionBuilder($this->user, 'default', 'nonexistent_plan');
    $builder->monthly()->withPix()->create();
})->throws(InvalidArgumentException::class, 'Subscription price must be set');

test('builder resolves price from config', function () {
    config(['cashier-asaas.plans.premium.price' => 199.90]);

    $subscriptionId = 'sub_'.uniqid();

    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response(
            AsaasApiFixtures::customer(['id' => 'cus_test123']),
            200
        ),
        Asaas::baseUrl().'/subscriptions' => Http::response(
            AsaasApiFixtures::subscription(['id' => $subscriptionId, 'value' => 199.90]),
            200
        ),
    ]);

    $builder = new SubscriptionBuilder($this->user, 'default', 'premium');
    $subscription = $builder
        ->monthly()
        ->withPix()
        ->create();

    expect($subscription->value)->toBe('199.90');
});
