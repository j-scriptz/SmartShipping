<?php
/**
 * Jscriptz SmartShipping - UPS Rate Request Builder
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Ups\Request;

use Jscriptz\SmartShipping\Model\Ups\Config;
use Magento\Directory\Model\RegionFactory;
use Magento\Quote\Model\Quote\Address\RateRequest;

class RateRequestBuilder
{
    public function __construct(
        private readonly Config $config,
        private readonly RegionFactory $regionFactory
    ) {
    }

    /**
     * Build UPS API rate request from Magento rate request
     */
    public function build(RateRequest $request, ?int $storeId = null): array
    {
        $accountNumber = $this->config->getAccountNumber($storeId);
        $packageType = $this->config->getPackageType($storeId);

        // Get weight - ensure at least 0.1 lbs
        $weight = max(0.1, (float) $request->getPackageWeight());

        // Build the request payload
        return [
            'RateRequest' => [
                'Request' => [
                    'SubVersion' => '2205',
                    'TransactionReference' => [
                        'CustomerContext' => 'Magento Rate Request',
                    ],
                ],
                'Shipment' => [
                    'Shipper' => $this->buildShipperAddress($request, $accountNumber, $storeId),
                    'ShipTo' => $this->buildShipToAddress($request),
                    'ShipFrom' => $this->buildShipFromAddress($request, $storeId),
                    'PaymentDetails' => [
                        'ShipmentCharge' => [
                            'Type' => '01', // Transportation
                            'BillShipper' => [
                                'AccountNumber' => $accountNumber,
                            ],
                        ],
                    ],
                    'Package' => [
                        [
                            'PackagingType' => [
                                'Code' => $packageType,
                            ],
                            'Dimensions' => [
                                'UnitOfMeasurement' => [
                                    'Code' => 'IN',
                                ],
                                'Length' => '10',
                                'Width' => '10',
                                'Height' => '10',
                            ],
                            'PackageWeight' => [
                                'UnitOfMeasurement' => [
                                    'Code' => 'LBS',
                                ],
                                'Weight' => (string) round($weight, 1),
                            ],
                        ],
                    ],
                    'ShipmentRatingOptions' => [
                        'NegotiatedRatesIndicator' => '',
                    ],
                ],
            ],
        ];
    }

    /**
     * Build shipper address (your business)
     */
    private function buildShipperAddress(RateRequest $request, string $accountNumber, ?int $storeId): array
    {
        return [
            'ShipperNumber' => $accountNumber,
            'Address' => [
                'AddressLine' => [$this->getOriginStreet($request)],
                'City' => $this->getOriginCity($request),
                'StateProvinceCode' => $this->getOriginRegionCode($request),
                'PostalCode' => $this->getOriginPostcode($request),
                'CountryCode' => $this->getOriginCountry($request),
            ],
        ];
    }

    /**
     * Build ship-to address (customer destination)
     */
    private function buildShipToAddress(RateRequest $request): array
    {
        $address = [
            'Address' => [
                'City' => $request->getDestCity() ?? '',
                'StateProvinceCode' => $request->getDestRegionCode() ?? '',
                'PostalCode' => $this->formatPostalCode(
                    $request->getDestPostcode() ?? '',
                    $request->getDestCountryId() ?? 'US'
                ),
                'CountryCode' => $request->getDestCountryId() ?? 'US',
            ],
        ];

        // Add street if available
        if ($request->getDestStreet()) {
            $address['Address']['AddressLine'] = [$request->getDestStreet()];
        }

        // Mark as residential if shipping to a street address
        if ($request->getDestStreet()) {
            $address['Address']['ResidentialAddressIndicator'] = '';
        }

        return $address;
    }

    /**
     * Build ship-from address (origin)
     */
    private function buildShipFromAddress(RateRequest $request, ?int $storeId): array
    {
        return [
            'Address' => [
                'AddressLine' => [$this->getOriginStreet($request)],
                'City' => $this->getOriginCity($request),
                'StateProvinceCode' => $this->getOriginRegionCode($request),
                'PostalCode' => $this->getOriginPostcode($request),
                'CountryCode' => $this->getOriginCountry($request),
            ],
        ];
    }

    /**
     * Get origin region code, resolving from region ID if needed
     */
    private function getOriginRegionCode(RateRequest $request): string
    {
        // First try to get region code directly
        $regionCode = $request->getOrigRegionCode();
        if (!empty($regionCode)) {
            return $regionCode;
        }

        // Fall back to looking up by region ID from request
        $regionId = $request->getOrigRegionId();
        if ($regionId) {
            $region = $this->regionFactory->create()->load($regionId);
            if ($region->getId()) {
                return $region->getCode();
            }
        }

        // Last resort: try to get from shipping origin config
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

        // Fall back to shipping origin config
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

        // Fall back to shipping origin config
        return $this->getShippingOriginConfig('city', $request->getStoreId());
    }

    /**
     * Get origin street with fallback to config
     */
    private function getOriginStreet(RateRequest $request): string
    {
        $street = $request->getOrigStreet();
        if (!empty($street)) {
            return $street;
        }

        // Fall back to shipping origin config
        return $this->getShippingOriginConfig('street_line1', $request->getStoreId());
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

        // Fall back to shipping origin config
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
        // For US, use only first 5 digits
        if ($countryCode === 'US' && strlen($postalCode) > 5) {
            return substr($postalCode, 0, 5);
        }
        return $postalCode;
    }
}
