<?php
/**
 * Jscriptz SmartShipping - Webhook Subscription Repository
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model;

use Jscriptz\SmartShipping\Api\Data\WebhookSubscriptionInterface;
use Jscriptz\SmartShipping\Api\WebhookSubscriptionRepositoryInterface;
use Jscriptz\SmartShipping\Model\ResourceModel\WebhookSubscription as ResourceModel;
use Jscriptz\SmartShipping\Model\ResourceModel\WebhookSubscription\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class WebhookSubscriptionRepository implements WebhookSubscriptionRepositoryInterface
{
    public function __construct(
        private readonly ResourceModel $resourceModel,
        private readonly WebhookSubscriptionFactory $subscriptionFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(WebhookSubscriptionInterface $subscription): WebhookSubscriptionInterface
    {
        try {
            $this->resourceModel->save($subscription);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save webhook subscription: %1', $e->getMessage()),
                $e
            );
        }
        return $subscription;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $entityId): WebhookSubscriptionInterface
    {
        $subscription = $this->subscriptionFactory->create();
        $this->resourceModel->load($subscription, $entityId);

        if (!$subscription->getEntityId()) {
            throw new NoSuchEntityException(
                __('Webhook subscription with ID "%1" does not exist.', $entityId)
            );
        }

        return $subscription;
    }

    /**
     * @inheritDoc
     */
    public function getByWebhookId(string $webhookId): WebhookSubscriptionInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(WebhookSubscriptionInterface::WEBHOOK_ID, $webhookId);
        $collection->setPageSize(1);

        $subscription = $collection->getFirstItem();

        if (!$subscription->getEntityId()) {
            throw new NoSuchEntityException(
                __('Webhook subscription with webhook ID "%1" does not exist.', $webhookId)
            );
        }

        return $subscription;
    }

    /**
     * @inheritDoc
     */
    public function getAccountSubscription(string $carrierCode, string $accountNumber): ?WebhookSubscriptionInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addCarrierFilter($carrierCode);
        $collection->addTypeFilter(WebhookSubscriptionInterface::TYPE_ACCOUNT);
        $collection->addAccountNumberFilter($accountNumber);
        $collection->addActiveFilter();
        $collection->setPageSize(1);

        $subscription = $collection->getFirstItem();
        return $subscription->getEntityId() ? $subscription : null;
    }

    /**
     * @inheritDoc
     */
    public function getTrackingSubscription(string $carrierCode, string $trackingNumber): ?WebhookSubscriptionInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addCarrierFilter($carrierCode);
        $collection->addTypeFilter(WebhookSubscriptionInterface::TYPE_TRACKING);
        $collection->addTrackingNumberFilter($trackingNumber);
        $collection->addActiveFilter();
        $collection->setPageSize(1);

        $subscription = $collection->getFirstItem();
        return $subscription->getEntityId() ? $subscription : null;
    }

    /**
     * @inheritDoc
     */
    public function getActiveByCarrier(string $carrierCode): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addCarrierFilter($carrierCode);
        $collection->addActiveFilter();

        return $collection->getItems();
    }

    /**
     * @inheritDoc
     */
    public function delete(WebhookSubscriptionInterface $subscription): bool
    {
        try {
            $this->resourceModel->delete($subscription);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete webhook subscription: %1', $e->getMessage()),
                $e
            );
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $entityId): bool
    {
        return $this->delete($this->getById($entityId));
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function deactivate(int $entityId, ?string $errorMessage = null): void
    {
        $subscription = $this->getById($entityId);
        $subscription->setStatus(WebhookSubscriptionInterface::STATUS_INACTIVE);

        if ($errorMessage) {
            $subscription->setErrorMessage($errorMessage);
        }

        $this->save($subscription);
    }

    /**
     * @inheritDoc
     */
    public function getExpiringSubscriptions(int $daysUntilExpiry = 7): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addActiveFilter();

        $expiryDate = date('Y-m-d H:i:s', strtotime("+{$daysUntilExpiry} days"));
        $collection->addFieldToFilter(
            WebhookSubscriptionInterface::EXPIRES_AT,
            ['lteq' => $expiryDate]
        );

        return $collection->getItems();
    }
}
