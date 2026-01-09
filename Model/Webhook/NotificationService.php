<?php
/**
 * Jscriptz SmartShipping - Webhook Notification Service
 *
 * Handles customer email notifications for tracking events.
 * Supports all carriers (FedEx, UPS, USPS) with POD photo preferences.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Webhook;

use Jscriptz\SmartShipping\Api\Data\TrackingEventInterface;
use Jscriptz\SmartShipping\Api\TrackingEventRepositoryInterface;
use Jscriptz\SmartShipping\Model\Config;
use Jscriptz\SmartShipping\Model\Config\Source\TrackingEventType;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Area;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Api\ShipmentTrackRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class NotificationService
{
    public function __construct(
        private readonly Config $config,
        private readonly TransportBuilder $transportBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly ShipmentTrackRepositoryInterface $trackRepository,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly TrackingEventRepositoryInterface $eventRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly TrackingEventType $eventTypeSource,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Queue a notification for a tracking event
     */
    public function queueNotification(TrackingEventInterface $event): void
    {
        if (!$this->shouldNotify($event)) {
            return;
        }

        try {
            $this->sendNotification($event);
            $this->eventRepository->markEmailSent($event->getEntityId());
        } catch (\Exception $e) {
            $this->logger->error("[SmartShipping] Failed to send tracking notification: " . $e->getMessage(), [
                'tracking_number' => $event->getTrackingNumber(),
                'event_type' => $event->getEventType(),
                'carrier' => $event->getCarrierCode(),
            ]);
        }
    }

    /**
     * Check if notification should be sent for this event
     */
    private function shouldNotify(TrackingEventInterface $event): bool
    {
        // Must have a track_id to find the order
        if (!$event->getTrackId()) {
            return false;
        }

        // Check if notifications are enabled
        if (!$this->config->isNotificationEnabled()) {
            return false;
        }

        // Get configured event types that trigger notifications
        $configuredEvents = $this->config->getNotificationEvents();

        // Empty array means all events trigger notification
        if (empty($configuredEvents)) {
            return true;
        }

        return in_array($event->getEventType(), $configuredEvents, true);
    }

    /**
     * Send the notification email
     */
    private function sendNotification(TrackingEventInterface $event): void
    {
        $trackId = $event->getTrackId();
        if (!$trackId) {
            $this->logger->debug("[SmartShipping] Cannot send notification - no track ID for event: " . $event->getEntityId());
            return;
        }

        try {
            $track = $this->trackRepository->get($trackId);
            $shipment = $this->shipmentRepository->get($track->getParentId());
            $order = $this->orderRepository->get($shipment->getOrderId());
        } catch (\Exception $e) {
            $this->logger->warning("[SmartShipping] Cannot load order for tracking notification", [
                'track_id' => $trackId,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        $storeId = (int) $order->getStoreId();
        $customerEmail = $order->getCustomerEmail();
        $customerName = trim($order->getCustomerFirstname() . ' ' . $order->getCustomerLastname()) ?: 'Customer';

        // Format location
        $location = $this->formatLocation($event);

        // Format timestamp
        $formattedDate = $this->formatTimestamp($event->getEventTimestamp());

        // Determine if POD photo should be included
        $deliveryPhotoUrl = $this->getDeliveryPhotoUrl($event, $order);

        // Get carrier-specific info
        $carrierCode = $event->getCarrierCode();
        $carrierName = $this->eventTypeSource->getCarrierName($carrierCode);
        $trackingUrl = $this->eventTypeSource->getTrackingUrl($carrierCode, $event->getTrackingNumber());

        // Get sender and template
        $sender = $this->config->getNotificationSender($storeId);
        $templateId = $this->config->getNotificationTemplate($storeId);

        // Build template variables
        $templateVars = [
            'order' => $order,
            'shipment' => $shipment,
            'track' => $track,
            'event' => $event,
            'customer_name' => $customerName,
            'order_increment_id' => $order->getIncrementId(),
            'tracking_number' => $event->getTrackingNumber(),
            'carrier_code' => $carrierCode,
            'carrier_name' => $carrierName,
            'event_type' => $event->getEventType(),
            'event_type_label' => $this->eventTypeSource->getLabel($event->getEventType()),
            'event_description' => $event->getEventDescription(),
            'event_timestamp' => $formattedDate,
            'location' => $location,
            'tracking_url' => $trackingUrl,
            'delivery_photo_url' => $deliveryPhotoUrl,
            'signature_name' => $event->getSignatureProof(),
            'signature_proof' => $event->getSignatureProof(),
        ];

        $transport = $this->transportBuilder
            ->setTemplateIdentifier($templateId)
            ->setTemplateOptions([
                'area' => Area::AREA_FRONTEND,
                'store' => $storeId
            ])
            ->setTemplateVars($templateVars)
            ->setFromByScope($sender, $storeId)
            ->addTo($customerEmail, $customerName)
            ->getTransport();

        $transport->sendMessage();

        $this->logger->info("[SmartShipping] Tracking notification sent", [
            'order_id' => $order->getIncrementId(),
            'tracking_number' => $event->getTrackingNumber(),
            'event_type' => $event->getEventType(),
            'carrier' => $carrierName,
            'customer_email' => $customerEmail
        ]);
    }

    /**
     * Format location from event data
     */
    private function formatLocation(TrackingEventInterface $event): string
    {
        $parts = array_filter([
            $event->getLocationCity(),
            $event->getLocationState(),
            $event->getLocationCountry()
        ]);

        return implode(', ', $parts);
    }

    /**
     * Format timestamp for display
     */
    private function formatTimestamp(string $timestamp): string
    {
        try {
            $date = new \DateTime($timestamp);
            return $date->format('F j, Y \a\t g:i A');
        } catch (\Exception $e) {
            return $timestamp;
        }
    }

    /**
     * Get delivery photo URL if enabled in admin and by customer
     *
     * Returns the photo URL only if:
     * 1. The event has a photo URL
     * 2. Admin has enabled POD photos in emails
     * 3. Customer has not disabled POD photos in their account (if logged in)
     */
    private function getDeliveryPhotoUrl(
        TrackingEventInterface $event,
        OrderInterface $order
    ): ?string {
        // Check if event has a photo
        $photoUrl = $event->getImageUrl();
        if (empty($photoUrl)) {
            return null;
        }

        // Check admin config - must be enabled
        $storeId = (int) $order->getStoreId();
        if (!$this->config->isPodPhotoEnabled($storeId)) {
            return null;
        }

        // Check customer preference (if customer exists)
        $customerId = $order->getCustomerId();
        if ($customerId) {
            try {
                $customer = $this->customerRepository->getById($customerId);
                $disablePodPhotos = $customer->getCustomAttribute('disable_pod_photos');
                if ($disablePodPhotos && $disablePodPhotos->getValue()) {
                    $this->logger->debug("[SmartShipping] Customer {$customerId} has disabled POD photos");
                    return null;
                }
            } catch (\Exception $e) {
                // Customer not found - proceed with photo
                $this->logger->debug("[SmartShipping] Could not load customer {$customerId}: " . $e->getMessage());
            }
        }

        return $photoUrl;
    }

    /**
     * Process pending notifications (for cron job)
     */
    public function processPendingNotifications(int $limit = 100): int
    {
        $events = $this->eventRepository->getPendingEmailEvents($limit);
        $sent = 0;

        foreach ($events as $event) {
            try {
                if ($this->shouldNotify($event)) {
                    $this->sendNotification($event);
                }
                $this->eventRepository->markEmailSent($event->getEntityId());
                $sent++;
            } catch (\Exception $e) {
                $this->logger->error("[SmartShipping] Failed to process pending notification: " . $e->getMessage());
            }
        }

        return $sent;
    }
}
