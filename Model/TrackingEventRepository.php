<?php
/**
 * Jscriptz SmartShipping - Tracking Event Repository
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model;

use Jscriptz\SmartShipping\Api\Data\TrackingEventInterface;
use Jscriptz\SmartShipping\Api\TrackingEventRepositoryInterface;
use Jscriptz\SmartShipping\Model\ResourceModel\TrackingEvent as ResourceModel;
use Jscriptz\SmartShipping\Model\ResourceModel\TrackingEvent\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class TrackingEventRepository implements TrackingEventRepositoryInterface
{
    public function __construct(
        private readonly ResourceModel $resourceModel,
        private readonly TrackingEventFactory $trackingEventFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(TrackingEventInterface $event): TrackingEventInterface
    {
        try {
            $this->resourceModel->save($event);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save tracking event: %1', $e->getMessage()),
                $e
            );
        }
        return $event;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $entityId): TrackingEventInterface
    {
        $event = $this->trackingEventFactory->create();
        $this->resourceModel->load($event, $entityId);

        if (!$event->getEntityId()) {
            throw new NoSuchEntityException(
                __('Tracking event with ID "%1" does not exist.', $entityId)
            );
        }

        return $event;
    }

    /**
     * @inheritDoc
     */
    public function getByTrackingNumber(string $trackingNumber): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addTrackingNumberFilter($trackingNumber);
        $collection->orderByEventTimestamp('DESC');

        return $collection->getItems();
    }

    /**
     * @inheritDoc
     */
    public function getByTrackId(int $trackId): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addTrackIdFilter($trackId);
        $collection->orderByEventTimestamp('DESC');

        return $collection->getItems();
    }

    /**
     * @inheritDoc
     */
    public function getLatestEvent(string $trackingNumber): ?TrackingEventInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addTrackingNumberFilter($trackingNumber);
        $collection->orderByEventTimestamp('DESC');
        $collection->setPageSize(1);

        $event = $collection->getFirstItem();
        return $event->getEntityId() ? $event : null;
    }

    /**
     * @inheritDoc
     */
    public function delete(TrackingEventInterface $event): bool
    {
        try {
            $this->resourceModel->delete($event);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete tracking event: %1', $e->getMessage()),
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
    public function eventExists(string $trackingNumber, string $eventCode, string $eventTimestamp): bool
    {
        $collection = $this->collectionFactory->create();
        $collection->addTrackingNumberFilter($trackingNumber);
        $collection->addFieldToFilter(TrackingEventInterface::EVENT_CODE, $eventCode);
        $collection->addFieldToFilter(TrackingEventInterface::EVENT_TIMESTAMP, $eventTimestamp);
        $collection->setPageSize(1);

        return $collection->getSize() > 0;
    }

    /**
     * @inheritDoc
     */
    public function getPendingEmailEvents(int $limit = 100): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addPendingEmailFilter();
        $collection->orderByEventTimestamp('ASC'); // Process oldest first
        $collection->setPageSize($limit);

        return $collection->getItems();
    }

    /**
     * @inheritDoc
     */
    public function markEmailSent(int $entityId): void
    {
        $event = $this->getById($entityId);
        $event->setEmailSent(true);
        $this->save($event);
    }
}
