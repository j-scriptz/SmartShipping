<?php
/**
 * Jscriptz SmartShipping - USPS Label Provider
 *
 * Implements LabelProviderInterface for USPS carrier.
 * Ties together the USPS clients for label creation and tracking.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Usps;

use Jscriptz\SmartShipping\Api\Data\ShipmentLabelInterface;
use Jscriptz\SmartShipping\Api\LabelProviderInterface;
use Jscriptz\SmartShipping\Model\Usps\Client\LabelClient;
use Jscriptz\SmartShipping\Model\Usps\Client\TrackingClient;
use Jscriptz\SmartShipping\Model\Usps\Request\LabelRequestBuilder;
use Jscriptz\SmartShipping\Model\Usps\Response\LabelResponseParser;
use Jscriptz\SmartShipping\Model\Usps\Response\TrackingResponseParser;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

class LabelProvider implements LabelProviderInterface
{
    private const CARRIER_CODE = 'uspsv3';

    private const SUPPORTED_FORMATS = ['PDF', 'PNG', 'ZPL'];

    public function __construct(
        private readonly Config $config,
        private readonly LabelClient $labelClient,
        private readonly TrackingClient $trackingClient,
        private readonly LabelRequestBuilder $requestBuilder,
        private readonly LabelResponseParser $responseParser,
        private readonly TrackingResponseParser $trackingResponseParser,
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
                __('USPS label generation is not available. Please check configuration.')
            );
        }

        try {
            // Build the API request
            $requestData = $this->requestBuilder->build($order, $packageData);

            // Get label format
            $labelFormat = $packageData['label_format'] ?? $this->config->getLabelFormat($storeId);

            if ($this->config->isDebugEnabled($storeId)) {
                $this->logger->debug('[USPSv3 LabelProvider] Creating label for order', [
                    'order_id' => $order->getEntityId(),
                    'increment_id' => $order->getIncrementId(),
                    'mail_class' => $requestData['packageDescription']['mailClass'] ?? 'unknown',
                    'format' => $labelFormat,
                ]);
            }

            // Call USPS Labels API
            $response = $this->labelClient->createLabel($requestData, $storeId);

            // Parse response into ShipmentLabel
            $label = $this->responseParser->parse($response, $labelFormat);

            if ($this->config->isDebugEnabled($storeId)) {
                $this->logger->debug('[USPSv3 LabelProvider] Label created successfully', [
                    'tracking_number' => $label->getTrackingNumber(),
                    'postage' => $label->getPostage(),
                ]);
            }

            return $label;

        } catch (LocalizedException $e) {
            $this->logger->error('[USPSv3 LabelProvider] Label creation failed', [
                'order_id' => $order->getEntityId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('[USPSv3 LabelProvider] Unexpected error', [
                'order_id' => $order->getEntityId(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            throw new LocalizedException(
                __('Failed to create USPS label: %1', $e->getMessage()),
                $e
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function getTrackingInfo(string $trackingNumber, ?int $storeId = null): array
    {
        try {
            $response = $this->trackingClient->getTracking($trackingNumber, 'DETAIL', $storeId);
            return $this->trackingResponseParser->parse($response);
        } catch (LocalizedException $e) {
            $this->logger->error('[USPSv3 LabelProvider] Tracking request failed', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * @inheritDoc
     */
    public function voidLabel(string $trackingNumber, ?int $storeId = null): bool
    {
        try {
            return $this->labelClient->voidLabel($trackingNumber, $storeId);
        } catch (LocalizedException $e) {
            $this->logger->error('[USPSv3 LabelProvider] Label void failed', [
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
