<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Events\InvoiceAuthorized;
use Fevinta\CashierAsaas\Events\InvoiceCanceled;
use Fevinta\CashierAsaas\Events\InvoiceCancellationDenied;
use Fevinta\CashierAsaas\Events\InvoiceCreated;
use Fevinta\CashierAsaas\Events\InvoiceError;
use Fevinta\CashierAsaas\Events\InvoiceSynchronized;
use Fevinta\CashierAsaas\Events\InvoiceUpdated;
use Fevinta\CashierAsaas\Invoice;
use Fevinta\CashierAsaas\Payment;
use Fevinta\CashierAsaas\Tests\Fixtures\User;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake();

    $this->user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'cpf_cnpj' => generateTestCpf(),
        'asaas_id' => 'cus_test123',
    ]);

    $this->payment = Payment::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'pay_test123',
        'billing_type' => 'PIX',
        'value' => 100.00,
        'net_value' => 98.00,
        'status' => 'CONFIRMED',
        'due_date' => now(),
        'payment_date' => now(),
    ]);
});

test('invoice created webhook creates invoice record with payment link', function () {
    $response = $this->postJson('/asaas/webhook', invoiceWebhookPayload('INVOICE_CREATED', [
        'id' => 'inv_new123',
        'payment' => 'pay_test123',
        'status' => 'SCHEDULED',
        'value' => 100.00,
        'netValue' => 100.00,
        'serviceDescription' => 'Consulting Services',
        'effectiveDate' => now()->addDays(5)->format('Y-m-d'),
    ]));

    $response->assertStatus(200);

    $invoice = Invoice::where('asaas_id', 'inv_new123')->first();
    expect($invoice)->not->toBeNull();
    expect($invoice->payment_id)->toBe($this->payment->id);
    expect($invoice->customer_id)->toBe($this->user->id);
    expect((float) $invoice->value)->toBe(100.00);
});

test('invoice created webhook creates invoice with externalReference', function () {
    $response = $this->postJson('/asaas/webhook', invoiceWebhookPayload('INVOICE_CREATED', [
        'id' => 'inv_ext_ref123',
        'status' => 'SCHEDULED',
        'value' => 50.00,
        'netValue' => 50.00,
        'serviceDescription' => 'Test Service',
        'externalReference' => (string) $this->user->id,
    ]));

    $response->assertStatus(200);

    $invoice = Invoice::where('asaas_id', 'inv_ext_ref123')->first();
    expect($invoice)->not->toBeNull();
    expect($invoice->customer_id)->toBe($this->user->id);
});

test('invoice created webhook creates invoice with customer asaas_id', function () {
    $response = $this->postJson('/asaas/webhook', invoiceWebhookPayload('INVOICE_CREATED', [
        'id' => 'inv_cus123',
        'customer' => 'cus_test123',
        'status' => 'SCHEDULED',
        'value' => 75.00,
        'netValue' => 75.00,
        'serviceDescription' => 'Service',
    ]));

    $response->assertStatus(200);

    $invoice = Invoice::where('asaas_id', 'inv_cus123')->first();
    expect($invoice)->not->toBeNull();
    expect($invoice->customer_id)->toBe($this->user->id);
});

test('invoice created with empty id returns 200 but does not create invoice', function () {
    $response = $this->postJson('/asaas/webhook', invoiceWebhookPayload('INVOICE_CREATED', [
        'id' => '',
        'status' => 'SCHEDULED',
        'value' => 100.00,
    ]));

    $response->assertStatus(200);
    Event::assertNotDispatched(InvoiceCreated::class);
});

test('invoice created webhook dispatches InvoiceCreated event', function () {
    $this->postJson('/asaas/webhook', invoiceWebhookPayload('INVOICE_CREATED', [
        'id' => 'inv_event123',
        'payment' => 'pay_test123',
        'status' => 'SCHEDULED',
        'value' => 100.00,
        'serviceDescription' => 'Test',
    ]));

    Event::assertDispatched(InvoiceCreated::class, function ($event) {
        return $event->invoice->asaas_id === 'inv_event123';
    });
});

test('invoice updated webhook updates invoice data', function () {
    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'payment_id' => $this->payment->id,
        'asaas_id' => 'inv_test123',
        'status' => 'SCHEDULED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Original Service',
    ]);

    $response = $this->postJson('/asaas/webhook', invoiceWebhookPayload('INVOICE_UPDATED', [
        'id' => 'inv_test123',
        'status' => 'SCHEDULED',
        'value' => 150.00,
        'netValue' => 145.00,
        'serviceDescription' => 'Updated Service',
    ]));

    $response->assertStatus(200);

    $invoice->refresh();
    expect((float) $invoice->value)->toBe(150.00);
    expect((float) $invoice->net_value)->toBe(145.00);
});

test('invoice updated webhook dispatches InvoiceUpdated event', function () {
    Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'SCHEDULED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test',
    ]);

    $this->postJson('/asaas/webhook', invoiceWebhookPayload('INVOICE_UPDATED', [
        'id' => 'inv_test123',
        'status' => 'SCHEDULED',
        'value' => 100.00,
    ]));

    Event::assertDispatched(InvoiceUpdated::class);
});

test('invoice synchronized webhook updates invoice status', function () {
    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'SCHEDULED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test',
    ]);

    $response = $this->postJson('/asaas/webhook', invoiceWebhookPayload('INVOICE_SYNCHRONIZED', [
        'id' => 'inv_test123',
        'status' => 'SYNCHRONIZED',
        'rpsNumber' => '12345',
        'rpsSerie' => 'A1',
    ]));

    $response->assertStatus(200);

    $invoice->refresh();
    expect($invoice->status)->toBe('SYNCHRONIZED');
});

test('invoice synchronized webhook dispatches InvoiceSynchronized event', function () {
    Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'SCHEDULED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test',
    ]);

    $this->postJson('/asaas/webhook', invoiceWebhookPayload('INVOICE_SYNCHRONIZED', [
        'id' => 'inv_test123',
        'status' => 'SYNCHRONIZED',
    ]));

    Event::assertDispatched(InvoiceSynchronized::class);
});

test('invoice authorized webhook updates invoice status', function () {
    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'SYNCHRONIZED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test',
    ]);

    $response = $this->postJson('/asaas/webhook', invoiceWebhookPayload('INVOICE_AUTHORIZED', [
        'id' => 'inv_test123',
        'status' => 'AUTHORIZED',
        'number' => '00001',
        'verificationCode' => 'ABCD1234',
        'pdfUrl' => 'https://sandbox.asaas.com/invoices/pdf/inv_test123',
        'xmlUrl' => 'https://sandbox.asaas.com/invoices/xml/inv_test123',
    ]));

    $response->assertStatus(200);

    $invoice->refresh();
    expect($invoice->status)->toBe('AUTHORIZED');
    expect($invoice->invoice_number)->toBe('00001');
    expect($invoice->verification_code)->toBe('ABCD1234');
    expect($invoice->pdf_url)->toBe('https://sandbox.asaas.com/invoices/pdf/inv_test123');
    expect($invoice->xml_url)->toBe('https://sandbox.asaas.com/invoices/xml/inv_test123');
});

test('invoice authorized webhook dispatches InvoiceAuthorized event', function () {
    Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'SYNCHRONIZED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test',
    ]);

    $this->postJson('/asaas/webhook', invoiceWebhookPayload('INVOICE_AUTHORIZED', [
        'id' => 'inv_test123',
        'status' => 'AUTHORIZED',
    ]));

    Event::assertDispatched(InvoiceAuthorized::class);
});

test('invoice canceled webhook updates status', function () {
    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'AUTHORIZED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test',
    ]);

    $response = $this->postJson('/asaas/webhook', invoiceWebhookPayload('INVOICE_CANCELED', [
        'id' => 'inv_test123',
        'status' => 'CANCELED',
    ]));

    $response->assertStatus(200);

    $invoice->refresh();
    expect($invoice->status)->toBe('CANCELED');
});

test('invoice canceled webhook dispatches InvoiceCanceled event', function () {
    Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'AUTHORIZED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test',
    ]);

    $this->postJson('/asaas/webhook', invoiceWebhookPayload('INVOICE_CANCELED', [
        'id' => 'inv_test123',
        'status' => 'CANCELED',
    ]));

    Event::assertDispatched(InvoiceCanceled::class);
});

test('invoice processing cancellation webhook updates status', function () {
    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'AUTHORIZED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test',
    ]);

    $response = $this->postJson('/asaas/webhook', invoiceWebhookPayload('INVOICE_PROCESSING_CANCELLATION', [
        'id' => 'inv_test123',
        'status' => 'PROCESSING_CANCELLATION',
    ]));

    $response->assertStatus(200);

    $invoice->refresh();
    expect($invoice->status)->toBe('PROCESSING_CANCELLATION');
});

test('invoice cancellation denied webhook updates status', function () {
    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'PROCESSING_CANCELLATION',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test',
    ]);

    $response = $this->postJson('/asaas/webhook', invoiceWebhookPayload('INVOICE_CANCELLATION_DENIED', [
        'id' => 'inv_test123',
        'status' => 'CANCELLATION_DENIED',
    ]));

    $response->assertStatus(200);

    $invoice->refresh();
    expect($invoice->status)->toBe('CANCELLATION_DENIED');
});

test('invoice cancellation denied webhook dispatches InvoiceCancellationDenied event', function () {
    Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'PROCESSING_CANCELLATION',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test',
    ]);

    $this->postJson('/asaas/webhook', invoiceWebhookPayload('INVOICE_CANCELLATION_DENIED', [
        'id' => 'inv_test123',
        'status' => 'CANCELLATION_DENIED',
    ]));

    Event::assertDispatched(InvoiceCancellationDenied::class);
});

test('invoice error webhook updates status', function () {
    $invoice = Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'SYNCHRONIZED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test',
    ]);

    $response = $this->postJson('/asaas/webhook', invoiceWebhookPayload('INVOICE_ERROR', [
        'id' => 'inv_test123',
        'status' => 'ERROR',
    ]));

    $response->assertStatus(200);

    $invoice->refresh();
    expect($invoice->status)->toBe('ERROR');
});

test('invoice error webhook dispatches InvoiceError event', function () {
    Invoice::create([
        'customer_id' => $this->user->id,
        'asaas_id' => 'inv_test123',
        'status' => 'SYNCHRONIZED',
        'value' => 100.00,
        'net_value' => 100.00,
        'effective_date' => now(),
        'service_description' => 'Test',
    ]);

    $this->postJson('/asaas/webhook', invoiceWebhookPayload('INVOICE_ERROR', [
        'id' => 'inv_test123',
        'status' => 'ERROR',
    ]));

    Event::assertDispatched(InvoiceError::class);
});

test('invoice updated does nothing when invoice not found', function () {
    $response = $this->postJson('/asaas/webhook', invoiceWebhookPayload('INVOICE_UPDATED', [
        'id' => 'inv_nonexistent',
        'status' => 'SCHEDULED',
    ]));

    $response->assertStatus(200);
    Event::assertNotDispatched(InvoiceUpdated::class);
});

test('invoice updated does nothing when invoice id is null', function () {
    $response = $this->postJson('/asaas/webhook', invoiceWebhookPayload('INVOICE_UPDATED', [
        'status' => 'SCHEDULED',
    ]));

    $response->assertStatus(200);
    Event::assertNotDispatched(InvoiceUpdated::class);
});
