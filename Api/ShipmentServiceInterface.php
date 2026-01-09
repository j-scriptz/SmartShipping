<?php
/**
 * Jscriptz SmartShipping - Shipment Service Interface
 *
 * Service contract for creating shipments with labels or manual tracking.
 * Use this interface via DI, never instantiate directly via ObjectManager.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Api;

use Jscriptz\SmartShipping\Api\Data\ShipmentResultInterface;

interface ShipmentServiceInterface
{
    /**
     * Create shipment with API-generated label
     *
     * Uses the appropriate carrier's Label API to generate a shipping label
     * and creates a Magento shipment with tracking information.
     *
     * @param int $orderId Magento order entity ID
     * @param mixed[] $packageData Optional package dimensions/weight override
     * @param bool $notify Whether to send tracking notification email
     * @return ShipmentResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createWithLabel(
        int $orderId,
        array $packageData = [],
        bool $notify = true
    ): ShipmentResultInterface;

    /**
     * Create shipment with manual tracking number
     *
     * For cases where label is generated outside the system or
     * the carrier's Label API is not available.
     *
     * @param int $orderId Magento order entity ID
     * @param string $trackingNumber Carrier tracking number
     * @param string $carrierCode Carrier code (uspsv3, upsv3, fedexv3)
     * @param string|null $title Optional carrier/service title
     * @param bool $notify Whether to send tracking notification email
     * @return ShipmentResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createWithTracking(
        int $orderId,
        string $trackingNumber,
        string $carrierCode,
        ?string $title = null,
        bool $notify = true
    ): ShipmentResultInterface;

    /**
     * Check if order can be shipped
     *
     * @param int $orderId Magento order entity ID
     * @return bool
     */
    public function canShip(int $orderId): bool;

    /**
     * Check if label generation is available for order's carrier
     *
     * @param int $orderId Magento order entity ID
     * @return bool
     */
    public function isLabelAvailable(int $orderId): bool;
}
