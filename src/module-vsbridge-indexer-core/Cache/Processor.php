<?php
/**
 * @package  Divante\VsbridgeIndexerCore
 * @author Agata Firlejczyk <afirlejczyk@divante.pl>
 * @copyright 2019 Divante Sp. z o.o.
 * @license See LICENSE_DIVANTE.txt for license details.
 */

namespace Divante\VsbridgeIndexerCore\Cache;

use Magento\Framework\HTTP\Adapter\CurlFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Psr\Log\LoggerInterface;
use Divante\VsbridgeIndexerCore\Service\CacheTagsResolver;

/**
 * Class Processor
 */
class Processor
{
    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CurlFactory
     */
    private $curlFactory;

    /**
     * Processor constructor.
     *
     * @param CurlFactory $curlFactory
     * @param ConfigInterface $config
     * @param EventManager $manager
     * @param LoggerInterface $logger
     */
    public function __construct(
        CurlFactory $curlFactory,
        ConfigInterface $config,
        LoggerInterface $logger,
        private CacheTagsResolver $cacheTagsResolver
    ) {
        $this->curlFactory = $curlFactory;
        $this->logger = $logger;
        $this->config = $config;
    }

    /**
     * @param int $storeId
     * @param string $dataType
     * @param array $entityIds
     *
     * @return $this
     */
    public function cleanCacheByDocIds($storeId, $dataType, array $entityIds)
    {
        if ($this->config->clearCache($storeId)) {
            if (!empty($entityIds)) {
                $this->cleanCacheInBatches($storeId, $dataType, $entityIds);
            } else {
                $cacheTags = $this->cacheTagsResolver->getCacheTags();

                if (isset($cacheTags[$dataType])) {
                    $this->cleanCacheByTags($storeId, [$dataType]);
                }
            }
        }

        return $this;
    }

    /**
     * @param int $storeId
     * @param string $dataType
     * @param array $entityIds
     */
    public function cleanCacheInBatches(int $storeId, string $dataType, array $entityIds)
    {
        $batchSize = $this->getInvalidateEntitiesBatchSize($storeId);
        $batches = [$entityIds];

        if ($batchSize) {
            $batches = array_chunk($entityIds, $batchSize);
        }

        foreach ($batches as $batch) {
            $this->logger->debug('BATCHES ' . implode(', ', $batch));
            $cacheInvalidateUrl = $this->getCacheInvalidateUrl($storeId, $dataType, $entityIds);

            try {
                $this->call($storeId, $cacheInvalidateUrl);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }
    }

    /**
     * @param int $storeId
     * @param array $tags
     */
    public function cleanCacheByTags($storeId, array $tags)
    {
        $storeId = (int) $storeId;

        if ($this->config->clearCache($storeId)) {
            $cacheTags = implode(',', $tags);
            $cacheInvalidateUrl = $this->getInvalidateCacheUrl($storeId) . $cacheTags;

            try {
                $this->call($storeId, $cacheInvalidateUrl);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }
    }

    /**
     * @param int $storeId
     *
     * @return int
     */
    private function getInvalidateEntitiesBatchSize(int $storeId)
    {
        return $this->config->getInvalidateEntitiesBatchSize($storeId);
    }

    /**
     * @param string $storeId
     * @param string $uri
     */
    private function call($storeId, $uri)
    {
        $config = $this->config->getConnectionOptions($storeId);
        /** @var \Magento\Framework\HTTP\Adapter\Curl $curl */
        $curl = $this->curlFactory->create();
        $curl->setConfig($config);
        $curl->write(\Zend_Http_Client::GET, $uri, '1.0');
        $response = $curl->read();

        if ($response !== false && !empty($response)) {
            $httpCode = \Zend_Http_Response::extractCode($response);

            if ($httpCode !== 200) {
                $response = \Zend_Http_Response::extractBody($response);
                $this->logger->error($response);
            }
        } else {
            $this->logger->error('Problem with clearing VSF cache.');
        }
    }

    /**
     * @param int $storeId
     * @param string $type
     * @param array  $ids
     *
     * @return string
     */
    private function getCacheInvalidateUrl($storeId, $type, array $ids)
    {
        $fullUrl = $this->getInvalidateCacheUrl($storeId);
        $params = $this->cacheTagsResolver->getTagsList($type, $ids);
        $fullUrl .= $params;

        return $fullUrl;
    }

    /**
     * @return string
     */
    private function getInvalidateCacheUrl($storeId)
    {
        $url = $this->config->getVsfBaseUrl($storeId);
        $url .= sprintf('invalidate?key=%s&tag=', $this->config->getInvalidateCacheKey($storeId));

        return $url;
    }
}
