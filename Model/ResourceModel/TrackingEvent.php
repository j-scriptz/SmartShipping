<?php
/**
 * Jscriptz SmartShipping - Tracking Event Resource Model
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\ResourceModel;

use Jscriptz\SmartShipping\Api\Data\TrackingEventInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class TrackingEvent extends AbstractDb
{
    public const TABLE_NAME = 'jscriptz_shipping_tracking_event';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, TrackingEventInterface::ENTITY_ID);
    }
}
