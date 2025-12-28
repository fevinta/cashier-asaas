<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Tests;

use Fevinta\CashierAsaas\CashierAsaasServiceProvider;
use Fevinta\CashierAsaas\Tests\Fixtures\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            CashierAsaasServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Use SQLite in-memory database
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set Cashier Asaas configuration
        $app['config']->set('cashier-asaas.api_key', 'test_api_key');
        $app['config']->set('cashier-asaas.sandbox', true);
        $app['config']->set('cashier-asaas.model', User::class);
        $app['config']->set('cashier-asaas.currency', 'BRL');
        $app['config']->set('cashier-asaas.currency_locale', 'pt_BR');
    }

    protected function setUpDatabase(): void
    {
        // Create users table
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('cpf_cnpj')->nullable();
            $table->string('phone')->nullable();
            $table->string('asaas_id')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
        });

        // Run package migrations
        $this->artisan('migrate', ['--database' => 'testing'])->run();
    }

    /**
     * Create a test user.
     */
    protected function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'cpf_cnpj' => generateTestCpf(),
        ], $attributes));
    }

    /**
     * Create a user with an Asaas ID.
     */
    protected function createBillableUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Billable User',
            'email' => 'billable@example.com',
            'cpf_cnpj' => generateTestCpf(),
            'asaas_id' => 'cus_'.uniqid(),
        ], $attributes));
    }
}
