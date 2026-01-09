<?php
/**
 * Jscriptz SmartShipping - FedEx Rate Response Parser
 *
 * Parses FedEx Rate API response and extracts rates and transit times.
 * Unlike UPS, FedEx includes transit time data directly in the Rate response.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Fedex\Response;

use Jscriptz\SmartShipping\Model\Fedex\Config;
use Jscriptz\SmartShipping\Model\Fedex\Config\Source\ServiceType;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Psr\Log\LoggerInterface;

class RateResponseParser
{
    private const CARRIER_CODE = 'fedexv3';

    /**
     * Extracted transit times from last parse, keyed by service code
     */
    private array $extractedTransitTimes = [];

    public function __construct(
        private readonly Config $config,
        private readonly ServiceType $serviceType,
        private readonly ResultFactory $rateResultFactory,
        private readonly MethodFactory $rateMethodFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Parse FedEx Rate API response into Magento shipping methods
     */
    public function parse(array $response, RateRequest $request, ?int $storeId = null): Result
    {
        $result = $this->rateResultFactory->create();
        $this->extractedTransitTimes = [];

        // Get allowed methods from config
        $allowedMethods = $this->config->getAllowedMethods($storeId);

        // Get rate details from response
        $rateReplyDetails = $response['output']['rateReplyDetails'] ?? [];

        if (empty($rateReplyDetails)) {
            if ($this->config->isDebugEnabled($storeId)) {
                $this->logger->debug('[FEDEXv3 Parser] No rate details in response');
            }
            return $result;
        }

        // Get subtotal for free shipping check
        $subtotal = (float) $request->getPackageValue();
        $freeShippingThreshold = $this->config->getFreeShippingThreshold($storeId);
        $handlingFee = $this->config->getHandlingFee($storeId);

        foreach ($rateReplyDetails as $rateDetail) {
            $serviceCode = $rateDetail['serviceType'] ?? '';

            // Skip if not in allowed methods (unless allowed is empty = all allowed)
            if (!empty($allowedMethods) && !in_array($serviceCode, $allowedMethods)) {
                continue;
            }

            // Get rate from rated shipment details
            $ratedShipmentDetails = $rateDetail['ratedShipmentDetails'] ?? [];
            if (empty($ratedShipmentDetails)) {
                continue;
            }

            // Use first rated option (usually ACCOUNT rate, or LIST if no account)
            $rateData = $ratedShipmentDetails[0];
            $totalNetCharge = $rateData['totalNetCharge'] ?? 0;

            if ($totalNetCharge <= 0) {
                // Try alternate structure
                $totalNetCharge = $rateData['totalNetFedExCharge'] ?? $rateData['totalNetChargeWithDutiesAndTaxes'] ?? 0;
            }

            // Convert to float
            $price = (float) $totalNetCharge;

            if ($price <= 0) {
                if ($this->config->isDebugEnabled($storeId)) {
                    $this->logger->debug('[FEDEXv3 Parser] Skipping service with zero price', [
                        'service' => $serviceCode,
                    ]);
                }
                continue;
            }

            // Apply handling fee
            $price += $handlingFee;

            // Check for free shipping
            if ($freeShippingThreshold > 0 && $subtotal >= $freeShippingThreshold) {
                $price = 0;
            }

            // Get service label
            $serviceLabel = $this->serviceType->getServiceLabel($serviceCode);

            // Extract transit time data
            $transitData = $this->extractTransitTime($rateDetail, $storeId);
            if (!empty($transitData)) {
                $this->extractedTransitTimes[$serviceCode] = $transitData;
            }

            // Create rate method
            $method = $this->rateMethodFactory->create();
            $method->setCarrier(self::CARRIER_CODE);
            $method->setCarrierTitle($this->config->getTitle($storeId));
            $method->setMethod($serviceCode);
            $method->setMethodTitle($serviceLabel);
            $method->setPrice($price);
            $method->setCost($price - $handlingFee);

            $result->append($method);

            if ($this->config->isDebugEnabled($storeId)) {
                $this->logger->debug('[FEDEXv3 Parser] Added rate', [
                    'service' => $serviceCode,
                    'label' => $serviceLabel,
                    'price' => $price,
                    'transit' => $transitData,
                ]);
            }
        }

        return $result;
    }

    /**
     * Extract transit time data from rate detail
     *
     * FedEx includes delivery date information in the Rate response,
     * unlike UPS which requires a separate Time in Transit API call.
     */
    private function extractTransitTime(array $rateDetail, ?int $storeId): array
    {
        $transitData = [];

        // Get operational detail which contains transit information
        $operationalDetail = $rateDetail['operationalDetail'] ?? [];

        // Transit time in days
        if (isset($operationalDetail['transitTime'])) {
            $transitData['transit_time'] = $operationalDetail['transitTime'];
            $transitData['business_days'] = $this->parseTransitTimeToDays($operationalDetail['transitTime']);
        }

        // Delivery date and time
        if (isset($operationalDetail['deliveryDate'])) {
            $transitData['delivery_date'] = $operationalDetail['deliveryDate'];
        }

        if (isset($operationalDetail['deliveryTime'])) {
            $transitData['delivery_time'] = $operationalDetail['deliveryTime'];
        }

        // Day of week
        if (isset($operationalDetail['deliveryDay'])) {
            $transitData['delivery_day_of_week'] = $operationalDetail['deliveryDay'];
        }

        // Commit (guaranteed delivery) information
        $commit = $rateDetail['commit'] ?? [];
        if (!empty($commit)) {
            if (isset($commit['dateDetail']['dayOfWeek'])) {
                $transitData['delivery_day_of_week'] = $commit['dateDetail']['dayOfWeek'];
            }
            if (isset($commit['dateDetail']['dayCxsFormat'])) {
                $transitData['delivery_date'] = $commit['dateDetail']['dayCxsFormat'];
            }
            if (isset($commit['commitTimestamp'])) {
                $transitData['commit_timestamp'] = $commit['commitTimestamp'];
                // Extract date and time from timestamp
                try {
                    $dt = new \DateTime($commit['commitTimestamp']);
                    $transitData['delivery_date'] = $dt->format('Y-m-d');
                    $transitData['delivery_time'] = $dt->format('H:i');
                } catch (\Exception $e) {
                    // Ignore parsing errors
                }
            }

            // Transit days from commit
            if (isset($commit['transitDays'])) {
                $transitData['business_days'] = $this->parseTransitTimeToDays($commit['transitDays']);
            }

            // Guaranteed indicator
            $transitData['guaranteed'] = !empty($commit['guaranteedDeliveryTimestamp'])
                || !empty($commit['commitMessageDetails']);
        }

        // Service description may contain additional info
        $serviceDescription = $rateDetail['serviceDescription'] ?? [];
        if (!empty($serviceDescription['description'])) {
            $transitData['service_description'] = $serviceDescription['description'];
        }

        return $transitData;
    }

    /**
     * Parse FedEx transit time to number of days
     *
     * FedEx returns values like "ONE_DAY", "TWO_DAYS", "THREE_DAYS", etc.
     * Can also return an array with 'description' key or numeric values.
     */
    private function parseTransitTimeToDays(string|array $transitTime): int
    {
        // Handle array input - extract string value
        if (is_array($transitTime)) {
            // Try common keys that might contain the transit time string
            $transitTime = $transitTime['description'] ?? $transitTime['value'] ?? $transitTime[0] ?? '';
            if (!is_string($transitTime)) {
                return 5; // Default if we can't extract a string
            }
        }

        $mapping = [
            'ONE_DAY' => 1,
            'TWO_DAYS' => 2,
            'THREE_DAYS' => 3,
            'FOUR_DAYS' => 4,
            'FIVE_DAYS' => 5,
            'SIX_DAYS' => 6,
            'SEVEN_DAYS' => 7,
            'EIGHT_DAYS' => 8,
            'NINE_DAYS' => 9,
            'TEN_DAYS' => 10,
            'ELEVEN_DAYS' => 11,
            'TWELVE_DAYS' => 12,
            'THIRTEEN_DAYS' => 13,
            'FOURTEEN_DAYS' => 14,
            'FIFTEEN_DAYS' => 15,
            'SIXTEEN_DAYS' => 16,
            'SEVENTEEN_DAYS' => 17,
            'EIGHTEEN_DAYS' => 18,
            'NINETEEN_DAYS' => 19,
            'TWENTY_DAYS' => 20,
        ];

        $transitTime = strtoupper((string) $transitTime);

        if (isset($mapping[$transitTime])) {
            return $mapping[$transitTime];
        }

        // Try to extract number from string
        if (preg_match('/(\d+)/', $transitTime, $matches)) {
            return (int) $matches[1];
        }

        // Default to 5 days if unknown
        return 5;
    }

    /**
     * Get transit times extracted from last parse
     *
     * @return array Keyed by service code
     */
    public function getExtractedTransitTimes(): array
    {
        return $this->extractedTransitTimes;
    }

    /**
     * Get methods from result that don't have transit data
     *
     * FedEx includes transit data for most services, but some may be missing.
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
}
