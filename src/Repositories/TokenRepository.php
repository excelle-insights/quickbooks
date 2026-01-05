<?php
namespace ExcelleInsights\QuickBooks\Repositories;

use PDO;

class TokenRepository
{
    public function __construct(private PDO $pdo) {}

    public function getLatest(string $app, string $userId): ?object
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM api_access_tokens
            WHERE app = ? AND user_id = ?
            ORDER BY updated_at DESC
            LIMIT 1
        ");
        $stmt->execute([$app, $userId]);
        $record = $stmt->fetch(PDO::FETCH_OBJ);
        return $record ?: null;
    }

    public function save(string $app, string $userId, array $accessToken): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO api_access_tokens (app, access_token, user_id)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE access_token = VALUES(access_token), updated_at = NOW()
        ");
        $stmt->execute([$app, json_encode($accessToken), $userId]);
    }
}
