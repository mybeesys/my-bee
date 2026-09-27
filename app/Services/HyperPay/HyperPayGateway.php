<?php

namespace App\Services\HyperPay;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HyperPayGateway
{
    public function __construct(protected HyperPayConfig $config)
    {
    }

    public static function make(?HyperPayConfig $config = null): self
    {
        return new self($config ?? HyperPayConfig::instance());
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function createCheckout(array $parameters): array
    {
        $response = $this->request('post', '/v1/checkouts', $parameters);

        if (! isset($response['id']) || $response['id'] === '') {
            throw new RuntimeException($this->errorMessage($response, __('fields.hyperpay_checkout_failed')));
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchPaymentStatus(string $resourcePath): array
    {
        $path = $resourcePath;

        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        return $this->request('get', $path, [
            'entityId' => $this->config->entityId(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected function request(string $method, string $path, array $parameters): array
    {
        $token = $this->config->accessToken();

        if ($token === '' || $this->config->entityId() === '') {
            throw new RuntimeException(__('fields.hyperpay_not_configured'));
        }

        $http = Http::timeout($this->config->timeout())
            ->withToken($token)
            ->acceptJson();

        $url = $this->config->baseUrl().$path;

        $response = $method === 'get'
            ? $http->get($url, $parameters)
            : $http->asForm()->post($url, $parameters);

        return $this->decode($response);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decode(Response $response): array
    {
        $json = $response->json();

        if (! is_array($json)) {
            throw new RuntimeException(__('fields.hyperpay_gateway_unreachable'));
        }

        if ($response->failed() && ! isset($json['id'])) {
            throw new RuntimeException($this->errorMessage($json, __('fields.hyperpay_gateway_unreachable')));
        }

        return $json;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function errorMessage(array $payload, string $fallback): string
    {
        $description = $payload['result']['description'] ?? null;

        return is_string($description) && $description !== '' ? $description : $fallback;
    }
}
