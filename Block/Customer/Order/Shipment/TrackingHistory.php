<?php
/**
 * Jscriptz SmartShipping - Customer Tracking History Block
 *
 * Displays tracking event timeline on customer account shipment view.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Block\Customer\Order\Shipment;

use Jscriptz\SmartShipping\Api\Data\TrackingEventInterface;
use Jscriptz\SmartShipping\Api\TrackingEventRepositoryInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\Track;

class TrackingHistory extends Template
{
    protected $_template = 'Jscriptz_SmartShipping::order/shipment/tracking_history.phtml';

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
     * Get CSS class for event type
     */
    public function getEventTypeClass(string $eventType): string
    {
        return match ($eventType) {
            TrackingEventInterface::TYPE_DELIVERED => 'delivered',
            TrackingEventInterface::TYPE_OUT_FOR_DELIVERY => 'out-for-delivery',
            TrackingEventInterface::TYPE_IN_TRANSIT => 'in-transit',
            TrackingEventInterface::TYPE_PICKED_UP => 'picked-up',
            TrackingEventInterface::TYPE_LABEL_CREATED => 'label-created',
            TrackingEventInterface::TYPE_EXCEPTION => 'exception',
            TrackingEventInterface::TYPE_CANCELLED => 'cancelled',
            default => 'unknown',
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
            default => __('Update')->render(),
        };
    }

    /**
     * Format event timestamp for display
     */
    public function formatEventTime(string $timestamp): string
    {
        try {
            $date = new \DateTime($timestamp);
            return $date->format('M j, Y \a\t g:i A');
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

    /**
     * Get icon SVG for event type
     */
    public function getEventIcon(string $eventType): string
    {
        return match ($eventType) {
            TrackingEventInterface::TYPE_DELIVERED => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm13.36-1.814a.75.75 0 10-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.14-.094l3.75-5.25z" clip-rule="evenodd" /></svg>',
            TrackingEventInterface::TYPE_OUT_FOR_DELIVERY => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M3.375 4.5C2.339 4.5 1.5 5.34 1.5 6.375V13.5h12V6.375c0-1.036-.84-1.875-1.875-1.875h-8.25zM13.5 15h-12v2.625c0 1.035.84 1.875 1.875 1.875h.375a3 3 0 116 0h3a.75.75 0 00.75-.75V15z" /><path d="M8.25 19.5a1.5 1.5 0 10-3 0 1.5 1.5 0 003 0zM15.75 6.75a.75.75 0 00-.75.75v11.25c0 .087.015.17.042.248a3 3 0 015.958.252.75.75 0 00.75-.75V8.625a.75.75 0 00-.75-.75h-5.25z" /><path d="M19.5 19.5a1.5 1.5 0 10-3 0 1.5 1.5 0 003 0z" /></svg>',
            TrackingEventInterface::TYPE_IN_TRANSIT => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M8.161 2.58a1.875 1.875 0 011.678 0l4.993 2.498c.106.052.23.052.336 0l3.869-1.935A1.875 1.875 0 0121.75 4.82v12.485c0 .71-.401 1.36-1.037 1.677l-4.875 2.437a1.875 1.875 0 01-1.676 0l-4.994-2.497a.375.375 0 00-.336 0l-3.868 1.935A1.875 1.875 0 012.25 19.18V6.695c0-.71.401-1.36 1.036-1.677l4.875-2.437zM9 6a.75.75 0 01.75.75V15a.75.75 0 01-1.5 0V6.75A.75.75 0 019 6zm6.75 3a.75.75 0 00-1.5 0v8.25a.75.75 0 001.5 0V9z" clip-rule="evenodd" /></svg>',
            TrackingEventInterface::TYPE_PICKED_UP => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M11.47 1.72a.75.75 0 011.06 0l3 3a.75.75 0 01-1.06 1.06l-1.72-1.72V7.5h-1.5V4.06L9.53 5.78a.75.75 0 01-1.06-1.06l3-3zM11.25 7.5V15a.75.75 0 001.5 0V7.5h3.75a3 3 0 013 3v9a3 3 0 01-3 3h-9a3 3 0 01-3-3v-9a3 3 0 013-3h3.75z" /></svg>',
            TrackingEventInterface::TYPE_LABEL_CREATED => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M5.625 1.5c-1.036 0-1.875.84-1.875 1.875v17.25c0 1.035.84 1.875 1.875 1.875h12.75c1.035 0 1.875-.84 1.875-1.875V12.75A3.75 3.75 0 0016.5 9h-1.875a1.875 1.875 0 01-1.875-1.875V5.25A3.75 3.75 0 009 1.5H5.625zM7.5 15a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5A.75.75 0 017.5 15zm.75 2.25a.75.75 0 000 1.5H12a.75.75 0 000-1.5H8.25z" clip-rule="evenodd" /><path d="M12.971 1.816A5.23 5.23 0 0114.25 5.25v1.875c0 .207.168.375.375.375H16.5a5.23 5.23 0 013.434 1.279 9.768 9.768 0 00-6.963-6.963z" /></svg>',
            TrackingEventInterface::TYPE_EXCEPTION,
            TrackingEventInterface::TYPE_CANCELLED => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd" /></svg>',
            default => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm8.706-1.442c1.146-.573 2.437.463 2.126 1.706l-.709 2.836.042-.02a.75.75 0 01.67 1.34l-.04.022c-1.147.573-2.438-.463-2.127-1.706l.71-2.836-.042.02a.75.75 0 11-.671-1.34l.041-.022zM12 9a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd" /></svg>',
        };
    }
}
