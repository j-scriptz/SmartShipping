<?php
/**
 * Jscriptz SmartShipping - Tracking Event Repository Interface
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Api;

use Jscriptz\SmartShipping\Api\Data\TrackingEventInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface TrackingEventRepositoryInterface
{
    /**
     * Save tracking event
     *
     * @throws CouldNotSaveException
     */
    public function save(TrackingEventInterface $event): TrackingEventInterface;

    /**
     * Get tracking event by ID
     *
     * @throws NoSuchEntityException
     */
    public function getById(int $entityId): TrackingEventInterface;

    /**
     * Get tracking events by tracking number
     *
     * @return TrackingEventInterface[]
     */
    public function getByTrackingNumber(string $trackingNumber): array;

    /**
     * Get tracking events by track ID (FK to sales_shipment_track)
     *
     * @return TrackingEventInterface[]
     */
    public function getByTrackId(int $trackId): array;

    /**
     * Get latest event for a tracking number
     */
    public function getLatestEvent(string $trackingNumber): ?TrackingEventInterface;

    /**
     * Delete tracking event
     *
     * @throws CouldNotDeleteException
     */
    public function delete(TrackingEventInterface $event): bool;

    /**
     * Delete tracking event by ID
     *
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $entityId): bool;

    /**
     * Get list of tracking events matching criteria
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    /**
     * Check if an event already exists (to prevent duplicates)
     */
    public function eventExists(string $trackingNumber, string $eventCode, string $eventTimestamp): bool;

    /**
     * Get events pending email notification
     *
     * @return TrackingEventInterface[]
     */
    public function getPendingEmailEvents(int $limit = 100): array;

    /**
     * Mark event as email sent
     */
    public function markEmailSent(int $entityId): void;
}
