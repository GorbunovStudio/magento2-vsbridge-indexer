<?php

namespace Divante\VsbridgeIndexerCore\Elasticsearch;

use Divante\VsbridgeIndexerCore\Api\Client\ClientInterface;
use OpenSearch\Exception\NotFoundHttpException;

class Client implements ClientInterface
{
    private $client;

    public function __construct(\OpenSearch\Client $client)
    {
        $this->client = $client;
    }

    public function bulk(array $bulkParams)
    {
        return $this->asArray($this->client->bulk($bulkParams));
    }

    public function changeRefreshInterval(string $indexName, $value)
    {
        $this->asArray($this->client->indices()->putSettings([
            'index' => $indexName, 'body' => ['refresh_interval' => $value],
        ]));
    }

    public function changeNumberOfReplicas(string $indexName, int $value)
    {
        $this->asArray($this->client->indices()->putSettings([
            'index' => $indexName, 'body' => ['number_of_replicas' => $value],
        ]));
    }

    public function createIndex(string $indexName, array $indexSettings)
    {
        return $this->asArray($this->client->indices()->create([
            'index' => $indexName, 'body' => $indexSettings,
        ]));
    }

    public function getClustersHealth(): array
    {
        return $this->asArray($this->client->cat()->health(['format' => 'json']));
    }

    public function getIndicesNameByAlias(string $indexAlias): array
    {
        try {
            return array_keys($this->asArray($this->client->indices()->getAlias(['name' => $indexAlias])));
        } catch (NotFoundHttpException $exception) {
            return [];
        }
    }

    public function getIndexSettings(string $indexName): array
    {
        return $this->asArray($this->client->indices()->getSettings(['index' => $indexName]));
    }

    public function updateAliases(array $aliasActions)
    {
        $response = $this->asArray($this->client->indices()->updateAliases(['body' => ['actions' => $aliasActions]]));
        if (empty($response['acknowledged'])) {
            throw new \RuntimeException('OpenSearch did not acknowledge the alias update; old indexes were retained.');
        }
    }

    public function refreshIndex(string $indexName)
    {
        $response = $this->asArray($this->client->indices()->refresh(['index' => $indexName]));
        if (!isset($response['_shards']['failed']) || $response['_shards']['failed'] > 0) {
            throw new \RuntimeException('OpenSearch could not refresh all index shards.');
        }
    }

    public function indexExists(string $indexName)
    {
        return $this->client->indices()->exists(['index' => $indexName]);
    }

    public function deleteIndex(string $indexName)
    {
        return $this->asArray($this->client->indices()->delete(['index' => $indexName]));
    }

    public function putMapping(string $indexName, array $mapping)
    {
        $this->asArray($this->client->indices()->putMapping(['index' => $indexName, 'body' => $mapping]));
    }

    public function deleteByQuery(array $params)
    {
        $response = $this->asArray($this->client->deleteByQuery($params));
        if (!empty($response['failures']) || !empty($response['timed_out'])) {
            throw new \RuntimeException('OpenSearch could not complete document cleanup.');
        }
    }

    private function asArray($response): array
    {
        if (!is_array($response)) {
            throw new \UnexpectedValueException('Expected an OpenSearch JSON response, received ' . get_debug_type($response));
        }

        return $response;
    }
}
