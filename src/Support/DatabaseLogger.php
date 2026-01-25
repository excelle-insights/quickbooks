<?php

namespace ExcelleInsights\QuickBooks\Support;

use PDO;
use Throwable;
use ExcelleInsights\QuickBooks\Contracts\LoggerInterface;

class DatabaseLogger implements LoggerInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $context);
    }

    private function write(string $level, array $context): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO http_request_logs (
                    method,
                    url,
                    request_headers,
                    request_body,
                    response_status,
                    response_headers,
                    response_body
                ) VALUES (
                    :method,
                    :url,
                    :request_headers,
                    :request_body,
                    :response_status,
                    :response_headers,
                    :response_body
                )"
            );

            $stmt->execute([
                ':method'           => $context['method'] ?? null,
                ':url'              => $context['url'] ?? null,
                ':request_headers'  => $this->json($context['request_headers'] ?? null),
                ':request_body'     => $this->json($context['request_body'] ?? null),
                ':response_status'  => $context['response_status'] ?? null,
                ':response_headers' => $this->json($context['response_headers'] ?? null),
                ':response_body'    => $this->json($context['response_body'] ?? null),
            ]);
        } catch (Throwable $e) {
            // Never break application flow
            error_log('HTTP LOGGER FAILURE: ' . $e->getMessage());
        }
    }

    private function json($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
    }
}
