<?php
/**
 * Jscriptz SmartShipping - UPS Configuration Reader
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Ups;

use Jscriptz\SmartShipping\Model\Ups\Config\Source\Environment;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_PREFIX = 'carriers/upsv3/';

    private const SANDBOX_BASE_URL = 'https://wwwcie.ups.com';
    private const PRODUCTION_BASE_URL = 'https://onlinetools.ups.com';

    private const OAUTH_TOKEN_PATH = '/security/v1/oauth/token';
    private const RATING_PATH = '/api/rating/v2205/Shop';
    private const TIME_IN_TRANSIT_PATH = '/api/shipments/v1/transittimes';
    private const SHIP_PATH = '/api/shipments/v2205/ship';
    private const SHIP_CANCEL_PATH = '/api/shipments/v2205/void/cancel';
    private const TRACK_PATH = '/api/track/v1/details/';

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
        return $this->getValue('title', $storeId) ?? 'UPS';
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

    public function getRatingUrl(?int $storeId = null): string
    {
        return $this->getBaseUrl($storeId) . self::RATING_PATH;
    }

    public function getTimeInTransitUrl(?int $storeId = null): string
    {
        return $this->getBaseUrl($storeId) . self::TIME_IN_TRANSIT_PATH;
    }

    public function getClientId(?int $storeId = null): string
    {
        return $this->getValue('client_id', $storeId) ?? '';
    }

    public function getClientSecret(?int $storeId = null): string
    {
        return $this->getValue('client_secret', $storeId) ?? '';
    }

    public function getAccountNumber(?int $storeId = null): string
    {
        return $this->getValue('account_number', $storeId) ?? '';
    }

    public function getAllowedMethods(?int $storeId = null): array
    {
        $methods = $this->getValue('allowed_methods', $storeId);
        if (empty($methods)) {
            return [];
        }
        return is_array($methods) ? $methods : explode(',', $methods);
    }

    public function getPackageType(?int $storeId = null): string
    {
        return $this->getValue('package_type', $storeId) ?? '02';
    }

    public function getMaxWeight(?int $storeId = null): float
    {
        return (float) ($this->getValue('max_weight', $storeId) ?? 150);
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

    public function isTransitEnabled(?int $storeId = null): bool
    {
        return $this->getFlag('transit_enabled', $storeId);
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

    public function hasCredentials(?int $storeId = null): bool
    {
        return !empty($this->getClientId($storeId))
            && !empty($this->getClientSecret($storeId))
            && !empty($this->getAccountNumber($storeId));
    }

    // =========================================================================
    // Label Generation Configuration
    // =========================================================================

    /**
     * Get Ship API URL (for creating shipments/labels)
     */
    public function getShipUrl(?int $storeId = null): string
    {
        return $this->getBaseUrl($storeId) . self::SHIP_PATH;
    }

    /**
     * Get Ship Cancel URL (for voiding labels)
     */
    public function getCancelShipUrl(?int $storeId = null): string
    {
        return $this->getBaseUrl($storeId) . self::SHIP_CANCEL_PATH;
    }

    /**
     * Get Tracking URL
     */
    public function getTrackUrl(string $trackingNumber, ?int $storeId = null): string
    {
        return $this->getBaseUrl($storeId) . self::TRACK_PATH . $trackingNumber;
    }

    /**
     * Check if label generation is enabled
     */
    public function isLabelEnabled(?int $storeId = null): bool
    {
        return $this->getFlag('label_enabled', $storeId);
    }

    /**
     * Check if all required label credentials are configured
     */
    public function hasLabelCredentials(?int $storeId = null): bool
    {
        return $this->hasCredentials($storeId);
    }

    /**
     * Get label format (GIF, PNG, PDF, ZPL, EPL)
     */
    public function getLabelFormat(?int $storeId = null): string
    {
        return $this->getValue('label_format', $storeId) ?? 'GIF';
    }

    /**
     * Get label stock type
     */
    public function getLabelStockType(?int $storeId = null): string
    {
        return $this->getValue('label_stock_type', $storeId) ?? '4X6';
    }

    /**
     * Get default package weight (lbs)
     */
    public function getDefaultWeight(?int $storeId = null): float
    {
        return (float) ($this->getValue('default_weight', $storeId) ?? 1.0);
    }

    /**
     * Get default package length (inches)
     */
    public function getDefaultLength(?int $storeId = null): float
    {
        return (float) ($this->getValue('default_length', $storeId) ?? 12.0);
    }

    /**
     * Get default package width (inches)
     */
    public function getDefaultWidth(?int $storeId = null): float
    {
        return (float) ($this->getValue('default_width', $storeId) ?? 9.0);
    }

    /**
     * Get default package height (inches)
     */
    public function getDefaultHeight(?int $storeId = null): float
    {
        return (float) ($this->getValue('default_height', $storeId) ?? 6.0);
    }

    /**
     * Get shipper company name
     */
    public function getShipperCompany(?int $storeId = null): string
    {
        return $this->getValue('shipper_company', $storeId) ?? '';
    }

    /**
     * Get shipper contact name
     */
    public function getShipperContactName(?int $storeId = null): string
    {
        return $this->getValue('shipper_contact_name', $storeId) ?? '';
    }

    /**
     * Get shipper phone number
     */
    public function getShipperPhone(?int $storeId = null): string
    {
        return $this->getValue('shipper_phone', $storeId) ?? '';
    }

    /**
     * Get shipper street address
     */
    public function getShipperStreet(?int $storeId = null): string
    {
        return $this->getValue('shipper_street', $storeId) ?? '';
    }

    /**
     * Get shipper city
     */
    public function getShipperCity(?int $storeId = null): string
    {
        return $this->getValue('shipper_city', $storeId) ?? '';
    }

    /**
     * Get shipper state/province code
     */
    public function getShipperState(?int $storeId = null): string
    {
        return $this->getValue('shipper_state', $storeId) ?? '';
    }

    /**
     * Get shipper postal code
     */
    public function getShipperPostalCode(?int $storeId = null): string
    {
        return $this->getValue('shipper_postal_code', $storeId) ?? '';
    }

    /**
     * Get shipper country code
     */
    public function getShipperCountry(?int $storeId = null): string
    {
        return $this->getValue('shipper_country', $storeId) ?? 'US';
    }

    /**
     * Check if shipper address is configured
     */
    public function hasShipperAddress(?int $storeId = null): bool
    {
        return !empty($this->getShipperStreet($storeId))
            && !empty($this->getShipperCity($storeId))
            && !empty($this->getShipperState($storeId))
            && !empty($this->getShipperPostalCode($storeId));
    }
}
