<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to verify Asaas webhook signatures.
 *
 * This middleware validates the webhook token sent by Asaas.
 * Verification is optional by default - it only runs when
 * ASAAS_WEBHOOK_TOKEN is configured.
 */
class VerifyWebhookSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = config('cashier-asaas.webhook_token');

        // If no token is configured, skip verification (development mode)
        if (empty($configuredToken)) {
            if (App::environment('production')) {
                Log::warning(
                    'Cashier Asaas: Webhook signature verification is disabled. '.
                    'Set ASAAS_WEBHOOK_TOKEN in production for security.'
                );
            }

            return $next($request);
        }

        // Get the token from the request header
        $requestToken = $request->header('asaas-access-token');

        if (empty($requestToken)) {
            Log::warning('Cashier Asaas: Webhook received without access token header.');

            return response('Unauthorized: Missing access token', 403);
        }

        // Verify the token matches
        if (! hash_equals($configuredToken, $requestToken)) {
            Log::warning('Cashier Asaas: Webhook signature verification failed.', [
                'ip' => $request->ip(),
            ]);

            return response('Unauthorized: Invalid access token', 403);
        }

        return $next($request);
    }
}
