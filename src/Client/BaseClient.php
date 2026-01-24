<?php

namespace ExcelleInsights\QuickBooks\Client;

use ExcelleInsights\QuickBooks\Auth\Authentication;
use ExcelleInsights\QuickBooks\Contracts\HttpClientInterface;

abstract class BaseClient
{
    protected string $baseUrl;
    protected string $companyId;
    protected Authentication $auth;
    protected HttpClientInterface $http;

    public function __construct(
        string $baseUrl,
        string $companyId,
        Authentication $auth,
        HttpClientInterface $http
    ) {
        $this->baseUrl   = rtrim($baseUrl, '/');
        $this->companyId = $companyId;
        $this->auth      = $auth;
        $this->http      = $http;
    }

    /**
     * Perform a HTTP request to QuickBooks Online API
     *
     * @throws \RuntimeException
     */
    protected function sendRequest(string $method, string $endpoint, array $data = []): object
    {
        $url = $this->baseUrl . $endpoint;

        $headers = [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $this->auth->accessToken(),
        ];

        $payload = empty($data) ? null : json_encode($data);

        $response = $this->http->send(
            $method,
            $url,
            $headers,
            $payload
        );

        $status = $response['status'];
        $body   = $response['body'];

        // Decode JSON if needed
        if (is_string($body)) {
            $decoded = json_decode($body);
        } else {
            $decoded = $body;
        }

        if ($status >= 400) {
            $message = is_object($decoded) && property_exists($decoded, 'Fault')
                ? json_encode($decoded->Fault)
                : json_encode($decoded);

            throw new \RuntimeException(
                "QBO API Error ({$status}): {$message}"
            );
        }

        return is_object($decoded) ? $decoded : (object) $decoded;
    }

    /**
     * Helper to build standard API endpoint with minorversion
     */
    protected function endpoint(string $path, int $minorVersion = 69): string
    {
        return sprintf(
            '/v3/company/%s/%s?minorversion=%d',
            $this->companyId,
            ltrim($path, '/'),
            $minorVersion
        );
    }
}
