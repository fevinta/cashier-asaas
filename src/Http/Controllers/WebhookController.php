<?php

declare(strict_types=1);

namespace FernandoHS\CashierAsaas\Http\Controllers;

use Carbon\Carbon;
use FernandoHS\CashierAsaas\Events\PaymentConfirmed;
use FernandoHS\CashierAsaas\Events\PaymentCreated;
use FernandoHS\CashierAsaas\Events\PaymentDeleted;
use FernandoHS\CashierAsaas\Events\PaymentOverdue;
use FernandoHS\CashierAsaas\Events\PaymentReceived;
use FernandoHS\CashierAsaas\Events\PaymentRefunded;
use FernandoHS\CashierAsaas\Events\PaymentUpdated;
use FernandoHS\CashierAsaas\Events\SubscriptionCreated;
use FernandoHS\CashierAsaas\Events\SubscriptionDeleted;
use FernandoHS\CashierAsaas\Events\SubscriptionUpdated;
use FernandoHS\CashierAsaas\Events\WebhookHandled;
use FernandoHS\CashierAsaas\Events\WebhookReceived;
use FernandoHS\CashierAsaas\Payment;
use FernandoHS\CashierAsaas\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WebhookController extends Controller
{
    /**
     * Handle an Asaas webhook call.
     */
    public function handleWebhook(Request $request): SymfonyResponse
    {
        $payload = $request->all();
        $event = $payload['event'] ?? null;

        WebhookReceived::dispatch($payload);

        // Route to appropriate handler
        $method = 'handle' . Str::studly(str_replace('.', '_', $event));

        if (method_exists($this, $method)) {
            $response = $this->{$method}($payload);
            
            WebhookHandled::dispatch($payload);

            return $response;
        }

        return $this->successResponse();
    }

    /**
     * Handle payment created.
     */
    protected function handlePaymentCreated(array $payload): SymfonyResponse
    {
        $payment = $payload['payment'] ?? [];
        
        // Find subscription by external reference
        if (isset($payment['subscription'])) {
            $subscription = Subscription::where('asaas_id', $payment['subscription'])->first();
            
            if ($subscription) {
                // Create local payment record
                $localPayment = Payment::updateOrCreate(
                    ['asaas_id' => $payment['id']],
                    [
                        'subscription_id' => $subscription->id,
                        'customer_id' => $subscription->user_id,
                        'billing_type' => $payment['billingType'],
                        'value' => $payment['value'],
                        'net_value' => $payment['netValue'] ?? $payment['value'],
                        'status' => $payment['status'],
                        'due_date' => Carbon::parse($payment['dueDate']),
                        'invoice_url' => $payment['invoiceUrl'] ?? null,
                        'bank_slip_url' => $payment['bankSlipUrl'] ?? null,
                        'pix_qrcode' => $payment['pixQrCode'] ?? null,
                        'pix_copy_paste' => $payment['pixCopiaECola'] ?? null,
                    ]
                );

                PaymentCreated::dispatch($localPayment, $payload);
            }
        }

        return $this->successResponse();
    }

    /**
     * Handle payment received.
     */
    protected function handlePaymentReceived(array $payload): SymfonyResponse
    {
        $payment = $payload['payment'] ?? [];
        
        $localPayment = Payment::where('asaas_id', $payment['id'])->first();
        
        if ($localPayment) {
            $localPayment->update([
                'status' => $payment['status'],
                'payment_date' => isset($payment['paymentDate']) 
                    ? Carbon::parse($payment['paymentDate']) 
                    : Carbon::now(),
                'net_value' => $payment['netValue'] ?? $localPayment->net_value,
            ]);

            PaymentReceived::dispatch($localPayment, $payload);
        }

        return $this->successResponse();
    }

    /**
     * Handle payment confirmed.
     */
    protected function handlePaymentConfirmed(array $payload): SymfonyResponse
    {
        $payment = $payload['payment'] ?? [];
        
        $localPayment = Payment::where('asaas_id', $payment['id'])->first();
        
        if ($localPayment) {
            $localPayment->update([
                'status' => $payment['status'],
                'confirmed_date' => Carbon::now(),
            ]);

            PaymentConfirmed::dispatch($localPayment, $payload);
        }

        return $this->successResponse();
    }

    /**
     * Handle payment overdue.
     */
    protected function handlePaymentOverdue(array $payload): SymfonyResponse
    {
        $payment = $payload['payment'] ?? [];
        
        $localPayment = Payment::where('asaas_id', $payment['id'])->first();
        
        if ($localPayment) {
            $localPayment->update([
                'status' => $payment['status'],
            ]);

            PaymentOverdue::dispatch($localPayment, $payload);
        }

        return $this->successResponse();
    }

    /**
     * Handle payment refunded.
     */
    protected function handlePaymentRefunded(array $payload): SymfonyResponse
    {
        $payment = $payload['payment'] ?? [];
        
        $localPayment = Payment::where('asaas_id', $payment['id'])->first();
        
        if ($localPayment) {
            $localPayment->update([
                'status' => $payment['status'],
                'refunded_at' => Carbon::now(),
            ]);

            PaymentRefunded::dispatch($localPayment, $payload);
        }

        return $this->successResponse();
    }

    /**
     * Handle payment deleted.
     */
    protected function handlePaymentDeleted(array $payload): SymfonyResponse
    {
        $payment = $payload['payment'] ?? [];
        
        $localPayment = Payment::where('asaas_id', $payment['id'])->first();
        
        if ($localPayment) {
            $localPayment->update([
                'status' => 'DELETED',
            ]);

            PaymentDeleted::dispatch($localPayment, $payload);
        }

        return $this->successResponse();
    }

    /**
     * Handle payment updated.
     */
    protected function handlePaymentUpdated(array $payload): SymfonyResponse
    {
        $payment = $payload['payment'] ?? [];
        
        $localPayment = Payment::where('asaas_id', $payment['id'])->first();
        
        if ($localPayment) {
            $localPayment->update([
                'status' => $payment['status'],
                'value' => $payment['value'],
                'net_value' => $payment['netValue'] ?? $localPayment->net_value,
                'due_date' => Carbon::parse($payment['dueDate']),
            ]);

            PaymentUpdated::dispatch($localPayment, $payload);
        }

        return $this->successResponse();
    }

    /**
     * Return a successful response.
     */
    protected function successResponse(): Response
    {
        return new Response('Webhook handled', 200);
    }
}
