<?php
/**
 * Jscriptz SmartShipping - UPS Label Client
 *
 * Creates shipping labels via the UPS Shipping API.
 * Handles shipment creation, label retrieval, and shipment cancellation.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Ups\Client;

use Jscriptz\SmartShipping\Model\Ups\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class LabelClient
{
    private const TIMEOUT = 45; // Longer timeout for label generation

    public function __construct(
        private readonly Config $config,
        private readonly AuthClient $authClient,
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Create shipping label via UPS Ship API
     *
     * @param mixed[] $requestData Built by LabelRequestBuilder
     * @param int|null $storeId
     * @return mixed[] API response
     * @throws LocalizedException
     */
    public function createLabel(array $requestData, ?int $storeId = null): array
    {
        $url = $this->config->getShipUrl($storeId);
        $accessToken = $this->authClient->getAccessToken($storeId);

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[UPSv3 Label] Creating shipment', [
                'url' => $url,
                'service_code' => $requestData['ShipmentRequest']['Shipment']['Service']['Code'] ?? 'unknown',
            ]);
        }

        $this->curl->setHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
            'transId' => uniqid('ups_label_'),
            'transactionSrc' => 'Magento SmartShipping',
        ]);
        $this->curl->setTimeout(self::TIMEOUT);

        try {
            $this->curl->post($url, $this->json->serialize($requestData));
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('UPS Ship API connection failed: %1', $e->getMessage())
            );
        }

        $statusCode = $this->curl->getStatus();
        $responseBody = $this->curl->getBody();

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[UPSv3 Label] API Response', [
                'status' => $statusCode,
                'body' => $this->sanitizeResponseForLog($responseBody),
            ]);
        }

        // Handle authentication errors
        if ($statusCode === 401 || $statusCode === 403) {
            $this->authClient->invalidateToken($storeId);
            throw new LocalizedException(
                __('UPS authentication failed. Please check credentials and retry.')
            );
        }

        if ($statusCode !== 200 && $statusCode !== 201) {
            $error = $this->parseError($responseBody, $statusCode);
            throw new LocalizedException(
                __('UPS label creation failed: %1', $error)
            );
        }

        try {
            $response = $this->json->unserialize($responseBody);
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Invalid UPS Ship API response: %1', $e->getMessage())
            );
        }

        // Check for API-level errors
        if (!empty($response['response']['errors'])) {
            $errorMessages = [];
            foreach ($response['response']['errors'] as $error) {
                $errorMessages[] = ($error['code'] ?? '') . ': ' . ($error['message'] ?? 'Unknown error');
            }
            throw new LocalizedException(
                __('UPS API errors: %1', implode(', ', $errorMessages))
            );
        }

        return $response;
    }

    /**
     * Cancel/void a shipment
     *
     * @param string $shipmentId The shipment identification number
     * @param string $trackingNumber The tracking number
     * @param int|null $storeId
     * @return bool
     * @throws LocalizedException
     */
    public function voidLabel(string $shipmentId, string $trackingNumber, ?int $storeId = null): bool
    {
        // UPS void endpoint uses DELETE method
        $url = $this->config->getCancelShipUrl($storeId) . '/' . $shipmentId;
        $accessToken = $this->authClient->getAccessToken($storeId);

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[UPSv3 Label] Cancelling shipment', [
                'shipment_id' => $shipmentId,
                'tracking_number' => $trackingNumber,
            ]);
        }

        $this->curl->setHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
            'transId' => uniqid('ups_void_'),
            'transactionSrc' => 'Magento SmartShipping',
        ]);
        $this->curl->setTimeout(self::TIMEOUT);
        $this->curl->setOption(CURLOPT_CUSTOMREQUEST, 'DELETE');

        try {
            // For DELETE, we use an empty body
            $this->curl->post($url, '');
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('UPS void shipment failed: %1', $e->getMessage())
            );
        }

        $statusCode = $this->curl->getStatus();
        $responseBody = $this->curl->getBody();

        if ($statusCode === 200 || $statusCode === 204) {
            if ($this->config->isDebugEnabled($storeId)) {
                $this->logger->debug('[UPSv3 Label] Shipment cancelled successfully', [
                    'shipment_id' => $shipmentId,
                ]);
            }
            return true;
        }

        $error = $this->parseError($responseBody, $statusCode);
        throw new LocalizedException(
            __('UPS void shipment failed: %1', $error)
        );
    }

    /**
     * Parse error from response
     */
    private function parseError(string $responseBody, int $statusCode): string
    {
        try {
            $response = $this->json->unserialize($responseBody);

            if (!empty($response['response']['errors']) && is_array($response['response']['errors'])) {
                $messages = [];
                foreach ($response['response']['errors'] as $error) {
                    $code = $error['code'] ?? '';
                    $message = $error['message'] ?? 'Unknown error';
                    $messages[] = $code ? "{$code}: {$message}" : $message;
                }
                return implode(', ', $messages);
            }

            if (!empty($response['message'])) {
                return $response['message'];
            }
        } catch (\Exception $e) {
            // Response is not JSON
        }

        return "HTTP {$statusCode}: " . substr($responseBody, 0, 500);
    }

    /**
     * Sanitize response for logging (truncate label images)
     */
    private function sanitizeResponseForLog(string $responseBody): string
    {
        try {
            $response = $this->json->unserialize($responseBody);

            // Truncate label images
            if (!empty($response['ShipmentResponse']['ShipmentResults']['PackageResults'])) {
                foreach ($response['ShipmentResponse']['ShipmentResults']['PackageResults'] as &$package) {
                    if (!empty($package['ShippingLabel']['GraphicImage'])) {
                        $package['ShippingLabel']['GraphicImage'] = '[BASE64_' . strlen($package['ShippingLabel']['GraphicImage']) . '_bytes]';
                    }
                }
            }

            return $this->json->serialize($response);
        } catch (\Exception $e) {
            return substr($responseBody, 0, 2000) . '... [truncated]';
        }
    }
}
