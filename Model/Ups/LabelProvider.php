<?php
/**
 * Jscriptz SmartShipping - UPS Label Provider
 *
 * Implements LabelProviderInterface for UPS carrier.
 * Ties together the UPS clients for label creation and tracking.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Ups;

use Jscriptz\SmartShipping\Api\Data\ShipmentLabelInterface;
use Jscriptz\SmartShipping\Api\LabelProviderInterface;
use Jscriptz\SmartShipping\Model\Ups\Client\LabelClient;
use Jscriptz\SmartShipping\Model\Ups\Request\LabelRequestBuilder;
use Jscriptz\SmartShipping\Model\Ups\Response\LabelResponseParser;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

class LabelProvider implements LabelProviderInterface
{
    private const CARRIER_CODE = 'upsv3';

    private const SUPPORTED_FORMATS = ['GIF', 'PNG', 'PDF', 'ZPL', 'EPL'];

    public function __construct(
        private readonly Config $config,
        private readonly LabelClient $labelClient,
        private readonly LabelRequestBuilder $requestBuilder,
        private readonly LabelResponseParser $responseParser,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCarrierCode(): string
    {
        return self::CARRIER_CODE;
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(?int $storeId = null): bool
    {
        // Check if carrier is active
        if (!$this->config->isActive($storeId)) {
            return false;
        }

        // Check if label generation is enabled
        if (!$this->config->isLabelEnabled($storeId)) {
            return false;
        }

        // Check if all required credentials are configured
        return $this->config->hasLabelCredentials($storeId);
    }

    /**
     * @inheritDoc
     */
    public function createLabel(OrderInterface $order, array $packageData = []): ShipmentLabelInterface
    {
        $storeId = (int) $order->getStoreId();

        if (!$this->isAvailable($storeId)) {
            throw new LocalizedException(
                __('UPS label generation is not available. Please check configuration.')
            );
        }

        try {
            // Build the API request
            $requestData = $this->requestBuilder->build($order, $packageData);

            // Get label format
            $labelFormat = $packageData['label_format'] ?? $this->config->getLabelFormat($storeId);

            if ($this->config->isDebugEnabled($storeId)) {
                $this->logger->debug('[UPSv3 LabelProvider] Creating label for order', [
                    'order_id' => $order->getEntityId(),
                    'increment_id' => $order->getIncrementId(),
                    'service_code' => $requestData['ShipmentRequest']['Shipment']['Service']['Code'] ?? 'unknown',
                    'format' => $labelFormat,
                ]);
            }

            // Call UPS Ship API
            $response = $this->labelClient->createLabel($requestData, $storeId);

            // Parse response into ShipmentLabel
            $label = $this->responseParser->parse($response, $labelFormat);

            if ($this->config->isDebugEnabled($storeId)) {
                $this->logger->debug('[UPSv3 LabelProvider] Label created successfully', [
                    'tracking_number' => $label->getTrackingNumber(),
                    'postage' => $label->getPostage(),
                    'service_type' => $label->getServiceType(),
                ]);
            }

            return $label;

        } catch (LocalizedException $e) {
            $this->logger->error('[UPSv3 LabelProvider] Label creation failed', [
                'order_id' => $order->getEntityId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('[UPSv3 LabelProvider] Unexpected error', [
                'order_id' => $order->getEntityId(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            throw new LocalizedException(
                __('Failed to create UPS label: %1', $e->getMessage()),
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function getTrackingInfo(string $trackingNumber, ?int $storeId = null): array
    {
        // TODO: Implement UPS Track API client
        // Endpoint: GET /api/track/v1/details/{trackingNumber}
        throw new LocalizedException(
            __('UPS tracking API is not yet implemented.')
        );
    }

    /**
     * @inheritDoc
     */
    public function voidLabel(string $trackingNumber, ?int $storeId = null): bool
    {
        try {
            if ($this->config->isDebugEnabled($storeId)) {
                $this->logger->debug('[UPSv3 LabelProvider] Voiding label', [
                    'tracking_number' => $trackingNumber,
                ]);
            }

            // UPS void requires the shipment ID, not just tracking number
            // For now, use tracking number as shipment ID (they're often the same)
            return $this->labelClient->voidLabel($trackingNumber, $trackingNumber, $storeId);
        } catch (LocalizedException $e) {
            $this->logger->error('[UPSv3 LabelProvider] Label void failed', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function getSupportedFormats(): array
    {
        return self::SUPPORTED_FORMATS;
    }
}
