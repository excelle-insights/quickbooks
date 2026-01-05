<?php

namespace ExcelleInsights\QuickBooks\Client;

use ExcelleInsights\QuickBooks\Auth\Authentication;

abstract class BaseClient
{
    protected string $baseUrl;
    protected string $companyId;
    protected Authentication $auth;

    public function __construct(string $baseUrl, string $companyId, Authentication $auth)
    {
        $this->baseUrl   = rtrim($baseUrl, '/');
        $this->companyId = $companyId;
        $this->auth      = $auth;
    }

    /**
     * Perform a HTTP request to QuickBooks Online API
     *
     * @param string $method GET|POST|PUT|DELETE
     * @param string $endpoint e.g. '/customer'
     * @param array  $data Optional payload
     *
     * @return object JSON-decoded response
     * @throws \RuntimeException on HTTP or curl error
     */
    protected function sendRequest(string $method, string $endpoint, array $data = []): object
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init($url);

        $headers = [
            "Accept: application/json",
            "Content-Type: application/json",
            "Authorization: Bearer " . $this->auth->accessToken()
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers
        ]);

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            throw new \RuntimeException('cURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        $decoded = json_decode($response);

        if ($status >= 400) {
            $message = property_exists($decoded, 'Fault') 
                ? json_encode($decoded->Fault) 
                : $response;
            throw new \RuntimeException("QBO API Error ({$status}): {$message}");
        }

        return $decoded;
    }

    /**
     * Helper to build standard API endpoint with minorversion
     */
    protected function endpoint(string $path, int $minorVersion = 69): string
    {
        return sprintf('/v3/company/%s/%s?minorversion=%d', $this->companyId, ltrim($path, '/'), $minorVersion);
    }
}
