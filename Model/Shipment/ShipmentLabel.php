<?php
/**
 * Jscriptz SmartShipping - Shipment Label Model
 *
 * Data model for shipping label information.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Shipment;

use Jscriptz\SmartShipping\Api\Data\ShipmentLabelInterface;
use Magento\Framework\DataObject;

class ShipmentLabel extends DataObject implements ShipmentLabelInterface
{
    /**
     * @inheritDoc
     */
    public function getTrackingNumber(): string
    {
        return (string) $this->getData(self::TRACKING_NUMBER);
    }

    /**
     * @inheritDoc
     */
    public function setTrackingNumber(string $trackingNumber): ShipmentLabelInterface
    {
        return $this->setData(self::TRACKING_NUMBER, $trackingNumber);
    }

    /**
     * @inheritDoc
     */
    public function getLabelImage(): string
    {
        return (string) $this->getData(self::LABEL_IMAGE);
    }

    /**
     * @inheritDoc
     */
    public function setLabelImage(string $labelImage): ShipmentLabelInterface
    {
        return $this->setData(self::LABEL_IMAGE, $labelImage);
    }

    /**
     * @inheritDoc
     */
    public function getLabelFormat(): string
    {
        return (string) $this->getData(self::LABEL_FORMAT);
    }

    /**
     * @inheritDoc
     */
    public function setLabelFormat(string $labelFormat): ShipmentLabelInterface
    {
        return $this->setData(self::LABEL_FORMAT, $labelFormat);
    }

    /**
     * @inheritDoc
     */
    public function getCarrierCode(): string
    {
        return (string) $this->getData(self::CARRIER_CODE);
    }

    /**
     * @inheritDoc
     */
    public function setCarrierCode(string $carrierCode): ShipmentLabelInterface
    {
        return $this->setData(self::CARRIER_CODE, $carrierCode);
    }

    /**
     * @inheritDoc
     */
    public function getServiceType(): string
    {
        return (string) $this->getData(self::SERVICE_TYPE);
    }

    /**
     * @inheritDoc
     */
    public function setServiceType(string $serviceType): ShipmentLabelInterface
    {
        return $this->setData(self::SERVICE_TYPE, $serviceType);
    }

    /**
     * @inheritDoc
     */
    public function getPostage(): float
    {
        return (float) $this->getData(self::POSTAGE);
    }

    /**
     * @inheritDoc
     */
    public function setPostage(float $postage): ShipmentLabelInterface
    {
        return $this->setData(self::POSTAGE, $postage);
    }

    /**
     * @inheritDoc
     */
    public function getZone(): ?string
    {
        return $this->getData(self::ZONE);
    }

    /**
     * @inheritDoc
     */
    public function setZone(?string $zone): ShipmentLabelInterface
    {
        return $this->setData(self::ZONE, $zone);
    }

    /**
     * @inheritDoc
     */
    public function getWeight(): float
    {
        return (float) $this->getData(self::WEIGHT);
    }

    /**
     * @inheritDoc
     */
    public function setWeight(float $weight): ShipmentLabelInterface
    {
        return $this->setData(self::WEIGHT, $weight);
    }
}
