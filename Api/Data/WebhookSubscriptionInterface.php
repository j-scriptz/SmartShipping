<?php
/**
 * Jscriptz SmartShipping - Webhook Subscription Interface
 *
 * Data interface for managing webhook subscriptions with carriers.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Api\Data;

interface WebhookSubscriptionInterface
{
    public const ENTITY_ID = 'entity_id';
    public const CARRIER_CODE = 'carrier_code';
    public const SUBSCRIPTION_TYPE = 'subscription_type';
    public const WEBHOOK_ID = 'webhook_id';
    public const ACCOUNT_NUMBER = 'account_number';
    public const TRACKING_NUMBER = 'tracking_number';
    public const TRACK_ID = 'track_id';
    public const CALLBACK_URL = 'callback_url';
    public const SECURITY_TOKEN = 'security_token';
    public const STATUS = 'status';
    public const EVENTS_FILTER = 'events_filter';
    public const EXPIRES_AT = 'expires_at';
    public const ERROR_MESSAGE = 'error_message';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    // Subscription types
    public const TYPE_ACCOUNT = 'account';
    public const TYPE_TRACKING = 'tracking';

    // Status values
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ERROR = 'error';

    /**
     * Get entity ID
     */
    public function getEntityId(): ?int;

    /**
     * Set entity ID
     */
    public function setEntityId(int $entityId): self;

    /**
     * Get carrier code (fedexv3, upsv3, uspsv3)
     */
    public function getCarrierCode(): string;

    /**
     * Set carrier code
     */
    public function setCarrierCode(string $carrierCode): self;

    /**
     * Get subscription type (account or tracking)
     */
    public function getSubscriptionType(): string;

    /**
     * Set subscription type
     */
    public function setSubscriptionType(string $type): self;

    /**
     * Get webhook ID (carrier-assigned)
     */
    public function getWebhookId(): ?string;

    /**
     * Set webhook ID
     */
    public function setWebhookId(?string $webhookId): self;

    /**
     * Get account number (for account-level subscriptions)
     */
    public function getAccountNumber(): ?string;

    /**
     * Set account number
     */
    public function setAccountNumber(?string $accountNumber): self;

    /**
     * Get tracking number (for tracking-level subscriptions)
     */
    public function getTrackingNumber(): ?string;

    /**
     * Set tracking number
     */
    public function setTrackingNumber(?string $trackingNumber): self;

    /**
     * Get track ID (FK to sales_shipment_track)
     */
    public function getTrackId(): ?int;

    /**
     * Set track ID
     */
    public function setTrackId(?int $trackId): self;

    /**
     * Get callback URL
     */
    public function getCallbackUrl(): string;

    /**
     * Set callback URL
     */
    public function setCallbackUrl(string $url): self;

    /**
     * Get security token (encrypted)
     */
    public function getSecurityToken(): string;

    /**
     * Set security token
     */
    public function setSecurityToken(string $token): self;

    /**
     * Get subscription status
     */
    public function getStatus(): string;

    /**
     * Set subscription status
     */
    public function setStatus(string $status): self;

    /**
     * Get events filter (JSON array)
     */
    public function getEventsFilter(): ?string;

    /**
     * Set events filter
     */
    public function setEventsFilter(?string $filter): self;

    /**
     * Get events filter as array
     *
     * @return string[]
     */
    public function getEventsFilterArray(): array;

    /**
     * Set events filter from array
     *
     * @param string[] $events
     */
    public function setEventsFilterArray(array $events): self;

    /**
     * Get expiration date
     */
    public function getExpiresAt(): ?string;

    /**
     * Set expiration date
     */
    public function setExpiresAt(?string $expiresAt): self;

    /**
     * Get error message
     */
    public function getErrorMessage(): ?string;

    /**
     * Set error message
     */
    public function setErrorMessage(?string $message): self;

    /**
     * Get created at timestamp
     */
    public function getCreatedAt(): ?string;

    /**
     * Get updated at timestamp
     */
    public function getUpdatedAt(): ?string;

    /**
     * Check if subscription is active
     */
    public function isActive(): bool;

    /**
     * Check if subscription has expired
     */
    public function isExpired(): bool;
}
