<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Api\PaymentApi;
use Fevinta\CashierAsaas\Asaas;
use Fevinta\CashierAsaas\Exceptions\AsaasApiException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('create creates payment successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/payments' => Http::response([
            'id' => 'pay_123',
            'customer' => 'cus_123',
            'billingType' => 'PIX',
            'value' => 99.90,
            'status' => 'PENDING',
            'dueDate' => '2024-12-31',
        ], 200),
    ]);

    $api = new PaymentApi;
    $result = $api->create([
        'customer' => 'cus_123',
        'billingType' => 'PIX',
        'value' => 99.90,
        'dueDate' => '2024-12-31',
    ]);

    expect($result['id'])->toBe('pay_123');
    expect($result['value'])->toBe(99.90);
});

test('create throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/payments' => Http::response([
            'errors' => [['description' => 'Invalid customer', 'code' => 'INVALID_CUSTOMER']],
        ], 400),
    ]);

    $api = new PaymentApi;
    $api->create(['customer' => 'invalid']);
})->throws(AsaasApiException::class);

test('find returns payment by id', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_123' => Http::response([
            'id' => 'pay_123',
            'customer' => 'cus_123',
            'value' => 99.90,
            'status' => 'CONFIRMED',
        ], 200),
    ]);

    $api = new PaymentApi;
    $result = $api->find('pay_123');

    expect($result['id'])->toBe('pay_123');
    expect($result['status'])->toBe('CONFIRMED');
});

test('find throws exception when payment not found', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_nonexistent' => Http::response([
            'errors' => [['description' => 'Payment not found', 'code' => 'NOT_FOUND']],
        ], 404),
    ]);

    $api = new PaymentApi;
    $api->find('pay_nonexistent');
})->throws(AsaasApiException::class);

test('update updates payment successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_123' => Http::response([
            'id' => 'pay_123',
            'value' => 150.00,
            'description' => 'Updated description',
        ], 200),
    ]);

    $api = new PaymentApi;
    $result = $api->update('pay_123', [
        'value' => 150.00,
        'description' => 'Updated description',
    ]);

    expect($result['value'])->toBe(150);
});

test('update throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_123' => Http::response([
            'errors' => [['description' => 'Cannot update confirmed payment', 'code' => 'CANNOT_UPDATE']],
        ], 400),
    ]);

    $api = new PaymentApi;
    $api->update('pay_123', ['value' => 100]);
})->throws(AsaasApiException::class);

test('delete deletes payment successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_123' => Http::response([
            'deleted' => true,
        ], 200),
    ]);

    $api = new PaymentApi;
    $result = $api->delete('pay_123');

    expect($result)->toBeTrue();
});

test('delete throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_123' => Http::response([
            'errors' => [['description' => 'Cannot delete confirmed payment', 'code' => 'CANNOT_DELETE']],
        ], 400),
    ]);

    $api = new PaymentApi;
    $api->delete('pay_123');
})->throws(AsaasApiException::class);

test('list returns payments with pagination', function () {
    Http::fake([
        Asaas::baseUrl().'/payments*' => Http::response([
            'data' => [
                ['id' => 'pay_1', 'value' => 99.90],
                ['id' => 'pay_2', 'value' => 150.00],
            ],
            'totalCount' => 2,
            'limit' => 10,
            'offset' => 0,
            'hasMore' => false,
        ], 200),
    ]);

    $api = new PaymentApi;
    $result = $api->list();

    expect($result['data'])->toHaveCount(2);
    expect($result['totalCount'])->toBe(2);
});

test('list throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/payments*' => Http::response([
            'errors' => [['description' => 'Unauthorized', 'code' => 'UNAUTHORIZED']],
        ], 401),
    ]);

    $api = new PaymentApi;
    $api->list();
})->throws(AsaasApiException::class);

test('refund refunds payment successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_123/refund' => Http::response([
            'id' => 'pay_123',
            'status' => 'REFUNDED',
            'refundedValue' => 99.90,
        ], 200),
    ]);

    $api = new PaymentApi;
    $result = $api->refund('pay_123');

    expect($result['status'])->toBe('REFUNDED');
});

test('refund with partial amount works correctly', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_123/refund' => Http::response([
            'id' => 'pay_123',
            'status' => 'REFUNDED',
            'refundedValue' => 50.00,
        ], 200),
    ]);

    $api = new PaymentApi;
    $result = $api->refund('pay_123', ['value' => 50.00]);

    expect($result['refundedValue'])->toBe(50);
});

test('refund throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_123/refund' => Http::response([
            'errors' => [['description' => 'Payment cannot be refunded', 'code' => 'CANNOT_REFUND']],
        ], 400),
    ]);

    $api = new PaymentApi;
    $api->refund('pay_123');
})->throws(AsaasApiException::class);

test('status returns payment status', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_123' => Http::response([
            'id' => 'pay_123',
            'status' => 'CONFIRMED',
        ], 200),
    ]);

    $api = new PaymentApi;
    $result = $api->status('pay_123');

    expect($result)->toBe('CONFIRMED');
});

test('identificationField returns boleto identification field', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_123/identificationField' => Http::response([
            'identificationField' => '23793.38128 60800.000003 00000.000400 1 90000000009990',
            'nossoNumero' => '12345678',
        ], 200),
    ]);

    $api = new PaymentApi;
    $result = $api->identificationField('pay_123');

    expect($result['identificationField'])->toBeString();
    expect($result['nossoNumero'])->toBe('12345678');
});

test('identificationField throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_123/identificationField' => Http::response([
            'errors' => [['description' => 'Payment is not boleto', 'code' => 'INVALID_BILLING_TYPE']],
        ], 400),
    ]);

    $api = new PaymentApi;
    $api->identificationField('pay_123');
})->throws(AsaasApiException::class);

test('pixQrCode returns PIX QR code data', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_123/pixQrCode' => Http::response([
            'encodedImage' => 'data:image/png;base64,abc123',
            'payload' => '00020126580014br.gov.bcb.pix',
            'expirationDate' => '2024-12-31T23:59:59Z',
        ], 200),
    ]);

    $api = new PaymentApi;
    $result = $api->pixQrCode('pay_123');

    expect($result['encodedImage'])->toContain('base64');
    expect($result['payload'])->toContain('pix');
});

test('pixQrCode throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_123/pixQrCode' => Http::response([
            'errors' => [['description' => 'Payment is not PIX', 'code' => 'INVALID_BILLING_TYPE']],
        ], 400),
    ]);

    $api = new PaymentApi;
    $api->pixQrCode('pay_123');
})->throws(AsaasApiException::class);

test('receiveInCash confirms payment received in cash', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_123/receiveInCash' => Http::response([
            'id' => 'pay_123',
            'status' => 'RECEIVED_IN_CASH',
            'paymentDate' => '2024-12-15',
        ], 200),
    ]);

    $api = new PaymentApi;
    $result = $api->receiveInCash('pay_123');

    expect($result['status'])->toBe('RECEIVED_IN_CASH');
});

test('receiveInCash with payment date works correctly', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_123/receiveInCash' => Http::response([
            'id' => 'pay_123',
            'status' => 'RECEIVED_IN_CASH',
            'paymentDate' => '2024-12-10',
        ], 200),
    ]);

    $api = new PaymentApi;
    $result = $api->receiveInCash('pay_123', ['paymentDate' => '2024-12-10']);

    expect($result['paymentDate'])->toBe('2024-12-10');
});

test('receiveInCash throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/payments/pay_123/receiveInCash' => Http::response([
            'errors' => [['description' => 'Payment already confirmed', 'code' => 'ALREADY_CONFIRMED']],
        ], 400),
    ]);

    $api = new PaymentApi;
    $api->receiveInCash('pay_123');
})->throws(AsaasApiException::class);

test('tokenize tokenizes credit card', function () {
    Http::fake([
        Asaas::baseUrl().'/creditCard/tokenize' => Http::response([
            'creditCardToken' => 'cc_tok_123',
            'creditCardNumber' => '4242',
            'creditCardBrand' => 'VISA',
        ], 200),
    ]);

    $api = new PaymentApi;
    $result = $api->tokenize([
        'customer' => 'cus_123',
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

    expect($result['creditCardToken'])->toBe('cc_tok_123');
    expect($result['creditCardBrand'])->toBe('VISA');
});

test('tokenize throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/creditCard/tokenize' => Http::response([
            'errors' => [['description' => 'Invalid card number', 'code' => 'INVALID_CARD']],
        ], 400),
    ]);

    $api = new PaymentApi;
    $api->tokenize(['creditCard' => ['number' => 'invalid']]);
})->throws(AsaasApiException::class);
