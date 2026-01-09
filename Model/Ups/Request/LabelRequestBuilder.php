<?php
/**
 * Jscriptz SmartShipping - UPS Label Request Builder
 *
 * Builds the UPS Ship API request from a Magento order.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Ups\Request;

use Jscriptz\SmartShipping\Model\Ups\Config;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class LabelRequestBuilder
{
    // UPS Service Codes to Names mapping
    private const SERVICE_NAMES = [
        '01' => 'Next Day Air',
        '02' => '2nd Day Air',
        '03' => 'Ground',
        '07' => 'Express',
        '08' => 'Expedited',
        '11' => 'UPS Standard',
        '12' => '3 Day Select',
        '13' => 'Next Day Air Saver',
        '14' => 'Next Day Air Early AM',
        '54' => 'Express Plus',
        '59' => '2nd Day Air AM',
        '65' => 'UPS Saver',
        '82' => 'UPS Today Standard',
        '83' => 'UPS Today Dedicated Courier',
        '84' => 'UPS Today Intercity',
        '85' => 'UPS Today Express',
        '86' => 'UPS Today Express Saver',
        '96' => 'UPS Worldwide Express Freight',
    ];

    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly RegionFactory $regionFactory,
        private readonly DateTime $dateTime,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Build label request from order
     *
     * @param OrderInterface $order
     * @param mixed[] $packageData Optional package overrides
     * @return mixed[]
     */
    public function build(OrderInterface $order, array $packageData = []): array
    {
        $storeId = (int) $order->getStoreId();
        $shippingAddress = $order->getShippingAddress();

        if (!$shippingAddress) {
            throw new \InvalidArgumentException('Order has no shipping address');
        }

        $accountNumber = $this->config->getAccountNumber($storeId);

        // Extract service code from shipping method (e.g., "upsv3_03" -> "03")
        $serviceCode = $this->extractServiceCode($order->getShippingMethod());
        $labelFormat = $packageData['label_format'] ?? $this->config->getLabelFormat($storeId);

        $request = [
            'ShipmentRequest' => [
                'Request' => [
                    'SubVersion' => '2205',
                    'RequestOption' => 'nonvalidate',
                    'TransactionReference' => [
                        'CustomerContext' => 'Order ' . $order->getIncrementId(),
                    ],
                ],
                'Shipment' => [
                    'Description' => 'Order ' . $order->getIncrementId(),
                    'Shipper' => $this->buildShipperAddress($storeId, $accountNumber),
                    'ShipTo' => $this->buildShipToAddress($shippingAddress),
                    'ShipFrom' => $this->buildShipFromAddress($storeId),
                    'PaymentInformation' => [
                        'ShipmentCharge' => [
                            [
                                'Type' => '01', // Transportation
                                'BillShipper' => [
                                    'AccountNumber' => $accountNumber,
                                ],
                            ],
                        ],
                    ],
                    'Service' => [
                        'Code' => $serviceCode,
                        'Description' => self::SERVICE_NAMES[$serviceCode] ?? 'UPS',
                    ],
                    'Package' => [
                        $this->buildPackage($order, $packageData, $storeId),
                    ],
                    'ReferenceNumber' => [
                        [
                            'Code' => '00', // Shipper's Reference
                            'Value' => $order->getIncrementId(),
                        ],
                    ],
                ],
                'LabelSpecification' => $this->buildLabelSpecification($labelFormat),
            ],
        ];

        return $request;
    }

    /**
     * Build shipper (origin) address from config
     */
    private function buildShipperAddress(int $storeId, string $accountNumber): array
    {
        // Try to use UPS-specific shipper config first, fallback to store info
        $company = $this->config->getShipperCompany($storeId);
        $contactName = $this->config->getShipperContactName($storeId);
        $phone = $this->config->getShipperPhone($storeId);
        $street = $this->config->getShipperStreet($storeId);
        $city = $this->config->getShipperCity($storeId);
        $state = $this->config->getShipperState($storeId);
        $postalCode = $this->config->getShipperPostalCode($storeId);
        $country = $this->config->getShipperCountry($storeId);

        // Fallback to Magento shipping origin settings if not configured
        if (empty($street)) {
            $company = $company ?: ($this->scopeConfig->getValue(
                'general/store_information/name',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ) ?? '');
            $contactName = $contactName ?: $company;
            $phone = $phone ?: ($this->scopeConfig->getValue(
                'general/store_information/phone',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ) ?? '');
            $street = $this->scopeConfig->getValue(
                'shipping/origin/street_line1',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ) ?? '';
            $city = $this->scopeConfig->getValue(
                'shipping/origin/city',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ) ?? '';
            $regionId = $this->scopeConfig->getValue(
                'shipping/origin/region_id',
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            $state = $regionId ? $this->getStateCodeById((int) $regionId) : '';
            $postalCode = $this->scopeConfig->getValue(
                'shipping/origin/postcode',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ) ?? '';
            $country = $this->scopeConfig->getValue(
                'shipping/origin/country_id',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ) ?? 'US';
        }

        // Clean phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);

        return [
            'Name' => $company ?: 'Shipper',
            'AttentionName' => $contactName ?: $company ?: 'Shipper',
            'ShipperNumber' => $accountNumber,
            'Phone' => [
                'Number' => $phone ?: '0000000000',
            ],
            'Address' => [
                'AddressLine' => [$street],
                'City' => $city,
                'StateProvinceCode' => $state,
                'PostalCode' => $postalCode,
                'CountryCode' => $country,
            ],
        ];
    }

    /**
     * Build ship-to address from shipping address
     */
    private function buildShipToAddress($address): array
    {
        $street = $address->getStreet();
        $streetLines = is_array($street) ? array_filter($street) : [$street];

        // Clean phone number
        $phone = preg_replace('/[^0-9]/', '', $address->getTelephone() ?? '');

        $name = trim($address->getFirstname() . ' ' . $address->getLastname());

        $shipTo = [
            'Name' => $address->getCompany() ?: $name,
            'AttentionName' => $name,
            'Phone' => [
                'Number' => $phone ?: '0000000000',
            ],
            'Address' => [
                'AddressLine' => array_values($streetLines),
                'City' => $address->getCity(),
                'StateProvinceCode' => $this->getStateCode(
                    $address->getRegionId() ? (int) $address->getRegionId() : null,
                    $address->getRegion()
                ),
                'PostalCode' => $this->formatPostalCode(
                    $address->getPostcode(),
                    $address->getCountryId()
                ),
                'CountryCode' => $address->getCountryId(),
            ],
        ];

        // Add residential indicator if no company
        if (empty($address->getCompany())) {
            $shipTo['Address']['ResidentialAddressIndicator'] = '';
        }

        return $shipTo;
    }

    /**
     * Build ship-from address (can be different from shipper)
     */
    private function buildShipFromAddress(int $storeId): array
    {
        $company = $this->config->getShipperCompany($storeId);
        $contactName = $this->config->getShipperContactName($storeId);
        $phone = $this->config->getShipperPhone($storeId);
        $street = $this->config->getShipperStreet($storeId);
        $city = $this->config->getShipperCity($storeId);
        $state = $this->config->getShipperState($storeId);
        $postalCode = $this->config->getShipperPostalCode($storeId);
        $country = $this->config->getShipperCountry($storeId);

        // Fallback to Magento shipping origin settings
        if (empty($street)) {
            $company = $company ?: ($this->scopeConfig->getValue(
                'general/store_information/name',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ) ?? '');
            $contactName = $contactName ?: $company;
            $phone = $phone ?: ($this->scopeConfig->getValue(
                'general/store_information/phone',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ) ?? '');
            $street = $this->scopeConfig->getValue(
                'shipping/origin/street_line1',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ) ?? '';
            $city = $this->scopeConfig->getValue(
                'shipping/origin/city',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ) ?? '';
            $regionId = $this->scopeConfig->getValue(
                'shipping/origin/region_id',
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            $state = $regionId ? $this->getStateCodeById((int) $regionId) : '';
            $postalCode = $this->scopeConfig->getValue(
                'shipping/origin/postcode',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ) ?? '';
            $country = $this->scopeConfig->getValue(
                'shipping/origin/country_id',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ) ?? 'US';
        }

        $phone = preg_replace('/[^0-9]/', '', $phone);

        return [
            'Name' => $company ?: 'Ship From',
            'AttentionName' => $contactName ?: $company ?: 'Ship From',
            'Phone' => [
                'Number' => $phone ?: '0000000000',
            ],
            'Address' => [
                'AddressLine' => [$street],
                'City' => $city,
                'StateProvinceCode' => $state,
                'PostalCode' => $postalCode,
                'CountryCode' => $country,
            ],
        ];
    }

    /**
     * Build package data
     */
    private function buildPackage(OrderInterface $order, array $packageData, int $storeId): array
    {
        $weight = $packageData['weight'] ?? $this->calculateOrderWeight($order);

        $package = [
            'Description' => 'Order ' . $order->getIncrementId(),
            'Packaging' => [
                'Code' => $this->config->getPackageType($storeId),
            ],
            'Dimensions' => [
                'UnitOfMeasurement' => [
                    'Code' => 'IN',
                ],
                'Length' => (string) (int) ($packageData['length'] ?? $this->config->getDefaultLength($storeId)),
                'Width' => (string) (int) ($packageData['width'] ?? $this->config->getDefaultWidth($storeId)),
                'Height' => (string) (int) ($packageData['height'] ?? $this->config->getDefaultHeight($storeId)),
            ],
            'PackageWeight' => [
                'UnitOfMeasurement' => [
                    'Code' => 'LBS',
                ],
                'Weight' => (string) round($weight, 1),
            ],
        ];

        // Add declared value for insurance (for orders over $100)
        $orderTotal = (float) $order->getGrandTotal();
        if ($orderTotal > 100) {
            $package['PackageServiceOptions'] = [
                'DeclaredValue' => [
                    'CurrencyCode' => $order->getOrderCurrencyCode() ?? 'USD',
                    'MonetaryValue' => (string) round($orderTotal, 2),
                ],
            ];
        }

        return $package;
    }

    /**
     * Build label specification
     */
    private function buildLabelSpecification(string $format): array
    {
        // Map format to UPS image format code
        $imageFormat = $this->mapLabelFormat($format);

        return [
            'LabelImageFormat' => [
                'Code' => $imageFormat,
            ],
            'LabelStockSize' => [
                'Height' => '6',
                'Width' => '4',
            ],
        ];
    }

    /**
     * Calculate order weight from items
     */
    private function calculateOrderWeight(OrderInterface $order): float
    {
        $totalWeight = 0.0;

        foreach ($order->getItems() as $item) {
            if ($item->getProductType() === 'simple' || !$item->getHasChildren()) {
                $weight = (float) $item->getWeight();
                $totalWeight += $weight * (float) $item->getQtyOrdered();
            }
        }

        // Minimum weight of 0.5 lbs
        return max(0.5, $totalWeight);
    }

    /**
     * Extract service code from shipping method
     */
    private function extractServiceCode(?string $shippingMethod): string
    {
        if (empty($shippingMethod)) {
            return '03'; // Default to Ground
        }

        // Format: upsv3_03
        $parts = explode('_', $shippingMethod, 2);
        return $parts[1] ?? '03';
    }

    /**
     * Map label format to UPS image format code
     */
    private function mapLabelFormat(string $format): string
    {
        $map = [
            'GIF' => 'GIF',
            'PNG' => 'PNG',
            'PDF' => 'PDF',
            'ZPL' => 'ZPL',
            'EPL' => 'EPL',
        ];

        return $map[strtoupper($format)] ?? 'GIF';
    }

    /**
     * Get state code from region ID
     */
    private function getStateCode(?int $regionId, ?string $regionName): string
    {
        if ($regionId) {
            return $this->getStateCodeById($regionId);
        }

        // If region is already a 2-letter code
        if ($regionName && strlen($regionName) === 2) {
            return strtoupper($regionName);
        }

        return '';
    }

    /**
     * Get state code by region ID
     */
    private function getStateCodeById(int $regionId): string
    {
        $region = $this->regionFactory->create()->load($regionId);
        return $region->getCode() ?? '';
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

    /**
     * Get service name from code
     */
    public function getServiceName(string $code): string
    {
        return self::SERVICE_NAMES[$code] ?? 'UPS';
    }
}
