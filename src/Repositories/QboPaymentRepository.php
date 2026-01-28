<?php

namespace ExcelleInsights\QuickBooks\Repositories;

use PDO;
use DateTime;

class QboPaymentRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Create a new payment record
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO qbo_payments
            (pay_id, qbo_customer_id, total_amount, txn_date, payment_ref, deposit_account_id, private_note, status, retry_count, error_message, last_attempt_at, created_at, updated_at)
            VALUES
            (:pay_id, :qbo_customer_id, :total_amount, :txn_date, :payment_ref, :deposit_account_id, :private_note, :status, :retry_count, :error_message, :last_attempt_at, NOW(), NOW())
        ");

        $stmt->execute([
            ':pay_id' => $data['pay_id'],
            ':qbo_customer_id' => $data['qbo_customer_id'],
            ':total_amount' => $data['total_amount'],
            ':txn_date' => $data['txn_date'] ?? null,
            ':payment_ref' => $data['payment_ref'] ?? null,
            ':deposit_account_id' => $data['deposit_account_id'] ?? null,
            ':private_note' => $data['private_note'] ?? null,
            ':status' => $data['status'] ?? 'PENDING',
            ':retry_count' => $data['retry_count'] ?? 0,
            ':error_message' => $data['error_message'] ?? null,
            ':last_attempt_at' => $data['last_attempt_at'] ?? (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updatePayment(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            $fields[] = "$key = :$key";
            $params[":$key"] = $value;
        }

        $sql = "UPDATE qbo_payments SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    /**
     * Mark payment as synced after successful QBO creation
     */
    public function markSynced(
        int $id,
        string $qboId,
        string $syncToken
    ): void {
        $stmt = $this->pdo->prepare("
            UPDATE qbo_payments 
            SET
                qbo_id      = :qbo_id,
                sync_token  = :sync_token,
                status      = 'synced',
                last_attempt_at  = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':qbo_id'     => $qboId,
            ':sync_token' => $syncToken,
            ':id'         => $id,
        ]);
    }

    /**
     * Mark invoice as failed (retry later)
     */
    public function markFailed(int $id, string $reason): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE qbo_payments 
            SET
                status = 'failed',
                retry_count = retry_count + 1, 
                error_message = :reason,
                last_attempt_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':reason' => $reason,
            ':id'     => $id,
        ]);
    }
    public function getUnsynced(int $limit = 100, int $maxRetries = 5): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM qbo_payments
            WHERE status IN ('pending', 'failed') 
                AND retry_count < :maxRetries
            ORDER BY created_at ASC
            LIMIT :limit
        ");
        $stmt->execute([
            ':retry_count' => $maxRetries,
            ':limit' => $limit
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByQboId(string $qboPaymentId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM qbo_payments WHERE qbo_payment_id = :qbo_payment_id LIMIT 1
        ");
        $stmt->execute([':qbo_payment_id' => $qboPaymentId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
