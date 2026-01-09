<?php
/**
 * Jscriptz SmartShipping - Tracking Event Interface
 *
 * Data interface for shipping tracking events received from carrier webhooks.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Api\Data;

interface TrackingEventInterface
{
    public const ENTITY_ID = 'entity_id';
    public const TRACK_ID = 'track_id';
    public const TRACKING_NUMBER = 'tracking_number';
    public const CARRIER_CODE = 'carrier_code';
    public const EVENT_CODE = 'event_code';
    public const EVENT_TYPE = 'event_type';
    public const EVENT_DESCRIPTION = 'event_description';
    public const EVENT_TIMESTAMP = 'event_timestamp';
    public const LOCATION_CITY = 'location_city';
    public const LOCATION_STATE = 'location_state';
    public const LOCATION_COUNTRY = 'location_country';
    public const LOCATION_POSTAL_CODE = 'location_postal_code';
    public const RAW_PAYLOAD = 'raw_payload';
    public const SIGNATURE_PROOF = 'signature_proof';
    public const IMAGE_URL = 'image_url';
    public const EMAIL_SENT = 'email_sent';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    // Normalized event types
    public const TYPE_LABEL_CREATED = 'label_created';
    public const TYPE_PICKED_UP = 'picked_up';
    public const TYPE_IN_TRANSIT = 'in_transit';
    public const TYPE_OUT_FOR_DELIVERY = 'out_for_delivery';
    public const TYPE_DELIVERED = 'delivered';
    public const TYPE_EXCEPTION = 'exception';
    public const TYPE_CANCELLED = 'cancelled';
    public const TYPE_UNKNOWN = 'unknown';

    /**
     * Get entity ID
     */
    public function getEntityId(): ?int;

    /**
     * Set entity ID
     */
    public function setEntityId(int $entityId): self;

    /**
     * Get track ID (FK to sales_shipment_track)
     */
    public function getTrackId(): ?int;

    /**
     * Set track ID
     */
    public function setTrackId(?int $trackId): self;

    /**
     * Get tracking number
     */
    public function getTrackingNumber(): string;

    /**
     * Set tracking number
     */
    public function setTrackingNumber(string $trackingNumber): self;

    /**
     * Get carrier code (fedexv3, upsv3, uspsv3)
     */
    public function getCarrierCode(): string;

    /**
     * Set carrier code
     */
    public function setCarrierCode(string $carrierCode): self;

    /**
     * Get carrier-specific event code
     */
    public function getEventCode(): string;

    /**
     * Set event code
     */
    public function setEventCode(string $eventCode): self;

    /**
     * Get normalized event type
     */
    public function getEventType(): string;

    /**
     * Set event type
     */
    public function setEventType(string $eventType): self;

    /**
     * Get event description
     */
    public function getEventDescription(): ?string;

    /**
     * Set event description
     */
    public function setEventDescription(?string $description): self;

    /**
     * Get event timestamp
     */
    public function getEventTimestamp(): string;

    /**
     * Set event timestamp
     */
    public function setEventTimestamp(string $timestamp): self;

    /**
     * Get location city
     */
    public function getLocationCity(): ?string;

    /**
     * Set location city
     */
    public function setLocationCity(?string $city): self;

    /**
     * Get location state
     */
    public function getLocationState(): ?string;

    /**
     * Set location state
     */
    public function setLocationState(?string $state): self;

    /**
     * Get location country
     */
    public function getLocationCountry(): ?string;

    /**
     * Set location country
     */
    public function setLocationCountry(?string $country): self;

    /**
     * Get location postal code
     */
    public function getLocationPostalCode(): ?string;

    /**
     * Set location postal code
     */
    public function setLocationPostalCode(?string $postalCode): self;

    /**
     * Get raw payload JSON
     */
    public function getRawPayload(): ?string;

    /**
     * Set raw payload
     */
    public function setRawPayload(?string $payload): self;

    /**
     * Get signature proof
     */
    public function getSignatureProof(): ?string;

    /**
     * Set signature proof
     */
    public function setSignatureProof(?string $proof): self;

    /**
     * Get image URL (proof of delivery)
     */
    public function getImageUrl(): ?string;

    /**
     * Set image URL
     */
    public function setImageUrl(?string $url): self;

    /**
     * Check if email was sent
     */
    public function isEmailSent(): bool;

    /**
     * Set email sent flag
     */
    public function setEmailSent(bool $sent): self;

    /**
     * Get created at timestamp
     */
    public function getCreatedAt(): ?string;

    /**
     * Get updated at timestamp
     */
    public function getUpdatedAt(): ?string;

    /**
     * Get formatted location string (e.g., "Memphis, TN, US")
     */
    public function getFormattedLocation(): string;
}
