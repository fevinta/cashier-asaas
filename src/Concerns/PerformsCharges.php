<?php

declare(strict_types=1);

namespace FernandoHS\CashierAsaas\Concerns;

use Carbon\Carbon;
use FernandoHS\CashierAsaas\Asaas;
use FernandoHS\CashierAsaas\Enums\BillingType;
use FernandoHS\CashierAsaas\Payment;

trait PerformsCharges
{
    /**
     * Create a single charge.
     */
    public function charge(
        float $amount,
        BillingType $billingType = BillingType::UNDEFINED,
        array $options = []
    ): Payment {
        $this->createOrGetAsaasCustomer();

        $payload = array_merge([
            'customer' => $this->asaas_id,
            'billingType' => $billingType->value,
            'value' => $amount,
            'dueDate' => ($options['dueDate'] ?? Carbon::tomorrow())->format('Y-m-d'),
            'description' => $options['description'] ?? 'Cobrança',
            'externalReference' => $options['externalReference'] ?? (string) $this->getKey(),
        ], $options);

        // Handle credit card data
        if ($billingType === BillingType::CREDIT_CARD) {
            if (isset($options['creditCardToken'])) {
                $payload['creditCardToken'] = $options['creditCardToken'];
            } elseif (isset($options['creditCard'])) {
                $payload['creditCard'] = $options['creditCard'];
                $payload['creditCardHolderInfo'] = $options['creditCardHolderInfo'] ?? [];
            }
            $payload['remoteIp'] = $options['remoteIp'] ?? request()->ip();
        }

        // Remove non-API options
        unset($payload['dueDate']);
        $payload['dueDate'] = ($options['dueDate'] ?? Carbon::tomorrow())->format('Y-m-d');

        $asaasPayment = Asaas::payment()->create($payload);

        return Payment::create([
            'customer_id' => $this->getKey(),
            'asaas_id' => $asaasPayment['id'],
            'billing_type' => $asaasPayment['billingType'],
            'value' => $asaasPayment['value'],
            'net_value' => $asaasPayment['netValue'] ?? $asaasPayment['value'],
            'status' => $asaasPayment['status'],
            'due_date' => Carbon::parse($asaasPayment['dueDate']),
            'invoice_url' => $asaasPayment['invoiceUrl'] ?? null,
            'bank_slip_url' => $asaasPayment['bankSlipUrl'] ?? null,
            'pix_qrcode' => $asaasPayment['pixQrCode'] ?? null,
            'pix_copy_paste' => $asaasPayment['pixCopiaECola'] ?? null,
        ]);
    }

    /**
     * Create a charge with PIX.
     */
    public function chargeWithPix(float $amount, array $options = []): Payment
    {
        return $this->charge($amount, BillingType::PIX, $options);
    }

    /**
     * Create a charge with boleto.
     */
    public function chargeWithBoleto(float $amount, array $options = []): Payment
    {
        return $this->charge($amount, BillingType::BOLETO, $options);
    }

    /**
     * Create a charge with credit card.
     */
    public function chargeWithCreditCard(
        float $amount,
        string $creditCardToken,
        array $options = []
    ): Payment {
        $options['creditCardToken'] = $creditCardToken;
        
        return $this->charge($amount, BillingType::CREDIT_CARD, $options);
    }

    /**
     * Create an installment charge.
     */
    public function chargeInstallments(
        float $totalAmount,
        int $installments,
        array $options = []
    ): Payment {
        $this->createOrGetAsaasCustomer();

        $installmentValue = round($totalAmount / $installments, 2);

        $payload = [
            'customer' => $this->asaas_id,
            'billingType' => BillingType::CREDIT_CARD->value,
            'value' => $installmentValue,
            'totalValue' => $totalAmount,
            'installmentCount' => $installments,
            'installmentValue' => $installmentValue,
            'dueDate' => ($options['dueDate'] ?? Carbon::tomorrow())->format('Y-m-d'),
            'description' => $options['description'] ?? "Parcelamento em {$installments}x",
        ];

        if (isset($options['creditCardToken'])) {
            $payload['creditCardToken'] = $options['creditCardToken'];
        } elseif (isset($options['creditCard'])) {
            $payload['creditCard'] = $options['creditCard'];
            $payload['creditCardHolderInfo'] = $options['creditCardHolderInfo'] ?? [];
        }

        $payload['remoteIp'] = $options['remoteIp'] ?? request()->ip();

        $asaasPayment = Asaas::payment()->create($payload);

        return Payment::create([
            'customer_id' => $this->getKey(),
            'asaas_id' => $asaasPayment['id'],
            'billing_type' => $asaasPayment['billingType'],
            'value' => $asaasPayment['value'],
            'net_value' => $asaasPayment['netValue'] ?? $asaasPayment['value'],
            'status' => $asaasPayment['status'],
            'due_date' => Carbon::parse($asaasPayment['dueDate']),
            'metadata' => [
                'installments' => $installments,
                'total_value' => $totalAmount,
            ],
        ]);
    }

    /**
     * Refund a payment.
     */
    public function refund(string $paymentId, ?float $amount = null, ?string $description = null): array
    {
        $payload = [];
        
        if ($amount !== null) {
            $payload['value'] = $amount;
        }
        
        if ($description !== null) {
            $payload['description'] = $description;
        }

        return Asaas::payment()->refund($paymentId, $payload);
    }

    /**
     * Get all payments for this customer.
     */
    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payment::class, 'customer_id');
    }

    /**
     * Get payments from Asaas.
     */
    public function asaasPayments(array $filters = []): array
    {
        if (! $this->hasAsaasId()) {
            return ['data' => [], 'totalCount' => 0];
        }

        $filters['customer'] = $this->asaas_id;
        
        return Asaas::payment()->list($filters);
    }
}
