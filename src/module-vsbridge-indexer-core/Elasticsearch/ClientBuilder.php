<?php
/**
 * @package   Divante\VsbridgeIndexerCore
 * @author    Agata Firlejczyk <afirlejczyk@divante.pl>
 * @copyright 2019 Divante Sp. z o.o.
 * @license   See LICENSE_DIVANTE.txt for license details.
 */

namespace Divante\VsbridgeIndexerCore\Elasticsearch;

use Divante\VsbridgeIndexerCore\Api\Client\BuilderInterface;
use OpenSearch\GuzzleClientFactory;

class ClientBuilder implements BuilderInterface
{
    public function build(array $options = [])
    {
        $options = array_merge([
            'host' => 'localhost',
            'port' => '9200',
            'scheme' => 'http',
            'enable_http_auth' => false,
            'auth_user' => null,
            'auth_pwd' => null,
            'timeout' => 30,
            'connect_timeout' => 30,
        ], $options);

        $scheme = array_key_exists('enable_https_mode', $options)
            ? ($options['enable_https_mode'] ? 'https' : 'http')
            : $options['scheme'];

        $httpOptions = [
            'base_uri' => $scheme . '://' . $options['host'] . ':' . $options['port'],
            'timeout' => $options['timeout'],
            'connect_timeout' => $options['connect_timeout'],
            'verify' => true,
        ];

        if ($options['enable_http_auth']) {
            $httpOptions['auth'] = [$options['auth_user'], $options['auth_pwd']];
        }

        // Avoid replaying an entire bulk request after a partial or ambiguous failure.
        return (new GuzzleClientFactory(0))->create($httpOptions);
    }
}
