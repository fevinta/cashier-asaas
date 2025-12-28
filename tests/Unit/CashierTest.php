<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Cashier;
use Fevinta\CashierAsaas\Payment;
use Fevinta\CashierAsaas\Subscription;

beforeEach(function () {
    // Reset static properties to default values before each test
    Cashier::$registersRoutes = true;
    Cashier::$deactivatePastDue = true;
    Cashier::$subscriptionModel = Subscription::class;
    Cashier::$paymentModel = Payment::class;
    Cashier::formatCurrencyUsing(null);
});

afterEach(function () {
    // Reset static properties after each test
    Cashier::$registersRoutes = true;
    Cashier::$deactivatePastDue = true;
    Cashier::$subscriptionModel = Subscription::class;
    Cashier::$paymentModel = Payment::class;
    Cashier::formatCurrencyUsing(null);
});

test('ignoreRoutes sets registersRoutes to false', function () {
    expect(Cashier::$registersRoutes)->toBeTrue();

    Cashier::ignoreRoutes();

    expect(Cashier::$registersRoutes)->toBeFalse();
});

test('formatCurrencyUsing sets custom formatter', function () {
    Cashier::formatCurrencyUsing(function ($amount, $currency) {
        return "CUSTOM: {$amount} {$currency}";
    });

    $result = Cashier::formatAmount(100.50, 'BRL');

    expect($result)->toBe('CUSTOM: 100.5 BRL');
});

test('formatCurrencyUsing with null resets to default formatter', function () {
    Cashier::formatCurrencyUsing(function ($amount, $currency) {
        return "CUSTOM: {$amount}";
    });

    Cashier::formatCurrencyUsing(null);

    $result = Cashier::formatAmount(100.50, 'BRL');

    // Default formatter uses IntlMoneyFormatter
    expect($result)->toContain('100');
});

test('formatAmount uses default currency from config', function () {
    // The default currency is BRL as configured in TestCase
    $result = Cashier::formatAmount(99.90);

    // Should format as Brazilian Real
    expect($result)->toContain('99');
});

test('formatAmount accepts custom currency', function () {
    $result = Cashier::formatAmount(99.90, 'USD');

    expect($result)->toContain('99');
});

test('formatAmount handles integer amounts', function () {
    $result = Cashier::formatAmount(100, 'BRL');

    expect($result)->toContain('100');
});

test('keepPastDueSubscriptionsActive sets deactivatePastDue to false', function () {
    expect(Cashier::$deactivatePastDue)->toBeTrue();

    Cashier::keepPastDueSubscriptionsActive();

    expect(Cashier::$deactivatePastDue)->toBeFalse();
});

test('useSubscriptionModel sets custom subscription model', function () {
    $customModel = 'App\\Models\\CustomSubscription';

    Cashier::useSubscriptionModel($customModel);

    expect(Cashier::$subscriptionModel)->toBe($customModel);
    expect(Cashier::subscriptionModel())->toBe($customModel);
});

test('usePaymentModel sets custom payment model', function () {
    $customModel = 'App\\Models\\CustomPayment';

    Cashier::usePaymentModel($customModel);

    expect(Cashier::$paymentModel)->toBe($customModel);
    expect(Cashier::paymentModel())->toBe($customModel);
});

test('subscriptionModel returns default model', function () {
    expect(Cashier::subscriptionModel())->toBe(Subscription::class);
});

test('paymentModel returns default model', function () {
    expect(Cashier::paymentModel())->toBe(Payment::class);
});

test('formatAmount with custom formatter receives amount and currency', function () {
    $capturedArgs = [];

    Cashier::formatCurrencyUsing(function ($amount, $currency) use (&$capturedArgs) {
        $capturedArgs = ['amount' => $amount, 'currency' => $currency];

        return 'formatted';
    });

    Cashier::formatAmount(150.75, 'EUR');

    expect($capturedArgs['amount'])->toBe(150.75);
    expect($capturedArgs['currency'])->toBe('EUR');
});
