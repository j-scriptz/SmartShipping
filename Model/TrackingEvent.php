<?php
/**
 * Jscriptz SmartShipping - Tracking Event Model
 *
 * Model for shipping tracking events received from carrier webhooks.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model;

use Jscriptz\SmartShipping\Api\Data\TrackingEventInterface;
use Jscriptz\SmartShipping\Model\ResourceModel\TrackingEvent as ResourceModel;
use Magento\Framework\Model\AbstractModel;

class TrackingEvent extends AbstractModel implements TrackingEventInterface
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel::class);
    }

    /**
     * @inheritDoc
     */
    public function getEntityId(): ?int
    {
        $id = $this->getData(self::ENTITY_ID);
        return $id !== null ? (int) $id : null;
    }

    /**
     * @inheritDoc
     */
    public function setEntityId($entityId): self
    {
        return $this->setData(self::ENTITY_ID, $entityId);
    }

    /**
     * @inheritDoc
     */
    public function getTrackId(): ?int
    {
        $id = $this->getData(self::TRACK_ID);
        return $id !== null ? (int) $id : null;
    }

    /**
     * @inheritDoc
     */
    public function setTrackId(?int $trackId): self
    {
        return $this->setData(self::TRACK_ID, $trackId);
    }

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
    public function setTrackingNumber(string $trackingNumber): self
    {
        return $this->setData(self::TRACKING_NUMBER, $trackingNumber);
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
    public function setCarrierCode(string $carrierCode): self
    {
        return $this->setData(self::CARRIER_CODE, $carrierCode);
    }

    /**
     * @inheritDoc
     */
    public function getEventCode(): string
    {
        return (string) $this->getData(self::EVENT_CODE);
    }

    /**
     * @inheritDoc
     */
    public function setEventCode(string $eventCode): self
    {
        return $this->setData(self::EVENT_CODE, $eventCode);
    }

    /**
     * @inheritDoc
     */
    public function getEventType(): string
    {
        return (string) $this->getData(self::EVENT_TYPE);
    }

    /**
     * @inheritDoc
     */
    public function setEventType(string $eventType): self
    {
        return $this->setData(self::EVENT_TYPE, $eventType);
    }

    /**
     * @inheritDoc
     */
    public function getEventDescription(): ?string
    {
        return $this->getData(self::EVENT_DESCRIPTION);
    }

    /**
     * @inheritDoc
     */
    public function setEventDescription(?string $description): self
    {
        return $this->setData(self::EVENT_DESCRIPTION, $description);
    }

    /**
     * @inheritDoc
     */
    public function getEventTimestamp(): string
    {
        return (string) $this->getData(self::EVENT_TIMESTAMP);
    }

    /**
     * @inheritDoc
     */
    public function setEventTimestamp(string $timestamp): self
    {
        return $this->setData(self::EVENT_TIMESTAMP, $timestamp);
    }

    /**
     * @inheritDoc
     */
    public function getLocationCity(): ?string
    {
        return $this->getData(self::LOCATION_CITY);
    }

    /**
     * @inheritDoc
     */
    public function setLocationCity(?string $city): self
    {
        return $this->setData(self::LOCATION_CITY, $city);
    }

    /**
     * @inheritDoc
     */
    public function getLocationState(): ?string
    {
        return $this->getData(self::LOCATION_STATE);
    }

    /**
     * @inheritDoc
     */
    public function setLocationState(?string $state): self
    {
        return $this->setData(self::LOCATION_STATE, $state);
    }

    /**
     * @inheritDoc
     */
    public function getLocationCountry(): ?string
    {
        return $this->getData(self::LOCATION_COUNTRY);
    }

    /**
     * @inheritDoc
     */
    public function setLocationCountry(?string $country): self
    {
        return $this->setData(self::LOCATION_COUNTRY, $country);
    }

    /**
     * @inheritDoc
     */
    public function getLocationPostalCode(): ?string
    {
        return $this->getData(self::LOCATION_POSTAL_CODE);
    }

    /**
     * @inheritDoc
     */
    public function setLocationPostalCode(?string $postalCode): self
    {
        return $this->setData(self::LOCATION_POSTAL_CODE, $postalCode);
    }

    /**
     * @inheritDoc
     */
    public function getRawPayload(): ?string
    {
        return $this->getData(self::RAW_PAYLOAD);
    }

    /**
     * @inheritDoc
     */
    public function setRawPayload(?string $payload): self
    {
        return $this->setData(self::RAW_PAYLOAD, $payload);
    }

    /**
     * @inheritDoc
     */
    public function getSignatureProof(): ?string
    {
        return $this->getData(self::SIGNATURE_PROOF);
    }

    /**
     * @inheritDoc
     */
    public function setSignatureProof(?string $proof): self
    {
        return $this->setData(self::SIGNATURE_PROOF, $proof);
    }

    /**
     * @inheritDoc
     */
    public function getImageUrl(): ?string
    {
        return $this->getData(self::IMAGE_URL);
    }

    /**
     * @inheritDoc
     */
    public function setImageUrl(?string $url): self
    {
        return $this->setData(self::IMAGE_URL, $url);
    }

    /**
     * @inheritDoc
     */
    public function isEmailSent(): bool
    {
        return (bool) $this->getData(self::EMAIL_SENT);
    }

    /**
     * @inheritDoc
     */
    public function setEmailSent(bool $sent): self
    {
        return $this->setData(self::EMAIL_SENT, $sent ? 1 : 0);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function getFormattedLocation(): string
    {
        $parts = array_filter([
            $this->getLocationCity(),
            $this->getLocationState(),
            $this->getLocationCountry()
        ]);

        return implode(', ', $parts);
    }
}
