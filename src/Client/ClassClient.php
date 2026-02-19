<?php

namespace ExcelleInsights\QuickBooks\Client;

use ExcelleInsights\QuickBooks\Auth\Authentication;

class ClassClient extends BaseClient
{
    /**
     * Create a new Class in QuickBooks
     */
    public function create(array $data): object
    {
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Class name is required.');
        }

        $payload = [
            'Name'   => $data['name'],
            'Active' => $data['active'] ?? true,
        ];

        // Include parent if available and has qbo_id
        if (!empty($data['parent_qbo_id'])) {
            $payload['ParentRef'] = [
                'value' => $data['parent_qbo_id']
            ];
        }

        return $this->sendRequest('POST', $this->endpoint('class'), $payload);
    }

    /**
     * Retrieve a class by QBO ID
     */
    public function getById(string $qboClassId): object
    {
        return $this->sendRequest('GET', $this->endpoint('class/' . urlencode($qboClassId)));
    }
    public function getAll(): object
    {
        $query = "select * from Class";
        return $this->sendRequest('GET', $this->endpoint("query?query=" . rawurlencode($query)));
    }

    /**
     * Search class by name
     */
    public function search(string $className): object
    {
        $query = "select Id from Class Where Name = '" . addslashes(trim($className)) . "'";
        return $this->sendRequest('GET', $this->endpoint('query?query=' . rawurlencode($query)));
    }
}
