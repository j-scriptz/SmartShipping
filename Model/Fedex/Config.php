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
    private const SHIP_PATH = '/ship/v1/shipments';
    private const SHIP_CANCEL_PATH = '/ship/v1/shipments/cancel';
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

    // =========================================================================
    // Label Generation Configuration
    // =========================================================================

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
        return $this->hasCredentials($storeId)
            && !empty($this->getAccountNumber($storeId));
    }

    /**
     * Get label format (PDF, PNG, ZPLII, EPL2)
     */
    public function getLabelFormat(?int $storeId = null): string
    {
        return $this->getValue('label_format', $storeId) ?? 'PDF';
    }

    /**
     * Get label stock type
     */
    public function getLabelStockType(?int $storeId = null): string
    {
        return $this->getValue('label_stock_type', $storeId) ?? 'PAPER_4X6';
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

    /**
     * Get drop off type (REGULAR_PICKUP, REQUEST_COURIER, DROP_BOX, etc.)
     */
    public function getDropOffType(?int $storeId = null): string
    {
        return $this->getValue('drop_off_type', $storeId) ?? 'REGULAR_PICKUP';
    }

    // =========================================================================
    // Package Attribute Mapping Configuration
    // =========================================================================

    /**
     * Get weight attribute code
     */
    public function getWeightAttribute(?int $storeId = null): ?string
    {
        $value = $this->getValue('weight_attribute', $storeId);
        return !empty($value) ? $value : null;
    }

    /**
     * Get weight unit (LB, KG, OZ)
     */
    public function getWeightUnit(?int $storeId = null): string
    {
        return $this->getValue('weight_unit', $storeId) ?? 'LB';
    }

    /**
     * Get length attribute code
     */
    public function getLengthAttribute(?int $storeId = null): ?string
    {
        $value = $this->getValue('length_attribute', $storeId);
        return !empty($value) ? $value : null;
    }

    /**
     * Get width attribute code
     */
    public function getWidthAttribute(?int $storeId = null): ?string
    {
        $value = $this->getValue('width_attribute', $storeId);
        return !empty($value) ? $value : null;
    }

    /**
     * Get height attribute code
     */
    public function getHeightAttribute(?int $storeId = null): ?string
    {
        $value = $this->getValue('height_attribute', $storeId);
        return !empty($value) ? $value : null;
    }

    /**
     * Get dimension unit (IN, CM, FT, M)
     */
    public function getDimensionUnit(?int $storeId = null): string
    {
        return $this->getValue('dimension_unit', $storeId) ?? 'IN';
    }

    /**
     * Get dimension rounding method (up, down, nearest)
     */
    public function getDimensionRounding(?int $storeId = null): string
    {
        return $this->getValue('dimension_rounding', $storeId) ?? 'up';
    }

    /**
     * Check if product attribute mapping is configured for dimensions
     */
    public function hasDimensionAttributes(?int $storeId = null): bool
    {
        return $this->getLengthAttribute($storeId) !== null
            || $this->getWidthAttribute($storeId) !== null
            || $this->getHeightAttribute($storeId) !== null;
    }

    /**
     * Check if product attribute mapping is configured for weight
     */
    public function hasWeightAttribute(?int $storeId = null): bool
    {
        return $this->getWeightAttribute($storeId) !== null;
    }

    // =========================================================================
    // Oversized Package Detection Configuration
    // =========================================================================

    /**
     * Check if oversized detection is enabled
     */
    public function isOversizedDetectionEnabled(?int $storeId = null): bool
    {
        return $this->getFlag('oversized_enabled', $storeId);
    }

    /**
     * Get maximum weight for parcel shipping (lbs)
     */
    public function getOversizedMaxWeight(?int $storeId = null): float
    {
        return (float) ($this->getValue('oversized_max_weight', $storeId) ?? 150.0);
    }

    /**
     * Get maximum length for parcel shipping (inches)
     */
    public function getOversizedMaxLength(?int $storeId = null): float
    {
        return (float) ($this->getValue('oversized_max_length', $storeId) ?? 108.0);
    }

    /**
     * Get maximum length + girth for parcel shipping (inches)
     * Girth = 2 * width + 2 * height
     */
    public function getOversizedMaxGirth(?int $storeId = null): float
    {
        return (float) ($this->getValue('oversized_max_girth', $storeId) ?? 165.0);
    }

    /**
     * Get oversized message to display
     */
    public function getOversizedMessage(?int $storeId = null): string
    {
        return $this->getValue('oversized_message', $storeId)
            ?? 'This item requires freight shipping. Please contact us for a quote.';
    }

    /**
     * Check if oversized message should show as a shipping method
     */
    public function showOversizedAsMethod(?int $storeId = null): bool
    {
        return $this->getFlag('oversized_show_as_method', $storeId);
    }

    /**
     * Get oversized method title
     */
    public function getOversizedMethodTitle(?int $storeId = null): string
    {
        return $this->getValue('oversized_method_title', $storeId) ?? 'Freight Quote Required';
    }

    /**
     * Check if package dimensions exceed parcel limits
     *
     * @param float $weight Weight in pounds
     * @param float $length Length in inches
     * @param float $width Width in inches
     * @param float $height Height in inches
     * @return array{is_oversized: bool, reasons: string[]}
     */
    public function checkOversized(
        float $weight,
        float $length,
        float $width,
        float $height,
        ?int $storeId = null
    ): array {
        if (!$this->isOversizedDetectionEnabled($storeId)) {
            return ['is_oversized' => false, 'reasons' => []];
        }

        $reasons = [];

        // Check weight
        if ($weight > $this->getOversizedMaxWeight($storeId)) {
            $reasons[] = sprintf(
                'Weight (%.1f lbs) exceeds maximum of %.0f lbs',
                $weight,
                $this->getOversizedMaxWeight($storeId)
            );
        }

        // Check length
        if ($length > $this->getOversizedMaxLength($storeId)) {
            $reasons[] = sprintf(
                'Length (%.1f in) exceeds maximum of %.0f inches',
                $length,
                $this->getOversizedMaxLength($storeId)
            );
        }

        // Check length + girth
        $girth = (2 * $width) + (2 * $height);
        $lengthPlusGirth = $length + $girth;
        if ($lengthPlusGirth > $this->getOversizedMaxGirth($storeId)) {
            $reasons[] = sprintf(
                'Length + Girth (%.1f in) exceeds maximum of %.0f inches',
                $lengthPlusGirth,
                $this->getOversizedMaxGirth($storeId)
            );
        }

        return [
            'is_oversized' => !empty($reasons),
            'reasons' => $reasons,
        ];
    }

    /**
     * Get formatted oversized message with reason placeholder replaced
     */
    public function getFormattedOversizedMessage(array $reasons, ?int $storeId = null): string
    {
        $message = $this->getOversizedMessage($storeId);
        $reasonText = implode('; ', $reasons);

        return str_replace('{reason}', $reasonText, $message);
    }
}
