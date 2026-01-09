<?php
/**
 * Jscriptz SmartShipping - Auto Ship on Order Place Observer
 *
 * Automatically creates shipment with label when order is placed,
 * if auto-shipment is enabled and trigger is set to 'order_place'.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Observer;

use Jscriptz\SmartShipping\Api\ShipmentServiceInterface;
use Jscriptz\SmartShipping\Model\Shipment\Config;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class AutoShipOnOrderPlace implements ObserverInterface
{
    // Carriers that support auto-shipment
    private const SUPPORTED_CARRIERS = ['uspsv3', 'upsv3', 'fedexv3'];

    public function __construct(
        private readonly ShipmentServiceInterface $shipmentService,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $observer->getEvent()->getOrder();

        if (!$order) {
            return;
        }

        $storeId = (int) $order->getStoreId();

        // Check if auto-ship is enabled
        if (!$this->config->isAutoShipEnabled($storeId)) {
            return;
        }

        // Check if trigger is 'order_place'
        if (!$this->config->isOrderPlaceTrigger($storeId)) {
            return;
        }

        // Check if order can be shipped
        if (!$order->canShip()) {
            return;
        }

        // Check if shipping method is from a supported carrier
        $carrierCode = $this->extractCarrierCode($order->getShippingMethod());
        if (!in_array($carrierCode, self::SUPPORTED_CARRIERS)) {
            return;
        }

        // Check if carrier is enabled for auto-shipment
        if (!$this->config->isCarrierEnabled($carrierCode, $storeId)) {
            return;
        }

        // Check if label generation is available for this order
        if (!$this->shipmentService->isLabelAvailable($order->getId())) {
            if ($this->config->isDebugEnabled($storeId)) {
                $this->logger->debug('[SmartShipping AutoShip] Label not available for order', [
                    'order_id' => $order->getId(),
                    'carrier' => $carrierCode,
                ]);
            }
            return;
        }

        try {
            $this->logger->info('[SmartShipping AutoShip] Auto-creating shipment for order', [
                'order_id' => $order->getId(),
                'increment_id' => $order->getIncrementId(),
                'carrier' => $carrierCode,
                'trigger' => 'order_place',
            ]);

            $result = $this->shipmentService->createWithLabel(
                (int) $order->getId(),
                [],
                $this->config->isSendTrackingEmailEnabled($storeId)
            );

            if ($result->isSuccess()) {
                $this->logger->info('[SmartShipping AutoShip] Shipment created successfully', [
                    'order_id' => $order->getId(),
                    'shipment_id' => $result->getShipmentId(),
                    'tracking_number' => $result->getTrackingNumber(),
                ]);
            } else {
                $this->logger->error('[SmartShipping AutoShip] Shipment creation failed', [
                    'order_id' => $order->getId(),
                    'error' => $result->getErrorMessage(),
                ]);
            }

        } catch (\Exception $e) {
            // Log but don't throw - we don't want to fail the order placement
            $this->logger->error('[SmartShipping AutoShip] Exception during auto-shipment', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract carrier code from shipping method
     */
    private function extractCarrierCode(?string $shippingMethod): string
    {
        if (empty($shippingMethod)) {
            return '';
        }

        $parts = explode('_', $shippingMethod, 2);
        return $parts[0] ?? '';
    }
}
