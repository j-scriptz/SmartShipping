<?php
/**
 * Jscriptz SmartShipping - UPS Time in Transit API Client
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Ups\Client;

use Jscriptz\SmartShipping\Model\Ups\Config;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class TransitClient
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
     * Get time in transit data from UPS API
     *
     * @param array $requestData Request payload from TransitRequestBuilder
     * @param int|null $storeId
     * @param bool $isRetry Flag to prevent infinite recursion
     * @return array API response with transit times by service
     */
    public function getTransitTimes(array $requestData, ?int $storeId = null, bool $isRetry = false): array
    {
        if (!$this->config->isTransitEnabled($storeId)) {
            return [];
        }

        $token = $this->authClient->getAccessToken($storeId);
        $url = $this->config->getTimeInTransitUrl($storeId);

        // Set request headers
        $this->curl->setHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'transId' => 'mg_transit_' . uniqid(),
            'transactionSrc' => 'Magento',
        ]);

        $this->curl->setOption(CURLOPT_TIMEOUT, 30);
        $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 10);

        $jsonPayload = $this->json->serialize($requestData);

        // Debug logging
        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[UPSv3 Transit] Request', [
                'url' => $url,
                'payload' => $requestData,
            ]);
        }

        try {
            $this->curl->post($url, $jsonPayload);

            $status = $this->curl->getStatus();
            $body = $this->curl->getBody();

            if ($this->config->isDebugEnabled($storeId)) {
                $this->logger->debug('[UPSv3 Transit] Response', [
                    'status' => $status,
                    'body' => $body,
                ]);
            }

            // Handle auth errors - retry once with fresh token (but only once!)
            if ($status === 401 && !$isRetry) {
                $this->logger->warning('[UPSv3 Transit] Token expired, refreshing...');
                $this->authClient->invalidateToken($storeId);
                return $this->getTransitTimes($requestData, $storeId, true);
            }

            if ($status !== 200) {
                $this->logger->warning('[UPSv3 Transit] API returned non-200 status', [
                    'status' => $status,
                    'body' => $body,
                ]);
                // Return empty array on error - transit times are optional
                return [];
            }

            $response = $this->json->unserialize($body);
            return $this->parseTransitResponse($response);

        } catch (\Exception $e) {
            $this->logger->warning('[UPSv3 Transit] Exception: ' . $e->getMessage());
            // Return empty array on error - transit times are optional
            return [];
        }
    }

    /**
     * Parse transit response into service code => delivery date mapping
     */
    private function parseTransitResponse(array $response): array
    {
        $transitTimes = [];

        // Check for valid response structure
        $services = $response['emsResponse']['services'] ?? [];

        foreach ($services as $service) {
            // Transit API uses 'serviceLevel' not 'serviceCode'
            $serviceLevel = $service['serviceLevel'] ?? null;
            if (!$serviceLevel) {
                continue;
            }

            // Map UPS service codes (they use different codes in transit API)
            $ratingApiCode = $this->mapServiceCode($serviceLevel);
            if (!$ratingApiCode) {
                continue;
            }

            $transitTimes[$ratingApiCode] = [
                'business_days' => (int) ($service['businessTransitDays'] ?? 0),
                'delivery_date' => $service['deliveryDate'] ?? null,
                'delivery_time' => $service['deliveryTime'] ?? null,
                'delivery_day_of_week' => $service['deliveryDayOfWeek'] ?? null,
                'guaranteed' => ($service['guaranteeIndicator'] ?? '0') === '1',
            ];
        }

        return $transitTimes;
    }

    /**
     * Map UPS Transit API service codes to Rating API service codes
     * Transit API uses different codes than Rating API
     */
    private function mapServiceCode(string $transitCode): ?string
    {
        // Service code mapping (Transit API code => Rating API code)
        $mapping = [
            'GND' => '03',      // Ground
            '3DS' => '12',      // 3 Day Select
            '2DA' => '02',      // 2nd Day Air
            '2DM' => '59',      // 2nd Day Air A.M.
            '1DA' => '01',      // Next Day Air
            '1DM' => '14',      // Next Day Air Early
            '1DP' => '13',      // Next Day Air Saver
            'STD' => '11',      // Standard (International)
            'XPR' => '07',      // Worldwide Express
            'XDM' => '54',      // Worldwide Express Plus
            'XPD' => '08',      // Worldwide Expedited
            'WXS' => '65',      // Saver
        ];

        return $mapping[$transitCode] ?? null;
    }
}
