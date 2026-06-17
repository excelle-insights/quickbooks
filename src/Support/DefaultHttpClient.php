<?php

namespace ExcelleInsights\QuickBooks\Support;

use PDO;
use ExcelleInsights\QuickBooks\Contracts\HttpClientInterface;
use ExcelleInsights\QuickBooks\Contracts\LoggerInterface;

class DefaultHttpClient implements HttpClientInterface
{
    private ?LoggerInterface $logger;

    /**
     * Logger is optional to avoid hard coupling
     */
    public function __construct(PDO $pdo)
    {
        $this->logger = new DatabaseLogger($pdo);
    }
    // public static function make(): self
    // {
    //     return new self();
    // }

    public function send(
        string $method,
        string $url,
        array $headers = [],
        $body = null
    ): array {
        $startTime = microtime(true);

        $ch = curl_init();

        $formattedHeaders = [];
        foreach ($headers as $key => $value) {
            $formattedHeaders[] = "{$key}: {$value}";
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => $formattedHeaders,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HEADER         => true,
        ]);

        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
            curl_setopt(
                $ch,
                CURLOPT_POSTFIELDS,
                is_array($body) ? json_encode($body) : $body
            );
        }

        $rawResponse = curl_exec($ch);
        $statusCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curlError   = curl_error($ch);

        curl_close($ch);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        $logLevel    = 'info';
        $responseHeaders = [];
        $responseBody    = null;

        if ($rawResponse === false) {
            $logLevel    = 'error';
            $responseBody = $curlError;
        } else {
            $rawHeaders      = substr($rawResponse, 0, $headerSize);
            $responseBody    = substr($rawResponse, $headerSize);
            $responseHeaders = $this->parseHeaders($rawHeaders);

            if ($curlError || $statusCode >= 400) {
                $logLevel = 'error';
            }
        }

        $this->log($logLevel, 'HTTP Transaction', [
            'method'           => strtoupper($method),
            'url'              => $url,
            'request_headers'  => $this->redactHeaders($headers),
            'request_body'     => $body,
            'response_status'  => $statusCode,
            'response_headers' => $responseHeaders,
            'response_body'    => $responseBody,
            'duration_ms'      => $durationMs,
            'curl_error'       => $curlError ?: null,
        ]);

        if ($rawResponse === false) {
            return [
                'status'  => 0,
                'headers' => [],
                'body'    => $curlError,
            ];
        }

        return [
            'status'  => $statusCode,
            'headers' => $responseHeaders,
            'body'    => $responseBody,
        ];
    }

    private function parseHeaders(string $rawHeaders): array
    {
        $headers = [];
        $lines   = explode("\r\n", trim($rawHeaders));
        array_shift($lines);

        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        return $headers;
    }

    private function redactHeaders(array $headers): array
    {
        $sensitive = ['authorization', 'x-api-key'];

        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $sensitive, true)) {
                $headers[$key] = '[REDACTED]';
            }
        }

        return $headers;
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->{$level}($message, $context);
        }
    }
}
