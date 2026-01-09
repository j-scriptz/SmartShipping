<?php
/**
 * Jscriptz SmartShipping - Tracking Event Type Source Model
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Config\Source;

use Jscriptz\SmartShipping\Api\Data\TrackingEventInterface;
use Magento\Framework\Data\OptionSourceInterface;

class TrackingEventType implements OptionSourceInterface
{
    private const LABELS = [
        TrackingEventInterface::TYPE_LABEL_CREATED => 'Label Created',
        TrackingEventInterface::TYPE_PICKED_UP => 'Picked Up',
        TrackingEventInterface::TYPE_IN_TRANSIT => 'In Transit',
        TrackingEventInterface::TYPE_OUT_FOR_DELIVERY => 'Out for Delivery',
        TrackingEventInterface::TYPE_DELIVERED => 'Delivered',
        TrackingEventInterface::TYPE_EXCEPTION => 'Exception',
        TrackingEventInterface::TYPE_CANCELLED => 'Cancelled',
        TrackingEventInterface::TYPE_UNKNOWN => 'Unknown',
    ];

    private const CARRIER_NAMES = [
        'fedexv3' => 'FedEx',
        'upsv3' => 'UPS',
        'uspsv3' => 'USPS',
    ];

    private const TRACKING_URLS = [
        'fedexv3' => 'https://www.fedex.com/fedextrack/?trknbr=',
        'upsv3' => 'https://www.ups.com/track?tracknum=',
        'uspsv3' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels=',
    ];

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (self::LABELS as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => __($label)
            ];
        }
        return $options;
    }

    /**
     * Get label for event type
     */
    public function getLabel(string $eventType): string
    {
        return self::LABELS[$eventType] ?? ucwords(str_replace('_', ' ', $eventType));
    }

    /**
     * Get carrier display name
     */
    public function getCarrierName(string $carrierCode): string
    {
        return self::CARRIER_NAMES[$carrierCode] ?? ucfirst(str_replace('v3', '', $carrierCode));
    }

    /**
     * Get tracking URL for carrier
     */
    public function getTrackingUrl(string $carrierCode, string $trackingNumber): string
    {
        $baseUrl = self::TRACKING_URLS[$carrierCode] ?? '';
        if (empty($baseUrl)) {
            return '';
        }
        return $baseUrl . urlencode($trackingNumber);
    }
}
