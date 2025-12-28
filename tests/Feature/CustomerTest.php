<?php

declare(strict_types=1);

use Fevinta\CashierAsaas\Asaas;
use Fevinta\CashierAsaas\Exceptions\CustomerAlreadyCreated;
use Fevinta\CashierAsaas\Tests\Concerns\MocksAsaasApi;
use Fevinta\CashierAsaas\Tests\Fixtures\AsaasApiFixtures;
use Fevinta\CashierAsaas\Tests\Fixtures\User;
use Illuminate\Support\Facades\Http;

uses(MocksAsaasApi::class);

beforeEach(function () {
    $this->mockAsaasApi();
});

test('user can create asaas customer', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'cpf_cnpj' => generateTestCpf(),
    ]);

    $customerId = 'cus_'.uniqid();
    Http::fake([
        Asaas::baseUrl().'/customers' => Http::response(
            AsaasApiFixtures::customer(['id' => $customerId, 'name' => 'Test User']),
            200
        ),
    ]);

    $customer = $user->createAsAsaasCustomer();

    expect($customer['id'])->toBe($customerId);
    expect($user->fresh()->asaas_id)->toBe($customerId);
    expect($user->hasAsaasId())->toBeTrue();
});

test('user cannot create duplicate customer', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'cpf_cnpj' => generateTestCpf(),
        'asaas_id' => 'cus_existing',
    ]);

    expect(fn () => $user->createAsAsaasCustomer())
        ->toThrow(CustomerAlreadyCreated::class);
});

test('user can update asaas customer', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'cpf_cnpj' => generateTestCpf(),
        'asaas_id' => 'cus_test123',
    ]);

    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response(
            AsaasApiFixtures::customer([
                'id' => 'cus_test123',
                'name' => 'Updated Name',
            ]),
            200
        ),
    ]);

    $customer = $user->updateAsaasCustomer(['name' => 'Updated Name']);

    expect($customer['name'])->toBe('Updated Name');
});

test('user can sync customer details', function () {
    $user = User::create([
        'name' => 'New Name',
        'email' => 'new@example.com',
        'cpf_cnpj' => generateTestCpf(),
        'asaas_id' => 'cus_test123',
    ]);

    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response(
            AsaasApiFixtures::customer([
                'id' => 'cus_test123',
                'name' => 'New Name',
                'email' => 'new@example.com',
            ]),
            200
        ),
    ]);

    $result = $user->syncAsaasCustomerDetails();

    expect($result)->toBe($user);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/customers/cus_test123')
            && $request['name'] === 'New Name'
            && $request['email'] === 'new@example.com';
    });
});

test('customer is created when not exists using createOrGet', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'cpf_cnpj' => generateTestCpf(),
    ]);

    $customerId = 'cus_'.uniqid();
    Http::fake([
        Asaas::baseUrl().'/customers' => Http::response(
            AsaasApiFixtures::customer(['id' => $customerId]),
            200
        ),
    ]);

    $customer = $user->createOrGetAsaasCustomer();

    expect($customer['id'])->toBe($customerId);
    expect($user->fresh()->asaas_id)->toBe($customerId);
});

test('customer is retrieved when already exists using createOrGet', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'cpf_cnpj' => generateTestCpf(),
        'asaas_id' => 'cus_existing',
    ]);

    Http::fake([
        Asaas::baseUrl().'/customers/cus_existing' => Http::response(
            AsaasApiFixtures::customer(['id' => 'cus_existing']),
            200
        ),
    ]);

    $customer = $user->createOrGetAsaasCustomer();

    expect($customer['id'])->toBe('cus_existing');
});

test('asaas id is stored on user', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'cpf_cnpj' => generateTestCpf(),
    ]);

    $customerId = 'cus_abc123';
    Http::fake([
        Asaas::baseUrl().'/customers' => Http::response(
            AsaasApiFixtures::customer(['id' => $customerId]),
            200
        ),
    ]);

    $user->createAsAsaasCustomer();

    expect($user->asaas_id)->toBe($customerId);
    expect($user->asaasId())->toBe($customerId);
});

test('asAsaasCustomer retrieves customer data', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'cpf_cnpj' => generateTestCpf(),
        'asaas_id' => 'cus_test123',
    ]);

    Http::fake([
        Asaas::baseUrl().'/customers/cus_test123' => Http::response(
            AsaasApiFixtures::customer([
                'id' => 'cus_test123',
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]),
            200
        ),
    ]);

    $customer = $user->asAsaasCustomer();

    expect($customer['id'])->toBe('cus_test123');
    expect($customer['name'])->toBe('Test User');
});

test('user attribute mappers work correctly', function () {
    $user = User::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'cpf_cnpj' => '12345678909',
        'phone' => '11999998888',
    ]);

    expect($user->asaasName())->toBe('John Doe');
    expect($user->asaasEmail())->toBe('john@example.com');
    expect($user->asaasCpfCnpj())->toBe('12345678909');
    expect($user->asaasPhone())->toBe('11999998888');
});
