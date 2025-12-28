<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $table = 'asaas_payments';

    protected $fillable = [
        'subscription_id',
        'customer_id',
        'asaas_id',
        'billing_type',
        'value',
        'net_value',
        'status',
        'due_date',
        'payment_date',
        'confirmed_date',
        'refunded_at',
        'invoice_url',
        'bank_slip_url',
        'pix_qrcode',
        'pix_copy_paste',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'net_value' => 'decimal:2',
            'due_date' => 'date',
            'payment_date' => 'date',
            'confirmed_date' => 'datetime',
            'refunded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the subscription this payment belongs to.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the owner of this payment.
     */
    public function owner(): BelongsTo
    {
        $model = config('cashier-asaas.model');

        return $this->belongsTo($model, 'customer_id');
    }

    /**
     * Check if payment is paid.
     */
    public function isPaid(): bool
    {
        return in_array($this->status, ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH']);
    }

    /**
     * Check if payment is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    /**
     * Check if payment is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status === 'OVERDUE';
    }

    /**
     * Check if payment is refunded.
     */
    public function isRefunded(): bool
    {
        return $this->status === 'REFUNDED' || $this->refunded_at !== null;
    }

    /**
     * Get the payment URL (invoice or bank slip).
     */
    public function paymentUrl(): ?string
    {
        return $this->invoice_url ?? $this->bank_slip_url;
    }

    /**
     * Get PIX QR Code.
     */
    public function pixQrCode(): ?string
    {
        return $this->pix_qrcode;
    }

    /**
     * Get PIX copy and paste code.
     */
    public function pixCopyPaste(): ?string
    {
        return $this->pix_copy_paste;
    }

    /**
     * Refund this payment.
     */
    public function refund(?float $amount = null, ?string $description = null): array
    {
        $payload = [];

        if ($amount !== null) {
            $payload['value'] = $amount;
        }

        if ($description !== null) {
            $payload['description'] = $description;
        }

        $result = Asaas::payment()->refund($this->asaas_id, $payload);

        $this->update([
            'status' => 'REFUNDED',
            'refunded_at' => Carbon::now(),
        ]);

        return $result;
    }

    /**
     * Get Asaas payment data.
     */
    public function asAsaasPayment(): array
    {
        return Asaas::payment()->find($this->asaas_id);
    }

    /**
     * Sync from Asaas.
     */
    public function syncFromAsaas(): self
    {
        $asaasPayment = $this->asAsaasPayment();

        $this->update([
            'status' => $asaasPayment['status'],
            'value' => $asaasPayment['value'],
            'net_value' => $asaasPayment['netValue'] ?? $this->net_value,
            'payment_date' => isset($asaasPayment['paymentDate'])
                ? Carbon::parse($asaasPayment['paymentDate'])
                : null,
        ]);

        return $this;
    }

    /**
     * Scope for paid payments.
     */
    public function scopePaid($query)
    {
        return $query->whereIn('status', ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH']);
    }

    /**
     * Scope for pending payments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    /**
     * Scope for overdue payments.
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'OVERDUE');
    }
}
