<?php
/**
 * Jscriptz SmartShipping - UPS Rating API Client
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Ups\Client;

use Jscriptz\SmartShipping\Model\Ups\Config;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class RatingClient
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
     * Get shipping rates from UPS API
     *
     * @param array $requestData Request payload from RateRequestBuilder
     * @param int|null $storeId
     * @return array API response
     * @throws \Exception
     */
    public function getRates(array $requestData, ?int $storeId = null): array
    {
        $token = $this->authClient->getAccessToken($storeId);
        $url = $this->config->getRatingUrl($storeId);

        // Set request headers
        $this->curl->setHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'transId' => 'mg_' . uniqid(),
            'transactionSrc' => 'Magento',
        ]);

        $this->curl->setOption(CURLOPT_TIMEOUT, 30);
        $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 10);

        $jsonPayload = $this->json->serialize($requestData);

        // Debug logging
        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[UPSv3 Rating] Request', [
                'url' => $url,
                'payload' => $requestData,
            ]);
        }

        try {
            $this->curl->post($url, $jsonPayload);

            $status = $this->curl->getStatus();
            $body = $this->curl->getBody();

            if ($this->config->isDebugEnabled($storeId)) {
                $this->logger->debug('[UPSv3 Rating] Response', [
                    'status' => $status,
                    'body' => $body,
                ]);
            }

            // Handle auth errors - retry once with fresh token
            if ($status === 401) {
                $this->logger->warning('[UPSv3 Rating] Token expired, refreshing...');
                $this->authClient->invalidateToken($storeId);
                return $this->getRates($requestData, $storeId);
            }

            if ($status !== 200) {
                $errorBody = $this->json->unserialize($body);
                $errorMessage = $this->extractErrorMessage($errorBody);
                $this->logger->error('[UPSv3 Rating] API Error', [
                    'status' => $status,
                    'error' => $errorMessage,
                    'body' => $body,
                ]);
                throw new \Exception('UPS Rating API error: ' . $errorMessage);
            }

            return $this->json->unserialize($body);

        } catch (\Exception $e) {
            $this->logger->error('[UPSv3 Rating] Exception: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extract error message from API response
     */
    private function extractErrorMessage(array $response): string
    {
        // Check for standard UPS error response format
        if (isset($response['response']['errors'][0]['message'])) {
            return $response['response']['errors'][0]['message'];
        }

        if (isset($response['Fault']['detail']['Errors']['ErrorDetail']['PrimaryErrorCode']['Description'])) {
            return $response['Fault']['detail']['Errors']['ErrorDetail']['PrimaryErrorCode']['Description'];
        }

        if (isset($response['RateResponse']['Response']['ResponseStatus']['Description'])) {
            return $response['RateResponse']['Response']['ResponseStatus']['Description'];
        }

        return 'Unknown error';
    }
}
