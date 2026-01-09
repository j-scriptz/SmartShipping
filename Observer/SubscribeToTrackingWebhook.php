<?php
/**
 * Jscriptz SmartShipping - Subscribe To Tracking Webhook Observer
 *
 * Automatically subscribes to carrier webhooks when a tracking number is added to a shipment.
 * This enables real-time tracking updates for individual shipments.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Observer;

use Jscriptz\SmartShipping\Api\Data\WebhookSubscriptionInterface;
use Jscriptz\SmartShipping\Api\Data\WebhookSubscriptionInterfaceFactory;
use Jscriptz\SmartShipping\Api\WebhookSubscriptionRepositoryInterface;
use Jscriptz\SmartShipping\Model\Webhook\ProcessorPool;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Shipment\Track;
use Psr\Log\LoggerInterface;

class SubscribeToTrackingWebhook implements ObserverInterface
{
    private const SUPPORTED_CARRIERS = ['fedexv3', 'upsv3', 'uspsv3'];

    public function __construct(
        private readonly ProcessorPool $processorPool,
        private readonly WebhookSubscriptionInterfaceFactory $subscriptionFactory,
        private readonly WebhookSubscriptionRepositoryInterface $subscriptionRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        /** @var Track $track */
        $track = $observer->getEvent()->getData('track');

        if (!$track instanceof Track) {
            return;
        }

        $carrierCode = $track->getCarrierCode();
        $trackingNumber = $track->getTrackNumber();
        $trackId = (int) $track->getEntityId();

        // Only process supported carriers
        if (!in_array($carrierCode, self::SUPPORTED_CARRIERS, true)) {
            return;
        }

        // Check if processor exists and is enabled
        if (!$this->processorPool->has($carrierCode)) {
            $this->logger->debug(sprintf(
                'SmartShipping: No webhook processor for carrier %s',
                $carrierCode
            ));
            return;
        }

        $processor = $this->processorPool->get($carrierCode);

        if (!$processor->isEnabled()) {
            $this->logger->debug(sprintf(
                'SmartShipping: Webhook processor for carrier %s is disabled',
                $carrierCode
            ));
            return;
        }

        // Check if subscription already exists
        if ($this->subscriptionExists($carrierCode, $trackingNumber)) {
            $this->logger->debug(sprintf(
                'SmartShipping: Webhook subscription already exists for %s %s',
                $carrierCode,
                $trackingNumber
            ));
            return;
        }

        try {
            $this->createSubscription($carrierCode, $trackingNumber, $trackId);
            $this->logger->info(sprintf(
                'SmartShipping: Created webhook subscription for %s tracking %s',
                $carrierCode,
                $trackingNumber
            ));
        } catch (LocalizedException $e) {
            $this->logger->error(sprintf(
                'SmartShipping: Failed to create webhook subscription for %s %s: %s',
                $carrierCode,
                $trackingNumber,
                $e->getMessage()
            ));
        }
    }

    /**
     * Check if a subscription already exists for this tracking number
     */
    private function subscriptionExists(string $carrierCode, string $trackingNumber): bool
    {
        try {
            $subscription = $this->subscriptionRepository->getTrackingSubscription($carrierCode, $trackingNumber);
            return $subscription !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create a new webhook subscription
     */
    private function createSubscription(
        string $carrierCode,
        string $trackingNumber,
        int $trackId
    ): void {
        $processor = $this->processorPool->get($carrierCode);

        /** @var WebhookSubscriptionInterface $subscription */
        $subscription = $this->subscriptionFactory->create();

        $subscription->setCarrierCode($carrierCode);
        $subscription->setSubscriptionType('tracking');
        $subscription->setTrackingNumber($trackingNumber);
        $subscription->setTrackId($trackId);
        $subscription->setSecurityToken($processor->getSecurityToken());
        $subscription->setStatus('pending');

        // Save the subscription record
        $this->subscriptionRepository->save($subscription);

        // Note: The actual API call to register the webhook with the carrier
        // is handled separately. For FedEx, this requires calling the
        // Advanced Integrated Visibility API to subscribe to tracking updates.
        // This can be done:
        // 1. Immediately here (synchronous)
        // 2. Via a cron job that processes pending subscriptions (async, recommended)
        // 3. Via a message queue consumer (async, for high volume)

        // For now, we just create the subscription record. The carrier-specific
        // WebhookClient can be called later to actually register with the carrier.
    }
}
