<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Http\Controllers;

use Carbon\Carbon;
use Fevinta\CashierAsaas\Cashier;
use Fevinta\CashierAsaas\Events\BoletoGenerated;
use Fevinta\CashierAsaas\Events\BoletoRegistered;
use Fevinta\CashierAsaas\Events\PaymentConfirmed;
use Fevinta\CashierAsaas\Events\PaymentCreated;
use Fevinta\CashierAsaas\Events\PaymentDeleted;
use Fevinta\CashierAsaas\Events\PaymentOverdue;
use Fevinta\CashierAsaas\Events\PaymentReceived;
use Fevinta\CashierAsaas\Events\PaymentRefunded;
use Fevinta\CashierAsaas\Events\PaymentUpdated;
use Fevinta\CashierAsaas\Events\PixGenerated;
use Fevinta\CashierAsaas\Events\SubscriptionCreated;
use Fevinta\CashierAsaas\Events\SubscriptionDeleted;
use Fevinta\CashierAsaas\Events\SubscriptionUpdated;
use Fevinta\CashierAsaas\Events\WebhookHandled;
use Fevinta\CashierAsaas\Events\WebhookReceived;
use Fevinta\CashierAsaas\Exceptions\InvalidWebhookPayload;
use Fevinta\CashierAsaas\Payment;
use Fevinta\CashierAsaas\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class WebhookController extends Controller
{
    /**
     * Handle an Asaas webhook call.
     */
    public function handleWebhook(Request $request): SymfonyResponse
    {
        $payload = $request->all();
        $event = $payload['event'] ?? null;

        if (empty($event)) {
            Log::warning('Cashier Asaas: Webhook received without event type.', ['payload' => $payload]);

            return $this->errorResponse('Missing event type', 400);
        }

        WebhookReceived::dispatch($payload);

        // Route to appropriate handler
        $method = 'handle'.Str::studly(str_replace('.', '_', $event));

        if (method_exists($this, $method)) {
            try {
                $response = $this->{$method}($payload);

                WebhookHandled::dispatch($payload);

                return $response;
            } catch (InvalidWebhookPayload $e) {
                Log::warning('Cashier Asaas: Invalid webhook payload.', [
                    'event' => $event,
                    'error' => $e->getMessage(),
                    'payload' => $payload,
                ]);

                return $this->errorResponse($e->getMessage(), 400);
            } catch (Throwable $e) {
                Log::error('Cashier Asaas: Webhook processing failed.', [
                    'event' => $event,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'payload' => $payload,
                ]);

                // Still return 200 to prevent Asaas from retrying
                // The error is logged and can be handled separately
                return $this->successResponse();
            }
        }

        // Unknown event type - log and acknowledge
        Log::debug('Cashier Asaas: Unhandled webhook event.', ['event' => $event]);

        return $this->successResponse();
    }

    /**
     * Handle payment created.
     */
    protected function handlePaymentCreated(array $payload): SymfonyResponse
    {
        $paymentData = $payload['payment'] ?? [];

        $localPayment = $this->findOrCreatePayment($paymentData);

        if ($localPayment) {
            PaymentCreated::dispatch($localPayment, $payload);

            // Dispatch Brazilian-specific events based on billing type
            $this->dispatchBrazilianPaymentEvents($localPayment, $paymentData, $payload);
        }

        return $this->successResponse();
    }

    /**
     * Handle payment received.
     */
    protected function handlePaymentReceived(array $payload): SymfonyResponse
    {
        $paymentData = $payload['payment'] ?? [];

        $localPayment = $this->findPayment($paymentData['id'] ?? null);

        if ($localPayment) {
            $localPayment->update([
                'status' => $paymentData['status'],
                'payment_date' => isset($paymentData['paymentDate'])
                    ? Carbon::parse($paymentData['paymentDate'])
                    : Carbon::now(),
                'net_value' => $paymentData['netValue'] ?? $localPayment->net_value,
            ]);

            PaymentReceived::dispatch($localPayment->fresh(), $payload);
        }

        return $this->successResponse();
    }

    /**
     * Handle payment confirmed.
     */
    protected function handlePaymentConfirmed(array $payload): SymfonyResponse
    {
        $paymentData = $payload['payment'] ?? [];

        $localPayment = $this->findPayment($paymentData['id'] ?? null);

        if ($localPayment) {
            $localPayment->update([
                'status' => $paymentData['status'],
                'confirmed_date' => Carbon::now(),
            ]);

            PaymentConfirmed::dispatch($localPayment->fresh(), $payload);
        }

        return $this->successResponse();
    }

    /**
     * Handle payment overdue.
     */
    protected function handlePaymentOverdue(array $payload): SymfonyResponse
    {
        $paymentData = $payload['payment'] ?? [];

        $localPayment = $this->findPayment($paymentData['id'] ?? null);

        if ($localPayment) {
            $localPayment->update([
                'status' => $paymentData['status'] ?? 'OVERDUE',
            ]);

            PaymentOverdue::dispatch($localPayment->fresh(), $payload);
        }

        return $this->successResponse();
    }

    /**
     * Handle payment refunded.
     */
    protected function handlePaymentRefunded(array $payload): SymfonyResponse
    {
        $paymentData = $payload['payment'] ?? [];

        $localPayment = $this->findPayment($paymentData['id'] ?? null);

        if ($localPayment) {
            $localPayment->update([
                'status' => $paymentData['status'] ?? 'REFUNDED',
                'refunded_at' => Carbon::now(),
            ]);

            PaymentRefunded::dispatch($localPayment->fresh(), $payload);
        }

        return $this->successResponse();
    }

    /**
     * Handle payment deleted.
     */
    protected function handlePaymentDeleted(array $payload): SymfonyResponse
    {
        $paymentData = $payload['payment'] ?? [];

        $localPayment = $this->findPayment($paymentData['id'] ?? null);

        if ($localPayment) {
            $localPayment->update([
                'status' => 'DELETED',
            ]);

            PaymentDeleted::dispatch($localPayment->fresh(), $payload);
        }

        return $this->successResponse();
    }

    /**
     * Handle payment updated.
     */
    protected function handlePaymentUpdated(array $payload): SymfonyResponse
    {
        $paymentData = $payload['payment'] ?? [];

        $localPayment = $this->findPayment($paymentData['id'] ?? null);

        if ($localPayment) {
            $localPayment->update([
                'status' => $paymentData['status'],
                'value' => $paymentData['value'],
                'net_value' => $paymentData['netValue'] ?? $localPayment->net_value,
                'due_date' => Carbon::parse($paymentData['dueDate']),
                'billing_type' => $paymentData['billingType'] ?? $localPayment->billing_type,
            ]);

            PaymentUpdated::dispatch($localPayment->fresh(), $payload);
        }

        return $this->successResponse();
    }

    /**
     * Handle subscription created.
     */
    protected function handleSubscriptionCreated(array $payload): SymfonyResponse
    {
        $subscriptionData = $payload['subscription'] ?? [];

        // Find existing subscription or create if needed
        $subscription = $this->findSubscription($subscriptionData['id'] ?? null);

        if ($subscription) {
            // Sync subscription data from Asaas
            $subscription->update([
                'asaas_status' => $subscriptionData['status'] ?? $subscription->asaas_status,
                'value' => $subscriptionData['value'] ?? $subscription->value,
                'cycle' => $subscriptionData['cycle'] ?? $subscription->cycle,
                'billing_type' => $subscriptionData['billingType'] ?? $subscription->billing_type,
                'next_due_date' => isset($subscriptionData['nextDueDate'])
                    ? Carbon::parse($subscriptionData['nextDueDate'])
                    : $subscription->next_due_date,
            ]);

            SubscriptionCreated::dispatch($subscription->fresh(), $payload);
        }

        return $this->successResponse();
    }

    /**
     * Handle subscription updated.
     */
    protected function handleSubscriptionUpdated(array $payload): SymfonyResponse
    {
        $subscriptionData = $payload['subscription'] ?? [];

        $subscription = $this->findSubscription($subscriptionData['id'] ?? null);

        if ($subscription) {
            $subscription->update([
                'asaas_status' => $subscriptionData['status'] ?? $subscription->asaas_status,
                'value' => $subscriptionData['value'] ?? $subscription->value,
                'cycle' => $subscriptionData['cycle'] ?? $subscription->cycle,
                'billing_type' => $subscriptionData['billingType'] ?? $subscription->billing_type,
                'next_due_date' => isset($subscriptionData['nextDueDate'])
                    ? Carbon::parse($subscriptionData['nextDueDate'])
                    : $subscription->next_due_date,
            ]);

            SubscriptionUpdated::dispatch($subscription->fresh(), $payload);
        }

        return $this->successResponse();
    }

    /**
     * Handle subscription deleted/cancelled.
     */
    protected function handleSubscriptionDeleted(array $payload): SymfonyResponse
    {
        $subscriptionData = $payload['subscription'] ?? [];

        $subscription = $this->findSubscription($subscriptionData['id'] ?? null);

        if ($subscription) {
            $subscription->update([
                'asaas_status' => 'INACTIVE',
                'ends_at' => $subscription->ends_at ?? Carbon::now(),
            ]);

            SubscriptionDeleted::dispatch($subscription->fresh(), $payload);
        }

        return $this->successResponse();
    }

    /**
     * Find a payment by Asaas ID.
     */
    protected function findPayment(?string $asaasId): ?Payment
    {
        if (! $asaasId) {
            return null;
        }

        return Cashier::$paymentModel::where('asaas_id', $asaasId)->first();
    }

    /**
     * Find or create a payment from webhook data.
     */
    protected function findOrCreatePayment(array $paymentData): ?Payment
    {
        if (empty($paymentData['id'])) {
            return null;
        }

        $subscriptionId = null;
        $customerId = null;

        // Find subscription if linked
        if (! empty($paymentData['subscription'])) {
            $subscription = $this->findSubscription($paymentData['subscription']);
            if ($subscription) {
                $subscriptionId = $subscription->id;
                $customerId = $subscription->user_id;
            }
        }

        // Try to find customer by external reference if no subscription
        if (! $customerId && ! empty($paymentData['externalReference'])) {
            $billableModel = config('cashier-asaas.model');
            $owner = $billableModel::find($paymentData['externalReference']);
            if ($owner) {
                $customerId = $owner->getKey();
            }
        }

        return Cashier::$paymentModel::updateOrCreate(
            ['asaas_id' => $paymentData['id']],
            [
                'subscription_id' => $subscriptionId,
                'customer_id' => $customerId,
                'billing_type' => $paymentData['billingType'] ?? null,
                'value' => $paymentData['value'] ?? 0,
                'net_value' => $paymentData['netValue'] ?? $paymentData['value'] ?? 0,
                'status' => $paymentData['status'] ?? 'PENDING',
                'due_date' => isset($paymentData['dueDate']) ? Carbon::parse($paymentData['dueDate']) : null,
                'invoice_url' => $paymentData['invoiceUrl'] ?? null,
                'bank_slip_url' => $paymentData['bankSlipUrl'] ?? null,
                'pix_qrcode' => $paymentData['pixQrCode'] ?? null,
                'pix_copy_paste' => $paymentData['pixCopiaECola'] ?? null,
            ]
        );
    }

    /**
     * Find a subscription by Asaas ID.
     */
    protected function findSubscription(?string $asaasId): ?Subscription
    {
        if (! $asaasId) {
            return null;
        }

        return Cashier::$subscriptionModel::where('asaas_id', $asaasId)->first();
    }

    /**
     * Dispatch Brazilian-specific payment events.
     */
    protected function dispatchBrazilianPaymentEvents(Payment $payment, array $paymentData, array $payload): void
    {
        $billingType = $paymentData['billingType'] ?? '';

        if ($billingType === 'PIX' && (! empty($paymentData['pixQrCode']) || ! empty($paymentData['pixCopiaECola']))) {
            PixGenerated::dispatch(
                $payment,
                $payload,
                $paymentData['pixQrCode'] ?? null,
                $paymentData['pixCopiaECola'] ?? null
            );
        }

        if ($billingType === 'BOLETO' && ! empty($paymentData['bankSlipUrl'])) {
            BoletoGenerated::dispatch(
                $payment,
                $payload,
                $paymentData['bankSlipUrl'] ?? null,
                $paymentData['identificationField'] ?? null
            );
        }
    }

    /**
     * Handle boleto registered at bank.
     *
     * This is a specific Asaas event when the boleto is registered
     * at the bank and becomes valid for payment.
     */
    protected function handlePaymentBankSlipViewed(array $payload): SymfonyResponse
    {
        // This event indicates the boleto has been registered
        $paymentData = $payload['payment'] ?? [];

        $localPayment = $this->findPayment($paymentData['id'] ?? null);

        if ($localPayment && $localPayment->billing_type === 'BOLETO') {
            BoletoRegistered::dispatch($localPayment, $payload);
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

    /**
     * Return an error response.
     */
    protected function errorResponse(string $message, int $status = 400): Response
    {
        return new Response($message, $status);
    }
}
