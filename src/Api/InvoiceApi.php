<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Api;

use Fevinta\CashierAsaas\Asaas;
use Fevinta\CashierAsaas\Exceptions\AsaasApiException;

class InvoiceApi
{
    /**
     * Schedule a new invoice (nota fiscal).
     *
     * @param  array{
     *     payment?: string,
     *     installment?: string,
     *     customer?: string,
     *     serviceDescription: string,
     *     observations?: string,
     *     value: float,
     *     deductions?: float,
     *     effectiveDate: string,
     *     municipalServiceId?: string,
     *     municipalServiceCode?: string,
     *     municipalServiceName: string,
     *     taxes?: array{
     *         retainIss?: bool,
     *         iss?: float,
     *         cofins?: float,
     *         csll?: float,
     *         inss?: float,
     *         ir?: float,
     *         pis?: float
     *     }
     * }  $data
     */
    public function schedule(array $data): array
    {
        $response = Asaas::client()->post('/invoices', $data);

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Alias for schedule.
     */
    public function create(array $data): array
    {
        return $this->schedule($data);
    }

    /**
     * Find an invoice by ID.
     */
    public function find(string $id): array
    {
        $response = Asaas::client()->get("/invoices/{$id}");

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Update an invoice.
     */
    public function update(string $id, array $data): array
    {
        $response = Asaas::client()->put("/invoices/{$id}", $data);

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * List invoices.
     */
    public function list(array $filters = []): array
    {
        $response = Asaas::client()->get('/invoices', $filters);

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Authorize/emit an invoice immediately.
     */
    public function authorize(string $id): array
    {
        $response = Asaas::client()->post("/invoices/{$id}/authorize");

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Cancel an invoice.
     */
    public function cancel(string $id): array
    {
        $response = Asaas::client()->post("/invoices/{$id}/cancel");

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Get municipal services for invoice creation.
     */
    public function municipalServices(array $filters = []): array
    {
        $response = Asaas::client()->get('/invoices/municipalServices', $filters);

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Get fiscal information configuration.
     */
    public function fiscalInfo(): array
    {
        $response = Asaas::client()->get('/fiscalInfo');

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Create or update fiscal information.
     */
    public function saveFiscalInfo(array $data): array
    {
        $response = Asaas::client()->post('/fiscalInfo', $data);

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Get municipal options for fiscal configuration.
     */
    public function municipalOptions(): array
    {
        $response = Asaas::client()->get('/fiscalInfo/municipalOptions');

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Configure invoice settings for a subscription.
     */
    public function configureSubscriptionInvoice(string $subscriptionId, array $data): array
    {
        $response = Asaas::client()->post("/subscriptions/{$subscriptionId}/invoiceSettings", $data);

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Get invoice settings for a subscription.
     */
    public function getSubscriptionInvoiceSettings(string $subscriptionId): array
    {
        $response = Asaas::client()->get("/subscriptions/{$subscriptionId}/invoiceSettings");

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Delete invoice settings for a subscription.
     */
    public function deleteSubscriptionInvoiceSettings(string $subscriptionId): bool
    {
        $response = Asaas::client()->delete("/subscriptions/{$subscriptionId}/invoiceSettings");

        if (! $response->successful()) {
            throw AsaasApiException::fromResponse($response);
        }

        return true;
    }

    /**
     * Find invoices by payment ID.
     */
    public function findByPayment(string $paymentId): array
    {
        return $this->list(['payment' => $paymentId]);
    }

    /**
     * Find invoices by customer ID.
     */
    public function findByCustomer(string $customerId): array
    {
        return $this->list(['customer' => $customerId]);
    }

    /**
     * Find invoices by status.
     */
    public function findByStatus(string $status): array
    {
        return $this->list(['status' => $status]);
    }

    /**
     * Find invoices by effective date range.
     */
    public function findByDateRange(string $startDate, string $endDate): array
    {
        return $this->list([
            'effectiveDate[ge]' => $startDate,
            'effectiveDate[le]' => $endDate,
        ]);
    }
}
