<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Api\InvoiceApi;
use Fevinta\CashierAsaas\Asaas;
use Fevinta\CashierAsaas\Exceptions\AsaasApiException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('schedule creates invoice successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices' => Http::response([
            'id' => 'inv_123',
            'status' => 'SCHEDULED',
            'value' => 100.00,
            'serviceDescription' => 'Consulting Services',
        ], 200),
    ]);

    $api = new InvoiceApi;
    $result = $api->schedule([
        'payment' => 'pay_123',
        'serviceDescription' => 'Consulting Services',
        'value' => 100.00,
        'effectiveDate' => '2024-12-31',
        'municipalServiceName' => 'Consultoria',
    ]);

    expect($result['id'])->toBe('inv_123');
    expect($result['status'])->toBe('SCHEDULED');
});

test('schedule throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices' => Http::response([
            'errors' => [['description' => 'Invalid data', 'code' => 'INVALID_REQUEST']],
        ], 400),
    ]);

    $api = new InvoiceApi;
    $api->schedule(['serviceDescription' => '']);
})->throws(AsaasApiException::class);

test('create is alias for schedule', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices' => Http::response([
            'id' => 'inv_123',
            'status' => 'SCHEDULED',
            'value' => 100.00,
        ], 200),
    ]);

    $api = new InvoiceApi;
    $result = $api->create([
        'serviceDescription' => 'Test Service',
        'value' => 100.00,
        'effectiveDate' => '2024-12-31',
        'municipalServiceName' => 'Test',
    ]);

    expect($result['id'])->toBe('inv_123');
});

test('find returns invoice by id', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices/inv_123' => Http::response([
            'id' => 'inv_123',
            'status' => 'AUTHORIZED',
            'value' => 100.00,
        ], 200),
    ]);

    $api = new InvoiceApi;
    $result = $api->find('inv_123');

    expect($result['id'])->toBe('inv_123');
    expect($result['status'])->toBe('AUTHORIZED');
});

test('find throws exception when invoice not found', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices/inv_nonexistent' => Http::response([
            'errors' => [['description' => 'Invoice not found', 'code' => 'NOT_FOUND']],
        ], 404),
    ]);

    $api = new InvoiceApi;
    $api->find('inv_nonexistent');
})->throws(AsaasApiException::class);

test('update updates invoice successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices/inv_123' => Http::response([
            'id' => 'inv_123',
            'value' => 150.00,
            'serviceDescription' => 'Updated Service',
        ], 200),
    ]);

    $api = new InvoiceApi;
    $result = $api->update('inv_123', [
        'value' => 150.00,
        'serviceDescription' => 'Updated Service',
    ]);

    expect($result['value'])->toBe(150);
});

test('update throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices/inv_123' => Http::response([
            'errors' => [['description' => 'Cannot update authorized invoice', 'code' => 'CANNOT_UPDATE']],
        ], 400),
    ]);

    $api = new InvoiceApi;
    $api->update('inv_123', ['value' => 100]);
})->throws(AsaasApiException::class);

test('list returns invoices with pagination', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices*' => Http::response([
            'data' => [
                ['id' => 'inv_1', 'value' => 100.00],
                ['id' => 'inv_2', 'value' => 200.00],
            ],
            'totalCount' => 2,
            'limit' => 10,
            'offset' => 0,
            'hasMore' => false,
        ], 200),
    ]);

    $api = new InvoiceApi;
    $result = $api->list();

    expect($result['data'])->toHaveCount(2);
    expect($result['totalCount'])->toBe(2);
});

test('list throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices*' => Http::response([
            'errors' => [['description' => 'Unauthorized', 'code' => 'UNAUTHORIZED']],
        ], 401),
    ]);

    $api = new InvoiceApi;
    $api->list();
})->throws(AsaasApiException::class);

test('authorize authorizes invoice successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices/inv_123/authorize' => Http::response([
            'id' => 'inv_123',
            'status' => 'SYNCHRONIZED',
        ], 200),
    ]);

    $api = new InvoiceApi;
    $result = $api->authorize('inv_123');

    expect($result['status'])->toBe('SYNCHRONIZED');
});

test('authorize throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices/inv_123/authorize' => Http::response([
            'errors' => [['description' => 'Invoice already authorized', 'code' => 'ALREADY_AUTHORIZED']],
        ], 400),
    ]);

    $api = new InvoiceApi;
    $api->authorize('inv_123');
})->throws(AsaasApiException::class);

test('cancel cancels invoice successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices/inv_123/cancel' => Http::response([
            'id' => 'inv_123',
            'status' => 'PROCESSING_CANCELLATION',
        ], 200),
    ]);

    $api = new InvoiceApi;
    $result = $api->cancel('inv_123');

    expect($result['status'])->toBe('PROCESSING_CANCELLATION');
});

test('cancel throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices/inv_123/cancel' => Http::response([
            'errors' => [['description' => 'Cannot cancel invoice', 'code' => 'CANNOT_CANCEL']],
        ], 400),
    ]);

    $api = new InvoiceApi;
    $api->cancel('inv_123');
})->throws(AsaasApiException::class);

test('municipalServices returns municipal services list', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices/municipalServices*' => Http::response([
            'data' => [
                ['id' => 'ms_1', 'name' => 'Consultoria em TI'],
                ['id' => 'ms_2', 'name' => 'Desenvolvimento de Software'],
            ],
            'totalCount' => 2,
        ], 200),
    ]);

    $api = new InvoiceApi;
    $result = $api->municipalServices();

    expect($result['data'])->toHaveCount(2);
});

test('municipalServices throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices/municipalServices*' => Http::response([
            'errors' => [['description' => 'Unauthorized', 'code' => 'UNAUTHORIZED']],
        ], 401),
    ]);

    $api = new InvoiceApi;
    $api->municipalServices();
})->throws(AsaasApiException::class);

test('fiscalInfo returns fiscal information', function () {
    Http::fake([
        Asaas::baseUrl().'/fiscalInfo' => Http::response([
            'simplesNacional' => true,
            'rpsSerie' => 'A1',
            'rpsNumber' => 100,
            'loteNumber' => 1,
        ], 200),
    ]);

    $api = new InvoiceApi;
    $result = $api->fiscalInfo();

    expect($result['simplesNacional'])->toBeTrue();
    expect($result['rpsSerie'])->toBe('A1');
});

test('fiscalInfo throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/fiscalInfo' => Http::response([
            'errors' => [['description' => 'Unauthorized', 'code' => 'UNAUTHORIZED']],
        ], 401),
    ]);

    $api = new InvoiceApi;
    $api->fiscalInfo();
})->throws(AsaasApiException::class);

test('saveFiscalInfo saves fiscal information', function () {
    Http::fake([
        Asaas::baseUrl().'/fiscalInfo' => Http::response([
            'simplesNacional' => true,
            'rpsSerie' => 'A1',
            'rpsNumber' => 100,
        ], 200),
    ]);

    $api = new InvoiceApi;
    $result = $api->saveFiscalInfo([
        'simplesNacional' => true,
        'rpsSerie' => 'A1',
        'rpsNumber' => 100,
    ]);

    expect($result['simplesNacional'])->toBeTrue();
});

test('saveFiscalInfo throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/fiscalInfo' => Http::response([
            'errors' => [['description' => 'Invalid data', 'code' => 'INVALID_REQUEST']],
        ], 400),
    ]);

    $api = new InvoiceApi;
    $api->saveFiscalInfo([]);
})->throws(AsaasApiException::class);

test('municipalOptions returns municipal options', function () {
    Http::fake([
        Asaas::baseUrl().'/fiscalInfo/municipalOptions' => Http::response([
            'data' => [
                ['id' => 'opt_1', 'description' => 'Option 1'],
            ],
        ], 200),
    ]);

    $api = new InvoiceApi;
    $result = $api->municipalOptions();

    expect($result['data'])->toHaveCount(1);
});

test('municipalOptions throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/fiscalInfo/municipalOptions' => Http::response([
            'errors' => [['description' => 'Unauthorized', 'code' => 'UNAUTHORIZED']],
        ], 401),
    ]);

    $api = new InvoiceApi;
    $api->municipalOptions();
})->throws(AsaasApiException::class);

test('configureSubscriptionInvoice configures invoice settings', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_123/invoiceSettings' => Http::response([
            'municipalServiceId' => 'ms_123',
            'municipalServiceCode' => '1234',
            'deductions' => 0,
        ], 200),
    ]);

    $api = new InvoiceApi;
    $result = $api->configureSubscriptionInvoice('sub_123', [
        'municipalServiceId' => 'ms_123',
        'municipalServiceCode' => '1234',
    ]);

    expect($result['municipalServiceId'])->toBe('ms_123');
});

test('configureSubscriptionInvoice throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_123/invoiceSettings' => Http::response([
            'errors' => [['description' => 'Subscription not found', 'code' => 'NOT_FOUND']],
        ], 404),
    ]);

    $api = new InvoiceApi;
    $api->configureSubscriptionInvoice('sub_123', []);
})->throws(AsaasApiException::class);

test('getSubscriptionInvoiceSettings returns invoice settings', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_123/invoiceSettings' => Http::response([
            'municipalServiceId' => 'ms_123',
            'municipalServiceCode' => '1234',
            'deductions' => 0,
        ], 200),
    ]);

    $api = new InvoiceApi;
    $result = $api->getSubscriptionInvoiceSettings('sub_123');

    expect($result['municipalServiceId'])->toBe('ms_123');
});

test('getSubscriptionInvoiceSettings throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_123/invoiceSettings' => Http::response([
            'errors' => [['description' => 'Subscription not found', 'code' => 'NOT_FOUND']],
        ], 404),
    ]);

    $api = new InvoiceApi;
    $api->getSubscriptionInvoiceSettings('sub_123');
})->throws(AsaasApiException::class);

test('deleteSubscriptionInvoiceSettings deletes invoice settings', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_123/invoiceSettings' => Http::response([], 200),
    ]);

    $api = new InvoiceApi;
    $result = $api->deleteSubscriptionInvoiceSettings('sub_123');

    expect($result)->toBeTrue();
});

test('deleteSubscriptionInvoiceSettings throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/subscriptions/sub_123/invoiceSettings' => Http::response([
            'errors' => [['description' => 'Subscription not found', 'code' => 'NOT_FOUND']],
        ], 404),
    ]);

    $api = new InvoiceApi;
    $api->deleteSubscriptionInvoiceSettings('sub_123');
})->throws(AsaasApiException::class);

test('findByPayment returns invoices for a payment', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices*' => Http::response([
            'data' => [
                ['id' => 'inv_1', 'payment' => 'pay_123'],
            ],
            'totalCount' => 1,
            'limit' => 10,
            'offset' => 0,
            'hasMore' => false,
        ], 200),
    ]);

    $api = new InvoiceApi;
    $result = $api->findByPayment('pay_123');

    expect($result['data'])->toHaveCount(1);
});

test('findByCustomer returns invoices for a customer', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices*' => Http::response([
            'data' => [
                ['id' => 'inv_1', 'customer' => 'cus_123'],
            ],
            'totalCount' => 1,
            'limit' => 10,
            'offset' => 0,
            'hasMore' => false,
        ], 200),
    ]);

    $api = new InvoiceApi;
    $result = $api->findByCustomer('cus_123');

    expect($result['data'])->toHaveCount(1);
});

test('findByStatus returns invoices with given status', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices*' => Http::response([
            'data' => [
                ['id' => 'inv_1', 'status' => 'AUTHORIZED'],
            ],
            'totalCount' => 1,
            'limit' => 10,
            'offset' => 0,
            'hasMore' => false,
        ], 200),
    ]);

    $api = new InvoiceApi;
    $result = $api->findByStatus('AUTHORIZED');

    expect($result['data'])->toHaveCount(1);
});

test('findByDateRange returns invoices within date range', function () {
    Http::fake([
        Asaas::baseUrl().'/invoices*' => Http::response([
            'data' => [
                ['id' => 'inv_1', 'effectiveDate' => '2024-12-01'],
                ['id' => 'inv_2', 'effectiveDate' => '2024-12-15'],
            ],
            'totalCount' => 2,
            'limit' => 10,
            'offset' => 0,
            'hasMore' => false,
        ], 200),
    ]);

    $api = new InvoiceApi;
    $result = $api->findByDateRange('2024-12-01', '2024-12-31');

    expect($result['data'])->toHaveCount(2);
});
