<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Concerns;

use Fevinta\CashierAsaas\Asaas;
use Fevinta\CashierAsaas\Exceptions\CustomerAlreadyCreated;
use Fevinta\CashierAsaas\Exceptions\InvalidCustomer;

trait ManagesCustomer
{
    /**
     * Create an Asaas customer for the billable model.
     */
    public function createAsAsaasCustomer(array $options = []): array
    {
        if ($this->hasAsaasId()) {
            throw CustomerAlreadyCreated::exists($this);
        }

        $options = array_merge([
            'name' => $this->asaasName(),
            'email' => $this->asaasEmail(),
            'cpfCnpj' => $this->asaasCpfCnpj(),
            'phone' => $this->asaasPhone(),
            'mobilePhone' => $this->asaasMobilePhone(),
            'address' => $this->asaasAddress(),
            'addressNumber' => $this->asaasAddressNumber(),
            'complement' => $this->asaasComplement(),
            'province' => $this->asaasProvince(),
            'postalCode' => $this->asaasPostalCode(),
            'externalReference' => (string) $this->getKey(),
            'notificationDisabled' => false,
        ], $options);

        // Remove null values
        $options = array_filter($options, fn ($value) => $value !== null);

        $customer = Asaas::customer()->create($options);

        $this->asaas_id = $customer['id'];
        $this->save();

        return $customer;
    }

    /**
     * Update the Asaas customer information.
     */
    public function updateAsaasCustomer(array $options = []): array
    {
        $this->assertCustomerExists();

        return Asaas::customer()->update($this->asaas_id, $options);
    }

    /**
     * Get the Asaas customer.
     */
    public function asAsaasCustomer(): array
    {
        $this->assertCustomerExists();

        return Asaas::customer()->find($this->asaas_id);
    }

    /**
     * Create an Asaas customer if not exists.
     */
    public function createOrGetAsaasCustomer(array $options = []): array
    {
        if ($this->hasAsaasId()) {
            return $this->asAsaasCustomer();
        }

        return $this->createAsAsaasCustomer($options);
    }

    /**
     * Sync local customer data with Asaas.
     */
    public function syncAsaasCustomerDetails(): self
    {
        $this->updateAsaasCustomer([
            'name' => $this->asaasName(),
            'email' => $this->asaasEmail(),
            'cpfCnpj' => $this->asaasCpfCnpj(),
        ]);

        return $this;
    }

    /**
     * Determine if the customer has an Asaas ID.
     */
    public function hasAsaasId(): bool
    {
        return ! is_null($this->asaas_id);
    }

    /**
     * Get the Asaas ID.
     */
    public function asaasId(): ?string
    {
        return $this->asaas_id;
    }

    /**
     * Assert that the customer exists in Asaas.
     */
    protected function assertCustomerExists(): void
    {
        if (! $this->hasAsaasId()) {
            throw InvalidCustomer::notYetCreated($this);
        }
    }

    /**
     * Get the name to use for Asaas.
     */
    public function asaasName(): ?string
    {
        return $this->name ?? null;
    }

    /**
     * Get the email to use for Asaas.
     */
    public function asaasEmail(): ?string
    {
        return $this->email ?? null;
    }

    /**
     * Get the CPF/CNPJ to use for Asaas.
     */
    public function asaasCpfCnpj(): ?string
    {
        return $this->cpf_cnpj ?? $this->cpf ?? $this->cnpj ?? null;
    }

    /**
     * Get the phone to use for Asaas.
     */
    public function asaasPhone(): ?string
    {
        return $this->phone ?? null;
    }

    /**
     * Get the mobile phone to use for Asaas.
     */
    public function asaasMobilePhone(): ?string
    {
        return $this->mobile_phone ?? $this->phone ?? null;
    }

    /**
     * Get the address to use for Asaas.
     */
    public function asaasAddress(): ?string
    {
        return $this->address ?? $this->street ?? null;
    }

    /**
     * Get the address number to use for Asaas.
     */
    public function asaasAddressNumber(): ?string
    {
        return $this->address_number ?? $this->number ?? null;
    }

    /**
     * Get the complement to use for Asaas.
     */
    public function asaasComplement(): ?string
    {
        return $this->complement ?? null;
    }

    /**
     * Get the province/neighborhood to use for Asaas.
     */
    public function asaasProvince(): ?string
    {
        return $this->province ?? $this->neighborhood ?? $this->bairro ?? null;
    }

    /**
     * Get the postal code to use for Asaas.
     */
    public function asaasPostalCode(): ?string
    {
        return $this->postal_code ?? $this->zip_code ?? $this->cep ?? null;
    }
}
