<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Exceptions\AsaasApiException;
use Fevinta\CashierAsaas\Exceptions\CashierException;
use Fevinta\CashierAsaas\Exceptions\CustomerAlreadyCreated;
use Fevinta\CashierAsaas\Exceptions\IncompletePayment;
use Fevinta\CashierAsaas\Exceptions\InvalidCustomer;
use Fevinta\CashierAsaas\Exceptions\InvalidSubscription;
use Fevinta\CashierAsaas\Exceptions\InvalidWebhookPayload;
use Fevinta\CashierAsaas\Exceptions\PaymentFailure;
use Fevinta\CashierAsaas\Exceptions\SubscriptionUpdateFailure;
use Fevinta\CashierAsaas\Exceptions\WebhookFailed;
use Fevinta\CashierAsaas\Payment;
use Fevinta\CashierAsaas\Subscription;
use Fevinta\CashierAsaas\Tests\Fixtures\User;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'cpf_cnpj' => generateTestCpf(),
        'asaas_id' => 'cus_test123',
    ]);
});

// CustomerAlreadyCreated Tests
test('CustomerAlreadyCreated exception contains owner', function () {
    $exception = CustomerAlreadyCreated::exists($this->user);

    expect($exception)->toBeInstanceOf(CustomerAlreadyCreated::class);
    expect($exception)->toBeInstanceOf(CashierException::class);
    expect($exception->owner)->toBe($this->user);
    expect($exception->getMessage())->toContain('cus_test123');
});

// InvalidCustomer Tests
test('InvalidCustomer notYetCreated exception', function () {
    $userWithoutAsaas = User::create([
        'name' => 'No Asaas',
        'email' => 'no-asaas@example.com',
        'cpf_cnpj' => generateTestCpf(),
    ]);

    $exception = InvalidCustomer::notYetCreated($userWithoutAsaas);

    expect($exception)->toBeInstanceOf(InvalidCustomer::class);
    expect($exception->owner)->toBe($userWithoutAsaas);
    expect($exception->getMessage())->toContain('not been created yet');
});

test('InvalidCustomer notFound exception', function () {
    $exception = InvalidCustomer::notFound($this->user);

    expect($exception)->toBeInstanceOf(InvalidCustomer::class);
    expect($exception->getMessage())->toContain('could not be found');
});

// IncompletePayment Tests
test('IncompletePayment exception contains payment', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now()->addDays(3),
    ]);

    $exception = IncompletePayment::forPayment($payment);

    expect($exception)->toBeInstanceOf(IncompletePayment::class);
    expect($exception->payment)->toBe($payment);
    expect($exception->getPayment())->toBe($payment);
    expect($exception->getMessage())->toContain('pay_test123');
});

// PaymentFailure Tests
test('PaymentFailure invalidCard exception', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'CREDIT_CARD',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now(),
    ]);

    $exception = PaymentFailure::invalidCard($payment, 'Card number invalid');

    expect($exception)->toBeInstanceOf(PaymentFailure::class);
    expect($exception)->toBeInstanceOf(IncompletePayment::class);
    expect($exception->payment)->toBe($payment);
    expect($exception->reason)->toBe('Card number invalid');
    expect($exception->getReason())->toBe('Card number invalid');
    expect($exception->getMessage())->toContain('invalid credit card');
});

test('PaymentFailure cardDeclined exception', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'CREDIT_CARD',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now(),
    ]);

    $exception = PaymentFailure::cardDeclined($payment);

    expect($exception)->toBeInstanceOf(PaymentFailure::class);
    expect($exception->getMessage())->toContain('declined');
});

test('PaymentFailure insufficientFunds exception', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'CREDIT_CARD',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now(),
    ]);

    $exception = PaymentFailure::insufficientFunds($payment);

    expect($exception)->toBeInstanceOf(PaymentFailure::class);
    expect($exception->reason)->toBe('insufficient_funds');
});

test('PaymentFailure expiredCard exception', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'CREDIT_CARD',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now(),
    ]);

    $exception = PaymentFailure::expiredCard($payment);

    expect($exception)->toBeInstanceOf(PaymentFailure::class);
    expect($exception->reason)->toBe('expired_card');
});

test('PaymentFailure failed with reason', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now(),
    ]);

    $exception = PaymentFailure::failed($payment, 'Transaction timeout');

    expect($exception)->toBeInstanceOf(PaymentFailure::class);
    expect($exception->reason)->toBe('Transaction timeout');
    expect($exception->getReason())->toBe('Transaction timeout');
    expect($exception->getMessage())->toContain('Transaction timeout');
});

test('PaymentFailure failed without reason', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test456',
        'billing_type' => 'BOLETO',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now(),
    ]);

    $exception = PaymentFailure::failed($payment);

    expect($exception)->toBeInstanceOf(PaymentFailure::class);
    expect($exception->reason)->toBeNull();
    expect($exception->getMessage())->toBe('Payment [pay_test456] failed.');
});

// SubscriptionUpdateFailure Tests
test('SubscriptionUpdateFailure incompleteSubscription exception', function () {
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_test123',
        'asaas_status' => 'ACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addMonth(),
    ]);

    $exception = SubscriptionUpdateFailure::incompleteSubscription($subscription);

    expect($exception)->toBeInstanceOf(SubscriptionUpdateFailure::class);
    expect($exception->subscription)->toBe($subscription);
    expect($exception->getSubscription())->toBe($subscription);
    expect($exception->getMessage())->toContain('incomplete payment');
});

test('SubscriptionUpdateFailure cannotSwapCancelled exception', function () {
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_test123',
        'asaas_status' => 'INACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addMonth(),
        'ends_at' => now()->subDay(),
    ]);

    $exception = SubscriptionUpdateFailure::cannotSwapCancelled($subscription);

    expect($exception->getMessage())->toContain('cancelled');
});

test('SubscriptionUpdateFailure cannotResume exception', function () {
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_test123',
        'asaas_status' => 'INACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addMonth(),
    ]);

    $exception = SubscriptionUpdateFailure::cannotResume($subscription);

    expect($exception->getMessage())->toContain('grace period');
});

test('SubscriptionUpdateFailure failed with reason', function () {
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_test789',
        'asaas_status' => 'ACTIVE',
        'plan' => 'premium',
        'value' => 99.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'PIX',
        'next_due_date' => now()->addMonth(),
    ]);

    $exception = SubscriptionUpdateFailure::failed($subscription, 'API timeout');

    expect($exception)->toBeInstanceOf(SubscriptionUpdateFailure::class);
    expect($exception->subscription)->toBe($subscription);
    expect($exception->getMessage())->toContain('API timeout');
});

test('SubscriptionUpdateFailure failed without reason', function () {
    $subscription = Subscription::create([
        'user_id' => $this->user->id,
        'type' => 'default',
        'asaas_id' => 'sub_test000',
        'asaas_status' => 'ACTIVE',
        'plan' => 'basic',
        'value' => 49.90,
        'cycle' => 'MONTHLY',
        'billing_type' => 'BOLETO',
        'next_due_date' => now()->addMonth(),
    ]);

    $exception = SubscriptionUpdateFailure::failed($subscription);

    expect($exception)->toBeInstanceOf(SubscriptionUpdateFailure::class);
    expect($exception->getMessage())->toBe('Subscription [sub_test000] update failed.');
});

// InvalidSubscription Tests
test('InvalidSubscription notFound exception', function () {
    $exception = InvalidSubscription::notFound('default');

    expect($exception)->toBeInstanceOf(InvalidSubscription::class);
    expect($exception->type)->toBe('default');
    expect($exception->getType())->toBe('default');
    expect($exception->getMessage())->toContain('default');
});

test('InvalidSubscription inactive exception', function () {
    $exception = InvalidSubscription::inactive('default');

    expect($exception)->toBeInstanceOf(InvalidSubscription::class);
    expect($exception->getMessage())->toContain('not active');
});

test('InvalidSubscription alreadyExists exception', function () {
    $exception = InvalidSubscription::alreadyExists('default');

    expect($exception)->toBeInstanceOf(InvalidSubscription::class);
    expect($exception->getMessage())->toContain('already exists');
});

// InvalidWebhookPayload Tests
test('InvalidWebhookPayload missingEvent exception', function () {
    $exception = InvalidWebhookPayload::missingEvent(['data' => 'test']);

    expect($exception)->toBeInstanceOf(InvalidWebhookPayload::class);
    expect($exception->payload)->toBe(['data' => 'test']);
    expect($exception->getPayload())->toBe(['data' => 'test']);
    expect($exception->getMessage())->toContain('event type');
});

test('InvalidWebhookPayload missingPaymentId exception', function () {
    $exception = InvalidWebhookPayload::missingPaymentId();

    expect($exception->getMessage())->toContain('payment ID');
});

test('InvalidWebhookPayload missingSubscriptionId exception', function () {
    $exception = InvalidWebhookPayload::missingSubscriptionId();

    expect($exception->getMessage())->toContain('subscription ID');
});

test('InvalidWebhookPayload invalidJson exception', function () {
    $exception = InvalidWebhookPayload::invalidJson();

    expect($exception->getMessage())->toContain('not valid JSON');
});

test('InvalidWebhookPayload missingCustomerId exception', function () {
    $payload = ['event' => 'PAYMENT_CREATED', 'payment' => ['id' => 'pay_123']];
    $exception = InvalidWebhookPayload::missingCustomerId($payload);

    expect($exception)->toBeInstanceOf(InvalidWebhookPayload::class);
    expect($exception->getMessage())->toContain('customer ID');
    expect($exception->payload)->toBe($payload);
});

// WebhookFailed Tests
test('WebhookFailed processingError exception', function () {
    $originalException = new Exception('Database error');
    $exception = WebhookFailed::processingError(
        'PAYMENT_CREATED',
        ['payment' => ['id' => 'pay_123']],
        $originalException
    );

    expect($exception)->toBeInstanceOf(WebhookFailed::class);
    expect($exception->eventType)->toBe('PAYMENT_CREATED');
    expect($exception->getEventType())->toBe('PAYMENT_CREATED');
    expect($exception->payload)->toBe(['payment' => ['id' => 'pay_123']]);
    expect($exception->getPayload())->toBe(['payment' => ['id' => 'pay_123']]);
    expect($exception->getPrevious())->toBe($originalException);
});

test('WebhookFailed unsupportedEvent exception', function () {
    $exception = WebhookFailed::unsupportedEvent('UNKNOWN_EVENT', []);

    expect($exception->getMessage())->toContain('not supported');
});

test('WebhookFailed handlerNotFound exception', function () {
    $exception = WebhookFailed::handlerNotFound('CUSTOM_EVENT', []);

    expect($exception->getMessage())->toContain('No handler found');
});

test('WebhookFailed databaseError exception', function () {
    $originalException = new Exception('Connection failed');
    $exception = WebhookFailed::databaseError(
        'PAYMENT_RECEIVED',
        [],
        $originalException
    );

    expect($exception->getMessage())->toContain('Database error');
    expect($exception->getPrevious())->toBe($originalException);
});

// AsaasApiException Tests
test('AsaasApiException can be constructed with all parameters', function () {
    $exception = new AsaasApiException(
        message: 'Test error',
        statusCode: 400,
        errors: [['code' => 'ERR_001', 'description' => 'Test error']],
        asaasCode: 'ERR_001'
    );

    expect($exception->getMessage())->toBe('Test error');
    expect($exception->getCode())->toBe(400);
    expect($exception->statusCode)->toBe(400);
    expect($exception->errors)->toBe([['code' => 'ERR_001', 'description' => 'Test error']]);
    expect($exception->asaasCode)->toBe('ERR_001');
});

test('AsaasApiException getErrors returns errors array', function () {
    $errors = [
        ['code' => 'ERR_001', 'description' => 'First error'],
        ['code' => 'ERR_002', 'description' => 'Second error'],
    ];

    $exception = new AsaasApiException(
        message: 'Multiple errors',
        statusCode: 400,
        errors: $errors
    );

    expect($exception->getErrors())->toBe($errors);
});

test('AsaasApiException getAsaasCode returns asaas code', function () {
    $exception = new AsaasApiException(
        message: 'Test error',
        statusCode: 400,
        asaasCode: 'INVALID_CUSTOMER'
    );

    expect($exception->getAsaasCode())->toBe('INVALID_CUSTOMER');
});

test('AsaasApiException getAsaasCode returns null when not set', function () {
    $exception = new AsaasApiException(
        message: 'Test error',
        statusCode: 400
    );

    expect($exception->getAsaasCode())->toBeNull();
});

test('AsaasApiException fromResponse creates exception from HTTP response', function () {
    $psr7Response = new Psr7Response(400, [], json_encode([
        'errors' => [
            ['code' => 'INVALID_CPF', 'description' => 'CPF is invalid'],
        ],
    ]));

    $response = new Response($psr7Response);

    $exception = AsaasApiException::fromResponse($response);

    expect($exception)->toBeInstanceOf(AsaasApiException::class);
    expect($exception->getMessage())->toBe('CPF is invalid');
    expect($exception->statusCode)->toBe(400);
    expect($exception->asaasCode)->toBe('INVALID_CPF');
    expect($exception->errors)->toHaveCount(1);
});

test('AsaasApiException fromResponse handles empty errors array', function () {
    $psr7Response = new Psr7Response(500, [], json_encode([
        'errors' => [],
    ]));

    $response = new Response($psr7Response);

    $exception = AsaasApiException::fromResponse($response);

    expect($exception->getMessage())->toBe('Asaas API Error');
    expect($exception->statusCode)->toBe(500);
    expect($exception->asaasCode)->toBeNull();
});

test('AsaasApiException fromResponse handles missing errors key', function () {
    $psr7Response = new Psr7Response(404, [], json_encode([
        'message' => 'Not found',
    ]));

    $response = new Response($psr7Response);

    $exception = AsaasApiException::fromResponse($response);

    expect($exception->getMessage())->toBe('Asaas API Error');
    expect($exception->statusCode)->toBe(404);
    expect($exception->errors)->toBe([]);
});

test('AsaasApiException fromResponse extracts first error description', function () {
    $psr7Response = new Psr7Response(400, [], json_encode([
        'errors' => [
            ['code' => 'ERR_1', 'description' => 'First error message'],
            ['code' => 'ERR_2', 'description' => 'Second error message'],
        ],
    ]));

    $response = new Response($psr7Response);

    $exception = AsaasApiException::fromResponse($response);

    expect($exception->getMessage())->toBe('First error message');
    expect($exception->asaasCode)->toBe('ERR_1');
});

test('AsaasApiException fromResponse handles error without description', function () {
    $psr7Response = new Psr7Response(400, [], json_encode([
        'errors' => [
            ['code' => 'UNKNOWN'],
        ],
    ]));

    $response = new Response($psr7Response);

    $exception = AsaasApiException::fromResponse($response);

    expect($exception->getMessage())->toBe('Asaas API Error');
    expect($exception->asaasCode)->toBe('UNKNOWN');
});

test('AsaasApiException fromResponse handles error without code', function () {
    $psr7Response = new Psr7Response(400, [], json_encode([
        'errors' => [
            ['description' => 'Some error occurred'],
        ],
    ]));

    $response = new Response($psr7Response);

    $exception = AsaasApiException::fromResponse($response);

    expect($exception->getMessage())->toBe('Some error occurred');
    expect($exception->asaasCode)->toBeNull();
});
