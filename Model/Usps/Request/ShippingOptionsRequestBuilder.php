<?php
/**
 * Jscriptz SmartShipping - USPS Shipping Options Request Builder
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Usps\Request;

use Jscriptz\SmartShipping\Model\Usps\Config;
use Magento\Quote\Model\Quote\Address\RateRequest;

class ShippingOptionsRequestBuilder
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function build(RateRequest $request, ?int $storeId = null): array
    {
        $originZip = $this->getOriginZipCode($storeId);
        $destZip = $this->getDestinationZipCode($request);
        $destCountry = $request->getDestCountryId() ?? 'US';
        $weight = $this->getPackageWeight($request, $storeId);
        $priceType = $this->config->getPriceType($storeId);

        if ($destCountry === 'US') {
            return $this->buildDomesticRequest($originZip, $destZip, $weight, $priceType);
        }

        return $this->buildInternationalRequest($originZip, $destCountry, $destZip, $weight, $priceType);
    }

    private function buildDomesticRequest(
        string $originZip,
        string $destZip,
        float $weight,
        string $priceType
    ): array {
        $mailingDate = $this->getMailingDate();

        return [
            'pricingOptions' => [
                [
                    'priceType' => $priceType,
                ],
            ],
            'originZIPCode' => $originZip,
            'destinationZIPCode' => $destZip,
            'destinationEntryFacilityType' => 'NONE',
            'packageDescription' => [
                'weight' => $weight,
                'length' => 10,
                'height' => 4,
                'width' => 6,
                'mailClass' => 'USPS_GROUND_ADVANTAGE',
                'mailingDate' => $mailingDate,
            ],
            'shippingFilter' => 'PRICE',
        ];
    }

    private function buildInternationalRequest(
        string $originZip,
        string $destCountry,
        string $destZip,
        float $weight,
        string $priceType
    ): array {
        $mailingDate = $this->getMailingDate();

        return [
            'pricingOptions' => [
                [
                    'priceType' => $priceType,
                ],
            ],
            'originZIPCode' => $originZip,
            'foreignPostalCode' => $destZip,
            'destinationCountryCode' => $destCountry,
            'destinationEntryFacilityType' => 'NONE',
            'packageDescription' => [
                'weight' => $weight,
                'length' => 10,
                'height' => 4,
                'width' => 6,
                'mailingDate' => $mailingDate,
            ],
            'shippingFilter' => 'PRICE',
        ];
    }

    private function getOriginZipCode(?int $storeId): string
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $scopeConfig = $objectManager->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);

        $postcode = $scopeConfig->getValue(
            'shipping/origin/postcode',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $this->normalizeZipCode($postcode ?? '');
    }

    private function getDestinationZipCode(RateRequest $request): string
    {
        $postcode = $request->getDestPostcode() ?? '';
        return $this->normalizeZipCode($postcode);
    }

    private function normalizeZipCode(string $zipCode): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $zipCode);
        return substr($cleaned ?? '', 0, 5);
    }

    private function getPackageWeight(RateRequest $request, ?int $storeId): float
    {
        $weight = (float) $request->getPackageWeight();

        if ($weight <= 0) {
            $weight = 1.0;
        }

        $maxWeight = $this->config->getMaxWeight($storeId);
        if ($weight > $maxWeight) {
            $weight = $maxWeight;
        }

        return round($weight, 1);
    }

    private function getMailingDate(): string
    {
        $now = new \DateTime();
        $dayOfWeek = (int) $now->format('N');

        if ($dayOfWeek === 6) {
            $now->modify('+2 days');
        } elseif ($dayOfWeek === 7) {
            $now->modify('+1 day');
        }

        return $now->format('Y-m-d');
    }
}
