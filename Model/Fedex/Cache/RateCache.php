<?php
/**
 * Jscriptz SmartShipping - FedEx Rate Caching with Fallback
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Fedex\Cache;

use Jscriptz\SmartShipping\Model\Fedex\Config;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;
use Psr\Log\LoggerInterface;

class RateCache
{
    private const CACHE_PREFIX = 'jscriptz_fedexv3_rates_';
    private const CACHE_TAG = 'JSCRIPTZ_FEDEXV3_RATES';

    public function __construct(
        private readonly Config $config,
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Build cache key from rate request
     */
    public function buildCacheKey(RateRequest $request): string
    {
        $keyData = [
            'orig_country' => $request->getOrigCountry(),
            'orig_postcode' => $request->getOrigPostcode(),
            'dest_country' => $request->getDestCountryId(),
            'dest_postcode' => $this->normalizePostcode(
                $request->getDestPostcode() ?? '',
                $request->getDestCountryId() ?? 'US'
            ),
            'dest_region' => $request->getDestRegionCode(),
            'weight' => round((float)$request->getPackageWeight(), 1),
            'store_id' => $request->getStoreId(),
            'residential' => empty($request->getDestCompany()) ? '1' : '0',
        ];

        return self::CACHE_PREFIX . hash('sha256', $this->json->serialize($keyData));
    }

    /**
     * Load cached rates (and optionally transit times)
     *
     * @param string $cacheKey
     * @param int|null $storeId
     * @param bool $includeTransitTimes If true, returns ['rates' => [...], 'transit_times' => [...]]
     * @return array|null Returns rates array, or full data array if $includeTransitTimes is true
     */
    public function load(string $cacheKey, ?int $storeId = null, bool $includeTransitTimes = false): ?array
    {
        $cached = $this->cache->load($cacheKey);
        if (!$cached) {
            return null;
        }

        try {
            $data = $this->json->unserialize($cached);

            // Check if cache is still fresh
            $ttl = $this->config->getCacheTtl($storeId);
            $age = time() - ($data['timestamp'] ?? 0);

            if ($age <= $ttl) {
                if ($this->config->isDebugEnabled($storeId)) {
                    $this->logger->debug('[FEDEXv3 Cache] Cache hit (age: ' . $age . 's)');
                }

                if ($includeTransitTimes) {
                    return [
                        'rates' => $data['rates'] ?? [],
                        'transit_times' => $data['transit_times'] ?? [],
                    ];
                }
                return $data['rates'] ?? null;
            }

            // Cache is stale but still available for fallback
            if ($this->config->isDebugEnabled($storeId)) {
                $this->logger->debug('[FEDEXv3 Cache] Cache expired (age: ' . $age . 's), available for fallback');
            }
            return null;

        } catch (\Exception $e) {
            $this->logger->warning('[FEDEXv3 Cache] Failed to load cache: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Load expired cache for fallback (used when API fails)
     *
     * @param string $cacheKey
     * @param int|null $storeId
     * @param bool $includeTransitTimes If true, returns ['rates' => [...], 'transit_times' => [...]]
     * @return array|null
     */
    public function loadExpired(string $cacheKey, ?int $storeId = null, bool $includeTransitTimes = false): ?array
    {
        $cached = $this->cache->load($cacheKey);
        if (!$cached) {
            return null;
        }

        try {
            $data = $this->json->unserialize($cached);
            $rates = $data['rates'] ?? null;

            if ($rates) {
                $age = time() - ($data['timestamp'] ?? 0);
                if ($this->config->isDebugEnabled($storeId)) {
                    $this->logger->info('[FEDEXv3 Cache] Using expired cache for fallback (age: ' . $age . 's)');
                }

                // Mark rates as cached in method title
                foreach ($rates as &$rate) {
                    if (isset($rate['method_title']) && strpos($rate['method_title'], '(cached)') === false) {
                        $rate['method_title'] .= ' (cached)';
                    }
                }
            }

            if ($includeTransitTimes) {
                return [
                    'rates' => $rates,
                    'transit_times' => $data['transit_times'] ?? [],
                ];
            }

            return $rates;

        } catch (\Exception $e) {
            $this->logger->warning('[FEDEXv3 Cache] Failed to load expired cache: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Save rates and transit times to cache
     *
     * @param string $cacheKey
     * @param array $rates
     * @param int|null $storeId
     * @param array $transitTimes Optional transit times to cache alongside rates
     */
    public function save(string $cacheKey, array $rates, ?int $storeId = null, array $transitTimes = []): void
    {
        try {
            $data = [
                'timestamp' => time(),
                'rates' => $rates,
                'transit_times' => $transitTimes,
            ];

            // Cache for 2x TTL to allow fallback usage
            $ttl = $this->config->getCacheTtl($storeId) * 2;

            $this->cache->save(
                $this->json->serialize($data),
                $cacheKey,
                [self::CACHE_TAG],
                $ttl
            );

            if ($this->config->isDebugEnabled($storeId)) {
                $transitCount = count($transitTimes);
                $this->logger->debug("[FEDEXv3 Cache] Cached " . count($rates) . " rates, {$transitCount} transit times (TTL: {$ttl}s)");
            }

        } catch (\Exception $e) {
            $this->logger->warning('[FEDEXv3 Cache] Failed to save cache: ' . $e->getMessage());
        }
    }

    /**
     * Invalidate all cached rates
     */
    public function invalidateAll(): void
    {
        $this->cache->clean([self::CACHE_TAG]);
        $this->logger->info('[FEDEXv3 Cache] Invalidated all cached rates');
    }

    /**
     * Normalize postal code for consistent cache keys
     */
    private function normalizePostcode(string $postcode, string $countryCode): string
    {
        // US: use only first 5 digits
        if ($countryCode === 'US') {
            return substr(preg_replace('/[^0-9]/', '', $postcode), 0, 5);
        }

        // Canada: normalize format (e.g., "A1A 1A1" -> "A1A1A1")
        if ($countryCode === 'CA') {
            return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $postcode));
        }

        return $postcode;
    }

    /**
     * Convert Result object to cacheable array
     */
    public function resultToArray(Result $result): array
    {
        $rates = [];
        foreach ($result->getAllRates() as $rate) {
            $rates[] = [
                'carrier' => $rate->getCarrier(),
                'carrier_title' => $rate->getCarrierTitle(),
                'method' => $rate->getMethod(),
                'method_title' => $rate->getMethodTitle(),
                'price' => $rate->getPrice(),
                'cost' => $rate->getCost(),
            ];
        }
        return $rates;
    }
}
