<?php
/**
 * Jscriptz SmartShipping - UPS Time in Transit Request Builder
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Ups\Request;

use Jscriptz\SmartShipping\Model\Ups\Config;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;

class TransitRequestBuilder
{
    public function __construct(
        private readonly Config $config,
        private readonly RegionFactory $regionFactory,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * Build UPS Time in Transit API request from Magento rate request
     */
    public function build(RateRequest $request, ?int $storeId = null): array
    {
        $shipDate = $this->calculateShipDate($storeId);
        $weight = max(0.1, (float) $request->getPackageWeight());

        return [
            'originCountryCode' => $this->getOriginCountry($request),
            'originStateProvince' => $this->getOriginRegionCode($request),
            'originCityName' => $this->getOriginCity($request),
            'originPostalCode' => $this->getOriginPostcode($request),
            'destinationCountryCode' => $request->getDestCountryId() ?? 'US',
            'destinationStateProvince' => $request->getDestRegionCode() ?? '',
            'destinationCityName' => $request->getDestCity() ?? '',
            'destinationPostalCode' => $this->formatPostalCode(
                $request->getDestPostcode() ?? '',
                $request->getDestCountryId() ?? 'US'
            ),
            'weight' => (string) round($weight, 1),
            'weightUnitOfMeasure' => 'LBS',
            'shipDate' => $shipDate,
            'shipTime' => $this->config->getCutoffTime($storeId) . ':00',
            'residentialIndicator' => $request->getDestStreet() ? '01' : '02', // 01 = Residential, 02 = Commercial
        ];
    }

    /**
     * Calculate the ship date based on cutoff time and pickup days
     */
    private function calculateShipDate(?int $storeId): string
    {
        $now = $this->timezone->date();
        $cutoffTime = $this->config->getCutoffTime($storeId);
        $pickupDays = $this->config->getPickupDays($storeId);

        // Parse cutoff time
        [$cutoffHour, $cutoffMinute] = array_map('intval', explode(':', $cutoffTime));
        $currentHour = (int) $now->format('H');
        $currentMinute = (int) $now->format('i');

        // Check if we're past cutoff time
        $isPastCutoff = ($currentHour > $cutoffHour) ||
                        ($currentHour === $cutoffHour && $currentMinute >= $cutoffMinute);

        // Start from today or tomorrow based on cutoff
        $shipDate = clone $now;
        if ($isPastCutoff) {
            $shipDate->modify('+1 day');
        }

        // Find the next valid pickup day
        $maxAttempts = 7;
        $attempts = 0;
        while ($attempts < $maxAttempts) {
            $dayOfWeek = (int) $shipDate->format('N'); // 1 = Monday, 7 = Sunday
            if (in_array($dayOfWeek, $pickupDays)) {
                break;
            }
            $shipDate->modify('+1 day');
            $attempts++;
        }

        return $shipDate->format('Y-m-d');
    }

    /**
     * Get origin region code, resolving from region ID if needed
     */
    private function getOriginRegionCode(RateRequest $request): string
    {
        $regionCode = $request->getOrigRegionCode();
        if (!empty($regionCode)) {
            return $regionCode;
        }

        $regionId = $request->getOrigRegionId();
        if ($regionId) {
            $region = $this->regionFactory->create()->load($regionId);
            if ($region->getId()) {
                return $region->getCode();
            }
        }

        // Fall back to shipping origin config
        $originRegionId = $this->getShippingOriginConfig('region_id', $request->getStoreId());
        if ($originRegionId) {
            $region = $this->regionFactory->create()->load($originRegionId);
            if ($region->getId()) {
                return $region->getCode();
            }
        }

        return '';
    }

    /**
     * Get origin postal code with fallback to config
     */
    private function getOriginPostcode(RateRequest $request): string
    {
        $postcode = $request->getOrigPostcode();
        if (!empty($postcode)) {
            return $postcode;
        }

        return $this->getShippingOriginConfig('postcode', $request->getStoreId());
    }

    /**
     * Get origin city with fallback to config
     */
    private function getOriginCity(RateRequest $request): string
    {
        $city = $request->getOrigCity();
        if (!empty($city)) {
            return $city;
        }

        return $this->getShippingOriginConfig('city', $request->getStoreId());
    }

    /**
     * Get origin country with fallback to config
     */
    private function getOriginCountry(RateRequest $request): string
    {
        $country = $request->getOrigCountry();
        if (!empty($country)) {
            return $country;
        }

        $configCountry = $this->getShippingOriginConfig('country_id', $request->getStoreId());
        return $configCountry ?: 'US';
    }

    /**
     * Get shipping origin config value
     */
    private function getShippingOriginConfig(string $field, ?int $storeId): string
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $scopeConfig = $objectManager->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);

        return (string) $scopeConfig->getValue(
            'shipping/origin/' . $field,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Format postal code for API
     */
    private function formatPostalCode(string $postalCode, string $countryCode): string
    {
        if ($countryCode === 'US' && strlen($postalCode) > 5) {
            return substr($postalCode, 0, 5);
        }
        return $postalCode;
    }
}
