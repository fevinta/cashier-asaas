<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Api\CustomerApi;
use Fevinta\CashierAsaas\Asaas;
use Fevinta\CashierAsaas\Exceptions\AsaasApiException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('create creates customer successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/customers' => Http::response([
            'id' => 'cus_123',
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'cpfCnpj' => '12345678909',
        ], 200),
    ]);

    $api = new CustomerApi();
    $result = $api->create([
        'name' => 'Test Customer',
        'email' => 'test@example.com',
        'cpfCnpj' => '12345678909',
    ]);

    expect($result['id'])->toBe('cus_123');
    expect($result['name'])->toBe('Test Customer');
});

test('create throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/customers' => Http::response([
            'errors' => [['description' => 'Invalid CPF/CNPJ', 'code' => 'INVALID_CPF_CNPJ']],
        ], 400),
    ]);

    $api = new CustomerApi();
    $api->create(['name' => 'Test', 'cpfCnpj' => 'invalid']);
})->throws(AsaasApiException::class, 'Invalid CPF/CNPJ');

test('find returns customer by id', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_123' => Http::response([
            'id' => 'cus_123',
            'name' => 'Test Customer',
            'email' => 'test@example.com',
        ], 200),
    ]);

    $api = new CustomerApi();
    $result = $api->find('cus_123');

    expect($result['id'])->toBe('cus_123');
});

test('find throws exception when customer not found', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_nonexistent' => Http::response([
            'errors' => [['description' => 'Customer not found', 'code' => 'NOT_FOUND']],
        ], 404),
    ]);

    $api = new CustomerApi();
    $api->find('cus_nonexistent');
})->throws(AsaasApiException::class);

test('update updates customer successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_123' => Http::response([
            'id' => 'cus_123',
            'name' => 'Updated Customer',
            'email' => 'updated@example.com',
        ], 200),
    ]);

    $api = new CustomerApi();
    $result = $api->update('cus_123', [
        'name' => 'Updated Customer',
        'email' => 'updated@example.com',
    ]);

    expect($result['name'])->toBe('Updated Customer');
});

test('update throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_123' => Http::response([
            'errors' => [['description' => 'Invalid email', 'code' => 'INVALID_EMAIL']],
        ], 400),
    ]);

    $api = new CustomerApi();
    $api->update('cus_123', ['email' => 'invalid']);
})->throws(AsaasApiException::class);

test('delete deletes customer successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_123' => Http::response([
            'deleted' => true,
        ], 200),
    ]);

    $api = new CustomerApi();
    $result = $api->delete('cus_123');

    expect($result)->toBeTrue();
});

test('delete throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_123' => Http::response([
            'errors' => [['description' => 'Cannot delete customer with pending payments', 'code' => 'CANNOT_DELETE']],
        ], 400),
    ]);

    $api = new CustomerApi();
    $api->delete('cus_123');
})->throws(AsaasApiException::class);

test('list returns customers with pagination', function () {
    Http::fake([
        Asaas::baseUrl().'/customers*' => Http::response([
            'data' => [
                ['id' => 'cus_1', 'name' => 'Customer 1'],
                ['id' => 'cus_2', 'name' => 'Customer 2'],
            ],
            'totalCount' => 2,
            'limit' => 10,
            'offset' => 0,
            'hasMore' => false,
        ], 200),
    ]);

    $api = new CustomerApi();
    $result = $api->list();

    expect($result['data'])->toHaveCount(2);
    expect($result['totalCount'])->toBe(2);
});

test('list throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/customers*' => Http::response([
            'errors' => [['description' => 'Unauthorized', 'code' => 'UNAUTHORIZED']],
        ], 401),
    ]);

    $api = new CustomerApi();
    $api->list();
})->throws(AsaasApiException::class);

test('list filters customers by parameters', function () {
    Http::fake([
        Asaas::baseUrl().'/customers*' => Http::response([
            'data' => [
                ['id' => 'cus_1', 'name' => 'Test', 'cpfCnpj' => '12345678909'],
            ],
            'totalCount' => 1,
        ], 200),
    ]);

    $api = new CustomerApi();
    $result = $api->list(['cpfCnpj' => '12345678909']);

    expect($result['data'])->toHaveCount(1);
});

test('findByCpfCnpj returns customer when found', function () {
    Http::fake([
        Asaas::baseUrl().'/customers*' => Http::response([
            'data' => [
                ['id' => 'cus_123', 'name' => 'Test', 'cpfCnpj' => '12345678909'],
            ],
            'totalCount' => 1,
        ], 200),
    ]);

    $api = new CustomerApi();
    $result = $api->findByCpfCnpj('12345678909');

    expect($result['id'])->toBe('cus_123');
    expect($result['cpfCnpj'])->toBe('12345678909');
});

test('findByCpfCnpj returns null when not found', function () {
    Http::fake([
        Asaas::baseUrl().'/customers*' => Http::response([
            'data' => [],
            'totalCount' => 0,
        ], 200),
    ]);

    $api = new CustomerApi();
    $result = $api->findByCpfCnpj('00000000000');

    expect($result)->toBeNull();
});

test('findByExternalReference returns customer when found', function () {
    Http::fake([
        Asaas::baseUrl().'/customers*' => Http::response([
            'data' => [
                ['id' => 'cus_123', 'name' => 'Test', 'externalReference' => 'user_1'],
            ],
            'totalCount' => 1,
        ], 200),
    ]);

    $api = new CustomerApi();
    $result = $api->findByExternalReference('user_1');

    expect($result['id'])->toBe('cus_123');
    expect($result['externalReference'])->toBe('user_1');
});

test('findByExternalReference returns null when not found', function () {
    Http::fake([
        Asaas::baseUrl().'/customers*' => Http::response([
            'data' => [],
            'totalCount' => 0,
        ], 200),
    ]);

    $api = new CustomerApi();
    $result = $api->findByExternalReference('nonexistent');

    expect($result)->toBeNull();
});

test('restore restores deleted customer successfully', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_123/restore' => Http::response([
            'id' => 'cus_123',
            'name' => 'Test Customer',
            'deleted' => false,
        ], 200),
    ]);

    $api = new CustomerApi();
    $result = $api->restore('cus_123');

    expect($result['id'])->toBe('cus_123');
    expect($result['deleted'])->toBeFalse();
});

test('restore throws exception when customer cannot be restored', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_123/restore' => Http::response([
            'errors' => [['description' => 'Customer is not deleted', 'code' => 'CANNOT_RESTORE']],
        ], 400),
    ]);

    $api = new CustomerApi();
    $api->restore('cus_123');
})->throws(AsaasApiException::class);

test('notifications returns customer notifications', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_123/notifications' => Http::response([
            'data' => [
                ['id' => 'notif_1', 'enabled' => true, 'event' => 'PAYMENT_CREATED'],
            ],
        ], 200),
    ]);

    $api = new CustomerApi();
    $result = $api->notifications('cus_123');

    expect($result['data'])->toHaveCount(1);
});

test('notifications throws exception on failure', function () {
    Http::fake([
        Asaas::baseUrl().'/customers/cus_123/notifications' => Http::response([
            'errors' => [['description' => 'Customer not found', 'code' => 'NOT_FOUND']],
        ], 404),
    ]);

    $api = new CustomerApi();
    $api->notifications('cus_123');
})->throws(AsaasApiException::class);
