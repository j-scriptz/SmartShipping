<?php
/**
 * Jscriptz SmartShipping - Transit Time Repository Interface
 *
 * Service contract for storing and retrieving transit time data from carriers.
 * Carriers save transit times during rate collection, which are then read
 * by SmartShipping for display at checkout.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Api;

use Jscriptz\SmartShipping\Api\Data\TransitTimeInterface;

interface TransitTimeRepositoryInterface
{
    /**
     * Save transit time for a carrier/method
     */
    public function save(TransitTimeInterface $transitTime): void;

    /**
     * Save multiple transit times for a carrier
     *
     * @param string $carrierCode
     * @param array $transitTimesData Array of transit time data arrays
     */
    public function saveForCarrier(string $carrierCode, array $transitTimesData): void;

    /**
     * Get transit time by carrier and method code
     */
    public function getByCarrierMethod(string $carrierCode, string $methodCode): ?TransitTimeInterface;

    /**
     * Get all transit times for a carrier
     *
     * @return TransitTimeInterface[]
     */
    public function getByCarrier(string $carrierCode): array;

    /**
     * Get all stored transit times
     *
     * @return TransitTimeInterface[]
     */
    public function getAll(): array;

    /**
     * Clear all transit times for a carrier
     */
    public function clearCarrier(string $carrierCode): void;

    /**
     * Clear all stored transit times
     */
    public function clearAll(): void;

    /**
     * Create a new transit time instance
     */
    public function create(): TransitTimeInterface;
}
