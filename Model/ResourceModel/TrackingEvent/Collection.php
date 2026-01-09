<?php
/**
 * Jscriptz SmartShipping - Tracking Event Collection
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\ResourceModel\TrackingEvent;

use Jscriptz\SmartShipping\Api\Data\TrackingEventInterface;
use Jscriptz\SmartShipping\Model\TrackingEvent as Model;
use Jscriptz\SmartShipping\Model\ResourceModel\TrackingEvent as ResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = TrackingEventInterface::ENTITY_ID;

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }

    /**
     * Filter by tracking number
     */
    public function addTrackingNumberFilter(string $trackingNumber): self
    {
        return $this->addFieldToFilter(TrackingEventInterface::TRACKING_NUMBER, $trackingNumber);
    }

    /**
     * Filter by track ID
     */
    public function addTrackIdFilter(int $trackId): self
    {
        return $this->addFieldToFilter(TrackingEventInterface::TRACK_ID, $trackId);
    }

    /**
     * Filter by carrier code
     */
    public function addCarrierFilter(string $carrierCode): self
    {
        return $this->addFieldToFilter(TrackingEventInterface::CARRIER_CODE, $carrierCode);
    }

    /**
     * Filter by event type
     */
    public function addEventTypeFilter(string $eventType): self
    {
        return $this->addFieldToFilter(TrackingEventInterface::EVENT_TYPE, $eventType);
    }

    /**
     * Filter events that need email notification
     */
    public function addPendingEmailFilter(): self
    {
        return $this->addFieldToFilter(TrackingEventInterface::EMAIL_SENT, 0);
    }

    /**
     * Order by event timestamp (newest first)
     */
    public function orderByEventTimestamp(string $direction = 'DESC'): self
    {
        return $this->setOrder(TrackingEventInterface::EVENT_TIMESTAMP, $direction);
    }
}
