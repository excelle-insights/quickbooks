<?php

namespace ExcelleInsights\QuickBooks\Services;

use ExcelleInsights\QuickBooks\Client\ClassClient;
use ExcelleInsights\QuickBooks\Repositories\QboClassRepository;

class ClassSyncService
{
    public function __construct(
        private QboClassRepository $classes,
        private ClassClient $qbo
    ) {}

    /**
     * Create a class locally and sync to QBO
     */
    public function create(array $data): object
    {
        // Create locally first
        $localId = $this->classes->create($data);

        try {
            // Build and send to QBO
            if (!empty($data['parent_id'])) {
                $parent = $this->classes->find($data['parent_id']);
                if (!$parent || !isset($parent->qbo_id)) {
                    throw new \InvalidArgumentException('Parent class does not exist in QBO');
                }
                $data['parent_qbo_id'] = $parent->qbo_id;
            }
            $payload = [
                'name'          => $data['name'],
                'active'        => $data['active'] ?? true,
                'parent_qbo_id'     => $data['parent_qbo_id'] ?? null,
            ];

            $response = $this->qbo->create($payload);

            if (!isset($response->Class)) {
                throw new \RuntimeException('Invalid QBO response for class creation');
            }

            // Update local record as synced
            $this->classes->markSynced(
                $localId,
                $response->Class->Id,
                $response->Class->SyncToken
            );

            return (object)[
                'status'   => 'synced',
                'local_id' => $localId,
                'qbo_id'   => $response->Class->Id,
                'data'     => $response
            ];

        } catch (\Throwable $e) {
            // 4️⃣ Mark as failed, leave pending for retry
            $this->classes->markFailed(
                $localId,
                $e->getMessage()
            );

            return (object)[
                'status'   => 'pending',
                'local_id' => $localId,
                'error'    => $e->getMessage()
            ];
        }
    }
}
