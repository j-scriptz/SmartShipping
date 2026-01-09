<?php
/**
 * Jscriptz SmartShipping - UPS OAuth2 Authentication Client
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Ups\Client;

use Jscriptz\SmartShipping\Model\Ups\Config;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class AuthClient
{
    private const TOKEN_CACHE_KEY = 'jscriptz_upsv3_oauth_token';
    private const TOKEN_CACHE_TAG = 'JSCRIPTZ_UPSV3_AUTH';
    private const TOKEN_CACHE_TTL = 14000; // ~4 hours (tokens valid ~4hrs, refresh early)

    public function __construct(
        private readonly Config $config,
        private readonly CacheInterface $cache,
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get OAuth2 access token (with caching)
     *
     * @throws \Exception
     */
    public function getAccessToken(?int $storeId = null): string
    {
        // Build store-specific cache key
        $cacheKey = self::TOKEN_CACHE_KEY . '_' . ($storeId ?? 'default');

        // Check cache first
        $cached = $this->cache->load($cacheKey);
        if ($cached) {
            if ($this->config->isDebugEnabled($storeId)) {
                $this->logger->debug('[UPSv3 Auth] Using cached OAuth token');
            }
            return $cached;
        }

        // Request new token
        $token = $this->requestNewToken($storeId);

        // Cache the token
        $this->cache->save(
            $token,
            $cacheKey,
            [self::TOKEN_CACHE_TAG],
            self::TOKEN_CACHE_TTL
        );

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[UPSv3 Auth] Obtained and cached new OAuth token');
        }

        return $token;
    }

    /**
     * Request new OAuth token from UPS API
     *
     * @throws \Exception
     */
    private function requestNewToken(?int $storeId): string
    {
        $clientId = $this->config->getClientId($storeId);
        $clientSecret = $this->config->getClientSecret($storeId);
        $tokenUrl = $this->config->getOAuthTokenUrl($storeId);

        if (empty($clientId) || empty($clientSecret)) {
            throw new \Exception('UPS API credentials not configured');
        }

        // Prepare Basic Auth header
        $authHeader = 'Basic ' . base64_encode($clientId . ':' . $clientSecret);

        // Set curl options
        $this->curl->setHeaders([
            'Authorization' => $authHeader,
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
        ]);

        $this->curl->setOption(CURLOPT_TIMEOUT, 30);
        $this->curl->setOption(CURLOPT_CONNECTTIMEOUT, 10);

        // Log request in debug mode
        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[UPSv3 Auth] Requesting OAuth token', [
                'url' => $tokenUrl,
                'environment' => $this->config->getEnvironment($storeId),
            ]);
        }

        try {
            // Make POST request
            $this->curl->post($tokenUrl, 'grant_type=client_credentials');

            $status = $this->curl->getStatus();
            $body = $this->curl->getBody();

            if ($this->config->isDebugEnabled($storeId)) {
                $this->logger->debug('[UPSv3 Auth] OAuth response', [
                    'status' => $status,
                    'body_length' => strlen($body),
                ]);
            }

            if ($status !== 200) {
                $this->logger->error('[UPSv3 Auth] OAuth token request failed', [
                    'status' => $status,
                    'body' => $body,
                ]);
                throw new \Exception('OAuth token request failed with status ' . $status);
            }

            $response = $this->json->unserialize($body);

            if (!isset($response['access_token'])) {
                $this->logger->error('[UPSv3 Auth] Invalid OAuth response', [
                    'response' => $response,
                ]);
                throw new \Exception('Invalid OAuth response - no access_token');
            }

            return $response['access_token'];

        } catch (\Exception $e) {
            $this->logger->error('[UPSv3 Auth] Exception during OAuth request: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Invalidate cached token (useful for retry logic)
     */
    public function invalidateToken(?int $storeId = null): void
    {
        $cacheKey = self::TOKEN_CACHE_KEY . '_' . ($storeId ?? 'default');
        $this->cache->remove($cacheKey);

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[UPSv3 Auth] Invalidated cached OAuth token');
        }
    }

    /**
     * Test API credentials
     */
    public function testCredentials(?int $storeId = null): array
    {
        try {
            // Force new token request (don't use cache)
            $this->invalidateToken($storeId);
            $token = $this->getAccessToken($storeId);

            return [
                'success' => true,
                'message' => 'Successfully authenticated with UPS API',
                'environment' => $this->config->getEnvironment($storeId),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Authentication failed: ' . $e->getMessage(),
                'environment' => $this->config->getEnvironment($storeId),
            ];
        }
    }
}
