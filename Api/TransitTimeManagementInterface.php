<?php
/**
 * Jscriptz SmartShipping - Transit Time Management Interface
 *
 * REST API interface for retrieving transit times by cart.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Api;

interface TransitTimeManagementInterface
{
    /**
     * Get transit times for a cart
     *
     * @param string $cartId Masked cart ID for guests, or cart ID for customers
     * @return \Jscriptz\SmartShipping\Api\Data\TransitTimeInterface[]
     */
    public function getByCartId(string $cartId): array;

    /**
     * Get all transit times from session
     *
     * @return \Jscriptz\SmartShipping\Api\Data\TransitTimeInterface[]
     */
    public function getAll(): array;
}
