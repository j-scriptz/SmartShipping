<?php
/**
 * Jscriptz SmartShipping - Shipment Service
 *
 * Main service for creating shipments with labels or manual tracking.
 * Uses Magento service contracts - never uses ObjectManager directly.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Shipment;

use Jscriptz\SmartShipping\Api\Data\ShipmentResultInterface;
use Jscriptz\SmartShipping\Api\ShipmentServiceInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\ShipmentTrackCreationInterfaceFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipOrderInterface;
use Psr\Log\LoggerInterface;

class ShipmentService implements ShipmentServiceInterface
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ShipOrderInterface $shipOrder,
        private readonly ShipmentTrackCreationInterfaceFactory $trackCreationFactory,
        private readonly LabelProviderPool $labelProviderPool,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function createWithLabel(
        int $orderId,
        array $packageData = [],
        bool $notify = true
    ): ShipmentResultInterface {
        try {
            $order = $this->orderRepository->get($orderId);

            if (!$order->canShip()) {
                return ShipmentResult::failure(
                    (string) __('Order cannot be shipped.')
                );
            }

            $carrierCode = $this->extractCarrierCode($order->getShippingMethod());

            if (!$this->labelProviderPool->has($carrierCode)) {
                return ShipmentResult::failure(
                    (string) __('No label provider available for carrier: %1', $carrierCode)
                );
            }

            $provider = $this->labelProviderPool->get($carrierCode);
            $storeId = (int) $order->getStoreId();

            if (!$provider->isAvailable($storeId)) {
                return ShipmentResult::failure(
                    (string) __('Label generation is not configured for carrier: %1', $carrierCode)
                );
            }

            // Merge default package data with provided overrides
            $packageData = $this->mergePackageData($packageData, $storeId);

            // Create label via carrier API
            $label = $provider->createLabel($order, $packageData);

            // Create track for shipment
            $track = $this->trackCreationFactory->create();
            $track->setTrackNumber($label->getTrackingNumber());
            $track->setCarrierCode($carrierCode);
            $track->setTitle($label->getServiceType());

            // Create shipment via Magento service contract
            $shipmentId = $this->shipOrder->execute(
                $orderId,
                [],      // items - empty means ship all
                $notify,
                false,   // appendComment
                null,    // comment
                [$track] // tracks
            );

            if ($this->config->isDebugEnabled($storeId)) {
                $this->logger->debug('[SmartShipping] Shipment created with label', [
                    'order_id' => $orderId,
                    'shipment_id' => $shipmentId,
                    'tracking_number' => $label->getTrackingNumber(),
                    'carrier' => $carrierCode,
                ]);
            }

            return ShipmentResult::success(
                $shipmentId,
                $label->getTrackingNumber(),
                $label
            );

        } catch (LocalizedException $e) {
            $this->logger->error('[SmartShipping] Label creation failed: ' . $e->getMessage(), [
                'order_id' => $orderId,
            ]);
            return ShipmentResult::failure($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('[SmartShipping] Unexpected error: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'exception' => $e,
            ]);
            return ShipmentResult::failure(
                (string) __('An error occurred while creating the shipment.')
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function createWithTracking(
        int $orderId,
        string $trackingNumber,
        string $carrierCode,
        ?string $title = null,
        bool $notify = true
    ): ShipmentResultInterface {
        try {
            $order = $this->orderRepository->get($orderId);

            if (!$order->canShip()) {
                return ShipmentResult::failure(
                    (string) __('Order cannot be shipped.')
                );
            }

            // Use carrier title if not provided
            if ($title === null) {
                $title = $this->getCarrierTitle($carrierCode);
            }

            // Create track for shipment
            $track = $this->trackCreationFactory->create();
            $track->setTrackNumber($trackingNumber);
            $track->setCarrierCode($carrierCode);
            $track->setTitle($title);

            // Create shipment via Magento service contract
            $shipmentId = $this->shipOrder->execute(
                $orderId,
                [],      // items
                $notify,
                false,   // appendComment
                null,    // comment
                [$track] // tracks
            );

            $storeId = (int) $order->getStoreId();
            if ($this->config->isDebugEnabled($storeId)) {
                $this->logger->debug('[SmartShipping] Shipment created with manual tracking', [
                    'order_id' => $orderId,
                    'shipment_id' => $shipmentId,
                    'tracking_number' => $trackingNumber,
                    'carrier' => $carrierCode,
                ]);
            }

            return ShipmentResult::success($shipmentId, $trackingNumber);

        } catch (LocalizedException $e) {
            $this->logger->error('[SmartShipping] Manual shipment failed: ' . $e->getMessage(), [
                'order_id' => $orderId,
            ]);
            return ShipmentResult::failure($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('[SmartShipping] Unexpected error: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'exception' => $e,
            ]);
            return ShipmentResult::failure(
                (string) __('An error occurred while creating the shipment.')
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function canShip(int $orderId): bool
    {
        try {
            $order = $this->orderRepository->get($orderId);
            return $order->canShip();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function isLabelAvailable(int $orderId): bool
    {
        try {
            $order = $this->orderRepository->get($orderId);
            $carrierCode = $this->extractCarrierCode($order->getShippingMethod());
            $storeId = (int) $order->getStoreId();

            return $this->labelProviderPool->isAvailable($carrierCode, $storeId);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Extract carrier code from shipping method
     *
     * Shipping method format: "carrier_code_method_code"
     */
    private function extractCarrierCode(?string $shippingMethod): string
    {
        if (empty($shippingMethod)) {
            return '';
        }

        $parts = explode('_', $shippingMethod, 2);
        return $parts[0] ?? '';
    }

    /**
     * Merge provided package data with defaults
     *
     * @param mixed[] $packageData
     * @param int|null $storeId
     * @return mixed[]
     */
    private function mergePackageData(array $packageData, ?int $storeId): array
    {
        $defaults = [
            'weight' => $this->config->getDefaultWeight($storeId),
            'length' => $this->config->getDefaultLength($storeId),
            'width' => $this->config->getDefaultWidth($storeId),
            'height' => $this->config->getDefaultHeight($storeId),
            'label_format' => $this->config->getLabelFormat($storeId),
        ];

        return array_merge($defaults, $packageData);
    }

    /**
     * Get carrier title by code
     */
    private function getCarrierTitle(string $carrierCode): string
    {
        $titles = [
            'uspsv3' => 'USPS',
            'upsv3' => 'UPS',
            'fedexv3' => 'FedEx',
        ];

        return $titles[$carrierCode] ?? $carrierCode;
    }
}
