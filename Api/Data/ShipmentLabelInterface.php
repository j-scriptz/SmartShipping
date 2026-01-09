<?php
/**
 * Jscriptz SmartShipping - Shipment Label Data Interface
 *
 * Service contract for shipping label data returned by label providers.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Api\Data;

interface ShipmentLabelInterface
{
    public const TRACKING_NUMBER = 'tracking_number';
    public const LABEL_IMAGE = 'label_image';
    public const LABEL_FORMAT = 'label_format';
    public const CARRIER_CODE = 'carrier_code';
    public const SERVICE_TYPE = 'service_type';
    public const POSTAGE = 'postage';
    public const ZONE = 'zone';
    public const WEIGHT = 'weight';

    /**
     * Get tracking number
     */
    public function getTrackingNumber(): string;

    /**
     * Set tracking number
     */
    public function setTrackingNumber(string $trackingNumber): self;

    /**
     * Get label image (base64 encoded)
     */
    public function getLabelImage(): string;

    /**
     * Set label image
     */
    public function setLabelImage(string $labelImage): self;

    /**
     * Get label format (PDF, PNG, ZPL, etc.)
     */
    public function getLabelFormat(): string;

    /**
     * Set label format
     */
    public function setLabelFormat(string $labelFormat): self;

    /**
     * Get carrier code
     */
    public function getCarrierCode(): string;

    /**
     * Set carrier code
     */
    public function setCarrierCode(string $carrierCode): self;

    /**
     * Get service type (e.g., "Priority Mail", "Ground Advantage")
     */
    public function getServiceType(): string;

    /**
     * Set service type
     */
    public function setServiceType(string $serviceType): self;

    /**
     * Get postage amount
     */
    public function getPostage(): float;

    /**
     * Set postage amount
     */
    public function setPostage(float $postage): self;

    /**
     * Get shipping zone
     */
    public function getZone(): ?string;

    /**
     * Set shipping zone
     */
    public function setZone(?string $zone): self;

    /**
     * Get package weight
     */
    public function getWeight(): float;

    /**
     * Set package weight
     */
    public function setWeight(float $weight): self;
}
