<?php
/**
 * Jscriptz SmartShipping - Transit Time Management
 *
 * REST API implementation for retrieving transit times.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model;

use Jscriptz\SmartShipping\Api\TransitTimeManagementInterface;
use Jscriptz\SmartShipping\Api\TransitTimeRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;

class TransitTimeManagement implements TransitTimeManagementInterface
{
    public function __construct(
        private readonly TransitTimeRepositoryInterface $transitTimeRepository,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getByCartId(string $cartId): array
    {
        // For guest carts, the cartId is a masked ID that needs to be resolved
        // For customer carts, it's already the quote ID
        // The transit times are stored per-session, so we just return all of them
        return $this->transitTimeRepository->getAll();
    }

    /**
     * @inheritDoc
     */
    public function getAll(): array
    {
        return $this->transitTimeRepository->getAll();
    }
}
