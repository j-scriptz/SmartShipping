<?php
/**
 * Jscriptz SmartShipping - USPS Rate Caching
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Usps\Cache;

use Jscriptz\SmartShipping\Model\Usps\Config;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;
use Psr\Log\LoggerInterface;

class RateCache
{
    private const CACHE_PREFIX = 'smartshipping_usps_rates_';
    private const CACHE_TAG = 'SMARTSHIPPING_USPS_RATES';

    public function __construct(
        private readonly Config $config,
        private readonly CacheInterface $cache,
        private readonly Json $json,
        private readonly LoggerInterface $logger
    ) {
    }

    public function buildCacheKey(RateRequest $request): string
    {
        $keyData = [
            'orig_postcode' => $this->normalizePostcode($request->getOrigPostcode() ?? '', 'US'),
            'dest_country' => $request->getDestCountryId(),
            'dest_postcode' => $this->normalizePostcode($request->getDestPostcode() ?? '', $request->getDestCountryId() ?? 'US'),
            'weight' => round((float)$request->getPackageWeight(), 1),
            'store_id' => $request->getStoreId(),
        ];

        return self::CACHE_PREFIX . hash('sha256', $this->json->serialize($keyData));
    }

    public function load(string $cacheKey, ?int $storeId = null, bool $includeTransitTimes = false): ?array
    {
        $cached = $this->cache->load($cacheKey);
        if (!$cached) {
            return null;
        }

        try {
            $data = $this->json->unserialize($cached);
            $ttl = $this->config->getCacheTtl($storeId);
            $age = time() - ($data['timestamp'] ?? 0);

            if ($age <= $ttl) {
                if ($includeTransitTimes) {
                    return [
                        'rates' => $data['rates'] ?? [],
                        'transit_times' => $data['transit_times'] ?? [],
                    ];
                }
                return $data['rates'] ?? null;
            }
            return null;

        } catch (\Exception $e) {
            return null;
        }
    }

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
            return null;
        }
    }

    public function save(string $cacheKey, array $rates, ?int $storeId = null, array $transitTimes = []): void
    {
        try {
            $data = [
                'timestamp' => time(),
                'rates' => $rates,
                'transit_times' => $transitTimes,
            ];

            $ttl = $this->config->getCacheTtl($storeId) * 2;

            $this->cache->save(
                $this->json->serialize($data),
                $cacheKey,
                [self::CACHE_TAG],
                $ttl
            );

        } catch (\Exception $e) {
            $this->logger->warning('[USPSv3 Cache] Failed to save cache: ' . $e->getMessage());
        }
    }

    public function invalidateAll(): void
    {
        $this->cache->clean([self::CACHE_TAG]);
    }

    private function normalizePostcode(string $postcode, string $countryCode): string
    {
        if ($countryCode === 'US') {
            return substr(preg_replace('/[^0-9]/', '', $postcode) ?? '', 0, 5);
        }
        if ($countryCode === 'CA') {
            return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $postcode) ?? '');
        }
        return $postcode;
    }

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
