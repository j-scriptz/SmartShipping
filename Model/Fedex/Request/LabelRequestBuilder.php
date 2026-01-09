<?php
/**
 * Jscriptz SmartShipping - FedEx Label Request Builder
 *
 * Builds the FedEx Ship API request from a Magento order.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Fedex\Request;

use Jscriptz\SmartShipping\Model\Config\Source\DimensionRounding;
use Jscriptz\SmartShipping\Model\Config\Source\DimensionUnit;
use Jscriptz\SmartShipping\Model\Config\Source\WeightUnit;
use Jscriptz\SmartShipping\Model\Fedex\Config;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class LabelRequestBuilder
{
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

        // Extract service type from shipping method
        $serviceType = $this->extractServiceType($order->getShippingMethod());

        $request = [
            'labelResponseOptions' => 'LABEL',
            'accountNumber' => [
                'value' => $this->config->getAccountNumber($storeId),
            ],
            'requestedShipment' => [
                'shipper' => $this->buildShipperAddress($storeId),
                'recipients' => [
                    $this->buildRecipientAddress($shippingAddress),
                ],
                'pickupType' => $this->mapPickupType($this->config->getDropOffType($storeId)),
                'serviceType' => $serviceType,
                'packagingType' => $this->config->getPackageType($storeId),
                'shippingChargesPayment' => [
                    'paymentType' => 'SENDER',
                    'payor' => [
                        'responsibleParty' => [
                            'accountNumber' => [
                                'value' => $this->config->getAccountNumber($storeId),
                            ],
                        ],
                    ],
                ],
                'labelSpecification' => $this->buildLabelSpecification($packageData, $storeId),
                'requestedPackageLineItems' => [
                    $this->buildPackageLineItem($order, $packageData, $storeId),
                ],
            ],
        ];

        // Add shipment date
        $request['requestedShipment']['shipDatestamp'] = $this->getShipDate($storeId);

        // Add order reference
        $request['requestedShipment']['requestedPackageLineItems'][0]['customerReferences'] = [
            [
                'customerReferenceType' => 'CUSTOMER_REFERENCE',
                'value' => $order->getIncrementId(),
            ],
        ];

        return $request;
    }

    /**
     * Build shipper (origin) address from config
     */
    private function buildShipperAddress(int $storeId): array
    {
        // Try to use FedEx-specific shipper config first, fallback to store info
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
            'contact' => [
                'personName' => $contactName ?: $company,
                'companyName' => $company,
                'phoneNumber' => $phone,
            ],
            'address' => [
                'streetLines' => [$street],
                'city' => $city,
                'stateOrProvinceCode' => $state,
                'postalCode' => $postalCode,
                'countryCode' => $country,
            ],
        ];
    }

    /**
     * Build recipient address from shipping address
     */
    private function buildRecipientAddress($address): array
    {
        $street = $address->getStreet();
        $streetLines = is_array($street) ? array_filter($street) : [$street];

        // Clean phone number
        $phone = preg_replace('/[^0-9]/', '', $address->getTelephone() ?? '');

        $recipient = [
            'contact' => [
                'personName' => $address->getFirstname() . ' ' . $address->getLastname(),
                'phoneNumber' => $phone,
            ],
            'address' => [
                'streetLines' => array_values($streetLines),
                'city' => $address->getCity(),
                'stateOrProvinceCode' => $this->getStateCode(
                    $address->getRegionId() ? (int) $address->getRegionId() : null,
                    $address->getRegion()
                ),
                'postalCode' => $address->getPostcode(),
                'countryCode' => $address->getCountryId(),
                'residential' => $this->isResidential($address),
            ],
        ];

        // Add company if present
        if ($address->getCompany()) {
            $recipient['contact']['companyName'] = $address->getCompany();
        }

        return $recipient;
    }

    /**
     * Build label specification
     */
    private function buildLabelSpecification(array $packageData, int $storeId): array
    {
        $format = $packageData['label_format'] ?? $this->config->getLabelFormat($storeId);
        $stockType = $this->config->getLabelStockType($storeId);

        // Map format to FedEx image type
        $imageType = $this->mapLabelFormat($format);

        $spec = [
            'labelFormatType' => 'COMMON2D',
            'imageType' => $imageType,
            'labelStockType' => $stockType,
        ];

        // Add resolution for thermal labels
        if (in_array($imageType, ['ZPLII', 'EPL2'])) {
            $spec['resolution'] = 203; // DPI
        }

        return $spec;
    }

    /**
     * Build package line item
     */
    private function buildPackageLineItem(OrderInterface $order, array $packageData, int $storeId): array
    {
        // Calculate weight from order items if not provided
        $weight = $packageData['weight'] ?? $this->calculateOrderWeight($order, $storeId);

        $item = [
            'weight' => [
                'value' => round($weight, 2),
                'units' => 'LB',
            ],
        ];

        // Add dimensions if using YOUR_PACKAGING
        if ($this->config->getPackageType($storeId) === 'YOUR_PACKAGING') {
            // Try to get dimensions from products first, then fall back to package overrides or defaults
            $productDimensions = $this->calculateOrderDimensions($order, $storeId);

            $item['dimensions'] = [
                'length' => (int) ($packageData['length'] ?? $productDimensions['length'] ?? $this->config->getDefaultLength($storeId)),
                'width' => (int) ($packageData['width'] ?? $productDimensions['width'] ?? $this->config->getDefaultWidth($storeId)),
                'height' => (int) ($packageData['height'] ?? $productDimensions['height'] ?? $this->config->getDefaultHeight($storeId)),
                'units' => 'IN',
            ];
        }

        // Add declared value for insurance
        $orderTotal = (float) $order->getGrandTotal();
        if ($orderTotal > 100) {
            $item['declaredValue'] = [
                'amount' => round($orderTotal, 2),
                'currency' => $order->getOrderCurrencyCode() ?? 'USD',
            ];
        }

        return $item;
    }

    /**
     * Calculate order weight from items using configured weight attribute
     */
    private function calculateOrderWeight(OrderInterface $order, int $storeId): float
    {
        $totalWeight = 0.0;
        $weightAttribute = $this->config->getWeightAttribute($storeId);
        $weightUnit = $this->config->getWeightUnit($storeId);

        foreach ($order->getItems() as $item) {
            if ($item->getProductType() === 'simple' || !$item->getHasChildren()) {
                $weight = 0.0;

                // Try to get weight from configured attribute
                if ($weightAttribute) {
                    try {
                        $product = $this->productRepository->getById((int) $item->getProductId());
                        $attrValue = $product->getData($weightAttribute);
                        if ($attrValue !== null && $attrValue !== '') {
                            $weight = (float) $attrValue;
                        }
                    } catch (\Exception $e) {
                        // Fall through to order item weight
                    }
                }

                // Fall back to order item weight if no attribute value
                if ($weight <= 0) {
                    $weight = (float) $item->getWeight();
                }

                $totalWeight += $weight * (float) $item->getQtyOrdered();
            }
        }

        // Convert from configured unit to pounds (FedEx API requires LB)
        if ($totalWeight > 0 && $weightUnit !== WeightUnit::LB) {
            $totalWeight = WeightUnit::toPounds($totalWeight, $weightUnit);
        }

        // Use default weight if no weight calculated
        if ($totalWeight <= 0) {
            $totalWeight = $this->config->getDefaultWeight($storeId);
        }

        // Ensure minimum weight (FedEx requires at least 0.5 lbs for some services)
        return max(0.5, $totalWeight);
    }

    /**
     * Calculate order dimensions from product attributes
     *
     * Returns dimensions in inches (FedEx API requirement)
     * Uses the largest dimensions from all products in the order
     *
     * @return array{length: int|null, width: int|null, height: int|null}
     */
    private function calculateOrderDimensions(OrderInterface $order, int $storeId): array
    {
        $lengthAttribute = $this->config->getLengthAttribute($storeId);
        $widthAttribute = $this->config->getWidthAttribute($storeId);
        $heightAttribute = $this->config->getHeightAttribute($storeId);
        $dimensionUnit = $this->config->getDimensionUnit($storeId);
        $roundingMethod = $this->config->getDimensionRounding($storeId);

        // If no attributes configured, return nulls to use defaults
        if (!$lengthAttribute && !$widthAttribute && !$heightAttribute) {
            return ['length' => null, 'width' => null, 'height' => null];
        }

        $maxLength = 0.0;
        $maxWidth = 0.0;
        $maxHeight = 0.0;

        foreach ($order->getItems() as $item) {
            if ($item->getProductType() === 'simple' || !$item->getHasChildren()) {
                try {
                    $product = $this->productRepository->getById((int) $item->getProductId());

                    // Get dimension values from product attributes
                    if ($lengthAttribute) {
                        $length = (float) ($product->getData($lengthAttribute) ?? 0);
                        $maxLength = max($maxLength, $length);
                    }

                    if ($widthAttribute) {
                        $width = (float) ($product->getData($widthAttribute) ?? 0);
                        $maxWidth = max($maxWidth, $width);
                    }

                    if ($heightAttribute) {
                        $height = (float) ($product->getData($heightAttribute) ?? 0);
                        $maxHeight = max($maxHeight, $height);
                    }
                } catch (\Exception $e) {
                    // Skip products that can't be loaded
                }
            }
        }

        // Convert from configured unit to inches (FedEx API requires IN)
        if ($dimensionUnit !== DimensionUnit::IN) {
            $maxLength = DimensionUnit::toInches($maxLength, $dimensionUnit);
            $maxWidth = DimensionUnit::toInches($maxWidth, $dimensionUnit);
            $maxHeight = DimensionUnit::toInches($maxHeight, $dimensionUnit);
        }

        // Apply rounding (FedEx requires whole numbers for dimensions)
        return [
            'length' => $maxLength > 0 ? DimensionRounding::apply($maxLength, $roundingMethod) : null,
            'width' => $maxWidth > 0 ? DimensionRounding::apply($maxWidth, $roundingMethod) : null,
            'height' => $maxHeight > 0 ? DimensionRounding::apply($maxHeight, $roundingMethod) : null,
        ];
    }

    /**
     * Get ship date (next business day if past cutoff)
     */
    private function getShipDate(int $storeId): string
    {
        $now = new \DateTime();
        $cutoffHour = $this->config->getCutoffHour($storeId);

        // If past cutoff, use next business day
        if ((int) $now->format('H') >= $cutoffHour) {
            $now->modify('+1 weekday');
        }

        // Skip weekends
        while ($now->format('N') >= 6) {
            $now->modify('+1 day');
        }

        return $now->format('Y-m-d');
    }

    /**
     * Extract service type from shipping method
     */
    private function extractServiceType(?string $shippingMethod): string
    {
        if (empty($shippingMethod)) {
            return 'FEDEX_GROUND';
        }

        // Format: fedexv3_SERVICE_TYPE
        $parts = explode('_', $shippingMethod, 2);
        return $parts[1] ?? 'FEDEX_GROUND';
    }

    /**
     * Map label format to FedEx image type
     */
    private function mapLabelFormat(string $format): string
    {
        $map = [
            'PDF' => 'PDF',
            'PNG' => 'PNG',
            'ZPLII' => 'ZPLII',
            'ZPL' => 'ZPLII',
            'EPL2' => 'EPL2',
        ];

        return $map[$format] ?? 'PDF';
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
     * Determine if address is residential
     */
    private function isResidential($address): bool
    {
        // If company is set, assume commercial
        if (!empty($address->getCompany())) {
            return false;
        }

        // Default to residential for consumer addresses
        return true;
    }

    /**
     * Map Rate API pickup types to Ship API pickup types
     *
     * Rate API values: REGULAR_PICKUP, REQUEST_COURIER, DROP_BOX, BUSINESS_SERVICE_CENTER, STATION
     * Ship API values: CONTACT_FEDEX_TO_SCHEDULE, DROPOFF_AT_FEDEX_LOCATION, USE_SCHEDULED_PICKUP
     */
    private function mapPickupType(string $ratePickupType): string
    {
        $map = [
            'REGULAR_PICKUP' => 'USE_SCHEDULED_PICKUP',
            'REQUEST_COURIER' => 'CONTACT_FEDEX_TO_SCHEDULE',
            'DROP_BOX' => 'DROPOFF_AT_FEDEX_LOCATION',
            'BUSINESS_SERVICE_CENTER' => 'DROPOFF_AT_FEDEX_LOCATION',
            'STATION' => 'DROPOFF_AT_FEDEX_LOCATION',
            // Ship API values pass through unchanged
            'USE_SCHEDULED_PICKUP' => 'USE_SCHEDULED_PICKUP',
            'CONTACT_FEDEX_TO_SCHEDULE' => 'CONTACT_FEDEX_TO_SCHEDULE',
            'DROPOFF_AT_FEDEX_LOCATION' => 'DROPOFF_AT_FEDEX_LOCATION',
        ];

        return $map[$ratePickupType] ?? 'DROPOFF_AT_FEDEX_LOCATION';
    }
}
