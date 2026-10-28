<?php

use Divante\VsbridgeIndexerCore\OpenSearch\Client;
use Divante\VsbridgeIndexerCore\OpenSearch\ClientBuilder;
use OpenSearch\Exception\UnauthorizedHttpException;
use OpenSearch\GuzzleClientFactory;
use PHPUnit\Framework\TestCase;

/** Only for the disposable fixture's self-signed demo certificates. */
class DemoTlsTest extends TestCase
{
    public function testTlsVerificationAndBasicAuthentication(): void
    {
        $endpoint = getenv('OPENSEARCH_DEMO_TLS_URL');
        if (!$endpoint) {
            self::markTestSkipped('Set OPENSEARCH_DEMO_TLS_URL to the isolated TLS fixture.');
        }
        $url = parse_url($endpoint);
        try {
            (new ClientBuilder())->build([
                'host' => $url['host'],
                'port' => $url['port'],
                'scheme' => 'https',
                'enable_http_auth' => true,
                'auth_user' => 'admin',
                'auth_pwd' => 'Local-OpenSearch-2-Test!',
            ])->info();
            self::fail('Production client must reject the untrusted demo certificate.');
        } catch (\GuzzleHttp\Exception\RequestException $exception) {
            self::assertStringContainsString('SSL', $exception->getMessage());
        }
        // Trust bypass is restricted to this disposable demo-fixture test, never the module builder.
        $raw = (new GuzzleClientFactory())->create([
            'base_uri' => $endpoint,
            'auth' => ['admin', 'Local-OpenSearch-2-Test!'],
            'verify' => false,
        ]);
        self::assertSame('2.15.0', $raw->info()['version']['number']);
        self::assertNotEmpty((new Client($raw))->getClustersHealth());
        $wrongPassword = (new GuzzleClientFactory())->create([
            'base_uri' => $endpoint,
            'auth' => ['admin', 'wrong-password'],
            'verify' => false,
        ]);
        $this->expectException(UnauthorizedHttpException::class);
        (new Client($wrongPassword))->getIndicesNameByAlias('missing');
    }
}
