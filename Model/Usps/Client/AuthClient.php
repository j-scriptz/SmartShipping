<?php
/**
 * Jscriptz SmartShipping - USPS OAuth2 Authentication Client
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Usps\Client;

use Jscriptz\SmartShipping\Model\Usps\Config;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class AuthClient
{
    private const CACHE_PREFIX = 'smartshipping_usps_token_';
    private const CACHE_TAG = 'SMARTSHIPPING_USPS_AUTH';
    private const TOKEN_BUFFER_SECONDS = 100;

    public function __construct(
        private readonly Config $config,
        private readonly Curl $curl,
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getAccessToken(?int $storeId = null): string
    {
        $cacheKey = $this->getCacheKey($storeId);
        $cachedToken = $this->cache->load($cacheKey);

        if ($cachedToken) {
            if ($this->config->isDebugEnabled($storeId)) {
                $this->logger->debug('[USPSv3 Auth] Using cached token');
            }
            return $cachedToken;
        }

        return $this->requestNewToken($storeId);
    }

    private function requestNewToken(?int $storeId): string
    {
        $url = $this->config->getOAuthTokenUrl($storeId);
        $consumerKey = $this->config->getConsumerKey($storeId);
        $consumerSecret = $this->config->getConsumerSecret($storeId);

        if (empty($consumerKey) || empty($consumerSecret)) {
            throw new \Exception('USPS API credentials not configured');
        }

        $postData = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $consumerKey,
            'client_secret' => $consumerSecret,
        ]);

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[USPSv3 Auth] Requesting new token', [
                'url' => $url,
                'environment' => $this->config->isSandbox($storeId) ? 'sandbox' : 'production',
            ]);
        }

        $this->curl->setHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);
        $this->curl->post($url, $postData);

        $statusCode = $this->curl->getStatus();
        $responseBody = $this->curl->getBody();

        if ($statusCode !== 200) {
            $error = $this->parseError($responseBody);
            throw new \Exception('USPS OAuth error: ' . $error);
        }

        try {
            $response = $this->json->unserialize($responseBody);
        } catch (\Exception $e) {
            throw new \Exception('Invalid USPS OAuth response: ' . $e->getMessage());
        }

        if (empty($response['access_token'])) {
            throw new \Exception('USPS OAuth response missing access_token');
        }

        $accessToken = $response['access_token'];
        $expiresIn = (int) ($response['expires_in'] ?? 3600);

        $cacheTtl = max(0, $expiresIn - self::TOKEN_BUFFER_SECONDS);
        $this->cacheToken($accessToken, $storeId, $cacheTtl);

        return $accessToken;
    }

    private function cacheToken(string $token, ?int $storeId, int $ttl): void
    {
        $cacheKey = $this->getCacheKey($storeId);
        $this->cache->save($token, $cacheKey, [self::CACHE_TAG], $ttl);
    }

    private function getCacheKey(?int $storeId): string
    {
        $environment = $this->config->isSandbox($storeId) ? 'sandbox' : 'production';
        return self::CACHE_PREFIX . $environment . '_' . ($storeId ?? 'default');
    }

    public function invalidateToken(?int $storeId = null): void
    {
        $cacheKey = $this->getCacheKey($storeId);
        $this->cache->remove($cacheKey);
    }

    private function parseError(string $responseBody): string
    {
        try {
            $response = $this->json->unserialize($responseBody);
            if (!empty($response['error_description'])) {
                return $response['error_description'];
            }
            if (!empty($response['error'])) {
                return is_string($response['error']) ? $response['error'] : json_encode($response['error']);
            }
        } catch (\Exception $e) {
        }
        return 'Unknown error';
    }
}
