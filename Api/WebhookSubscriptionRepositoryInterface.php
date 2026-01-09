<?php
/**
 * Jscriptz SmartShipping - Webhook Subscription Repository Interface
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Api;

use Jscriptz\SmartShipping\Api\Data\WebhookSubscriptionInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface WebhookSubscriptionRepositoryInterface
{
    /**
     * Save webhook subscription
     *
     * @throws CouldNotSaveException
     */
    public function save(WebhookSubscriptionInterface $subscription): WebhookSubscriptionInterface;

    /**
     * Get subscription by ID
     *
     * @throws NoSuchEntityException
     */
    public function getById(int $entityId): WebhookSubscriptionInterface;

    /**
     * Get subscription by webhook ID (carrier-assigned)
     *
     * @throws NoSuchEntityException
     */
    public function getByWebhookId(string $webhookId): WebhookSubscriptionInterface;

    /**
     * Get account-level subscription for a carrier
     */
    public function getAccountSubscription(string $carrierCode, string $accountNumber): ?WebhookSubscriptionInterface;

    /**
     * Get tracking-level subscription
     */
    public function getTrackingSubscription(string $carrierCode, string $trackingNumber): ?WebhookSubscriptionInterface;

    /**
     * Get active subscriptions for a carrier
     *
     * @return WebhookSubscriptionInterface[]
     */
    public function getActiveByCarrier(string $carrierCode): array;

    /**
     * Delete subscription
     *
     * @throws CouldNotDeleteException
     */
    public function delete(WebhookSubscriptionInterface $subscription): bool;

    /**
     * Delete subscription by ID
     *
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $entityId): bool;

    /**
     * Get list of subscriptions matching criteria
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    /**
     * Deactivate subscription
     */
    public function deactivate(int $entityId, ?string $errorMessage = null): void;

    /**
     * Get expired subscriptions that need renewal
     *
     * @return WebhookSubscriptionInterface[]
     */
    public function getExpiringSubscriptions(int $daysUntilExpiry = 7): array;
}
