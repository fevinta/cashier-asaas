<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $table = 'asaas_invoices';

    protected $fillable = [
        'payment_id',
        'customer_id',
        'asaas_id',
        'status',
        'type',
        'effective_date',
        'value',
        'deductions',
        'net_value',
        'service_description',
        'observations',
        'municipal_service_id',
        'municipal_service_code',
        'municipal_service_name',
        'rps_number',
        'rps_series',
        'invoice_number',
        'verification_code',
        'pdf_url',
        'xml_url',
        'taxes',
        'external_reference',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'value' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net_value' => 'decimal:2',
            'taxes' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the payment this invoice belongs to.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the owner of this invoice.
     */
    public function owner(): BelongsTo
    {
        $model = config('cashier-asaas.model');

        return $this->belongsTo($model, 'customer_id');
    }

    /**
     * Check if invoice is scheduled.
     */
    public function isScheduled(): bool
    {
        return $this->status === 'SCHEDULED';
    }

    /**
     * Check if invoice is synchronized with city hall.
     */
    public function isSynchronized(): bool
    {
        return $this->status === 'SYNCHRONIZED';
    }

    /**
     * Check if invoice is authorized/issued.
     */
    public function isAuthorized(): bool
    {
        return $this->status === 'AUTHORIZED';
    }

    /**
     * Check if invoice is canceled.
     */
    public function isCanceled(): bool
    {
        return $this->status === 'CANCELED';
    }

    /**
     * Check if invoice has error.
     */
    public function hasError(): bool
    {
        return $this->status === 'ERROR';
    }

    /**
     * Check if invoice cancellation is being processed.
     */
    public function isProcessingCancellation(): bool
    {
        return $this->status === 'PROCESSING_CANCELLATION';
    }

    /**
     * Check if invoice cancellation was denied.
     */
    public function isCancellationDenied(): bool
    {
        return $this->status === 'CANCELLATION_DENIED';
    }

    /**
     * Authorize/emit this invoice immediately.
     */
    public function authorize(): array
    {
        $result = Asaas::invoice()->authorize($this->asaas_id);

        $this->update([
            'status' => $result['status'] ?? 'SYNCHRONIZED',
        ]);

        return $result;
    }

    /**
     * Cancel this invoice.
     */
    public function cancel(): array
    {
        $result = Asaas::invoice()->cancel($this->asaas_id);

        $this->update([
            'status' => $result['status'] ?? 'PROCESSING_CANCELLATION',
        ]);

        return $result;
    }

    /**
     * Get invoice PDF URL.
     */
    public function pdfUrl(): ?string
    {
        return $this->pdf_url;
    }

    /**
     * Get invoice XML URL.
     */
    public function xmlUrl(): ?string
    {
        return $this->xml_url;
    }

    /**
     * Get Asaas invoice data.
     */
    public function asAsaasInvoice(): array
    {
        return Asaas::invoice()->find($this->asaas_id);
    }

    /**
     * Sync from Asaas.
     */
    public function syncFromAsaas(): self
    {
        $asaasInvoice = $this->asAsaasInvoice();

        $this->update([
            'status' => $asaasInvoice['status'] ?? $this->status,
            'value' => $asaasInvoice['value'] ?? $this->value,
            'net_value' => $asaasInvoice['netValue'] ?? $this->net_value,
            'rps_number' => $asaasInvoice['rpsSerie'] ?? $this->rps_number,
            'rps_series' => $asaasInvoice['rpsNumber'] ?? $this->rps_series,
            'invoice_number' => $asaasInvoice['number'] ?? $this->invoice_number,
            'verification_code' => $asaasInvoice['verificationCode'] ?? $this->verification_code,
            'pdf_url' => $asaasInvoice['pdfUrl'] ?? $this->pdf_url,
            'xml_url' => $asaasInvoice['xmlUrl'] ?? $this->xml_url,
            'effective_date' => isset($asaasInvoice['effectiveDate'])
                ? Carbon::parse($asaasInvoice['effectiveDate'])
                : $this->effective_date,
        ]);

        return $this;
    }

    /**
     * Scope for scheduled invoices.
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'SCHEDULED');
    }

    /**
     * Scope for authorized/issued invoices.
     */
    public function scopeAuthorized($query)
    {
        return $query->where('status', 'AUTHORIZED');
    }

    /**
     * Scope for canceled invoices.
     */
    public function scopeCanceled($query)
    {
        return $query->where('status', 'CANCELED');
    }

    /**
     * Scope for invoices with errors.
     */
    public function scopeWithError($query)
    {
        return $query->where('status', 'ERROR');
    }

    /**
     * Scope for synchronized invoices.
     */
    public function scopeSynchronized($query)
    {
        return $query->where('status', 'SYNCHRONIZED');
    }
}
