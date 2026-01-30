<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Asaas;
use Fevinta\CashierAsaas\Invoice;
use Fevinta\CashierAsaas\Payment;
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

test('invoice is scheduled', function () {
    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'SCHEDULED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
    ]);

    expect($invoice->isScheduled())->toBeTrue();
    expect($invoice->isAuthorized())->toBeFalse();
});

test('invoice is synchronized', function () {
    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'SYNCHRONIZED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
    ]);

    expect($invoice->isSynchronized())->toBeTrue();
    expect($invoice->isScheduled())->toBeFalse();
});

test('invoice is authorized', function () {
    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'AUTHORIZED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
    ]);

    expect($invoice->isAuthorized())->toBeTrue();
    expect($invoice->isCanceled())->toBeFalse();
});

test('invoice is canceled', function () {
    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'CANCELED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
    ]);

    expect($invoice->isCanceled())->toBeTrue();
    expect($invoice->isAuthorized())->toBeFalse();
});

test('invoice has error', function () {
    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'ERROR',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
    ]);

    expect($invoice->hasError())->toBeTrue();
    expect($invoice->isAuthorized())->toBeFalse();
});

test('invoice is processing cancellation', function () {
    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'PROCESSING_CANCELLATION',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
    ]);

    expect($invoice->isProcessingCancellation())->toBeTrue();
    expect($invoice->isCanceled())->toBeFalse();
});

test('invoice cancellation is denied', function () {
    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'CANCELLATION_DENIED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
    ]);

    expect($invoice->isCancellationDenied())->toBeTrue();
    expect($invoice->isCanceled())->toBeFalse();
});

test('pdfUrl returns pdf url', function () {
    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'AUTHORIZED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
        'pdf_url' => 'https://sandbox.asaas.com/invoices/pdf/inv_test123',
    ]);

    expect($invoice->pdfUrl())->toBe('https://sandbox.asaas.com/invoices/pdf/inv_test123');
});

test('pdfUrl returns null when not set', function () {
    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'SCHEDULED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
    ]);

    expect($invoice->pdfUrl())->toBeNull();
});

test('xmlUrl returns xml url', function () {
    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'AUTHORIZED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
        'xml_url' => 'https://sandbox.asaas.com/invoices/xml/inv_test123',
    ]);

    expect($invoice->xmlUrl())->toBe('https://sandbox.asaas.com/invoices/xml/inv_test123');
});

test('xmlUrl returns null when not set', function () {
    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'SCHEDULED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
    ]);

    expect($invoice->xmlUrl())->toBeNull();
});

test('invoice belongs to owner', function () {
    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'SCHEDULED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
    ]);

    expect($invoice->owner)->toBeInstanceOf(User::class);
    expect($invoice->owner->id)->toBe($this->user->id);
});

test('invoice belongs to payment', function () {
    $payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 100.00,
        'net_value' => 98.00,
        'status' => 'CONFIRMED',
        'due_date' => now(),
    ]);

    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'payment_id' => $payment->id,
        'asaas_id' => 'inv_test123',
        'status' => 'SCHEDULED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
    ]);

    expect($invoice->payment)->toBeInstanceOf(Payment::class);
    expect($invoice->payment->id)->toBe($payment->id);
});

test('invoice scope filters scheduled invoices', function () {
    Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_scheduled',
        'status' => 'SCHEDULED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
    ]);

    Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_authorized',
        'status' => 'AUTHORIZED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
    ]);

    expect(Invoice::scheduled()->count())->toBe(1);
    expect(Invoice::scheduled()->first()->asaas_id)->toBe('inv_scheduled');
});

test('invoice scope filters authorized invoices', function () {
    Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_authorized',
        'status' => 'AUTHORIZED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
    ]);

    expect(Invoice::authorized()->count())->toBe(1);
});

test('invoice scope filters canceled invoices', function () {
    Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_canceled',
        'status' => 'CANCELED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
    ]);

    expect(Invoice::canceled()->count())->toBe(1);
});

test('invoice scope filters invoices with error', function () {
    Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_error',
        'status' => 'ERROR',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
    ]);

    expect(Invoice::withError()->count())->toBe(1);
});

test('invoice scope filters synchronized invoices', function () {
    Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_synced',
        'status' => 'SYNCHRONIZED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
    ]);

    expect(Invoice::synchronized()->count())->toBe(1);
});

test('invoice casts values correctly', function () {
    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'AUTHORIZED',
        'value' => '250.50',
        'deductions' => '10.25',
        'net_value' => '240.25',
        'service_description' => 'Test Service',
        'effective_date' => now(),
        'taxes' => ['iss' => 5.0, 'cofins' => 3.0],
        'metadata' => ['order_id' => 456],
    ]);

    // decimal:2 cast returns a string with 2 decimal places
    expect($invoice->value)->toBeString();
    expect($invoice->value)->toBe('250.50');
    expect($invoice->deductions)->toBeString();
    expect($invoice->deductions)->toBe('10.25');
    expect($invoice->net_value)->toBeString();
    expect($invoice->net_value)->toBe('240.25');
    expect($invoice->effective_date)->toBeInstanceOf(\Carbon\Carbon::class);
    expect($invoice->taxes)->toBeArray();
    expect($invoice->taxes['iss'])->toBe(5);
    expect($invoice->metadata)->toBeArray();
    expect($invoice->metadata['order_id'])->toBe(456);
});

test('authorize authorizes invoice successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices/inv_test123/authorize' => Http::response([
            'id' => 'inv_test123',
            'status' => 'SYNCHRONIZED',
        ], 200),
    ]);

    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'SCHEDULED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
    ]);

    $result = $invoice->authorize();

    expect($result['status'])->toBe('SYNCHRONIZED');
    expect($invoice->fresh()->status)->toBe('SYNCHRONIZED');
});

test('cancel cancels invoice successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices/inv_test123/cancel' => Http::response([
            'id' => 'inv_test123',
            'status' => 'PROCESSING_CANCELLATION',
        ], 200),
    ]);

    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'AUTHORIZED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
    ]);

    $result = $invoice->cancel();

    expect($result['status'])->toBe('PROCESSING_CANCELLATION');
    expect($invoice->fresh()->status)->toBe('PROCESSING_CANCELLATION');
});

test('asAsaasInvoice returns invoice data from Asaas', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices/inv_test123' => Http::response([
            'id' => 'inv_test123',
            'status' => 'AUTHORIZED',
            'value' => 100.00,
            'netValue' => 95.00,
        ], 200),
    ]);

    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'SCHEDULED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
    ]);

    $result = $invoice->asAsaasInvoice();

    expect($result['id'])->toBe('inv_test123');
    expect($result['status'])->toBe('AUTHORIZED');
});

test('syncFromAsaas syncs invoice data from Asaas', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices/inv_test123' => Http::response([
            'id' => 'inv_test123',
            'status' => 'AUTHORIZED',
            'value' => 150.00,
            'netValue' => 142.50,
            'rpsSerie' => 'A1',
            'rpsNumber' => '12345',
            'number' => '00001',
            'verificationCode' => 'ABCD1234',
            'pdfUrl' => 'https://sandbox.asaas.com/invoices/pdf/inv_test123',
            'xmlUrl' => 'https://sandbox.asaas.com/invoices/xml/inv_test123',
            'effectiveDate' => '2024-12-15',
        ], 200),
    ]);

    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'SCHEDULED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
    ]);

    $result = $invoice->syncFromAsaas();

    expect($result)->toBeInstanceOf(Invoice::class);
    expect($invoice->fresh()->status)->toBe('AUTHORIZED');
    expect($invoice->fresh()->value)->toBe('150.00');
    expect($invoice->fresh()->net_value)->toBe('142.50');
    expect($invoice->fresh()->invoice_number)->toBe('00001');
    expect($invoice->fresh()->verification_code)->toBe('ABCD1234');
    expect($invoice->fresh()->pdf_url)->toBe('https://sandbox.asaas.com/invoices/pdf/inv_test123');
    expect($invoice->fresh()->xml_url)->toBe('https://sandbox.asaas.com/invoices/xml/inv_test123');
    expect($invoice->fresh()->effective_date)->not->toBeNull();
});

test('syncFromAsaas handles missing optional fields', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices/inv_test123' => Http::response([
            'id' => 'inv_test123',
            'status' => 'SCHEDULED',
            'value' => 100.00,
        ], 200),
    ]);

    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'SCHEDULED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test Service',
    ]);

    $invoice->syncFromAsaas();

    // net_value should remain unchanged when not provided
    expect($invoice->fresh()->net_value)->toBe('100.00');
});
