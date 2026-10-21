<?php
/**
 * @package   Divante\VsbridgeIndexerCore
 * @author    Agata Firlejczyk <afirlejczyk@divante.pl>
 * @copyright 2019 Divante Sp. z o.o.
 * @license   See LICENSE_DIVANTE.txt for license details.
 */

namespace Divante\VsbridgeIndexerCore\Api;

/**
 * Interface IndexInterface
 */
interface IndexInterface
{
    /**
     * Retrieve Index Name
     *
     * @return string
     */
    public function getName();

    /**
     * Retrieve Index Alias
     *
     * @return string
     */
    public function getAlias();

    /**
     * @return boolean
     */
    public function isNew();

    /**
     * @return MappingInterface|null
     */
    public function getMapping(): ?MappingInterface;
}
