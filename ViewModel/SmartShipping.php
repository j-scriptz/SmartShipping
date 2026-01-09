<?php
/**
 * Jscriptz SmartShipping - Smart Shipping Display ViewModel
 *
 * Provides configuration for enhanced shipping method display:
 * - Delivery date estimates per carrier/method (from carrier APIs)
 * - Fastest/Cheapest badges
 * - Free shipping progress bar
 * - Cutoff countdown timers
 *
 * Transit time data is populated by carrier modules (USPS, UPS, FedEx)
 * via the TransitTimeRepositoryInterface during rate collection.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\ViewModel;

use Jscriptz\SmartShipping\Api\Data\TransitTimeInterface;
use Jscriptz\SmartShipping\Api\TransitTimeRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;

class SmartShipping implements ArgumentInterface
{
    private const CONFIG_PATH = 'smartshipping/general/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Json $jsonSerializer,
        private readonly TransitTimeRepositoryInterface $transitTimeRepository
    ) {
    }

    /**
     * Check if delivery date estimates are enabled
     */
    public function isEstimatesEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH . 'estimates_enabled',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Check if fastest/cheapest badges are enabled
     */
    public function isBadgesEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH . 'badges_enabled',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Check if free shipping progress bar is enabled
     */
    public function isFreeShippingProgressEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH . 'free_shipping_enabled',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get processing days (business days to prepare order)
     */
    public function getProcessingDays(): int
    {
        $days = $this->scopeConfig->getValue(
            self::CONFIG_PATH . 'processing_days',
            ScopeInterface::SCOPE_STORE
        );

        return max(0, (int) ($days ?: 1));
    }

    /**
     * Get carrier transit days from repository
     * Format: ['carrier_method' => ['min' => int, 'max' => int, 'delivery_date' => string, ...]]
     */
    public function getCarrierTransitDays(): array
    {
        return $this->getApiTransitData();
    }

    /**
     * Get transit data from repository, formatted for frontend consumption
     */
    private function getApiTransitData(): array
    {
        $transitTimes = $this->transitTimeRepository->getAll();

        if (empty($transitTimes)) {
            return [];
        }

        $result = [];
        foreach ($transitTimes as $transitTime) {
            $key = $transitTime->getCarrierCode() . '_' . $transitTime->getMethodCode();
            $result[$key] = [
                'min' => $transitTime->getMinDays(),
                'max' => $transitTime->getMaxDays(),
                'delivery_date' => $transitTime->getDeliveryDate(),
                'delivery_day' => $transitTime->getDeliveryDay(),
                'delivery_time' => $transitTime->getDeliveryTime(),
                'guaranteed' => $transitTime->isGuaranteed(),
                'cutoff_hour' => $transitTime->getCutoffHour(),
            ];
        }

        return $result;
    }

    /**
     * Check if we have dynamic API transit data available
     */
    public function hasApiTransitData(): bool
    {
        return !empty($this->transitTimeRepository->getAll());
    }

    /**
     * Get free shipping threshold amount
     * Returns custom threshold if set, otherwise auto-detects from Free Shipping carrier
     */
    public function getFreeShippingThreshold(): ?float
    {
        // Check for custom threshold first
        $customThreshold = $this->scopeConfig->getValue(
            self::CONFIG_PATH . 'free_shipping_threshold',
            ScopeInterface::SCOPE_STORE
        );

        if ($customThreshold !== null && $customThreshold !== '') {
            return (float) $customThreshold;
        }

        // Auto-detect from Magento Free Shipping carrier configuration
        $freeShippingEnabled = $this->scopeConfig->isSetFlag(
            'carriers/freeshipping/active',
            ScopeInterface::SCOPE_STORE
        );

        if ($freeShippingEnabled) {
            $threshold = $this->scopeConfig->getValue(
                'carriers/freeshipping/free_shipping_subtotal',
                ScopeInterface::SCOPE_STORE
            );

            if ($threshold !== null && $threshold !== '') {
                return (float) $threshold;
            }
        }

        return null;
    }

    /**
     * Get fastest badge label
     */
    public function getFastestLabel(): string
    {
        $label = $this->scopeConfig->getValue(
            self::CONFIG_PATH . 'fastest_label',
            ScopeInterface::SCOPE_STORE
        );

        return $label ?: (string) __('Fastest');
    }

    /**
     * Get cheapest badge label
     */
    public function getCheapestLabel(): string
    {
        $label = $this->scopeConfig->getValue(
            self::CONFIG_PATH . 'cheapest_label',
            ScopeInterface::SCOPE_STORE
        );

        return $label ?: (string) __('Best Value');
    }

    /**
     * Get maximum number of shipping methods to display (0 = unlimited)
     */
    public function getMethodLimit(): int
    {
        $limit = $this->scopeConfig->getValue(
            self::CONFIG_PATH . 'method_limit',
            ScopeInterface::SCOPE_STORE
        );

        return max(0, (int) ($limit ?? 5));
    }

    /**
     * Get shipping method sort order
     * Options: default, price_asc, price_desc, time_asc, time_desc
     */
    public function getMethodSortBy(): string
    {
        $sortBy = $this->scopeConfig->getValue(
            self::CONFIG_PATH . 'method_sort_by',
            ScopeInterface::SCOPE_STORE
        );

        return $sortBy ?: 'price_asc';
    }

    /**
     * Check if frontend sorting is allowed
     */
    public function isMethodAllowFrontendSort(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH . 'method_allow_frontend_sort',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Check if "Show more" link is enabled
     */
    public function isMethodShowMoreEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH . 'method_show_more_enabled',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get free shipping progress message (with {amount} placeholder)
     */
    public function getFreeShippingMessage(): string
    {
        $message = $this->scopeConfig->getValue(
            self::CONFIG_PATH . 'free_shipping_message',
            ScopeInterface::SCOPE_STORE
        );

        return $message ?: (string) __('Add {amount} more for FREE shipping!');
    }

    /**
     * Get free shipping success message
     */
    public function getFreeShippingSuccessMessage(): string
    {
        $message = $this->scopeConfig->getValue(
            self::CONFIG_PATH . 'free_shipping_success_message',
            ScopeInterface::SCOPE_STORE
        );

        return $message ?: (string) __('You qualify for FREE shipping!');
    }

    /**
     * Check if shipping cutoff countdown is enabled
     */
    public function isCutoffEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH . 'cutoff_enabled',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get cutoff times from transit data
     * Format: ['carrier_method' => int] where int is hour (0-23)
     */
    public function getCutoffTimes(): array
    {
        $cutoffs = [];
        $transitTimes = $this->transitTimeRepository->getAll();

        foreach ($transitTimes as $transitTime) {
            $cutoffHour = $transitTime->getCutoffHour();
            if ($cutoffHour !== null) {
                $key = $transitTime->getCarrierCode() . '_' . $transitTime->getMethodCode();
                $cutoffs[$key] = $cutoffHour;
            }
        }

        return $cutoffs;
    }

    /**
     * Get cutoff countdown message
     */
    public function getCutoffMessage(): string
    {
        $message = $this->scopeConfig->getValue(
            self::CONFIG_PATH . 'cutoff_message',
            ScopeInterface::SCOPE_STORE
        );

        return $message ?: (string) __('Order in {time} for this delivery option');
    }

    /**
     * Get cutoff expired message
     */
    public function getCutoffExpiredMessage(): string
    {
        $message = $this->scopeConfig->getValue(
            self::CONFIG_PATH . 'cutoff_expired_message',
            ScopeInterface::SCOPE_STORE
        );

        return $message ?: (string) __('Cutoff passed - ships next business day');
    }

    /**
     * Check if cutoff time colors are enabled
     */
    public function isCutoffColorsEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH . 'cutoff_colors_enabled',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get red threshold in hours
     */
    public function getCutoffColorRedHours(): float
    {
        $hours = $this->scopeConfig->getValue(
            self::CONFIG_PATH . 'cutoff_color_red_hours',
            ScopeInterface::SCOPE_STORE
        );

        return max(0, (float) ($hours ?? 1));
    }

    /**
     * Get amber threshold in hours
     */
    public function getCutoffColorAmberHours(): float
    {
        $hours = $this->scopeConfig->getValue(
            self::CONFIG_PATH . 'cutoff_color_amber_hours',
            ScopeInterface::SCOPE_STORE
        );

        return max(0, (float) ($hours ?? 2));
    }

    /**
     * Get green threshold in hours
     */
    public function getCutoffColorGreenHours(): float
    {
        $hours = $this->scopeConfig->getValue(
            self::CONFIG_PATH . 'cutoff_color_green_hours',
            ScopeInterface::SCOPE_STORE
        );

        return max(0, (float) ($hours ?? 3));
    }

    /**
     * Get store timezone identifier
     */
    public function getStoreTimezone(): string
    {
        return $this->scopeConfig->getValue(
            'general/locale/timezone',
            ScopeInterface::SCOPE_STORE
        ) ?: 'America/New_York';
    }

    /**
     * Get full configuration as array for JavaScript
     */
    public function getConfig(): array
    {
        return [
            'estimatesEnabled' => $this->isEstimatesEnabled(),
            'badgesEnabled' => $this->isBadgesEnabled(),
            'freeShippingEnabled' => $this->isFreeShippingProgressEnabled(),
            'processingDays' => $this->getProcessingDays(),
            'carrierTransitDays' => $this->getCarrierTransitDays(),
            'hasApiTransitData' => $this->hasApiTransitData(),
            'freeShippingThreshold' => $this->getFreeShippingThreshold(),
            'fastestLabel' => $this->getFastestLabel(),
            'cheapestLabel' => $this->getCheapestLabel(),
            'methodLimit' => $this->getMethodLimit(),
            'methodSortBy' => $this->getMethodSortBy(),
            'methodAllowFrontendSort' => $this->isMethodAllowFrontendSort(),
            'methodShowMoreEnabled' => $this->isMethodShowMoreEnabled(),
            'freeShippingMessage' => $this->getFreeShippingMessage(),
            'freeShippingSuccessMessage' => $this->getFreeShippingSuccessMessage(),
            'excludedWeekdays' => [0, 6], // Exclude weekends by default
            'cutoffEnabled' => $this->isCutoffEnabled(),
            'cutoffTimes' => $this->getCutoffTimes(),
            'cutoffMessage' => $this->getCutoffMessage(),
            'cutoffExpiredMessage' => $this->getCutoffExpiredMessage(),
            'storeTimezone' => $this->getStoreTimezone(),
            'cutoffColorsEnabled' => $this->isCutoffColorsEnabled(),
            'cutoffColorRedHours' => $this->getCutoffColorRedHours(),
            'cutoffColorAmberHours' => $this->getCutoffColorAmberHours(),
            'cutoffColorGreenHours' => $this->getCutoffColorGreenHours(),
        ];
    }

    /**
     * Get configuration as JSON for template use
     */
    public function getConfigJson(): string
    {
        return $this->jsonSerializer->serialize($this->getConfig());
    }
}
