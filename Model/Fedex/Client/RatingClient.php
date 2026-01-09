<?php
/**
 * Jscriptz SmartShipping - FedEx Rating API Client
 *
 * Makes requests to the FedEx Rate API.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Fedex\Client;

use Jscriptz\SmartShipping\Model\Fedex\Config;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class RatingClient
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

    /**
     * Get shipping rates from FedEx API
     *
     * @param array $requestData Request payload built by RateRequestBuilder
     * @param int|null $storeId
     * @return array API response data
     * @throws \Exception If API request fails
     */
    public function getRates(array $requestData, ?int $storeId = null): array
    {
        $url = $this->config->getRateUrl($storeId);
        $accessToken = $this->authClient->getAccessToken($storeId);

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[FEDEXv3 Rating] Requesting rates', [
                'url' => $url,
                'request' => $this->sanitizeRequest($requestData),
            ]);
        }

        // Prepare request
        $this->curl->setHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
            'X-locale' => 'en_US',
            'Accept-Encoding' => 'gzip, deflate',
        ]);
        $this->curl->setTimeout(self::TIMEOUT);

        $jsonBody = $this->json->serialize($requestData);
        $this->curl->post($url, $jsonBody);

        $statusCode = $this->curl->getStatus();
        $responseBody = $this->curl->getBody();

        // Decompress gzip response if needed
        $responseBody = $this->decompressResponse($responseBody);

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[FEDEXv3 Rating] API response', [
                'status' => $statusCode,
                'body' => $this->truncateForLog($responseBody),
            ]);
        }

        // Handle authentication errors - token may have expired
        if ($statusCode === 401) {
            $this->authClient->invalidateToken($storeId);
            throw new \Exception('FedEx authentication failed - token invalidated, please retry');
        }

        if ($statusCode !== 200) {
            $error = $this->parseError($responseBody, $statusCode);
            throw new \Exception('FedEx Rate API error: ' . $error);
        }

        // Parse response
        try {
            $response = $this->json->unserialize($responseBody);
        } catch (\Exception $e) {
            throw new \Exception('Invalid FedEx Rate API response: ' . $e->getMessage());
        }

        // Check for API-level errors
        if (!empty($response['errors'])) {
            $errorMessages = [];
            foreach ($response['errors'] as $error) {
                $errorMessages[] = ($error['code'] ?? '') . ': ' . ($error['message'] ?? 'Unknown error');
            }
            throw new \Exception('FedEx API errors: ' . implode(', ', $errorMessages));
        }

        return $response;
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
     * Sanitize request for logging (hide sensitive data)
     */
    private function sanitizeRequest(array $request): array
    {
        $sanitized = $request;

        // Hide account number
        if (isset($sanitized['accountNumber']['value'])) {
            $value = $sanitized['accountNumber']['value'];
            $sanitized['accountNumber']['value'] = substr($value, 0, 3) . '***' . substr($value, -3);
        }

        return $sanitized;
    }

    /**
     * Decompress gzip/deflate response if needed
     */
    private function decompressResponse(string $response): string
    {
        // Check for gzip magic bytes (1f 8b)
        if (strlen($response) >= 2 && ord($response[0]) === 0x1f && ord($response[1]) === 0x8b) {
            $decompressed = @gzdecode($response);
            if ($decompressed !== false) {
                return $decompressed;
            }
        }

        // Check for deflate (zlib) format
        if (strlen($response) >= 2 && ord($response[0]) === 0x78) {
            $decompressed = @gzuncompress($response);
            if ($decompressed !== false) {
                return $decompressed;
            }
            // Try inflate for raw deflate
            $decompressed = @gzinflate($response);
            if ($decompressed !== false) {
                return $decompressed;
            }
        }

        return $response;
    }

    /**
     * Truncate response for logging
     */
    private function truncateForLog(string $response): string
    {
        if (strlen($response) > 2000) {
            return substr($response, 0, 2000) . '... [truncated]';
        }
        return $response;
    }
}
