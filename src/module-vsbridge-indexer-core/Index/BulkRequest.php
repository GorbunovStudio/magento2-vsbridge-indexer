<?php
/**
 * @package   Divante\VsbridgeIndexerCore
 * @author    Agata Firlejczyk <afirlejczyk@divante.pl>
 * @copyright 2019 Divante Sp. z o.o.
 * @license   See LICENSE_DIVANTE.txt for license details.
 */

namespace Divante\VsbridgeIndexerCore\Index;

use Divante\VsbridgeIndexerCore\Api\BulkRequestInterface;

/**
 * Class BulkRequest
 */
class BulkRequest implements BulkRequestInterface
{
    /**
     * Bulk operation stack.
     *
     * @var array
     */
    private $bulkData = [];

    /**
     * @inheritdoc
     */
    public function deleteDocuments($index, array $docIds)
    {
        foreach ($docIds as $docId) {
            $this->deleteDocument($index, $docId);
        }

        return $this;
    }

    /**
     * @param string $index
     * @param $docId
     *
     * @return $this
     */
    private function deleteDocument($index, $docId)
    {
        $this->bulkData[] = [
            'delete' => [
                '_index' => $index,
                '_id' => $docId,
            ]
        ];

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addDocuments($index, array $data)
    {
        foreach ($data as $docId => $documentData) {
            $documentData = $this->prepareDocument($documentData);
            $this->addDocument($index, $docId, $documentData);
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function prepareDocument(array $data)
    {
        unset($data['entity_id']);
        unset($data['row_id']);

        return $data;
    }

    /**
     * @inheritdoc
     */
    private function addDocument($index, $docId, array $data)
    {
        $this->bulkData[] = [
            'index' => [
                '_index' => $index,
                '_id' => $docId,
            ]
        ];

        $this->bulkData[] = $data;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isEmpty()
    {
        return count($this->bulkData) == 0;
    }

    /**
     * @inheritdoc
     */
    public function getOperations()
    {
        return $this->bulkData;
    }
}
