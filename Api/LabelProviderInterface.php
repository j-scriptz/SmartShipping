<?php
/**
 * Jscriptz SmartShipping - Label Provider Interface
 *
 * Service contract for carrier-specific label generation.
 * Each carrier (USPS, UPS, FedEx) implements this interface.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Api;

use Jscriptz\SmartShipping\Api\Data\ShipmentLabelInterface;
use Magento\Sales\Api\Data\OrderInterface;

interface LabelProviderInterface
{
    /**
     * Get carrier code this provider handles
     *
     * @return string Carrier code (e.g., 'uspsv3', 'upsv3', 'fedexv3')
     */
    public function getCarrierCode(): string;

    /**
     * Check if label generation is available
     *
     * Verifies that all required configuration and credentials are set.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isAvailable(?int $storeId = null): bool;

    /**
     * Create shipping label via carrier API
     *
     * @param OrderInterface $order
     * @param mixed[] $packageData Optional package dimensions/weight override
     * @return ShipmentLabelInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createLabel(OrderInterface $order, array $packageData = []): ShipmentLabelInterface;

    /**
     * Get tracking information for a tracking number
     *
     * @param string $trackingNumber
     * @param int|null $storeId
     * @return mixed[] Tracking events and status
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getTrackingInfo(string $trackingNumber, ?int $storeId = null): array;

    /**
     * Void/cancel a previously generated label
     *
     * @param string $trackingNumber
     * @param int|null $storeId
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function voidLabel(string $trackingNumber, ?int $storeId = null): bool;

    /**
     * Get supported label formats
     *
     * @return string[] List of supported formats (PDF, PNG, ZPL, etc.)
     */
    public function getSupportedFormats(): array;
}
