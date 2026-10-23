<?php

namespace Divante\VsbridgeIndexerCore\Index\Indices;

use Divante\VsbridgeIndexerCore\Index\MappingFactory;

/**
 * Build one OpenSearch index per logical entity.
 */
class ConfigParser implements ConfigParserInterface
{
    private $mappingFactory;

    public function __construct(MappingFactory $mappingFactory)
    {
        $this->mappingFactory = $mappingFactory;
    }

    /**
     * @param array $indexConfigData
     *
     * @return array
     */
    public function parse(array $indexConfigData): array
    {
        $indices = [];

        foreach ($indexConfigData as $entityName => $entityConfig) {
            $indices[$entityName] = ['mapping' => $this->mappingFactory->get($entityConfig['mapping'])];
        }

        return $indices;
    }
}
