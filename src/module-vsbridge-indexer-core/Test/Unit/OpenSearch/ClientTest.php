<?php

namespace Divante\VsbridgeIndexerCore\Test\Unit\OpenSearch;

use Divante\VsbridgeIndexerCore\OpenSearch\Client;
use Divante\VsbridgeIndexerCore\OpenSearch\ClientBuilder;
use Divante\VsbridgeIndexerCore\Index\BulkRequest;
use Divante\VsbridgeIndexerCore\Index\BulkResponse;
use OpenSearch\EndpointFactory;
use OpenSearch\Exception\NotFoundHttpException;
use OpenSearch\Exception\UnauthorizedHttpException;
use OpenSearch\Exception\ForbiddenHttpException;
use OpenSearch\Exception\InternalServerErrorHttpException;
use OpenSearch\Serializers\SmartSerializer;
use OpenSearch\TransportInterface;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    private function client($result, ?array &$requests = null): Client
    {
        $requests = [];
        $transport = $this->createMock(TransportInterface::class);
        $transport->method('sendRequest')->willReturnCallback(
            static function ($method, $uri, $params, $body) use ($result, &$requests) {
                $requests[] = [$method, $uri, $params, $body];
                if ($result instanceof \Throwable) {
                    throw $result;
                }
                return $result;
            }
        );
        return new Client(new \OpenSearch\Client($transport, new EndpointFactory(new SmartSerializer())));
    }

    public function testTypelessMappingAndBulk(): void
    {
        $client = $this->client(['acknowledged' => true], $requests);
        $mapping = ['properties' => ['sku' => ['type' => 'keyword']]];
        $client->putMapping('products', $mapping);
        self::assertSame(['PUT', '/products/_mapping', [], $mapping], $requests[0]);
        $bulk = (new BulkRequest())->addDocuments('products', [5 => ['entity_id' => 5, 'sku' => 'test']]);
        $bulk->deleteDocuments('products', [6]);
        self::assertSame([
            ['index' => ['_index' => 'products', '_id' => 5]],
            ['sku' => 'test'],
            ['delete' => ['_index' => 'products', '_id' => 6]],
        ], $bulk->getOperations());
    }

    public function testAliasNamesAndMissingAlias(): void
    {
        self::assertSame(['products_v1', 'products_v2'], $this->client([
            'products_v1' => ['aliases' => ['products' => []]],
            'products_v2' => ['aliases' => ['products' => []]],
        ])->getIndicesNameByAlias('products'));
        self::assertSame([], $this->client(new NotFoundHttpException(404))->getIndicesNameByAlias('missing'));
        self::assertFalse($this->client(new NotFoundHttpException(404))->indexExists('missing'));
        self::assertTrue($this->client(null)->indexExists('existing'));
    }

    public function testAccessAndServerFailuresPropagate(): void
    {
        foreach ([new UnauthorizedHttpException(401), new ForbiddenHttpException(403), new InternalServerErrorHttpException(500)] as $failure) {
            try {
                $this->client($failure)->getIndicesNameByAlias('products');
                self::fail('The failure must not be swallowed.');
            } catch (\Throwable $actual) {
                self::assertSame($failure, $actual);
            }
        }
    }

    public function testMalformedResponseIsRejected(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->client('not json')->getIndexSettings('products');
    }

    public function testFailedRefreshAndUnacknowledgedAliasAreRejected(): void
    {
        foreach ([['refreshIndex', ['_shards' => ['failed' => 1]], 'products'], ['updateAliases', ['acknowledged' => false], []]] as [$method, $response, $argument]) {
            try {
                $this->client($response)->$method($argument);
                self::fail('An incomplete operation must not be accepted.');
            } catch (\RuntimeException $exception) {
                self::assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function testBulkDiagnosticsDoNotRequireType(): void
    {
        $error = ['_index' => 'products', '_id' => '1', 'error' => ['type' => 'mapper_parsing_exception', 'reason' => 'invalid number']];
        $response = new BulkResponse(['errors' => true, 'items' => [
            ['index' => $error],
            ['index' => array_replace($error, ['_id' => '2'])],
            ['index' => ['_index' => 'products', '_id' => '3', 'status' => 201]],
        ]]);
        self::assertTrue($response->hasErrors());
        self::assertCount(1, $response->getSuccessItems());
        self::assertSame(2, $response->aggregateErrorsByReason()[0]['count']);
        self::assertSame(['1', '2'], $response->aggregateErrorsByReason()[0]['document_ids']);
    }

    public function testBuilderAppliesAuthenticationTimeoutsAndTls(): void
    {
        $client = (new ClientBuilder())->build([
            'host' => 'search.example', 'port' => 9243, 'scheme' => 'https',
            'enable_http_auth' => true, 'auth_user' => 'user', 'auth_pwd' => 'password',
            'timeout' => 7, 'connect_timeout' => 3,
        ]);
        $transport = (new \ReflectionProperty($client, 'httpTransport'))->getValue($client);
        $http = (new \ReflectionProperty($transport, 'client'))->getValue($transport);
        self::assertSame('https://search.example:9243', (string)$http->getConfig('base_uri'));
        self::assertSame(['user', 'password'], $http->getConfig('auth'));
        self::assertTrue($http->getConfig('verify'));
        self::assertSame(7, $http->getConfig('timeout'));
        self::assertSame(3, $http->getConfig('connect_timeout'));
    }
}
