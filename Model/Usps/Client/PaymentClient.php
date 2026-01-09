<?php
/**
 * Jscriptz SmartShipping - USPS Payment Authorization Client
 *
 * Creates payment authorization tokens required for label generation.
 * Tokens are valid for 8 hours and are cached to avoid unnecessary API calls.
 *
 * IMPORTANT: Labels API requires this token in the X-Payment-Authorization-Token header.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Usps\Client;

use Jscriptz\SmartShipping\Model\Usps\Config;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class PaymentClient
{
    private const CACHE_PREFIX = 'smartshipping_usps_payment_token_';
    private const CACHE_TAG = 'SMARTSHIPPING_USPS_PAYMENT';

    // Token is valid for 8 hours, cache for 7.5 hours to ensure refresh before expiry
    private const TOKEN_TTL_SECONDS = 27000;

    public function __construct(
        private readonly Config $config,
        private readonly AuthClient $authClient,
        private readonly Curl $curl,
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get payment authorization token
     *
     * Returns cached token if available, otherwise requests a new one.
     *
     * @param int|null $storeId
     * @return string Payment authorization token
     * @throws LocalizedException
     */
    public function getPaymentAuthToken(?int $storeId = null): string
    {
        $cacheKey = $this->getCacheKey($storeId);
        $cachedToken = $this->cache->load($cacheKey);

        if ($cachedToken) {
            if ($this->config->isDebugEnabled($storeId)) {
                $this->logger->debug('[USPSv3 Payment] Using cached payment token');
            }
            return $cachedToken;
        }

        return $this->requestNewToken($storeId);
    }

    /**
     * Request new payment authorization token from USPS API
     *
     * @param int|null $storeId
     * @return string
     * @throws LocalizedException
     */
    private function requestNewToken(?int $storeId): string
    {
        // Validate required credentials
        if (!$this->config->hasLabelCredentials($storeId)) {
            throw new LocalizedException(
                __('USPS label credentials not configured. CRID, MID, and Payment Account are required.')
            );
        }

        $url = $this->config->getPaymentAuthUrl($storeId);
        $accessToken = $this->authClient->getAccessToken($storeId);

        $payload = [
            'roles' => [
                [
                    'roleName' => 'PAYER',
                    'CRID' => $this->config->getCrid($storeId),
                    'accountType' => $this->config->getPaymentAccountType($storeId),
                    'accountNumber' => $this->config->getPaymentAccountNumber($storeId),
                ],
                [
                    'roleName' => 'LABEL_OWNER',
                    'CRID' => $this->config->getCrid($storeId),
                    'MID' => $this->config->getMid($storeId),
                    'manifestMID' => $this->config->getManifestMid($storeId),
                ],
            ],
        ];

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[USPSv3 Payment] Requesting payment authorization token', [
                'url' => $url,
                'crid' => $this->config->getCrid($storeId),
                'mid' => $this->config->getMid($storeId),
                'account_type' => $this->config->getPaymentAccountType($storeId),
            ]);
        }

        $this->curl->setHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ]);

        try {
            $this->curl->post($url, $this->json->serialize($payload));
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('USPS Payment API connection failed: %1', $e->getMessage())
            );
        }

        $statusCode = $this->curl->getStatus();
        $responseBody = $this->curl->getBody();

        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[USPSv3 Payment] API Response', [
                'status' => $statusCode,
                'body' => substr($responseBody, 0, 500),
            ]);
        }

        if ($statusCode !== 200) {
            $error = $this->parseError($responseBody);
            throw new LocalizedException(
                __('USPS Payment Authorization failed: %1', $error)
            );
        }

        try {
            $response = $this->json->unserialize($responseBody);
        } catch (\Exception $e) {
            throw new LocalizedException(
                __('Invalid USPS Payment API response: %1', $e->getMessage())
            );
        }

        if (empty($response['paymentAuthorizationToken'])) {
            throw new LocalizedException(
                __('USPS Payment API response missing paymentAuthorizationToken')
            );
        }

        $paymentToken = $response['paymentAuthorizationToken'];
        $this->cacheToken($paymentToken, $storeId);

        return $paymentToken;
    }

    /**
     * Cache payment token
     */
    private function cacheToken(string $token, ?int $storeId): void
    {
        $cacheKey = $this->getCacheKey($storeId);
        $this->cache->save($token, $cacheKey, [self::CACHE_TAG], self::TOKEN_TTL_SECONDS);
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
     * Invalidate cached payment token
     */
    public function invalidateToken(?int $storeId = null): void
    {
        $cacheKey = $this->getCacheKey($storeId);
        $this->cache->remove($cacheKey);
    }

    /**
     * Parse error from response body
     */
    private function parseError(string $responseBody): string
    {
        try {
            $response = $this->json->unserialize($responseBody);
            if (!empty($response['error']['message'])) {
                return $response['error']['message'];
            }
            if (!empty($response['message'])) {
                return $response['message'];
            }
            if (!empty($response['error'])) {
                return is_string($response['error'])
                    ? $response['error']
                    : $this->json->serialize($response['error']);
            }
        } catch (\Exception $e) {
            // Fall through to default
        }
        return 'Unknown error (HTTP ' . $this->curl->getStatus() . ')';
    }
}
