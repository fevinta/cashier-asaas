<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Asaas;
use Fevinta\CashierAsaas\Payment;
use Fevinta\CashierAsaas\Tests\Concerns\MocksAsaasApi;
use Fevinta\CashierAsaas\Tests\Fixtures\AsaasApiFixtures;
use Fevinta\CashierAsaas\Tests\Fixtures\User;
use Illuminate\Support\Facades\Http;

uses(MocksAsaasApi::class);

beforeEach(function () {
    $this->mockAsaasApi();

    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'cpf_cnpj' => generateTestCpf(),
        'asaas_id' => 'cus_test123',
    ]);
});

test('user can charge with pix', function () {
    $paymentId = 'pay_'.uniqid();

    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response(
            AsaasApiFixtures::customer(['id' => 'cus_test123']),
            200
        ),
        Asaas::baseUrl().'/payments' => Http::response(
            AsaasApiFixtures::pixPayment([
                'id' => $paymentId,
                'customer' => 'cus_test123',
                'value' => 100.00,
            ]),
            200
        ),
    ]);

    $payment = $this->user->chargeWithPix(100.00);

    expect($payment)->toBeInstanceOf(Payment::class);
    expect($payment->billing_type)->toBe('PIX');
    expect((float) $payment->value)->toBe(100.00);
    expect($payment->pix_qrcode)->not->toBeNull();
});

test('user can charge with boleto', function () {
    $paymentId = 'pay_'.uniqid();

    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response(
            AsaasApiFixtures::customer(['id' => 'cus_test123']),
            200
        ),
        Asaas::baseUrl().'/payments' => Http::response(
            AsaasApiFixtures::boletoPayment([
                'id' => $paymentId,
                'customer' => 'cus_test123',
                'value' => 150.00,
            ]),
            200
        ),
    ]);

    $payment = $this->user->chargeWithBoleto(150.00);

    expect($payment)->toBeInstanceOf(Payment::class);
    expect($payment->billing_type)->toBe('BOLETO');
    expect((float) $payment->value)->toBe(150.00);
    expect($payment->bank_slip_url)->not->toBeNull();
});

test('user can charge with credit card token', function () {
    $paymentId = 'pay_'.uniqid();

    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response(
            AsaasApiFixtures::customer(['id' => 'cus_test123']),
            200
        ),
        Asaas::baseUrl().'/payments' => Http::response(
            AsaasApiFixtures::creditCardPayment([
                'id' => $paymentId,
                'customer' => 'cus_test123',
                'value' => 200.00,
            ]),
            200
        ),
    ]);

    $payment = $this->user->chargeWithCreditCard(200.00, 'cc_token_123');

    expect($payment)->toBeInstanceOf(Payment::class);
    expect($payment->billing_type)->toBe('CREDIT_CARD');
    expect((float) $payment->value)->toBe(200.00);
    expect($payment->status)->toBe('CONFIRMED');
});

test('user can charge installments', function () {
    $paymentId = 'pay_'.uniqid();

    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response(
            AsaasApiFixtures::customer(['id' => 'cus_test123']),
            200
        ),
        Asaas::baseUrl().'/payments' => Http::response(
            AsaasApiFixtures::payment([
                'id' => $paymentId,
                'customer' => 'cus_test123',
                'value' => 100.00,
                'installmentCount' => 6,
            ]),
            200
        ),
    ]);

    $payment = $this->user->chargeInstallments(600.00, 6, [
        'creditCardToken' => 'cc_token_123',
    ]);

    expect($payment)->toBeInstanceOf(Payment::class);
});

test('user can refund payment', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_123/refund' => Http::response(
            AsaasApiFixtures::payment([
                'id' => 'pay_123',
                'status' => 'REFUNDED',
            ]),
            200
        ),
    ]);

    $result = $this->user->refund('pay_123');

    expect($result['status'])->toBe('REFUNDED');
});

test('user can refund partial amount', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_123/refund' => Http::response(
            AsaasApiFixtures::payment([
                'id' => 'pay_123',
                'status' => 'REFUNDED',
            ]),
            200
        ),
    ]);

    $result = $this->user->refund('pay_123', 50.00, 'Partial refund');

    expect($result)->toBeArray();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/refund')
            && $request['value'] === 50.00
            && $request['description'] === 'Partial refund';
    });
});

test('charge creates payment record', function () {
    $paymentId = 'pay_'.uniqid();

    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response(
            AsaasApiFixtures::customer(['id' => 'cus_test123']),
            200
        ),
        Asaas::baseUrl().'/payments' => Http::response(
            AsaasApiFixtures::pixPayment([
                'id' => $paymentId,
                'customer' => 'cus_test123',
                'value' => 99.90,
            ]),
            200
        ),
    ]);

    $payment = $this->user->chargeWithPix(99.90);

    expect($payment->exists)->toBeTrue();
    expect($payment->customer_id)->toBe($this->user->id);
    expect($payment->asaas_id)->toBe($paymentId);

    // Verify it's in the database
    $dbPayment = Payment::where('asaas_id', $paymentId)->first();
    expect($dbPayment)->not->toBeNull();
});

test('user payments relationship returns payments', function () {
    Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_1',
        'billing_type' => 'PIX',
        'value' => 99.90,
        'net_value' => 97.90,
        'status' => 'CONFIRMED',
        'due_date' => now(),
    ]);

    Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_2',
        'billing_type' => 'BOLETO',
        'value' => 149.90,
        'net_value' => 147.90,
        'status' => 'PENDING',
        'due_date' => now()->addDays(3),
    ]);

    expect($this->user->payments)->toHaveCount(2);
});

test('charge with options passes additional data', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response(
            AsaasApiFixtures::customer(['id' => 'cus_test123']),
            200
        ),
        Asaas::baseUrl().'/payments' => Http::response(
            AsaasApiFixtures::pixPayment(['id' => 'pay_123']),
            200
        ),
    ]);

    $this->user->chargeWithPix(100.00, [
        'description' => 'Premium upgrade',
        'externalReference' => 'order_456',
    ]);

    Http::assertSent(function ($request) {
        // Only check payment requests, not customer requests
        if (! str_contains($request->url(), '/payments')) {
            return true;
        }

        return $request['description'] === 'Premium upgrade'
            && $request['externalReference'] === 'order_456';
    });
});

test('charge creates customer if not exists', function () {
    $userWithoutAsaas = User::create([
        'name' => 'New User',
        'email' => 'new@example.com',
        'cpf_cnpj' => generateTestCpf(),
    ]);

    Http::fake([
        Asaas::baseUrl().'/customers' => Http::response(
            AsaasApiFixtures::customer(['id' => 'cus_new']),
            200
        ),
        Asaas::baseUrl().'/payments' => Http::response(
            AsaasApiFixtures::pixPayment(['id' => 'pay_new']),
            200
        ),
    ]);

    $payment = $userWithoutAsaas->chargeWithPix(100.00);

    expect($userWithoutAsaas->fresh()->asaas_id)->toBe('cus_new');
    expect($payment)->toBeInstanceOf(Payment::class);
});

test('charge with credit card data without token', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response(
            AsaasApiFixtures::customer(['id' => 'cus_test123']),
            200
        ),
        Asaas::baseUrl().'/payments' => Http::response(
            AsaasApiFixtures::creditCardPayment(['id' => 'pay_cc_123']),
            200
        ),
    ]);

    $payment = $this->user->charge(250.00, \Fevinta\CashierAsaas\Enums\BillingType::CREDIT_CARD, [
        'creditCard' => [
            'holderName' => 'Test User',
            'number' => '4242424242424242',
            'expiryMonth' => '12',
            'expiryYear' => '2030',
            'ccv' => '123',
        ],
        'creditCardHolderInfo' => [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'cpfCnpj' => '12345678909',
        ],
    ]);

    expect($payment->billing_type)->toBe('CREDIT_CARD');
});

test('charge installments with credit card data without token', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response(
            AsaasApiFixtures::customer(['id' => 'cus_test123']),
            200
        ),
        Asaas::baseUrl().'/payments' => Http::response(
            AsaasApiFixtures::payment([
                'id' => 'pay_installment_cc',
                'billingType' => 'CREDIT_CARD',
            ]),
            200
        ),
    ]);

    $payment = $this->user->chargeInstallments(1200.00, 12, [
        'creditCard' => [
            'holderName' => 'Test User',
            'number' => '4242424242424242',
            'expiryMonth' => '12',
            'expiryYear' => '2030',
            'ccv' => '123',
        ],
        'creditCardHolderInfo' => [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'cpfCnpj' => '12345678909',
        ],
    ]);

    expect($payment)->toBeInstanceOf(Payment::class);
});

test('asaasPayments returns empty when no asaas id', function () {
    $userWithoutAsaas = User::create([
        'name' => 'No Asaas User',
        'email' => 'noasaas@example.com',
        'cpf_cnpj' => generateTestCpf(),
    ]);

    $result = $userWithoutAsaas->asaasPayments();

    expect($result['data'])->toBe([]);
    expect($result['totalCount'])->toBe(0);
});

test('asaasPayments returns payments from Asaas', function () {
    Http::fake([
        Asaas::baseUrl().'/payments*' => Http::response([
            'data' => [
                ['id' => 'pay_1', 'value' => 99.90, 'status' => 'CONFIRMED'],
                ['id' => 'pay_2', 'value' => 149.90, 'status' => 'PENDING'],
            ],
            'totalCount' => 2,
        ], 200),
    ]);

    $result = $this->user->asaasPayments();

    expect($result['data'])->toHaveCount(2);
    expect($result['totalCount'])->toBe(2);
});

test('asaasPayments with filters', function () {
    Http::fake([
        Asaas::baseUrl().'/payments*' => Http::response([
            'data' => [
                ['id' => 'pay_1', 'value' => 99.90, 'status' => 'PENDING'],
            ],
            'totalCount' => 1,
        ], 200),
    ]);

    $result = $this->user->asaasPayments(['status' => 'PENDING']);

    expect($result['data'])->toHaveCount(1);
});
