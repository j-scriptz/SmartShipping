<?php
/**
 * Jscriptz SmartShipping - USPS Configuration Reader
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Usps;

use Jscriptz\SmartShipping\Model\Usps\Config\Source\Environment;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_PREFIX = 'carriers/uspsv3/';

    private const SANDBOX_BASE_URL = 'https://apis-tem.usps.com';
    private const PRODUCTION_BASE_URL = 'https://apis.usps.com';

    private const OAUTH_TOKEN_PATH = '/oauth2/v3/token';
    private const SHIPMENT_OPTIONS_PATH = '/shipments/v3/options/search';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    private function getValue(string $path, ?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    private function getFlag(string $path, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isActive(?int $storeId = null): bool
    {
        return $this->getFlag('active', $storeId);
    }

    public function getTitle(?int $storeId = null): string
    {
        return $this->getValue('title', $storeId) ?? 'USPS';
    }

    public function getEnvironment(?int $storeId = null): string
    {
        return $this->getValue('environment', $storeId) ?? Environment::SANDBOX;
    }

    public function isSandbox(?int $storeId = null): bool
    {
        return $this->getEnvironment($storeId) === Environment::SANDBOX;
    }

    public function getBaseUrl(?int $storeId = null): string
    {
        return $this->isSandbox($storeId) ? self::SANDBOX_BASE_URL : self::PRODUCTION_BASE_URL;
    }

    public function getOAuthTokenUrl(?int $storeId = null): string
    {
        return $this->getBaseUrl($storeId) . self::OAUTH_TOKEN_PATH;
    }

    public function getShippingOptionsUrl(?int $storeId = null): string
    {
        return $this->getBaseUrl($storeId) . self::SHIPMENT_OPTIONS_PATH;
    }

    public function getConsumerKey(?int $storeId = null): string
    {
        return $this->getValue('consumer_key', $storeId) ?? '';
    }

    public function getConsumerSecret(?int $storeId = null): string
    {
        return $this->getValue('consumer_secret', $storeId) ?? '';
    }

    public function getAllowedMethods(?int $storeId = null): array
    {
        $methods = $this->getValue('allowed_methods', $storeId);
        if (empty($methods)) {
            return [];
        }
        return is_array($methods) ? $methods : explode(',', $methods);
    }

    public function getPriceType(?int $storeId = null): string
    {
        return $this->getValue('price_type', $storeId) ?? 'RETAIL';
    }

    public function getMaxWeight(?int $storeId = null): float
    {
        return (float) ($this->getValue('max_weight', $storeId) ?? 70);
    }

    public function getHandlingFee(?int $storeId = null): float
    {
        return (float) ($this->getValue('handling_fee', $storeId) ?? 0);
    }

    public function getFreeShippingThreshold(?int $storeId = null): float
    {
        return (float) ($this->getValue('free_shipping_threshold', $storeId) ?? 0);
    }

    public function isCacheEnabled(?int $storeId = null): bool
    {
        return $this->getFlag('cache_enabled', $storeId);
    }

    public function getCacheTtl(?int $storeId = null): int
    {
        return (int) ($this->getValue('cache_ttl', $storeId) ?? 3600);
    }

    public function isCacheFallbackEnabled(?int $storeId = null): bool
    {
        return $this->getFlag('cache_fallback', $storeId);
    }

    public function isDebugEnabled(?int $storeId = null): bool
    {
        return $this->getFlag('debug', $storeId);
    }

    public function isShipToAllCountries(?int $storeId = null): bool
    {
        return !$this->getFlag('sallowspecific', $storeId);
    }

    public function getSpecificCountries(?int $storeId = null): array
    {
        $countries = $this->getValue('specificcountry', $storeId);
        if (empty($countries)) {
            return [];
        }
        return is_array($countries) ? $countries : explode(',', $countries);
    }

    public function showMethodIfNotApplicable(?int $storeId = null): bool
    {
        return $this->getFlag('showmethod', $storeId);
    }

    public function getSortOrder(?int $storeId = null): int
    {
        return (int) ($this->getValue('sort_order', $storeId) ?? 30);
    }

    public function hasCredentials(?int $storeId = null): bool
    {
        return !empty($this->getConsumerKey($storeId))
            && !empty($this->getConsumerSecret($storeId));
    }

    public function getCutoffTime(?int $storeId = null): string
    {
        return $this->getValue('cutoff_time', $storeId) ?? '14:00';
    }

    public function getCutoffHour(?int $storeId = null): int
    {
        $time = $this->getCutoffTime($storeId);
        $parts = explode(':', $time);
        return (int) ($parts[0] ?? 14);
    }

    /**
     * Get per-method cutoff times as array
     * @return array ['METHOD_CODE' => 'HH:MM', ...]
     */
    public function getMethodCutoffs(?int $storeId = null): array
    {
        $json = $this->getValue('method_cutoffs', $storeId);
        if (empty($json)) {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Get cutoff hour for a specific method (falls back to global cutoff)
     */
    public function getMethodCutoffHour(string $methodCode, ?int $storeId = null): int
    {
        $methodCutoffs = $this->getMethodCutoffs($storeId);
        if (isset($methodCutoffs[$methodCode])) {
            $parts = explode(':', $methodCutoffs[$methodCode]);
            return (int) ($parts[0] ?? 14);
        }
        return $this->getCutoffHour($storeId);
    }

    public function getPickupDays(?int $storeId = null): array
    {
        $days = $this->getValue('pickup_days', $storeId);
        if (empty($days)) {
            return [1, 2, 3, 4, 5]; // Mon-Fri default
        }
        return is_array($days) ? array_map('intval', $days) : array_map('intval', explode(',', $days));
    }

    public function getPickupTime(?int $storeId = null): string
    {
        return $this->getValue('pickup_time', $storeId) ?? '15:00';
    }

    public function getPickupHour(?int $storeId = null): int
    {
        $time = $this->getPickupTime($storeId);
        $parts = explode(':', $time);
        return (int) ($parts[0] ?? 15);
    }

    public function getGracePeriod(?int $storeId = null): int
    {
        return (int) ($this->getValue('grace_period', $storeId) ?? 0);
    }

    public function getGracePeriodUnit(?int $storeId = null): string
    {
        return $this->getValue('grace_period_unit', $storeId) ?? 'days';
    }

    /**
     * Get grace period in hours (for cutoff adjustment)
     */
    public function getGracePeriodHours(?int $storeId = null): int
    {
        $period = $this->getGracePeriod($storeId);
        $unit = $this->getGracePeriodUnit($storeId);

        if ($unit === 'days') {
            return $period * 24;
        }

        return $period;
    }
}
