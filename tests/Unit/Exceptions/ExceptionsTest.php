<?php

declare(strict_types=1);

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
