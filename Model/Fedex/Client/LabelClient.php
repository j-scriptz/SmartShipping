<?php
/**
 * Jscriptz SmartShipping - FedEx Label Client
 *
 * Creates shipping labels via the FedEx Ship API v1.
 * Handles shipment creation, label retrieval, and shipment cancellation.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Fedex\Client;

use Jscriptz\SmartShipping\Model\Fedex\Config;
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
     * Create shipping label via FedEx Ship API
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
            $this->logger->debug('[FEDEXv3 Label] Creating shipment', [
                'url' => $url,
                'service_type' => $requestData['requestedShipment']['serviceType'] ?? 'unknown',
            ]);
        }

        $this->curl->setHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
            'X-locale' => 'en_US',
        ]);
        $this->curl->setTimeout(self::TIMEOUT);

        try {
            $this->curl->post($url, $this->json->serialize($requestData));
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('FedEx Ship API connection failed: %1', $e->getMessage())
            );
        }

        $statusCode = $this->curl->getStatus();
        $responseBody = $this->curl->getBody();

        // Decompress if needed
        $responseBody = $this->decompressResponse($responseBody);

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[FEDEXv3 Label] API Response', [
                'status' => $statusCode,
                'body' => $this->sanitizeResponseForLog($responseBody),
            ]);
        }

        // Handle authentication errors
        if ($statusCode === 401 || $statusCode === 403) {
            $this->authClient->invalidateToken($storeId);
            throw new LocalizedException(
                __('FedEx authentication failed. Please check credentials and retry.')
            );
        }

        if ($statusCode !== 200 && $statusCode !== 201) {
            $error = $this->parseError($responseBody, $statusCode);
            throw new LocalizedException(
                __('FedEx label creation failed: %1', $error)
            );
        }

        try {
            $response = $this->json->unserialize($responseBody);
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Invalid FedEx Ship API response: %1', $e->getMessage())
            );
        }

        // Check for API-level errors
        if (!empty($response['errors'])) {
            $errorMessages = [];
            foreach ($response['errors'] as $error) {
                $errorMessages[] = ($error['code'] ?? '') . ': ' . ($error['message'] ?? 'Unknown error');
            }
            throw new LocalizedException(
                __('FedEx API errors: %1', implode(', ', $errorMessages))
            );
        }

        return $response;
    }

    /**
     * Cancel/void a shipment
     *
     * @param string $trackingNumber
     * @param int|null $storeId
     * @return bool
     * @throws LocalizedException
     */
    public function voidLabel(string $trackingNumber, ?int $storeId = null): bool
    {
        $url = $this->config->getCancelShipUrl($storeId);
        $accessToken = $this->authClient->getAccessToken($storeId);

        $requestData = [
            'accountNumber' => [
                'value' => $this->config->getAccountNumber($storeId),
            ],
            'trackingNumber' => $trackingNumber,
        ];

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[FEDEXv3 Label] Cancelling shipment', [
                'tracking_number' => $trackingNumber,
            ]);
        }

        $this->curl->setHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
            'X-locale' => 'en_US',
        ]);
        $this->curl->setTimeout(self::TIMEOUT);

        // FedEx uses PUT for cancel
        $this->curl->setOption(CURLOPT_CUSTOMREQUEST, 'PUT');

        try {
            $this->curl->post($url, $this->json->serialize($requestData));
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('FedEx cancel shipment failed: %1', $e->getMessage())
            );
        }

        $statusCode = $this->curl->getStatus();
        $responseBody = $this->curl->getBody();

        // Decompress if needed
        $responseBody = $this->decompressResponse($responseBody);

        if ($statusCode === 200 || $statusCode === 204) {
            if ($this->config->isDebugEnabled($storeId)) {
                $this->logger->debug('[FEDEXv3 Label] Shipment cancelled successfully', [
                    'tracking_number' => $trackingNumber,
                ]);
            }
            return true;
        }

        $error = $this->parseError($responseBody, $statusCode);
        throw new LocalizedException(
            __('FedEx cancel shipment failed: %1', $error)
        );
    }

    /**
     * Parse error from response
     */
    private function parseError(string $responseBody, int $statusCode): string
    {
        try {
            $response = $this->json->unserialize($responseBody);

            if (!empty($response['errors']) && is_array($response['errors'])) {
                $messages = [];
                foreach ($response['errors'] as $error) {
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
     * Decompress gzip/deflate response if needed
     */
    private function decompressResponse(string $response): string
    {
        if (strlen($response) >= 2 && ord($response[0]) === 0x1f && ord($response[1]) === 0x8b) {
            $decompressed = @gzdecode($response);
            if ($decompressed !== false) {
                return $decompressed;
            }
        }

        if (strlen($response) >= 2 && ord($response[0]) === 0x78) {
            $decompressed = @gzuncompress($response);
            if ($decompressed !== false) {
                return $decompressed;
            }
            $decompressed = @gzinflate($response);
            if ($decompressed !== false) {
                return $decompressed;
            }
        }

        return $response;
    }

    /**
     * Sanitize response for logging (truncate label images)
     */
    private function sanitizeResponseForLog(string $responseBody): string
    {
        try {
            $response = $this->json->unserialize($responseBody);

            // Truncate label images
            if (!empty($response['output']['transactionShipments'])) {
                foreach ($response['output']['transactionShipments'] as &$shipment) {
                    if (!empty($shipment['pieceResponses'])) {
                        foreach ($shipment['pieceResponses'] as &$piece) {
                            if (!empty($piece['packageDocuments'])) {
                                foreach ($piece['packageDocuments'] as &$doc) {
                                    if (!empty($doc['encodedLabel'])) {
                                        $doc['encodedLabel'] = '[BASE64_' . strlen($doc['encodedLabel']) . '_bytes]';
                                    }
                                }
                            }
                        }
                    }
                }
            }

            return $this->json->serialize($response);
        } catch (\Exception $e) {
            return substr($responseBody, 0, 2000) . '... [truncated]';
        }
    }
}
