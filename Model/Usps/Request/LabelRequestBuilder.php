<?php
/**
 * Jscriptz SmartShipping - USPS Label Request Builder
 *
 * Builds the USPS Labels API request from a Magento order.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Usps\Request;

use Jscriptz\SmartShipping\Model\Usps\Config;
use Jscriptz\SmartShipping\Model\Usps\Config\Source\MailClass;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;

class LabelRequestBuilder
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly RegionFactory $regionFactory,
        private readonly DateTime $dateTime
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

        // Determine mail class from shipping method
        $mailClass = $this->extractMailClass($order->getShippingMethod());

        $request = [
            'imageInfo' => [
                'imageType' => $this->mapLabelFormat($packageData['label_format'] ?? $this->config->getLabelFormat($storeId)),
                'labelType' => '4X6LABEL',
            ],
            'toAddress' => $this->buildToAddress($shippingAddress),
            'fromAddress' => $this->buildFromAddress($storeId),
            'packageDescription' => $this->buildPackageDescription($order, $mailClass, $packageData, $storeId),
        ];

        // Add extra services if needed (e.g., signature confirmation, insurance)
        $extraServices = $this->buildExtraServices($order, $packageData, $storeId);
        if (!empty($extraServices)) {
            $request['extraServices'] = $extraServices;
        }

        return $request;
    }

    /**
     * Build destination address
     */
    private function buildToAddress($address): array
    {
        $street = $address->getStreet();
        $streetAddress = is_array($street) ? implode(' ', $street) : $street;

        // Split into streetAddress and secondaryAddress if needed
        $lines = is_array($street) ? $street : [$street];

        $toAddress = [
            'firstName' => $address->getFirstname(),
            'lastName' => $address->getLastname(),
            'streetAddress' => $lines[0] ?? '',
            'city' => $address->getCity(),
            'state' => $this->getStateCode($address->getRegionId(), $address->getRegion()),
            'ZIPCode' => $this->formatZip($address->getPostcode()),
        ];

        // Add secondary address line if present
        if (isset($lines[1]) && !empty($lines[1])) {
            $toAddress['secondaryAddress'] = $lines[1];
        }

        // Add company name if present
        if ($address->getCompany()) {
            $toAddress['firm'] = $address->getCompany();
        }

        // Add phone if present (some services require it)
        if ($address->getTelephone()) {
            $toAddress['phone'] = preg_replace('/[^0-9]/', '', $address->getTelephone());
        }

        // Add email for notifications
        if ($address->getEmail()) {
            $toAddress['email'] = $address->getEmail();
        }

        return $toAddress;
    }

    /**
     * Build origin address from store configuration
     */
    private function buildFromAddress(int $storeId): array
    {
        $store = $this->storeManager->getStore($storeId);
        $scopeConfig = $store->getScopeConfig();

        // Get origin address from shipping settings
        $fromAddress = [
            'firm' => $scopeConfig->getValue('general/store_information/name') ?? '',
            'streetAddress' => $scopeConfig->getValue('shipping/origin/street_line1') ?? '',
            'city' => $scopeConfig->getValue('shipping/origin/city') ?? '',
            'state' => $scopeConfig->getValue('shipping/origin/region_id')
                ? $this->getStateCodeById((int) $scopeConfig->getValue('shipping/origin/region_id'))
                : '',
            'ZIPCode' => $this->formatZip($scopeConfig->getValue('shipping/origin/postcode') ?? ''),
        ];

        // Add secondary address if present
        $street2 = $scopeConfig->getValue('shipping/origin/street_line2');
        if ($street2) {
            $fromAddress['secondaryAddress'] = $street2;
        }

        // Add phone from store info
        $phone = $scopeConfig->getValue('general/store_information/phone');
        if ($phone) {
            $fromAddress['phone'] = preg_replace('/[^0-9]/', '', $phone);
        }

        return $fromAddress;
    }

    /**
     * Build package description
     */
    private function buildPackageDescription(
        OrderInterface $order,
        string $mailClass,
        array $packageData,
        int $storeId
    ): array {
        // Calculate weight from order items if not provided
        $weight = $packageData['weight'] ?? $this->calculateOrderWeight($order, $storeId);

        $description = [
            'mailClass' => $mailClass,
            'weight' => round($weight, 2),
            'length' => round($packageData['length'] ?? $this->config->getDefaultLength($storeId), 1),
            'width' => round($packageData['width'] ?? $this->config->getDefaultWidth($storeId), 1),
            'height' => round($packageData['height'] ?? $this->config->getDefaultHeight($storeId), 1),
            'mailingDate' => $this->getMailingDate($storeId),
            'processingCategory' => 'MACHINABLE',
            'rateIndicator' => 'SP', // Single-Piece
            'priceType' => $this->config->getPriceType($storeId),
        ];

        return $description;
    }

    /**
     * Build extra services (signature, insurance, etc.)
     */
    private function buildExtraServices(OrderInterface $order, array $packageData, int $storeId): array
    {
        $services = [];

        // Add insurance if order value is high
        $orderTotal = (float) $order->getGrandTotal();
        if ($orderTotal > 100) {
            $services[] = [
                'serviceCode' => 'INSURANCE',
                'value' => round($orderTotal, 2),
            ];
        }

        return $services;
    }

    /**
     * Calculate order weight from items
     */
    private function calculateOrderWeight(OrderInterface $order, int $storeId): float
    {
        $totalWeight = 0.0;

        foreach ($order->getItems() as $item) {
            if ($item->getProductType() === 'simple' || !$item->getHasChildren()) {
                $totalWeight += (float) $item->getWeight() * (float) $item->getQtyOrdered();
            }
        }

        // Use default weight if no weight calculated
        if ($totalWeight <= 0) {
            $totalWeight = $this->config->getDefaultWeight($storeId);
        }

        // Ensure minimum weight (USPS requires at least 0.1 lbs)
        return max(0.1, $totalWeight);
    }

    /**
     * Get mailing date (next business day if past cutoff)
     */
    private function getMailingDate(int $storeId): string
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
     * Extract mail class from shipping method
     */
    private function extractMailClass(?string $shippingMethod): string
    {
        if (empty($shippingMethod)) {
            return MailClass::GROUND_ADVANTAGE;
        }

        // Format: uspsv3_MAIL_CLASS
        $parts = explode('_', $shippingMethod, 2);
        return $parts[1] ?? MailClass::GROUND_ADVANTAGE;
    }

    /**
     * Map label format to USPS API format
     */
    private function mapLabelFormat(string $format): string
    {
        $map = [
            'PDF' => 'PDF',
            'PNG' => 'PNG',
            'ZPL' => 'ZPL203DPI',
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
     * Format ZIP code (5 digits or 5+4)
     */
    private function formatZip(string $zip): string
    {
        // Remove any non-numeric characters except dash
        $zip = preg_replace('/[^0-9\-]/', '', $zip);

        // If contains dash, it's already formatted
        if (strpos($zip, '-') !== false) {
            return $zip;
        }

        // If 9 digits, format as XXXXX-XXXX
        if (strlen($zip) === 9) {
            return substr($zip, 0, 5) . '-' . substr($zip, 5);
        }

        // Return first 5 digits
        return substr($zip, 0, 5);
    }
}
