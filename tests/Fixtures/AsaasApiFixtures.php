<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Tests\Fixtures;

/**
 * Fixture data for mocking Asaas API responses.
 */
class AsaasApiFixtures
{
    /**
     * Generate a customer response.
     */
    public static function customer(array $overrides = []): array
    {
        return array_merge([
            'id' => 'cus_'.uniqid(),
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'cpfCnpj' => '12345678909',
            'phone' => '11999999999',
            'mobilePhone' => '11999999999',
            'address' => 'Rua Teste',
            'addressNumber' => '123',
            'complement' => 'Apto 1',
            'province' => 'Centro',
            'postalCode' => '01310100',
            'externalReference' => '1',
            'notificationDisabled' => false,
            'dateCreated' => now()->format('Y-m-d'),
        ], $overrides);
    }

    /**
     * Generate a subscription response.
     */
    public static function subscription(array $overrides = []): array
    {
        return array_merge([
            'id' => 'sub_'.uniqid(),
            'customer' => 'cus_'.uniqid(),
            'billingType' => 'PIX',
            'value' => 99.90,
            'nextDueDate' => now()->addMonth()->format('Y-m-d'),
            'cycle' => 'MONTHLY',
            'description' => 'Premium Plan',
            'status' => 'ACTIVE',
            'dateCreated' => now()->format('Y-m-d'),
            'endDate' => null,
            'maxPayments' => null,
            'externalReference' => null,
        ], $overrides);
    }

    /**
     * Generate a payment response.
     */
    public static function payment(array $overrides = []): array
    {
        return array_merge([
            'id' => 'pay_'.uniqid(),
            'customer' => 'cus_'.uniqid(),
            'subscription' => null,
            'billingType' => 'PIX',
            'value' => 99.90,
            'netValue' => 97.90,
            'status' => 'PENDING',
            'dueDate' => now()->addDays(3)->format('Y-m-d'),
            'paymentDate' => null,
            'confirmedDate' => null,
            'description' => 'Payment',
            'invoiceUrl' => 'https://sandbox.asaas.com/i/'.uniqid(),
            'bankSlipUrl' => null,
            'pixQrCode' => null,
            'pixCopiaECola' => null,
            'externalReference' => null,
            'dateCreated' => now()->format('Y-m-d'),
        ], $overrides);
    }

    /**
     * Generate a PIX payment response.
     */
    public static function pixPayment(array $overrides = []): array
    {
        return self::payment(array_merge([
            'billingType' => 'PIX',
            'pixQrCode' => 'data:image/png;base64,'.base64_encode('fake-qr-code'),
            'pixCopiaECola' => '00020126580014br.gov.bcb.pix0136'.uniqid(),
        ], $overrides));
    }

    /**
     * Generate a Boleto payment response.
     */
    public static function boletoPayment(array $overrides = []): array
    {
        return self::payment(array_merge([
            'billingType' => 'BOLETO',
            'bankSlipUrl' => 'https://sandbox.asaas.com/b/'.uniqid(),
            'identificationField' => '23793.38128 60800.000003 00000.000400 1 '.random_int(1000, 9999).'0000009990',
        ], $overrides));
    }

    /**
     * Generate a credit card payment response.
     */
    public static function creditCardPayment(array $overrides = []): array
    {
        return self::payment(array_merge([
            'billingType' => 'CREDIT_CARD',
            'status' => 'CONFIRMED',
            'paymentDate' => now()->format('Y-m-d'),
            'confirmedDate' => now()->toIso8601String(),
        ], $overrides));
    }

    /**
     * Generate a credit card tokenization response.
     */
    public static function creditCardToken(array $overrides = []): array
    {
        return array_merge([
            'creditCardToken' => 'cc_tok_'.uniqid(),
            'creditCardNumber' => '4242',
            'creditCardBrand' => 'VISA',
        ], $overrides);
    }

    /**
     * Generate a PIX QR code response.
     */
    public static function pixQrCode(array $overrides = []): array
    {
        return array_merge([
            'encodedImage' => 'data:image/png;base64,'.base64_encode('fake-qr-code'),
            'payload' => '00020126580014br.gov.bcb.pix0136'.uniqid(),
            'expirationDate' => now()->addHours(24)->toIso8601String(),
        ], $overrides);
    }

    /**
     * Generate an error response.
     */
    public static function error(string $message = 'An error occurred', string $code = 'INVALID_REQUEST'): array
    {
        return [
            'errors' => [
                [
                    'description' => $message,
                    'code' => $code,
                ],
            ],
        ];
    }

    /**
     * Generate a list response with pagination.
     */
    public static function list(array $data, ?int $totalCount = null): array
    {
        return [
            'data' => $data,
            'totalCount' => $totalCount ?? count($data),
            'limit' => 10,
            'offset' => 0,
            'hasMore' => false,
        ];
    }

    /**
     * Generate a webhook payload for a payment event.
     */
    public static function paymentWebhook(string $event, array $paymentOverrides = []): array
    {
        return [
            'event' => $event,
            'payment' => self::payment($paymentOverrides),
        ];
    }

    /**
     * Generate a webhook payload for a subscription event.
     */
    public static function subscriptionWebhook(string $event, array $subscriptionOverrides = []): array
    {
        return [
            'event' => $event,
            'subscription' => self::subscription($subscriptionOverrides),
        ];
    }

    /**
     * Generate an invoice response.
     */
    public static function invoice(array $overrides = []): array
    {
        return array_merge([
            'id' => 'inv_'.uniqid(),
            'status' => 'SCHEDULED',
            'type' => 'NFS-e',
            'value' => 100.00,
            'deductions' => 0,
            'netValue' => 100.00,
            'serviceDescription' => 'Consulting Services',
            'observations' => null,
            'effectiveDate' => now()->addDays(5)->format('Y-m-d'),
            'municipalServiceId' => null,
            'municipalServiceCode' => '1234',
            'municipalServiceName' => 'Consultoria em TI',
            'rpsNumber' => null,
            'rpsSerie' => null,
            'number' => null,
            'verificationCode' => null,
            'pdfUrl' => null,
            'xmlUrl' => null,
            'taxes' => [
                'retainIss' => false,
                'iss' => 5.0,
                'cofins' => 0,
                'csll' => 0,
                'inss' => 0,
                'ir' => 0,
                'pis' => 0,
            ],
            'externalReference' => null,
        ], $overrides);
    }

    /**
     * Generate an invoice list response.
     */
    public static function invoiceList(int $count = 2): array
    {
        $invoices = [];
        for ($i = 0; $i < $count; $i++) {
            $invoices[] = self::invoice();
        }

        return self::list($invoices, $count);
    }

    /**
     * Generate a webhook payload for an invoice event.
     */
    public static function invoiceWebhook(string $event, array $invoiceOverrides = []): array
    {
        return [
            'event' => $event,
            'invoice' => self::invoice($invoiceOverrides),
        ];
    }
}
