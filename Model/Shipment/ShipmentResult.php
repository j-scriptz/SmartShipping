<?php
/**
 * Jscriptz SmartShipping - Shipment Result Model
 *
 * Data model for shipment operation results.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Shipment;

use Jscriptz\SmartShipping\Api\Data\ShipmentLabelInterface;
use Jscriptz\SmartShipping\Api\Data\ShipmentResultInterface;
use Magento\Framework\DataObject;

class ShipmentResult extends DataObject implements ShipmentResultInterface
{
    /**
     * @inheritDoc
     */
    public function isSuccess(): bool
    {
        return (bool) $this->getData(self::SUCCESS);
    }

    /**
     * @inheritDoc
     */
    public function setSuccess(bool $success): ShipmentResultInterface
    {
        return $this->setData(self::SUCCESS, $success);
    }

    /**
     * @inheritDoc
     */
    public function getShipmentId(): ?int
    {
        $id = $this->getData(self::SHIPMENT_ID);
        return $id !== null ? (int) $id : null;
    }

    /**
     * @inheritDoc
     */
    public function setShipmentId(?int $shipmentId): ShipmentResultInterface
    {
        return $this->setData(self::SHIPMENT_ID, $shipmentId);
    }

    /**
     * @inheritDoc
     */
    public function getTrackingNumber(): ?string
    {
        return $this->getData(self::TRACKING_NUMBER);
    }

    /**
     * @inheritDoc
     */
    public function setTrackingNumber(?string $trackingNumber): ShipmentResultInterface
    {
        return $this->setData(self::TRACKING_NUMBER, $trackingNumber);
    }

    /**
     * @inheritDoc
     */
    public function getLabel(): ?ShipmentLabelInterface
    {
        return $this->getData(self::LABEL);
    }

    /**
     * @inheritDoc
     */
    public function setLabel(?ShipmentLabelInterface $label): ShipmentResultInterface
    {
        return $this->setData(self::LABEL, $label);
    }

    /**
     * @inheritDoc
     */
    public function getErrorMessage(): ?string
    {
        return $this->getData(self::ERROR_MESSAGE);
    }

    /**
     * @inheritDoc
     */
    public function setErrorMessage(?string $errorMessage): ShipmentResultInterface
    {
        return $this->setData(self::ERROR_MESSAGE, $errorMessage);
    }

    /**
     * Create success result
     */
    public static function success(
        int $shipmentId,
        string $trackingNumber,
        ?ShipmentLabelInterface $label = null
    ): self {
        $result = new self();
        $result->setSuccess(true)
            ->setShipmentId($shipmentId)
            ->setTrackingNumber($trackingNumber)
            ->setLabel($label);
        return $result;
    }

    /**
     * Create failure result
     */
    public static function failure(string $errorMessage): self
    {
        $result = new self();
        $result->setSuccess(false)
            ->setErrorMessage($errorMessage);
        return $result;
    }
}
