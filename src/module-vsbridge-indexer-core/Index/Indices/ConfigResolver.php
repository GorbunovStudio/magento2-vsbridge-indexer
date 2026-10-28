<?php

namespace Divante\VsbridgeIndexerCore\Index\Indices;

/** Resolve the single entity-specific OpenSearch layout, ignoring obsolete engine settings. */
class ConfigResolver
{
    private $configData;
    private $configParser;

    public function __construct(Config\Data $configData, ConfigParser $configParser)
    {
        $this->configData = $configData;
        $this->configParser = $configParser;
    }

    public function resolve(): array
    {
        return $this->configParser->parse($this->configData->get());
    }
}
