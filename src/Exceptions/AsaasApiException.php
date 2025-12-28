<?php

declare(strict_types=1);

namespace FernandoHS\CashierAsaas\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

class AsaasApiException extends Exception
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly array $errors = [],
        public readonly ?string $asaasCode = null,
    ) {
        parent::__construct($message, $statusCode);
    }

    public static function fromResponse(Response $response): self
    {
        $body = $response->json();
        $errors = $body['errors'] ?? [];
        
        $message = 'Asaas API Error';
        $asaasCode = null;

        if (! empty($errors)) {
            $firstError = $errors[0] ?? [];
            $message = $firstError['description'] ?? $message;
            $asaasCode = $firstError['code'] ?? null;
        }

        return new self(
            message: $message,
            statusCode: $response->status(),
            errors: $errors,
            asaasCode: $asaasCode,
        );
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getAsaasCode(): ?string
    {
        return $this->asaasCode;
    }
}
