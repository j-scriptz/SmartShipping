<?php
/**
 * Jscriptz SmartShipping - FedEx Drop Off Type Source Model
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Fedex\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class DropOffType implements OptionSourceInterface
{
    public const REGULAR_PICKUP = 'REGULAR_PICKUP';
    public const REQUEST_COURIER = 'REQUEST_COURIER';
    public const DROP_BOX = 'DROP_BOX';
    public const BUSINESS_SERVICE_CENTER = 'BUSINESS_SERVICE_CENTER';
    public const STATION = 'STATION';

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::REGULAR_PICKUP, 'label' => __('Regular Pickup (Scheduled)')],
            ['value' => self::REQUEST_COURIER, 'label' => __('Request Courier (On-Demand)')],
            ['value' => self::DROP_BOX, 'label' => __('Drop Box')],
            ['value' => self::BUSINESS_SERVICE_CENTER, 'label' => __('Business Service Center')],
            ['value' => self::STATION, 'label' => __('FedEx Station')],
        ];
    }
}
