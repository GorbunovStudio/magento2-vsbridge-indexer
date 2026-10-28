<?php

namespace Divante\VsbridgeIndexerCore\Index;

use Divante\VsbridgeIndexerCore\Api\IndexInterface;
use Divante\VsbridgeIndexerCore\Api\MappingInterface;

/**
 * Class Index
 */
class Index implements IndexInterface
{
    /**
     * Name of the index.
     *
     * @var string
     */
    private $name;

    /**
     * Index mapping.
     *
     * @var MappingInterface|null
     */
    private $mapping;

    /**
     * Index alias
     *
     * @var string
     */
    private $alias;

    /**
     * Index constructor.
     *
     * @param string $name
     * @param string $alias
     * @param MappingInterface|null $mapping
     */
    public function __construct(
        string $name,
        string $alias,
        ?MappingInterface $mapping = null
    ) {
        $this->alias = $alias;
        $this->name = $name;
        $this->mapping = $mapping;
    }

    /**
     * @inheritdoc
     */
    public function isNew()
    {
        return $this->alias !== $this->name;
    }

    /**
     * @inheritdoc
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function getMapping(): ?MappingInterface
    {
        return $this->mapping;
    }
}
