<?php
/**
 * Jscriptz SmartShipping - FedEx Configuration Reader
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Fedex;

use Jscriptz\SmartShipping\Model\Fedex\Config\Source\Environment;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_PREFIX = 'carriers/fedexv3/';

    // API Endpoints
    private const SANDBOX_BASE_URL = 'https://apis-sandbox.fedex.com';
    private const PRODUCTION_BASE_URL = 'https://apis.fedex.com';

    private const OAUTH_TOKEN_PATH = '/oauth/token';
    private const RATE_PATH = '/rate/v1/rates/quotes';
    private const ADDRESS_VALIDATION_PATH = '/address/v1/addresses/resolve';
    private const TRACK_PATH = '/track/v1/trackingnumbers';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Get config value
     */
    private function getValue(string $path, ?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get config flag
     */
    private function getFlag(string $path, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if carrier is active
     */
    public function isActive(?int $storeId = null): bool
    {
        return $this->getFlag('active', $storeId);
    }

    /**
     * Get carrier title
     */
    public function getTitle(?int $storeId = null): string
    {
        return $this->getValue('title', $storeId) ?? 'FedEx';
    }

    /**
     * Get environment (sandbox/production)
     */
    public function getEnvironment(?int $storeId = null): string
    {
        return $this->getValue('environment', $storeId) ?? Environment::SANDBOX;
    }

    /**
     * Check if using sandbox
     */
    public function isSandbox(?int $storeId = null): bool
    {
        return $this->getEnvironment($storeId) === Environment::SANDBOX;
    }

    /**
     * Get API base URL based on environment
     */
    public function getBaseUrl(?int $storeId = null): string
    {
        return $this->isSandbox($storeId) ? self::SANDBOX_BASE_URL : self::PRODUCTION_BASE_URL;
    }

    /**
     * Get OAuth token URL
     */
    public function getOAuthTokenUrl(?int $storeId = null): string
    {
        return $this->getBaseUrl($storeId) . self::OAUTH_TOKEN_PATH;
    }

    /**
     * Get Rating API URL
     */
    public function getRateUrl(?int $storeId = null): string
    {
        return $this->getBaseUrl($storeId) . self::RATE_PATH;
    }

    /**
     * Get Address Validation URL
     */
    public function getAddressValidationUrl(?int $storeId = null): string
    {
        return $this->getBaseUrl($storeId) . self::ADDRESS_VALIDATION_PATH;
    }

    /**
     * Get Tracking URL
     */
    public function getTrackUrl(?int $storeId = null): string
    {
        return $this->getBaseUrl($storeId) . self::TRACK_PATH;
    }

    /**
     * Get API Key (Client ID)
     */
    public function getClientId(?int $storeId = null): string
    {
        return $this->getValue('client_id', $storeId) ?? '';
    }

    /**
     * Get Secret Key (Client Secret)
     */
    public function getClientSecret(?int $storeId = null): string
    {
        return $this->getValue('client_secret', $storeId) ?? '';
    }

    /**
     * Get FedEx Account Number
     */
    public function getAccountNumber(?int $storeId = null): string
    {
        return $this->getValue('account_number', $storeId) ?? '';
    }

    /**
     * Get allowed shipping methods
     */
    public function getAllowedMethods(?int $storeId = null): array
    {
        $methods = $this->getValue('allowed_methods', $storeId);
        if (empty($methods)) {
            return [];
        }
        return is_array($methods) ? $methods : explode(',', $methods);
    }

    /**
     * Get default package type
     */
    public function getPackageType(?int $storeId = null): string
    {
        return $this->getValue('package_type', $storeId) ?? 'YOUR_PACKAGING';
    }

    /**
     * Get maximum package weight
     */
    public function getMaxWeight(?int $storeId = null): float
    {
        return (float) ($this->getValue('max_weight', $storeId) ?? 150);
    }

    /**
     * Get handling fee
     */
    public function getHandlingFee(?int $storeId = null): float
    {
        return (float) ($this->getValue('handling_fee', $storeId) ?? 0);
    }

    /**
     * Get free shipping threshold
     */
    public function getFreeShippingThreshold(?int $storeId = null): float
    {
        return (float) ($this->getValue('free_shipping_threshold', $storeId) ?? 0);
    }

    /**
     * Get cutoff time
     */
    public function getCutoffTime(?int $storeId = null): string
    {
        return $this->getValue('cutoff_time', $storeId) ?? '14:00';
    }

    /**
     * Get cutoff hour (extracted from cutoff time)
     */
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

    /**
     * Get pickup days
     */
    public function getPickupDays(?int $storeId = null): array
    {
        $days = $this->getValue('pickup_days', $storeId);
        if (empty($days)) {
            return [1, 2, 3, 4, 5]; // Mon-Fri default
        }
        return is_array($days) ? array_map('intval', $days) : array_map('intval', explode(',', $days));
    }

    /**
     * Get pickup time
     */
    public function getPickupTime(?int $storeId = null): string
    {
        return $this->getValue('pickup_time', $storeId) ?? '15:00';
    }

    /**
     * Get pickup hour (extracted from pickup time)
     */
    public function getPickupHour(?int $storeId = null): int
    {
        $time = $this->getPickupTime($storeId);
        $parts = explode(':', $time);
        return (int) ($parts[0] ?? 15);
    }

    /**
     * Get grace period
     */
    public function getGracePeriod(?int $storeId = null): int
    {
        return (int) ($this->getValue('grace_period', $storeId) ?? 0);
    }

    /**
     * Get grace period unit (days or hours)
     */
    public function getGracePeriodUnit(?int $storeId = null): string
    {
        return $this->getValue('grace_period_unit', $storeId) ?? 'days';
    }

    /**
     * Check if caching is enabled
     */
    public function isCacheEnabled(?int $storeId = null): bool
    {
        return $this->getFlag('cache_enabled', $storeId);
    }

    /**
     * Get cache TTL in seconds
     */
    public function getCacheTtl(?int $storeId = null): int
    {
        return (int) ($this->getValue('cache_ttl', $storeId) ?? 3600);
    }

    /**
     * Check if cache fallback is enabled
     */
    public function isCacheFallbackEnabled(?int $storeId = null): bool
    {
        return $this->getFlag('cache_fallback', $storeId);
    }

    /**
     * Check if address validation is enabled
     */
    public function isAddressValidationEnabled(?int $storeId = null): bool
    {
        return $this->getFlag('address_validation_enabled', $storeId);
    }

    /**
     * Check if address auto-correct is enabled
     */
    public function isAutoCorrectEnabled(?int $storeId = null): bool
    {
        return $this->getFlag('address_auto_correct', $storeId);
    }

    /**
     * Check if debug mode is enabled
     */
    public function isDebugEnabled(?int $storeId = null): bool
    {
        return $this->getFlag('debug', $storeId);
    }

    /**
     * Check if shipping to all countries
     */
    public function isShipToAllCountries(?int $storeId = null): bool
    {
        return !$this->getFlag('sallowspecific', $storeId);
    }

    /**
     * Get specific countries to ship to
     */
    public function getSpecificCountries(?int $storeId = null): array
    {
        $countries = $this->getValue('specificcountry', $storeId);
        if (empty($countries)) {
            return [];
        }
        return is_array($countries) ? $countries : explode(',', $countries);
    }

    /**
     * Check if should show method when not applicable
     */
    public function showMethodIfNotApplicable(?int $storeId = null): bool
    {
        return $this->getFlag('showmethod', $storeId);
    }

    /**
     * Get sort order
     */
    public function getSortOrder(?int $storeId = null): int
    {
        return (int) ($this->getValue('sort_order', $storeId) ?? 20);
    }

    /**
     * Check if credentials are configured
     */
    public function hasCredentials(?int $storeId = null): bool
    {
        return !empty($this->getClientId($storeId))
            && !empty($this->getClientSecret($storeId));
    }
}
