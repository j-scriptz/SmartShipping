<?php
/**
 * Jscriptz SmartShipping - UPS Rate Response Parser
 *
 * Parses UPS Rating API response into Magento rate results.
 * Also extracts GuaranteedDelivery transit data for Smart Shipping integration.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Ups\Response;

use Jscriptz\SmartShipping\Model\Ups\Config;
use Jscriptz\SmartShipping\Model\Ups\Config\Source\ServiceType;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory as RateResultFactory;

class RateResponseParser
{
    /**
     * Transit times extracted from the last parsed response
     * @var array
     */
    private array $extractedTransitTimes = [];

    public function __construct(
        private readonly Config $config,
        private readonly RateResultFactory $rateResultFactory,
        private readonly MethodFactory $methodFactory,
        private readonly ServiceType $serviceType
    ) {
    }

    /**
     * Parse UPS API response into Magento rate result
     * Also extracts GuaranteedDelivery data - retrieve via getExtractedTransitTimes()
     */
    public function parse(array $response, RateRequest $request, ?int $storeId = null): Result
    {
        $result = $this->rateResultFactory->create();
        $this->extractedTransitTimes = [];

        // Check for valid response
        if (!isset($response['RateResponse']['RatedShipment'])) {
            return $result;
        }

        $ratedShipments = $response['RateResponse']['RatedShipment'];

        // Ensure it's an array of shipments
        if (isset($ratedShipments['Service'])) {
            $ratedShipments = [$ratedShipments];
        }

        $allowedMethods = $this->config->getAllowedMethods($storeId);
        $handlingFee = $this->config->getHandlingFee($storeId);
        $freeThreshold = $this->config->getFreeShippingThreshold($storeId);
        $cartTotal = $request->getPackageValueWithDiscount();

        foreach ($ratedShipments as $shipment) {
            $serviceCode = $shipment['Service']['Code'] ?? null;

            if (!$serviceCode) {
                continue;
            }

            // Filter by allowed methods (if configured)
            if (!empty($allowedMethods) && !in_array($serviceCode, $allowedMethods)) {
                continue;
            }

            // Get price - prefer negotiated rates
            $price = $this->extractPrice($shipment);
            if ($price === null) {
                continue;
            }

            // Apply handling fee
            $price += $handlingFee;

            // Apply free shipping threshold
            if ($freeThreshold > 0 && $cartTotal >= $freeThreshold) {
                $price = 0;
            }

            // Extract transit data from GuaranteedDelivery (for Smart Shipping)
            $transitData = $this->extractTransitData($shipment);
            if ($transitData) {
                $this->extractedTransitTimes[$serviceCode] = $transitData;
            }

            // Create rate method
            $method = $this->methodFactory->create();
            $method->setCarrier('upsv3');
            $method->setCarrierTitle($this->config->getTitle($storeId));
            $method->setMethod($serviceCode);
            $method->setMethodTitle($this->serviceType->getServiceLabel($serviceCode));
            $method->setPrice($price);
            $method->setCost($price);

            // Add transit days to method title if available
            if ($transitData && isset($transitData['business_days'])) {
                $transitDays = $transitData['business_days'];
                $method->setMethodTitle(
                    $this->serviceType->getServiceLabel($serviceCode) .
                    ' (' . $transitDays . ' ' . ($transitDays == 1 ? 'day' : 'days') . ')'
                );
            }

            $result->append($method);
        }

        return $result;
    }

    /**
     * Get transit times extracted from the last parsed response
     * Returns array keyed by service code: ['03' => ['business_days' => 3, ...], ...]
     */
    public function getExtractedTransitTimes(): array
    {
        return $this->extractedTransitTimes;
    }

    /**
     * Get service codes that don't have transit data (need fallback to Transit API)
     */
    public function getMethodsWithoutTransitData(Result $result): array
    {
        $methodsWithoutData = [];
        foreach ($result->getAllRates() as $rate) {
            $methodCode = $rate->getMethod();
            if (!isset($this->extractedTransitTimes[$methodCode])) {
                $methodsWithoutData[] = $methodCode;
            }
        }
        return $methodsWithoutData;
    }

    /**
     * Extract transit data from GuaranteedDelivery in shipment response
     */
    private function extractTransitData(array $shipment): ?array
    {
        if (!isset($shipment['GuaranteedDelivery'])) {
            return null;
        }

        $guaranteed = $shipment['GuaranteedDelivery'];
        $data = [];

        // Business days in transit
        if (isset($guaranteed['BusinessDaysInTransit'])) {
            $data['business_days'] = (int) $guaranteed['BusinessDaysInTransit'];
        }

        // Delivery by time (e.g., "10:30 A.M.")
        if (isset($guaranteed['DeliveryByTime'])) {
            $data['delivery_time'] = $guaranteed['DeliveryByTime'];
        }

        // Calculate estimated delivery date
        if (isset($data['business_days'])) {
            $data['delivery_date'] = $this->calculateDeliveryDate($data['business_days']);
            $data['delivery_day_of_week'] = (new \DateTime($data['delivery_date']))->format('l');
        }

        // Mark as guaranteed
        $data['guaranteed'] = true;

        return !empty($data) ? $data : null;
    }

    /**
     * Calculate delivery date based on business days
     */
    private function calculateDeliveryDate(int $businessDays): string
    {
        $date = new \DateTime();
        $daysAdded = 0;

        while ($daysAdded < $businessDays) {
            $date->modify('+1 day');
            // Skip weekends (0 = Sunday, 6 = Saturday)
            $dayOfWeek = (int) $date->format('w');
            if ($dayOfWeek !== 0 && $dayOfWeek !== 6) {
                $daysAdded++;
            }
        }

        return $date->format('Y-m-d');
    }

    /**
     * Extract price from rated shipment
     * Prefers negotiated rates over published rates
     */
    private function extractPrice(array $shipment): ?float
    {
        // Try negotiated rates first
        if (isset($shipment['NegotiatedRateCharges']['TotalCharge']['MonetaryValue'])) {
            return (float) $shipment['NegotiatedRateCharges']['TotalCharge']['MonetaryValue'];
        }

        // Fall back to standard rates
        if (isset($shipment['TotalCharges']['MonetaryValue'])) {
            return (float) $shipment['TotalCharges']['MonetaryValue'];
        }

        return null;
    }
}
