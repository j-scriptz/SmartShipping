<?php
/**
 * Jscriptz SmartShipping - Admin Tracking History Block
 *
 * Displays tracking event timeline on shipment view in admin.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Block\Adminhtml\Order\Shipment\View;

use Jscriptz\SmartShipping\Api\Data\TrackingEventInterface;
use Jscriptz\SmartShipping\Api\TrackingEventRepositoryInterface;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Track;

class TrackingHistory extends Template
{
    protected $_template = 'Jscriptz_SmartShipping::order/shipment/view/tracking_history.phtml';

    /**
     * @var TrackingEventInterface[][]
     */
    private array $eventsCache = [];

    public function __construct(
        Context $context,
        private readonly TrackingEventRepositoryInterface $trackingEventRepository,
        private readonly Registry $registry,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get current shipment
     */
    public function getShipment(): ?Shipment
    {
        return $this->registry->registry('current_shipment');
    }

    /**
     * Get all tracking numbers for this shipment
     *
     * @return Track[]
     */
    public function getTracks(): array
    {
        $shipment = $this->getShipment();
        if (!$shipment) {
            return [];
        }

        return $shipment->getAllTracks();
    }

    /**
     * Get tracking events for a specific track
     *
     * @return TrackingEventInterface[]
     */
    public function getEventsForTrack(Track $track): array
    {
        $trackId = (int) $track->getId();

        if (!isset($this->eventsCache[$trackId])) {
            $this->eventsCache[$trackId] = $this->trackingEventRepository->getByTrackId($trackId);
        }

        return $this->eventsCache[$trackId];
    }

    /**
     * Get all tracking events grouped by tracking number
     *
     * @return array<string, array{track: Track, events: TrackingEventInterface[]}>
     */
    public function getGroupedEvents(): array
    {
        $grouped = [];

        foreach ($this->getTracks() as $track) {
            $trackingNumber = $track->getTrackNumber();
            $events = $this->getEventsForTrack($track);

            $grouped[$trackingNumber] = [
                'track' => $track,
                'events' => $events,
                'carrier_title' => $track->getTitle(),
                'latest_status' => $this->getLatestStatus($events),
            ];
        }

        return $grouped;
    }

    /**
     * Get latest status from events
     */
    public function getLatestStatus(array $events): ?TrackingEventInterface
    {
        if (empty($events)) {
            return null;
        }

        // Events should already be sorted by timestamp desc
        return $events[0] ?? null;
    }

    /**
     * Check if there are any tracking events to display
     */
    public function hasEvents(): bool
    {
        foreach ($this->getTracks() as $track) {
            if (!empty($this->getEventsForTrack($track))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get CSS class for event type badge
     */
    public function getEventTypeBadgeClass(string $eventType): string
    {
        return match ($eventType) {
            TrackingEventInterface::TYPE_DELIVERED => 'success',
            TrackingEventInterface::TYPE_OUT_FOR_DELIVERY => 'notice',
            TrackingEventInterface::TYPE_IN_TRANSIT => 'minor',
            TrackingEventInterface::TYPE_PICKED_UP => 'minor',
            TrackingEventInterface::TYPE_LABEL_CREATED => 'minor',
            TrackingEventInterface::TYPE_EXCEPTION => 'critical',
            TrackingEventInterface::TYPE_CANCELLED => 'critical',
            default => 'minor',
        };
    }

    /**
     * Get human-readable label for event type
     */
    public function getEventTypeLabel(string $eventType): string
    {
        return match ($eventType) {
            TrackingEventInterface::TYPE_LABEL_CREATED => __('Label Created')->render(),
            TrackingEventInterface::TYPE_PICKED_UP => __('Picked Up')->render(),
            TrackingEventInterface::TYPE_IN_TRANSIT => __('In Transit')->render(),
            TrackingEventInterface::TYPE_OUT_FOR_DELIVERY => __('Out for Delivery')->render(),
            TrackingEventInterface::TYPE_DELIVERED => __('Delivered')->render(),
            TrackingEventInterface::TYPE_EXCEPTION => __('Exception')->render(),
            TrackingEventInterface::TYPE_CANCELLED => __('Cancelled')->render(),
            default => __('Unknown')->render(),
        };
    }

    /**
     * Format event timestamp for display
     */
    public function formatEventTime(string $timestamp): string
    {
        try {
            $date = new \DateTime($timestamp);
            return $date->format('M j, Y g:i A');
        } catch (\Exception $e) {
            return $timestamp;
        }
    }

    /**
     * Get carrier name for display
     */
    public function getCarrierName(string $carrierCode): string
    {
        return match ($carrierCode) {
            'fedexv3' => 'FedEx',
            'upsv3' => 'UPS',
            'uspsv3' => 'USPS',
            default => ucfirst(str_replace(['v3', 'v2'], '', $carrierCode)),
        };
    }

    /**
     * Get tracking URL for carrier
     */
    public function getTrackingUrl(string $carrierCode, string $trackingNumber): string
    {
        return match ($carrierCode) {
            'fedexv3' => 'https://www.fedex.com/fedextrack/?trknbr=' . urlencode($trackingNumber),
            'upsv3' => 'https://www.ups.com/track?tracknum=' . urlencode($trackingNumber),
            'uspsv3' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels=' . urlencode($trackingNumber),
            default => '#',
        };
    }
}
