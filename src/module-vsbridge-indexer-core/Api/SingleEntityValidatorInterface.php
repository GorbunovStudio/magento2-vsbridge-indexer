<?php

declare(strict_types=1);

namespace Divante\VsbridgeIndexerCore\Api;

use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Checks the entity requested by the vsbridge:index command before it is indexed.
 *
 * Implementations are registered per indexer code on SingleEntityIndexCommand's "entityValidators" argument.
 *
 * @api
 */
interface SingleEntityValidatorInterface
{
    /**
     * @param string $entityId
     *
     * @return void
     * @throws NoSuchEntityException
     */
    public function validate(string $entityId): void;
}
