<?php
/**
 * Jscriptz SmartShipping - USPS Tracking Client
 *
 * Retrieves tracking information via the USPS Tracking API v3.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Usps\Client;

use Jscriptz\SmartShipping\Model\Usps\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class TrackingClient
{
    public function __construct(
        private readonly Config $config,
        private readonly AuthClient $authClient,
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get tracking information for a tracking number
     *
     * @param string $trackingNumber
     * @param string $expand SUMMARY or DETAIL
     * @param int|null $storeId
     * @return mixed[] Tracking data
     * @throws LocalizedException
     */
    public function getTracking(
        string $trackingNumber,
        string $expand = 'DETAIL',
        ?int $storeId = null
    ): array {
        $url = $this->config->getTrackingUrl($storeId) . '/' . urlencode($trackingNumber);
        $url .= '?expand=' . $expand;

        $accessToken = $this->authClient->getAccessToken($storeId);

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[USPSv3 Tracking] Getting tracking info', [
                'tracking_number' => $trackingNumber,
                'expand' => $expand,
            ]);
        }

        $this->curl->setHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
        ]);

        try {
            $this->curl->get($url);
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('USPS Tracking API connection failed: %1', $e->getMessage())
            );
        }

        $statusCode = $this->curl->getStatus();
        $responseBody = $this->curl->getBody();

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[USPSv3 Tracking] API Response', [
                'status' => $statusCode,
                'body_length' => strlen($responseBody),
            ]);
        }

        if ($statusCode !== 200) {
            $error = $this->parseError($responseBody, $statusCode);

            if ($statusCode === 401) {
                $this->authClient->invalidateToken($storeId);
            }

            throw new LocalizedException(
                __('USPS Tracking request failed: %1', $error)
            );
        }

        try {
            return $this->json->unserialize($responseBody);
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Invalid USPS Tracking API response: %1', $e->getMessage())
            );
        }
    }

    /**
     * Parse error from response
     */
    private function parseError(string $responseBody, int $statusCode): string
    {
        try {
            $response = $this->json->unserialize($responseBody);

            if (!empty($response['error']['message'])) {
                return $response['error']['message'];
            }

            if (!empty($response['message'])) {
                return $response['message'];
            }
        } catch (\Exception $e) {
            // Fall through
        }

        return 'HTTP ' . $statusCode . ' error';
    }
}
