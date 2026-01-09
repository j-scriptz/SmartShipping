<?php
/**
 * Jscriptz SmartShipping - UPS Carrier
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Ups;

use Jscriptz\SmartShipping\Api\TransitTimeRepositoryInterface;
use Jscriptz\SmartShipping\Model\Ups\Cache\RateCache;
use Jscriptz\SmartShipping\Model\Ups\Client\RatingClient;
use Jscriptz\SmartShipping\Model\Ups\Client\TransitClient;
use Jscriptz\SmartShipping\Model\Ups\Config\Source\ServiceType;
use Jscriptz\SmartShipping\Model\Ups\Request\RateRequestBuilder;
use Jscriptz\SmartShipping\Model\Ups\Request\TransitRequestBuilder;
use Jscriptz\SmartShipping\Model\Ups\Response\RateResponseParser;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Psr\Log\LoggerInterface;

class Carrier extends AbstractCarrier implements CarrierInterface
{
    protected $_code = 'upsv3';
    protected $_isFixed = false;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        private readonly Config $config,
        private readonly RatingClient $ratingClient,
        private readonly RateRequestBuilder $requestBuilder,
        private readonly RateResponseParser $responseParser,
        private readonly RateCache $rateCache,
        private readonly ResultFactory $rateResultFactory,
        private readonly MethodFactory $rateMethodFactory,
        private readonly ServiceType $serviceType,
        private readonly TransitClient $transitClient,
        private readonly TransitRequestBuilder $transitRequestBuilder,
        private readonly TransitTimeRepositoryInterface $transitTimeRepository,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    public function collectRates(RateRequest $request): Result|bool
    {
        if (!$this->config->isActive()) {
            return false;
        }

        if (!$this->config->hasCredentials()) {
            $this->_logger->warning('[UPSv3] API credentials not configured');
            return false;
        }

        $weight = (float) $request->getPackageWeight();
        if ($weight > $this->config->getMaxWeight()) {
            return $this->getErrorResult(__('Package weight exceeds UPS limit'));
        }

        if (!$this->isCountryAllowed($request->getDestCountryId())) {
            return false;
        }

        $storeId = $request->getStoreId() ? (int) $request->getStoreId() : null;

        $cacheKey = $this->rateCache->buildCacheKey($request);
        if ($this->config->isCacheEnabled($storeId)) {
            $cachedData = $this->rateCache->load($cacheKey, $storeId, true);
            if ($cachedData && !empty($cachedData['rates'])) {
                if (!empty($cachedData['transit_times'])) {
                    $this->storeTransitTimesInRepository($cachedData['transit_times'], $storeId);
                }
                return $this->buildResultFromCache($cachedData['rates']);
            }
        }

        try {
            $apiRequest = $this->requestBuilder->build($request, $storeId);
            $apiResponse = $this->ratingClient->getRates($apiRequest, $storeId);
            $result = $this->responseParser->parse($apiResponse, $request, $storeId);

            $transitTimes = [];
            if ($this->hasValidRates($result)) {
                $transitTimes = $this->processTransitTimes($result, $request, $storeId);
                // Ensure all methods have cutoff data, even if API didn't provide transit times
                $transitTimes = $this->ensureAllMethodsHaveCutoffData($result, $transitTimes, $storeId);
            }

            if ($this->config->isCacheEnabled($storeId) && $this->hasValidRates($result)) {
                $ratesArray = $this->rateCache->resultToArray($result);
                $this->rateCache->save($cacheKey, $ratesArray, $storeId, $transitTimes);
            }

            return $result;

        } catch (\Exception $e) {
            $this->_logger->error('[UPSv3] Rating API Error: ' . $e->getMessage());

            if ($this->config->isCacheFallbackEnabled($storeId)) {
                $cachedData = $this->rateCache->loadExpired($cacheKey, $storeId, true);
                if ($cachedData && !empty($cachedData['rates'])) {
                    if (!empty($cachedData['transit_times'])) {
                        $this->storeTransitTimesInRepository($cachedData['transit_times'], $storeId);
                    }
                    return $this->buildResultFromCache($cachedData['rates']);
                }
            }

            if ($this->config->showMethodIfNotApplicable($storeId)) {
                return $this->getErrorResult(__('Unable to retrieve shipping rates'));
            }

            return false;
        }
    }

    private function processTransitTimes(Result $result, RateRequest $request, ?int $storeId): array
    {
        $transitTimes = $this->responseParser->getExtractedTransitTimes();
        $methodsWithoutData = $this->responseParser->getMethodsWithoutTransitData($result);

        if ($this->config->isTransitEnabled($storeId) && !empty($methodsWithoutData)) {
            try {
                $transitRequest = $this->transitRequestBuilder->build($request, $storeId);
                $apiTransitTimes = $this->transitClient->getTransitTimes($transitRequest, $storeId);

                foreach ($methodsWithoutData as $methodCode) {
                    if (isset($apiTransitTimes[$methodCode])) {
                        $transitTimes[$methodCode] = $apiTransitTimes[$methodCode];
                    }
                }
            } catch (\Exception $e) {
                $this->_logger->warning('[UPSv3] Transit API fallback failed: ' . $e->getMessage());
            }
        }

        if (!empty($transitTimes)) {
            $this->storeTransitTimesInRepository($transitTimes, $storeId);
        }

        return $transitTimes;
    }

    public function getAllowedMethods(): array
    {
        $allowedMethods = $this->config->getAllowedMethods();

        if (empty($allowedMethods)) {
            return ServiceType::SERVICES;
        }

        $methods = [];
        foreach ($allowedMethods as $code) {
            $methods[$code] = $this->serviceType->getServiceLabel($code);
        }

        return $methods;
    }

    private function isCountryAllowed(?string $countryId): bool
    {
        if (!$countryId) {
            return false;
        }

        if ($this->config->isShipToAllCountries()) {
            return true;
        }

        return in_array($countryId, $this->config->getSpecificCountries());
    }

    private function buildResultFromCache(array $cachedRates): Result
    {
        $result = $this->rateResultFactory->create();

        foreach ($cachedRates as $rateData) {
            $method = $this->rateMethodFactory->create();
            $method->setCarrier($rateData['carrier'] ?? 'upsv3');
            $method->setCarrierTitle($rateData['carrier_title'] ?? $this->config->getTitle());
            $method->setMethod($rateData['method']);
            $method->setMethodTitle($rateData['method_title']);
            $method->setPrice($rateData['price']);
            $method->setCost($rateData['cost']);
            $result->append($method);
        }

        return $result;
    }

    private function hasValidRates(Result $result): bool
    {
        $rates = $result->getAllRates();
        foreach ($rates as $rate) {
            if ($rate->getPrice() !== null && $rate->getPrice() >= 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ensure all methods in result have cutoff data, even if API didn't provide transit times
     */
    private function ensureAllMethodsHaveCutoffData(Result $result, array $transitTimes, ?int $storeId): array
    {
        foreach ($result->getAllRates() as $rate) {
            $methodCode = $rate->getMethod();
            if (!isset($transitTimes[$methodCode])) {
                // Create basic transit data with cutoff info for methods without API data
                $transitTimes[$methodCode] = [
                    'business_days' => null, // Unknown from API
                    'guaranteed' => false,
                ];
            }
        }
        return $transitTimes;
    }

    private function storeTransitTimesInRepository(array $transitTimes, ?int $storeId): void
    {
        try {
            $formattedData = [];
            foreach ($transitTimes as $methodCode => $data) {
                // Ensure method code is string (UPS uses numeric codes like "03" that PHP may convert to int)
                $methodCode = (string) $methodCode;
                $formattedData[$methodCode] = [
                    'min' => (int) ($data['business_days'] ?? 1),
                    'max' => (int) ($data['business_days'] ?? 1),
                    'delivery_date' => $data['delivery_date'] ?? null,
                    'delivery_day' => $data['delivery_day_of_week'] ?? null,
                    'delivery_time' => $data['delivery_time'] ?? null,
                    'guaranteed' => $data['guaranteed'] ?? false,
                    'cutoff_hour' => $this->config->getMethodCutoffHour($methodCode, $storeId),
                    'pickup_days' => $this->config->getPickupDays($storeId),
                    'pickup_hour' => $this->config->getPickupHour($storeId),
                    'grace_period' => $this->config->getGracePeriod($storeId),
                    'grace_period_unit' => $this->config->getGracePeriodUnit($storeId),
                ];
            }

            $this->transitTimeRepository->saveForCarrier($this->_code, $formattedData);

        } catch (\Exception $e) {
            $this->_logger->warning('[UPSv3] Failed to store transit times: ' . $e->getMessage());
        }
    }

    private function getErrorResult($message): Result
    {
        $result = $this->rateResultFactory->create();
        $error = $this->_rateErrorFactory->create();
        $error->setCarrier($this->_code);
        $error->setCarrierTitle($this->config->getTitle());
        $error->setErrorMessage($message);
        $result->append($error);
        return $result;
    }

    public function isTrackingAvailable(): bool
    {
        return true;
    }
}
