<?php
/**
 * Jscriptz SmartShipping - TrackingHistory GraphQL Resolver
 *
 * Resolves extended tracking history for an OrderShipment type.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Resolver;

use Jscriptz\SmartShipping\Api\Data\TrackingEventInterface;
use Jscriptz\SmartShipping\Api\TrackingEventRepositoryInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Api\ShipmentTrackRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class TrackingHistory implements ResolverInterface
{
    public function __construct(
        private readonly TrackingEventRepositoryInterface $trackingEventRepository,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
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
    ): array {
        if (!isset($value['id'])) {
            return [];
        }

        // Extract shipment ID from the encoded ID
        $shipmentId = $this->extractShipmentId($value['id']);
        if (!$shipmentId) {
            return [];
        }

        try {
            $shipment = $this->shipmentRepository->get($shipmentId);
        } catch (\Exception $e) {
            return [];
        }

        $result = [];

        foreach ($shipment->getTracks() as $track) {
            $trackingNumber = $track->getTrackNumber();
            $carrierCode = $track->getCarrierCode() ?? '';
            $events = $this->trackingEventRepository->getByTrackId((int) $track->getEntityId());

            $latestEvent = !empty($events) ? $events[0] : null;

            $result[] = [
                'tracking_number' => $trackingNumber,
                'carrier' => $track->getTitle(),
                'carrier_code' => $carrierCode,
                'current_status' => $latestEvent
                    ? $this->mapEventTypeToEnum($latestEvent->getEventType())
                    : null,
                'current_status_label' => $latestEvent
                    ? $this->getEventTypeLabel($latestEvent->getEventType())
                    : null,
                'tracking_url' => $this->getTrackingUrl($carrierCode, $trackingNumber),
                'events' => array_map(function (TrackingEventInterface $event) {
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
                }, $events),
                'estimated_delivery_date' => null, // Could be populated from carrier data
            ];
        }

        return $result;
    }

    /**
     * Extract shipment ID from encoded GraphQL ID
     */
    private function extractShipmentId(string $encodedId): ?int
    {
        // The ID format is typically base64 encoded
        $decoded = base64_decode($encodedId, true);
        if ($decoded === false) {
            // Try numeric ID directly
            return is_numeric($encodedId) ? (int) $encodedId : null;
        }

        // Extract numeric ID from decoded string
        if (preg_match('/(\d+)/', $decoded, $matches)) {
            return (int) $matches[1];
        }

        return null;
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

    /**
     * Get human-readable label for event type
     */
    private function getEventTypeLabel(string $eventType): string
    {
        return match ($eventType) {
            TrackingEventInterface::TYPE_LABEL_CREATED => 'Label Created',
            TrackingEventInterface::TYPE_PICKED_UP => 'Picked Up',
            TrackingEventInterface::TYPE_IN_TRANSIT => 'In Transit',
            TrackingEventInterface::TYPE_OUT_FOR_DELIVERY => 'Out for Delivery',
            TrackingEventInterface::TYPE_DELIVERED => 'Delivered',
            TrackingEventInterface::TYPE_EXCEPTION => 'Exception',
            TrackingEventInterface::TYPE_CANCELLED => 'Cancelled',
            default => 'Unknown',
        };
    }

    /**
     * Get tracking URL for carrier
     */
    private function getTrackingUrl(string $carrierCode, string $trackingNumber): string
    {
        return match ($carrierCode) {
            'fedexv3' => 'https://www.fedex.com/fedextrack/?trknbr=' . urlencode($trackingNumber),
            'upsv3' => 'https://www.ups.com/track?tracknum=' . urlencode($trackingNumber),
            'uspsv3' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels=' . urlencode($trackingNumber),
            default => '#',
        };
    }
}
