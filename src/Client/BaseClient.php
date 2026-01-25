<?php

namespace ExcelleInsights\QuickBooks\Client;

use ExcelleInsights\QuickBooks\Auth\Authentication;
use ExcelleInsights\QuickBooks\Contracts\HttpClientInterface;
use RuntimeException;

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
     * @param string $method GET|POST|PUT|DELETE
     * @param string $endpoint API endpoint path
     * @param array  $data Optional payload
     *
     * @return object JSON-decoded response
     * @throws RuntimeException on HTTP error or invalid JSON
     */
    protected function sendRequest(string $method, string $endpoint, array $data = []): object
    {
        $url = $this->baseUrl . $endpoint;

        $headers = [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $this->auth->accessToken(),
        ];

        // Let the HttpClient handle JSON encoding internally
        $response = $this->http->send(
            $method,
            $url,
            $headers,
            empty($data) ? null : $data
        );

        $status = $response['status'] ?? 0;
        $body   = $response['body'] ?? null;

        // Decode JSON safely
        if (is_string($body)) {
            $decoded = json_decode($body);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException(
                    "Invalid JSON response from QBO ({$status}): " . json_last_error_msg()
                );
            }
        } else {
            $decoded = $body;
        }

        // Handle API errors
        if ($status >= 400) {
            $message = is_object($decoded) && property_exists($decoded, 'Fault')
                ? json_encode($decoded->Fault)
                : json_encode($decoded);

            throw new RuntimeException("QBO API Error ({$status}): {$message}");
        }

        return is_object($decoded) ? $decoded : (object) $decoded;
    }

    /**
     * Build standard QuickBooks Online API endpoint with minorversion
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
