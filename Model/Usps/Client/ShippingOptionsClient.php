<?php
/**
 * Jscriptz SmartShipping - USPS Shipping Options API Client
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Usps\Client;

use Jscriptz\SmartShipping\Model\Usps\Config;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class ShippingOptionsClient
{
    private const TIMEOUT = 30;

    public function __construct(
        private readonly Config $config,
        private readonly AuthClient $authClient,
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getShippingOptions(array $requestData, ?int $storeId = null): array
    {
        $url = $this->config->getShippingOptionsUrl($storeId);
        $accessToken = $this->authClient->getAccessToken($storeId);

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[USPSv3 Shipping] Requesting shipping options', [
                'url' => $url,
                'request' => $requestData,
            ]);
        }

        $this->curl->setHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept' => 'application/json',
        ]);
        $this->curl->setTimeout(self::TIMEOUT);

        $jsonBody = $this->json->serialize($requestData);
        $this->curl->post($url, $jsonBody);

        $statusCode = $this->curl->getStatus();
        $responseBody = $this->curl->getBody();

        if ($statusCode === 401) {
            $this->authClient->invalidateToken($storeId);
            throw new \Exception('USPS authentication failed - token invalidated, please retry');
        }

        if ($statusCode !== 200) {
            $error = $this->parseError($responseBody, $statusCode);
            throw new \Exception('USPS Shipping Options API error: ' . $error);
        }

        try {
            $response = $this->json->unserialize($responseBody);
        } catch (\Exception $e) {
            throw new \Exception('Invalid USPS Shipping Options API response: ' . $e->getMessage());
        }

        if (!empty($response['error'])) {
            $errorMsg = is_array($response['error'])
                ? ($response['error']['message'] ?? json_encode($response['error']))
                : $response['error'];
            throw new \Exception('USPS API error: ' . $errorMsg);
        }

        return $response;
    }

    private function parseError(string $responseBody, int $statusCode): string
    {
        try {
            $response = $this->json->unserialize($responseBody);
            if (!empty($response['error']['message'])) {
                return $response['error']['message'];
            }
            if (!empty($response['errors']) && is_array($response['errors'])) {
                $messages = [];
                foreach ($response['errors'] as $error) {
                    $messages[] = ($error['code'] ?? '') . ': ' . ($error['message'] ?? 'Unknown error');
                }
                return implode(', ', $messages);
            }
        } catch (\Exception $e) {
        }
        return "HTTP {$statusCode}";
    }
}
