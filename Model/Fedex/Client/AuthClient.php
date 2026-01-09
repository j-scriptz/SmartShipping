<?php
/**
 * Jscriptz SmartShipping - FedEx OAuth2 Authentication Client
 *
 * Handles FedEx OAuth2 token management with caching.
 * Tokens expire after 3600 seconds (1 hour).
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Fedex\Client;

use Jscriptz\SmartShipping\Model\Fedex\Config;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class AuthClient
{
    private const CACHE_PREFIX = 'jscriptz_fedexv3_token_';
    private const CACHE_TAG = 'JSCRIPTZ_FEDEXV3_AUTH';
    private const TOKEN_BUFFER_SECONDS = 100; // Refresh token 100 seconds before expiry

    public function __construct(
        private readonly Config $config,
        private readonly Curl $curl,
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get OAuth2 access token
     *
     * Returns cached token if valid, otherwise requests new token.
     *
     * @throws \Exception If token request fails
     */
    public function getAccessToken(?int $storeId = null): string
    {
        // Check cache first
        $cacheKey = $this->getCacheKey($storeId);
        $cachedToken = $this->cache->load($cacheKey);

        if ($cachedToken) {
            if ($this->config->isDebugEnabled($storeId)) {
                $this->logger->debug('[FEDEXv3 Auth] Using cached token');
            }
            return $cachedToken;
        }

        // Request new token
        return $this->requestNewToken($storeId);
    }

    /**
     * Request new OAuth2 token from FedEx
     *
     * @throws \Exception If token request fails
     */
    private function requestNewToken(?int $storeId): string
    {
        $url = $this->config->getOAuthTokenUrl($storeId);
        $clientId = $this->config->getClientId($storeId);
        $clientSecret = $this->config->getClientSecret($storeId);

        if (empty($clientId) || empty($clientSecret)) {
            throw new \Exception('FedEx API credentials not configured');
        }

        // Prepare request
        $postData = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[FEDEXv3 Auth] Requesting new token', [
                'url' => $url,
                'environment' => $this->config->isSandbox($storeId) ? 'sandbox' : 'production',
            ]);
        }

        // Make request
        $this->curl->setHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);
        $this->curl->post($url, $postData);

        $statusCode = $this->curl->getStatus();
        $responseBody = $this->curl->getBody();

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[FEDEXv3 Auth] Token response', [
                'status' => $statusCode,
                'body' => $this->sanitizeResponse($responseBody),
            ]);
        }

        if ($statusCode !== 200) {
            $error = $this->parseError($responseBody);
            throw new \Exception('FedEx OAuth error: ' . $error);
        }

        // Parse response
        try {
            $response = $this->json->unserialize($responseBody);
        } catch (\Exception $e) {
            throw new \Exception('Invalid FedEx OAuth response: ' . $e->getMessage());
        }

        if (empty($response['access_token'])) {
            throw new \Exception('FedEx OAuth response missing access_token');
        }

        $accessToken = $response['access_token'];
        $expiresIn = (int) ($response['expires_in'] ?? 3600);

        // Cache token (with buffer to ensure we don't use expired tokens)
        $cacheTtl = max(0, $expiresIn - self::TOKEN_BUFFER_SECONDS);
        $this->cacheToken($accessToken, $storeId, $cacheTtl);

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[FEDEXv3 Auth] New token obtained', [
                'expires_in' => $expiresIn,
                'cache_ttl' => $cacheTtl,
            ]);
        }

        return $accessToken;
    }

    /**
     * Cache the access token
     */
    private function cacheToken(string $token, ?int $storeId, int $ttl): void
    {
        $cacheKey = $this->getCacheKey($storeId);
        $this->cache->save(
            $token,
            $cacheKey,
            [self::CACHE_TAG],
            $ttl
        );
    }

    /**
     * Get cache key for store
     */
    private function getCacheKey(?int $storeId): string
    {
        $environment = $this->config->isSandbox($storeId) ? 'sandbox' : 'production';
        return self::CACHE_PREFIX . $environment . '_' . ($storeId ?? 'default');
    }

    /**
     * Invalidate cached token
     */
    public function invalidateToken(?int $storeId = null): void
    {
        $cacheKey = $this->getCacheKey($storeId);
        $this->cache->remove($cacheKey);

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[FEDEXv3 Auth] Token cache invalidated');
        }
    }

    /**
     * Parse error from response
     */
    private function parseError(string $responseBody): string
    {
        try {
            $response = $this->json->unserialize($responseBody);

            // Check for various error formats
            if (!empty($response['error_description'])) {
                return $response['error_description'];
            }
            if (!empty($response['error'])) {
                return is_string($response['error']) ? $response['error'] : json_encode($response['error']);
            }
            if (!empty($response['errors']) && is_array($response['errors'])) {
                $messages = [];
                foreach ($response['errors'] as $error) {
                    $messages[] = $error['message'] ?? $error['code'] ?? 'Unknown error';
                }
                return implode(', ', $messages);
            }
        } catch (\Exception $e) {
            // Response is not JSON
        }

        return 'Unknown error (HTTP response: ' . substr($responseBody, 0, 200) . ')';
    }

    /**
     * Sanitize response for logging (hide sensitive data)
     */
    private function sanitizeResponse(string $responseBody): string
    {
        try {
            $response = $this->json->unserialize($responseBody);
            if (isset($response['access_token'])) {
                $response['access_token'] = substr($response['access_token'], 0, 20) . '...';
            }
            return $this->json->serialize($response);
        } catch (\Exception $e) {
            return substr($responseBody, 0, 500);
        }
    }
}
