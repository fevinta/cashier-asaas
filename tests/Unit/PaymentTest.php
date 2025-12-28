<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Asaas;
use Fevinta\CashierAsaas\Payment;
use Fevinta\CashierAsaas\Subscription;
use Fevinta\CashierAsaas\Tests\Fixtures\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'cpf_cnpj' => generateTestCpf(),
        'asaas_id' => 'cus_test123',
    ]);
});

test('payment is paid with RECEIVED status', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'RECEIVED',
        'due_date' => now()->addDays(3),
        'payment_date' => now(),
    ]);

    expect($payment->isPaid())->toBeTrue();
    expect($payment->isPending())->toBeFalse();
});

test('payment is paid with CONFIRMED status', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'CREDIT_CARD',
        'value' => 99.90,
        'net_value' => 95.90,
        'status' => 'CONFIRMED',
        'due_date' => now(),
        'payment_date' => now(),
        'confirmed_date' => now(),
    ]);

    expect($payment->isPaid())->toBeTrue();
});

test('payment is paid with RECEIVED_IN_CASH status', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'BOLETO',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'RECEIVED_IN_CASH',
        'due_date' => now()->addDays(3),
        'payment_date' => now(),
    ]);

    expect($payment->isPaid())->toBeTrue();
});

test('payment is pending', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now()->addDays(3),
    ]);

    expect($payment->isPending())->toBeTrue();
    expect($payment->isPaid())->toBeFalse();
});

test('payment is overdue', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'BOLETO',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'OVERDUE',
        'due_date' => now()->subDays(1),
    ]);

    expect($payment->isOverdue())->toBeTrue();
    expect($payment->isPaid())->toBeFalse();
});

test('payment is refunded', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'REFUNDED',
        'due_date' => now()->subDays(7),
        'payment_date' => now()->subDays(7),
        'refunded_at' => now(),
    ]);

    expect($payment->isRefunded())->toBeTrue();
});

test('payment is refunded when refunded_at is set', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'RECEIVED',
        'due_date' => now()->subDays(7),
        'payment_date' => now()->subDays(7),
        'refunded_at' => now(),
    ]);

    expect($payment->isRefunded())->toBeTrue();
});

test('payment url returns invoice url', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now()->addDays(3),
        'invoice_url' => 'https://sandbox.asaas.com/i/123',
    ]);

    expect($payment->paymentUrl())->toBe('https://sandbox.asaas.com/i/123');
});

test('payment url returns bank slip url as fallback', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'BOLETO',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now()->addDays(3),
        'bank_slip_url' => 'https://sandbox.asaas.com/b/123',
    ]);

    expect($payment->paymentUrl())->toBe('https://sandbox.asaas.com/b/123');
});

test('pix data accessors work correctly', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now()->addDays(3),
        'pix_qrcode' => 'data:image/png;base64,abc123',
        'pix_copy_paste' => '00020126580014br.gov.bcb.pix',
    ]);

    expect($payment->pixQrCode())->toBe('data:image/png;base64,abc123');
    expect($payment->pixCopyPaste())->toBe('00020126580014br.gov.bcb.pix');
});

test('payment belongs to owner', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now()->addDays(3),
    ]);

    expect($payment->owner)->toBeInstanceOf(User::class);
    expect($payment->owner->id)->toBe($this->user->id);
});

test('payment belongs to subscription', function () {
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

    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'subscription_id' => $subscription->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now()->addDays(3),
    ]);

    expect($payment->subscription)->toBeInstanceOf(Subscription::class);
    expect($payment->subscription->id)->toBe($subscription->id);
});

test('payment scope filters paid payments', function () {
    Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_paid',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'CONFIRMED',
        'due_date' => now(),
        'payment_date' => now(),
    ]);

    Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_pending',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now()->addDays(3),
    ]);

    expect(Payment::paid()->count())->toBe(1);
    expect(Payment::paid()->first()->asaas_id)->toBe('pay_paid');
});

test('payment scope filters pending payments', function () {
    Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_pending',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now()->addDays(3),
    ]);

    expect(Payment::pending()->count())->toBe(1);
});

test('payment scope filters overdue payments', function () {
    Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_overdue',
        'billing_type' => 'BOLETO',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'OVERDUE',
        'due_date' => now()->subDays(1),
    ]);

    expect(Payment::overdue()->count())->toBe(1);
});

test('payment casts values correctly', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => '99.90',
        'net_value' => '97.90',
        'status' => 'PENDING',
        'due_date' => now()->addDays(3),
        'metadata' => ['order_id' => 123],
    ]);

    // decimal:2 cast returns a string with 2 decimal places
    expect($payment->value)->toBeString();
    expect($payment->value)->toBe('99.90');
    expect($payment->net_value)->toBeString();
    expect($payment->net_value)->toBe('97.90');
    expect($payment->due_date)->toBeInstanceOf(\Carbon\Carbon::class);
    expect($payment->metadata)->toBeArray();
    expect($payment->metadata['order_id'])->toBe(123);
});

test('refund refunds payment successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_test123/refund' => Http::response([
            'id' => 'pay_test123',
            'status' => 'REFUNDED',
        ], 200),
    ]);

    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'RECEIVED',
        'due_date' => now()->subDays(3),
        'payment_date' => now()->subDays(3),
    ]);

    $result = $payment->refund();

    expect($result['status'])->toBe('REFUNDED');
    expect($payment->fresh()->status)->toBe('REFUNDED');
    expect($payment->fresh()->refunded_at)->not->toBeNull();
});

test('refund with partial amount works correctly', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_test123/refund' => Http::response([
            'id' => 'pay_test123',
            'status' => 'REFUNDED',
            'refundedValue' => 50.00,
        ], 200),
    ]);

    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'RECEIVED',
        'due_date' => now()->subDays(3),
        'payment_date' => now()->subDays(3),
    ]);

    $result = $payment->refund(50.00);

    expect($result['refundedValue'])->toBe(50);
});

test('refund with description works correctly', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_test123/refund' => Http::response([
            'id' => 'pay_test123',
            'status' => 'REFUNDED',
        ], 200),
    ]);

    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'RECEIVED',
        'due_date' => now()->subDays(3),
        'payment_date' => now()->subDays(3),
    ]);

    $result = $payment->refund(null, 'Customer requested refund');

    expect($result['status'])->toBe('REFUNDED');
});

test('refund with amount and description works correctly', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_test123/refund' => Http::response([
            'id' => 'pay_test123',
            'status' => 'REFUNDED',
        ], 200),
    ]);

    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'RECEIVED',
        'due_date' => now()->subDays(3),
        'payment_date' => now()->subDays(3),
    ]);

    $result = $payment->refund(25.00, 'Partial refund');

    expect($result['status'])->toBe('REFUNDED');
});

test('asAsaasPayment returns payment data from Asaas', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_test123' => Http::response([
            'id' => 'pay_test123',
            'customer' => 'cus_test123',
            'value' => 99.90,
            'netValue' => 97.90,
            'status' => 'CONFIRMED',
            'billingType' => 'PIX',
        ], 200),
    ]);

    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now()->addDays(3),
    ]);

    $result = $payment->asAsaasPayment();

    expect($result['id'])->toBe('pay_test123');
    expect($result['status'])->toBe('CONFIRMED');
});

test('syncFromAsaas syncs payment data from Asaas', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_test123' => Http::response([
            'id' => 'pay_test123',
            'customer' => 'cus_test123',
            'value' => 150.00,
            'netValue' => 147.50,
            'status' => 'CONFIRMED',
            'billingType' => 'PIX',
            'paymentDate' => now()->format('Y-m-d'),
        ], 200),
    ]);

    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now()->addDays(3),
    ]);

    $result = $payment->syncFromAsaas();

    expect($result)->toBeInstanceOf(Payment::class);
    expect($payment->fresh()->status)->toBe('CONFIRMED');
    expect($payment->fresh()->value)->toBe('150.00');
    expect($payment->fresh()->net_value)->toBe('147.50');
    expect($payment->fresh()->payment_date)->not->toBeNull();
});

test('syncFromAsaas handles missing netValue', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_test123' => Http::response([
            'id' => 'pay_test123',
            'customer' => 'cus_test123',
            'value' => 150.00,
            'status' => 'PENDING',
            'billingType' => 'PIX',
        ], 200),
    ]);

    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now()->addDays(3),
    ]);

    $payment->syncFromAsaas();

    // net_value should remain unchanged when not provided
    expect($payment->fresh()->net_value)->toBe('97.90');
});

test('syncFromAsaas handles missing paymentDate', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_test123' => Http::response([
            'id' => 'pay_test123',
            'customer' => 'cus_test123',
            'value' => 99.90,
            'netValue' => 97.90,
            'status' => 'PENDING',
            'billingType' => 'PIX',
        ], 200),
    ]);

    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now()->addDays(3),
    ]);

    $payment->syncFromAsaas();

    expect($payment->fresh()->payment_date)->toBeNull();
});

test('payment url returns null when no urls are set', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'PENDING',
        'due_date' => now()->addDays(3),
    ]);

    expect($payment->paymentUrl())->toBeNull();
});
