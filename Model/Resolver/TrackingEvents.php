<?php
/**
 * Jscriptz SmartShipping - TrackingEvents GraphQL Resolver
 *
 * Resolves tracking events for a ShipmentTracking type.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Resolver;

use Jscriptz\SmartShipping\Api\Data\TrackingEventInterface;
use Jscriptz\SmartShipping\Api\TrackingEventRepositoryInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\ShipmentTrackRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class TrackingEvents implements ResolverInterface
{
    public function __construct(
        private readonly TrackingEventRepositoryInterface $trackingEventRepository,
        private readonly ShipmentTrackRepositoryInterface $shipmentTrackRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
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
    ): ?array {
        if (!isset($value['number'])) {
            return null;
        }

        $trackingNumber = $value['number'];

        // Get events by tracking number
        $events = $this->trackingEventRepository->getByTrackingNumber($trackingNumber);

        if (empty($events)) {
            return [];
        }

        return array_map(function (TrackingEventInterface $event) {
            return [
                'entity_id' => $event->getEntityId(),
                'event_code' => $event->getEventCode(),
                'event_type' => $this->mapEventTypeToEnum($event->getEventType()),
                'event_description' => $event->getEventDescription(),
                'event_timestamp' => $event->getEventTimestamp(),
                'location' => [
                    'city' => $event->getLocationCity(),
                    'state' => $event->getLocationState(),
                    'country' => $event->getLocationCountry(),
                    'postal_code' => $event->getLocationPostalCode(),
                    'formatted' => $event->getFormattedLocation(),
                ],
                'image_url' => $event->getImageUrl(),
                'signature_proof' => $event->getSignatureProof(),
            ];
        }, $events);
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
