<?php
/**
 * Jscriptz SmartShipping - Shipment Result Data Interface
 *
 * Service contract for shipment operation results.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Api\Data;

interface ShipmentResultInterface
{
    public const SUCCESS = 'success';
    public const SHIPMENT_ID = 'shipment_id';
    public const TRACKING_NUMBER = 'tracking_number';
    public const LABEL = 'label';
    public const ERROR_MESSAGE = 'error_message';

    /**
     * Check if operation was successful
     */
    public function isSuccess(): bool;

    /**
     * Set success flag
     */
    public function setSuccess(bool $success): self;

    /**
     * Get Magento shipment entity ID
     */
    public function getShipmentId(): ?int;

    /**
     * Set Magento shipment entity ID
     */
    public function setShipmentId(?int $shipmentId): self;

    /**
     * Get tracking number
     */
    public function getTrackingNumber(): ?string;

    /**
     * Set tracking number
     */
    public function setTrackingNumber(?string $trackingNumber): self;

    /**
     * Get label data (if label was generated)
     */
    public function getLabel(): ?ShipmentLabelInterface;

    /**
     * Set label data
     */
    public function setLabel(?ShipmentLabelInterface $label): self;

    /**
     * Get error message (if operation failed)
     */
    public function getErrorMessage(): ?string;

    /**
     * Set error message
     */
    public function setErrorMessage(?string $errorMessage): self;
}
