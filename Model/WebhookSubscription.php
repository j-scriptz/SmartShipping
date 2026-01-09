<?php
/**
 * Jscriptz SmartShipping - Webhook Subscription Model
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model;

use Jscriptz\SmartShipping\Api\Data\WebhookSubscriptionInterface;
use Jscriptz\SmartShipping\Model\ResourceModel\WebhookSubscription as ResourceModel;
use Magento\Framework\Model\AbstractModel;

class WebhookSubscription extends AbstractModel implements WebhookSubscriptionInterface
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
    public function getSubscriptionType(): string
    {
        return (string) $this->getData(self::SUBSCRIPTION_TYPE);
    }

    /**
     * @inheritDoc
     */
    public function setSubscriptionType(string $type): self
    {
        return $this->setData(self::SUBSCRIPTION_TYPE, $type);
    }

    /**
     * @inheritDoc
     */
    public function getWebhookId(): ?string
    {
        return $this->getData(self::WEBHOOK_ID);
    }

    /**
     * @inheritDoc
     */
    public function setWebhookId(?string $webhookId): self
    {
        return $this->setData(self::WEBHOOK_ID, $webhookId);
    }

    /**
     * @inheritDoc
     */
    public function getAccountNumber(): ?string
    {
        return $this->getData(self::ACCOUNT_NUMBER);
    }

    /**
     * @inheritDoc
     */
    public function setAccountNumber(?string $accountNumber): self
    {
        return $this->setData(self::ACCOUNT_NUMBER, $accountNumber);
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
    public function setTrackingNumber(?string $trackingNumber): self
    {
        return $this->setData(self::TRACKING_NUMBER, $trackingNumber);
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
    public function getCallbackUrl(): string
    {
        return (string) $this->getData(self::CALLBACK_URL);
    }

    /**
     * @inheritDoc
     */
    public function setCallbackUrl(string $url): self
    {
        return $this->setData(self::CALLBACK_URL, $url);
    }

    /**
     * @inheritDoc
     */
    public function getSecurityToken(): string
    {
        return (string) $this->getData(self::SECURITY_TOKEN);
    }

    /**
     * @inheritDoc
     */
    public function setSecurityToken(string $token): self
    {
        return $this->setData(self::SECURITY_TOKEN, $token);
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): string
    {
        return $this->getData(self::STATUS) ?: self::STATUS_ACTIVE;
    }

    /**
     * @inheritDoc
     */
    public function setStatus(string $status): self
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritDoc
     */
    public function getEventsFilter(): ?string
    {
        return $this->getData(self::EVENTS_FILTER);
    }

    /**
     * @inheritDoc
     */
    public function setEventsFilter(?string $filter): self
    {
        return $this->setData(self::EVENTS_FILTER, $filter);
    }

    /**
     * @inheritDoc
     */
    public function getEventsFilterArray(): array
    {
        $filter = $this->getEventsFilter();
        if (!$filter) {
            return [];
        }

        $decoded = json_decode($filter, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @inheritDoc
     */
    public function setEventsFilterArray(array $events): self
    {
        return $this->setEventsFilter(json_encode($events));
    }

    /**
     * @inheritDoc
     */
    public function getExpiresAt(): ?string
    {
        return $this->getData(self::EXPIRES_AT);
    }

    /**
     * @inheritDoc
     */
    public function setExpiresAt(?string $expiresAt): self
    {
        return $this->setData(self::EXPIRES_AT, $expiresAt);
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
    public function setErrorMessage(?string $message): self
    {
        return $this->setData(self::ERROR_MESSAGE, $message);
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
    public function isActive(): bool
    {
        return $this->getStatus() === self::STATUS_ACTIVE && !$this->isExpired();
    }

    /**
     * @inheritDoc
     */
    public function isExpired(): bool
    {
        $expiresAt = $this->getExpiresAt();
        if (!$expiresAt) {
            return false;
        }

        return strtotime($expiresAt) < time();
    }
}
