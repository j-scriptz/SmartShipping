<?php
/**
 * Jscriptz SmartShipping - FedEx Rate Request Builder
 *
 * Builds the JSON payload for FedEx Rate API requests.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Fedex\Request;

use Jscriptz\SmartShipping\Model\Fedex\Config;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Store\Model\StoreManagerInterface;

class RateRequestBuilder
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Build FedEx Rate API request payload
     */
    public function build(RateRequest $request, ?int $storeId = null): array
    {
        $shipper = $this->buildShipperAddress($storeId);
        $recipient = $this->buildRecipientAddress($request);
        $packages = $this->buildPackages($request, $storeId);

        $accountNumber = $this->config->getAccountNumber($storeId);

        $payload = [
            'accountNumber' => [
                'value' => $accountNumber ?: '',
            ],
            'rateRequestControlParameters' => [
                'returnTransitTimes' => true,
                'rateSortOrder' => 'SERVICENAMETRADITIONAL',
            ],
            'requestedShipment' => [
                'shipper' => $shipper,
                'recipient' => $recipient,
                'pickupType' => 'USE_SCHEDULED_PICKUP',
                'packagingType' => $this->config->getPackageType($storeId) ?: 'YOUR_PACKAGING',
                // Only request ACCOUNT rates if we have an account number
                'rateRequestType' => $accountNumber ? ['ACCOUNT', 'LIST'] : ['LIST'],
                'requestedPackageLineItems' => $packages,
            ],
        ];

        return $payload;
    }

    /**
     * Build shipper address from store configuration
     */
    private function buildShipperAddress(?int $storeId): array
    {
        // Get origin address from shipping settings
        $countryId = $this->getConfigValue('shipping/origin/country_id', $storeId) ?? 'US';
        $regionId = $this->getConfigValue('shipping/origin/region_id', $storeId);
        $postcode = $this->getConfigValue('shipping/origin/postcode', $storeId) ?? '';

        // Get region code from region ID
        $regionCode = $this->getRegionCode($regionId, $countryId);

        // FedEx requires minimal address for rate quotes
        return [
            'address' => [
                'stateOrProvinceCode' => $regionCode,
                'postalCode' => $postcode,
                'countryCode' => $countryId,
            ],
        ];
    }

    /**
     * Build recipient address from rate request
     */
    private function buildRecipientAddress(RateRequest $request): array
    {
        // Determine if residential
        $residential = $this->isResidential($request);

        // FedEx requires minimal address for rate quotes
        return [
            'address' => [
                'stateOrProvinceCode' => $request->getDestRegionCode() ?? '',
                'postalCode' => $request->getDestPostcode() ?? '',
                'countryCode' => $request->getDestCountryId() ?? 'US',
                'residential' => $residential,
            ],
        ];
    }

    /**
     * Build package details
     */
    private function buildPackages(RateRequest $request, ?int $storeId): array
    {
        $weight = (float) $request->getPackageWeight();
        if ($weight <= 0) {
            $weight = 1; // Default to 1 lb if no weight specified
        }

        $package = [
            'weight' => [
                'units' => 'LB',
                'value' => round($weight, 1),
            ],
        ];

        return [$package];
    }

    /**
     * Determine if address is residential
     *
     * FedEx charges differently for residential vs commercial.
     * Home Delivery service is only for residential addresses.
     */
    private function isResidential(RateRequest $request): bool
    {
        // Check if explicitly set on the request
        if ($request->getData('dest_residential') !== null) {
            return (bool) $request->getData('dest_residential');
        }

        // Default to residential for non-business addresses
        $company = $request->getDestCompany();
        return empty($company);
    }

    /**
     * Get region code from region ID
     */
    private function getRegionCode(?string $regionId, string $countryId): string
    {
        if (empty($regionId)) {
            return '';
        }

        // If it's already a code (2-3 letters), return as-is
        if (preg_match('/^[A-Z]{2,3}$/', $regionId)) {
            return $regionId;
        }

        // Try to get region code from ID
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        try {
            $region = $objectManager->get(\Magento\Directory\Model\Region::class)->load($regionId);
            return $region->getCode() ?? '';
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Get config value
     */
    private function getConfigValue(string $path, ?int $storeId): ?string
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $scopeConfig = $objectManager->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);

        return $scopeConfig->getValue(
            $path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
