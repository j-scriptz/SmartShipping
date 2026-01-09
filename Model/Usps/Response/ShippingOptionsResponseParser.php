<?php
/**
 * Jscriptz SmartShipping - USPS Shipping Options Response Parser
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Usps\Response;

use Jscriptz\SmartShipping\Model\Usps\Config;
use Jscriptz\SmartShipping\Model\Usps\Config\Source\MailClass;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Psr\Log\LoggerInterface;

class ShippingOptionsResponseParser
{
    private const CARRIER_CODE = 'uspsv3';
    private array $extractedTransitTimes = [];

    public function __construct(
        private readonly Config $config,
        private readonly MailClass $mailClass,
        private readonly ResultFactory $rateResultFactory,
        private readonly MethodFactory $rateMethodFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function parse(array $response, RateRequest $request, ?int $storeId = null): Result
    {
        $result = $this->rateResultFactory->create();
        $this->extractedTransitTimes = [];

        $allowedMethods = $this->config->getAllowedMethods($storeId);

        $shippingOptions = [];
        if (isset($response['pricingOptions']) && is_array($response['pricingOptions'])) {
            foreach ($response['pricingOptions'] as $pricingOption) {
                if (isset($pricingOption['shippingOptions']) && is_array($pricingOption['shippingOptions'])) {
                    $shippingOptions = array_merge($shippingOptions, $pricingOption['shippingOptions']);
                }
            }
        } elseif (isset($response['shippingOptions'])) {
            $shippingOptions = $response['shippingOptions'];
        }

        if (empty($shippingOptions)) {
            return $result;
        }

        $subtotal = (float) $request->getPackageValue();
        $freeShippingThreshold = $this->config->getFreeShippingThreshold($storeId);
        $handlingFee = $this->config->getHandlingFee($storeId);

        foreach ($shippingOptions as $option) {
            $mailClassCode = $option['mailClass'] ?? '';

            if (!empty($allowedMethods) && !in_array($mailClassCode, $allowedMethods)) {
                continue;
            }

            $price = $this->extractPrice($option);

            if ($price <= 0) {
                continue;
            }

            $price += $handlingFee;

            if ($freeShippingThreshold > 0 && $subtotal >= $freeShippingThreshold) {
                $price = 0;
            }

            $serviceLabel = $this->mailClass->getMailClassLabel($mailClassCode);

            $transitData = $this->extractTransitTime($option, $storeId);
            if (!empty($transitData)) {
                $this->extractedTransitTimes[$mailClassCode] = $transitData;
            }

            $method = $this->rateMethodFactory->create();
            $method->setCarrier(self::CARRIER_CODE);
            $method->setCarrierTitle($this->config->getTitle($storeId));
            $method->setMethod($mailClassCode);
            $method->setMethodTitle($serviceLabel);
            $method->setPrice($price);
            $method->setCost($price - $handlingFee);

            $result->append($method);
        }

        return $result;
    }

    private function extractPrice(array $option): float
    {
        if (isset($option['totalPrice'])) {
            return (float) $option['totalPrice'];
        }
        if (isset($option['totalBasePrice'])) {
            return (float) $option['totalBasePrice'];
        }
        if (isset($option['price'])) {
            return (float) $option['price'];
        }
        if (isset($option['rateOptions']) && is_array($option['rateOptions'])) {
            foreach ($option['rateOptions'] as $rateOption) {
                if (isset($rateOption['totalPrice'])) {
                    return (float) $rateOption['totalPrice'];
                }
            }
        }
        return 0.0;
    }

    private function extractTransitTime(array $option, ?int $storeId): array
    {
        $transitData = [];

        $commitment = $option['commitment'] ?? $option['rateOptions'][0]['commitment'] ?? null;

        if ($commitment !== null) {
            $deliveryDate = $commitment['scheduleDeliveryDate'] ?? $commitment['scheduledDeliveryDateTime'] ?? null;
            if ($deliveryDate) {
                $transitData['commit_timestamp'] = $deliveryDate;
                try {
                    $dt = new \DateTime($deliveryDate);
                    $transitData['delivery_date'] = $dt->format('Y-m-d');
                    $transitData['delivery_day_of_week'] = $dt->format('l');
                } catch (\Exception $e) {
                }
            }

            if (isset($commitment['serviceDays'])) {
                $transitData['business_days'] = (int) $commitment['serviceDays'];
            } elseif (isset($commitment['name'])) {
                $transitData['business_days'] = $this->parseTransitTimeToDays($commitment['name']);
            }

            if (isset($commitment['guaranteedDelivery'])) {
                $transitData['guaranteed'] = (bool) $commitment['guaranteedDelivery'];
            }
        }

        if (isset($option['serviceDays'])) {
            $transitData['business_days'] = (int) $option['serviceDays'];
        }

        $transitData['guaranteed'] = isset($option['guaranteed']) && $option['guaranteed'] === true;

        return $transitData;
    }

    private function parseTransitTimeToDays(string $transitTime): int
    {
        if (preg_match('/(\d+)/', $transitTime, $matches)) {
            return (int) $matches[1];
        }
        return 5;
    }

    public function getExtractedTransitTimes(): array
    {
        return $this->extractedTransitTimes;
    }
}
