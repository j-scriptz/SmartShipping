<?php
/**
 * Jscriptz SmartShipping - Transit Time Model
 *
 * Data object representing transit time for a shipping method.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model;

use Jscriptz\SmartShipping\Api\Data\TransitTimeInterface;
use Magento\Framework\DataObject;

class TransitTime extends DataObject implements TransitTimeInterface
{
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
    public function setCarrierCode(string $carrierCode): TransitTimeInterface
    {
        return $this->setData(self::CARRIER_CODE, $carrierCode);
    }

    /**
     * @inheritDoc
     */
    public function getMethodCode(): string
    {
        return (string) $this->getData(self::METHOD_CODE);
    }

    /**
     * @inheritDoc
     */
    public function setMethodCode(string $methodCode): TransitTimeInterface
    {
        return $this->setData(self::METHOD_CODE, $methodCode);
    }

    /**
     * @inheritDoc
     */
    public function getMinDays(): int
    {
        return (int) $this->getData(self::MIN_DAYS);
    }

    /**
     * @inheritDoc
     */
    public function setMinDays(int $minDays): TransitTimeInterface
    {
        return $this->setData(self::MIN_DAYS, $minDays);
    }

    /**
     * @inheritDoc
     */
    public function getMaxDays(): int
    {
        return (int) $this->getData(self::MAX_DAYS);
    }

    /**
     * @inheritDoc
     */
    public function setMaxDays(int $maxDays): TransitTimeInterface
    {
        return $this->setData(self::MAX_DAYS, $maxDays);
    }

    /**
     * @inheritDoc
     */
    public function getDeliveryDate(): ?string
    {
        $value = $this->getData(self::DELIVERY_DATE);
        return $value !== null ? (string) $value : null;
    }

    /**
     * @inheritDoc
     */
    public function setDeliveryDate(?string $deliveryDate): TransitTimeInterface
    {
        return $this->setData(self::DELIVERY_DATE, $deliveryDate);
    }

    /**
     * @inheritDoc
     */
    public function getDeliveryDay(): ?string
    {
        $value = $this->getData(self::DELIVERY_DAY);
        return $value !== null ? (string) $value : null;
    }

    /**
     * @inheritDoc
     */
    public function setDeliveryDay(?string $deliveryDay): TransitTimeInterface
    {
        return $this->setData(self::DELIVERY_DAY, $deliveryDay);
    }

    /**
     * @inheritDoc
     */
    public function getDeliveryTime(): ?string
    {
        $value = $this->getData(self::DELIVERY_TIME);
        return $value !== null ? (string) $value : null;
    }

    /**
     * @inheritDoc
     */
    public function setDeliveryTime(?string $deliveryTime): TransitTimeInterface
    {
        return $this->setData(self::DELIVERY_TIME, $deliveryTime);
    }

    /**
     * @inheritDoc
     */
    public function isGuaranteed(): bool
    {
        return (bool) $this->getData(self::GUARANTEED);
    }

    /**
     * @inheritDoc
     */
    public function setGuaranteed(bool $guaranteed): TransitTimeInterface
    {
        return $this->setData(self::GUARANTEED, $guaranteed);
    }

    /**
     * @inheritDoc
     */
    public function getCutoffHour(): ?int
    {
        $value = $this->getData(self::CUTOFF_HOUR);
        return $value !== null ? (int) $value : null;
    }

    /**
     * @inheritDoc
     */
    public function setCutoffHour(?int $cutoffHour): TransitTimeInterface
    {
        return $this->setData(self::CUTOFF_HOUR, $cutoffHour);
    }
}
