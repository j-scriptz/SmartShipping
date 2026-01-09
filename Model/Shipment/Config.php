<?php
/**
 * Jscriptz SmartShipping - Shipment Automation Configuration
 *
 * Reads shipment automation settings from system configuration.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Shipment;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_PREFIX = 'smartshipping/shipment/';

    // Auto-ship trigger options
    public const TRIGGER_ORDER_PLACE = 'order_place';
    public const TRIGGER_INVOICE_CREATE = 'invoice_create';
    public const TRIGGER_MANUAL = 'manual';

    // Label format options
    public const FORMAT_PDF = 'PDF';
    public const FORMAT_PNG = 'PNG';
    public const FORMAT_ZPL = 'ZPL';

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
     * Check if auto-shipment is enabled
     */
    public function isAutoShipEnabled(?int $storeId = null): bool
    {
        return $this->getFlag('auto_ship_enabled', $storeId);
    }

    /**
     * Get auto-shipment trigger
     *
     * @return string order_place, invoice_create, or manual
     */
    public function getAutoShipTrigger(?int $storeId = null): string
    {
        return $this->getValue('auto_ship_trigger', $storeId) ?? self::TRIGGER_MANUAL;
    }

    /**
     * Check if trigger is order placement
     */
    public function isOrderPlaceTrigger(?int $storeId = null): bool
    {
        return $this->getAutoShipTrigger($storeId) === self::TRIGGER_ORDER_PLACE;
    }

    /**
     * Check if trigger is invoice creation
     */
    public function isInvoiceTrigger(?int $storeId = null): bool
    {
        return $this->getAutoShipTrigger($storeId) === self::TRIGGER_INVOICE_CREATE;
    }

    /**
     * Get label format
     */
    public function getLabelFormat(?int $storeId = null): string
    {
        return $this->getValue('label_format', $storeId) ?? self::FORMAT_PDF;
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
        return (float) ($this->getValue('default_length', $storeId) ?? 10.0);
    }

    /**
     * Get default package width (inches)
     */
    public function getDefaultWidth(?int $storeId = null): float
    {
        return (float) ($this->getValue('default_width', $storeId) ?? 6.0);
    }

    /**
     * Get default package height (inches)
     */
    public function getDefaultHeight(?int $storeId = null): float
    {
        return (float) ($this->getValue('default_height', $storeId) ?? 4.0);
    }

    /**
     * Get default package dimensions as array
     *
     * @return array{length: float, width: float, height: float}
     */
    public function getDefaultDimensions(?int $storeId = null): array
    {
        return [
            'length' => $this->getDefaultLength($storeId),
            'width' => $this->getDefaultWidth($storeId),
            'height' => $this->getDefaultHeight($storeId),
        ];
    }

    /**
     * Check if tracking email should be sent
     */
    public function isSendTrackingEmailEnabled(?int $storeId = null): bool
    {
        return $this->getFlag('send_tracking_email', $storeId);
    }

    /**
     * Check if debug mode is enabled
     */
    public function isDebugEnabled(?int $storeId = null): bool
    {
        return $this->getFlag('debug', $storeId);
    }

    /**
     * Get list of carriers enabled for auto-shipment
     *
     * @return string[]
     */
    public function getEnabledCarriers(?int $storeId = null): array
    {
        $carriers = $this->getValue('enabled_carriers', $storeId);
        if (empty($carriers)) {
            return ['uspsv3']; // Default to USPS only
        }
        return is_array($carriers) ? $carriers : explode(',', $carriers);
    }

    /**
     * Check if carrier is enabled for auto-shipment
     */
    public function isCarrierEnabled(string $carrierCode, ?int $storeId = null): bool
    {
        return in_array($carrierCode, $this->getEnabledCarriers($storeId));
    }
}
