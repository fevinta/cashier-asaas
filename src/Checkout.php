<?php

declare(strict_types=1);

namespace Fevinta\CashierAsaas;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use JsonSerializable;
use Symfony\Component\HttpFoundation\Response;

/**
 * @implements Arrayable<string, mixed>
 */
class Checkout implements Arrayable, Jsonable, JsonSerializable, Responsable
{
    /**
     * Create a new Checkout instance.
     */
    public function __construct(
        protected string $id,
        protected string $url,
        protected array $session = []
    ) {}

    /**
     * Create a new guest checkout builder.
     */
    public static function guest(): CheckoutBuilder
    {
        return CheckoutBuilder::guest();
    }

    /**
     * Create a checkout builder for an existing customer (billable model).
     */
    public static function customer(Model $owner): CheckoutBuilder
    {
        return CheckoutBuilder::customer($owner);
    }

    /**
     * Get the checkout session ID.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Get the checkout URL.
     */
    public function url(): string
    {
        return $this->url;
    }

    /**
     * Get the full checkout session data.
     */
    public function session(): array
    {
        return $this->session;
    }

    /**
     * Get the checkout status.
     */
    public function status(): ?string
    {
        return $this->session['status'] ?? null;
    }

    /**
     * Redirect to the checkout page.
     */
    public function redirect(): RedirectResponse
    {
        return new RedirectResponse($this->url, 303);
    }

    /**
     * Format the checkout URL from an ID.
     */
    public static function formatUrl(string $id): string
    {
        $baseUrl = config('cashier-asaas.sandbox', false)
            ? 'https://sandbox.asaas.com'
            : 'https://asaas.com';

        return "{$baseUrl}/checkoutSession/show?id={$id}";
    }

    /**
     * Get the instance as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'session' => $this->session,
        ];
    }

    /**
     * Convert the object to its JSON representation.
     */
    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options) ?: '';
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request): Response
    {
        return $this->redirect();
    }

    /**
     * Dynamically get values from the session.
     */
    public function __get(string $key): mixed
    {
        return $this->session[$key] ?? null;
    }

    /**
     * Dynamically check if a value is set on the session.
     */
    public function __isset(string $key): bool
    {
        return isset($this->session[$key]);
    }
}
