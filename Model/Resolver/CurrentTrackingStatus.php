<?php
/**
 * Jscriptz SmartShipping - CurrentTrackingStatus GraphQL Resolver
 *
 * Resolves current tracking status for a ShipmentTracking type.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Resolver;

use Jscriptz\SmartShipping\Api\Data\TrackingEventInterface;
use Jscriptz\SmartShipping\Api\TrackingEventRepositoryInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class CurrentTrackingStatus implements ResolverInterface
{
    public function __construct(
        private readonly TrackingEventRepositoryInterface $trackingEventRepository
    ) {
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): ?string {
        if (!isset($value['number'])) {
            return null;
        }

        $trackingNumber = $value['number'];
        $latestEvent = $this->trackingEventRepository->getLatestEvent($trackingNumber);

        if (!$latestEvent) {
            return null;
        }

        return $this->mapEventTypeToEnum($latestEvent->getEventType());
    }

    /**
     * Map internal event type to GraphQL enum value
     */
    private function mapEventTypeToEnum(string $eventType): string
    {
        return match ($eventType) {
            TrackingEventInterface::TYPE_LABEL_CREATED => 'LABEL_CREATED',
            TrackingEventInterface::TYPE_PICKED_UP => 'PICKED_UP',
            TrackingEventInterface::TYPE_IN_TRANSIT => 'IN_TRANSIT',
            TrackingEventInterface::TYPE_OUT_FOR_DELIVERY => 'OUT_FOR_DELIVERY',
            TrackingEventInterface::TYPE_DELIVERED => 'DELIVERED',
            TrackingEventInterface::TYPE_EXCEPTION => 'EXCEPTION',
            TrackingEventInterface::TYPE_CANCELLED => 'CANCELLED',
            default => 'UNKNOWN',
        };
    }
}
