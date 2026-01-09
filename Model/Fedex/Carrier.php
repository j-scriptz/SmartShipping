<?php
/**
 * Jscriptz SmartShipping - FedEx Shipping Carrier
 *
 * Integrates with Smart Shipping feature by storing transit times
 * in the TransitTimeRepository for use in checkout display.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Fedex;

use Jscriptz\SmartShipping\Api\TransitTimeRepositoryInterface;
use Jscriptz\SmartShipping\Model\Fedex\Cache\RateCache;
use Jscriptz\SmartShipping\Model\Fedex\Client\RatingClient;
use Jscriptz\SmartShipping\Model\Fedex\Config\Source\ServiceType;
use Jscriptz\SmartShipping\Model\Fedex\Request\RateRequestBuilder;
use Jscriptz\SmartShipping\Model\Fedex\Response\RateResponseParser;
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
    protected $_code = 'fedexv3';
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
        private readonly TransitTimeRepositoryInterface $transitTimeRepository,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * Collect shipping rates
     */
    public function collectRates(RateRequest $request): Result|bool
    {
        if (!$this->config->isActive()) {
            return false;
        }

        // Check if credentials are configured
        if (!$this->config->hasCredentials()) {
            $this->_logger->warning('[FEDEXv3] API credentials not configured');
            return false;
        }

        // Check weight limit
        $weight = (float) $request->getPackageWeight();
        if ($weight > $this->config->getMaxWeight()) {
            if ($this->config->isDebugEnabled()) {
                $this->_logger->debug('[FEDEXv3] Package weight exceeds limit: ' . $weight);
            }
            return $this->getErrorResult(__('Package weight exceeds FedEx limit'));
        }

        // Check country restrictions
        if (!$this->isCountryAllowed($request->getDestCountryId())) {
            if ($this->config->isDebugEnabled()) {
                $this->_logger->debug('[FEDEXv3] Country not allowed: ' . $request->getDestCountryId());
            }
            return false;
        }

        $storeId = $request->getStoreId() ? (int) $request->getStoreId() : null;

        // Check cache first
        $cacheKey = $this->rateCache->buildCacheKey($request);
        if ($this->config->isCacheEnabled($storeId)) {
            $cachedData = $this->rateCache->load($cacheKey, $storeId, true);
            if ($cachedData && !empty($cachedData['rates'])) {
                // Restore cached transit times to repository for Smart Shipping
                if (!empty($cachedData['transit_times'])) {
                    $this->storeTransitTimesInRepository($cachedData['transit_times'], $storeId);
                    if ($this->config->isDebugEnabled($storeId)) {
                        $this->_logger->debug('[FEDEXv3] Restored cached transit times', [
                            'methods' => array_keys($cachedData['transit_times']),
                        ]);
                    }
                }
                return $this->buildResultFromCache($cachedData['rates']);
            }
        }

        // Make API request
        try {
            $apiRequest = $this->requestBuilder->build($request, $storeId);
            $apiResponse = $this->ratingClient->getRates($apiRequest, $storeId);
            $result = $this->responseParser->parse($apiResponse, $request, $storeId);

            // Get transit times extracted from the response
            $transitTimes = $this->responseParser->getExtractedTransitTimes();

            // Ensure all methods have cutoff data, even if API didn't provide transit times
            $transitTimes = $this->ensureAllMethodsHaveCutoffData($result, $transitTimes, $storeId);

            // Store transit times for Smart Shipping integration
            if (!empty($transitTimes)) {
                $this->storeTransitTimesInRepository($transitTimes, $storeId);
            }

            // Cache successful result (including transit times)
            if ($this->config->isCacheEnabled($storeId) && $this->hasValidRates($result)) {
                $ratesArray = $this->rateCache->resultToArray($result);
                $this->rateCache->save($cacheKey, $ratesArray, $storeId, $transitTimes);
            }

            return $result;

        } catch (\Exception $e) {
            $this->_logger->error('[FEDEXv3] Rating API Error: ' . $e->getMessage());

            // Try fallback to cached rates
            if ($this->config->isCacheFallbackEnabled($storeId)) {
                $cachedData = $this->rateCache->loadExpired($cacheKey, $storeId, true);
                if ($cachedData && !empty($cachedData['rates'])) {
                    // Restore cached transit times to repository
                    if (!empty($cachedData['transit_times'])) {
                        $this->storeTransitTimesInRepository($cachedData['transit_times'], $storeId);
                    }
                    return $this->buildResultFromCache($cachedData['rates']);
                }
            }

            // Return error if show method is enabled
            if ($this->config->showMethodIfNotApplicable($storeId)) {
                return $this->getErrorResult(__('Unable to retrieve shipping rates'));
            }

            return false;
        }
    }

    /**
     * Get allowed shipping methods
     */
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

    /**
     * Check if country is allowed
     */
    private function isCountryAllowed(?string $countryId): bool
    {
        if (!$countryId) {
            return false;
        }

        if ($this->config->isShipToAllCountries()) {
            return true;
        }

        $specificCountries = $this->config->getSpecificCountries();
        return in_array($countryId, $specificCountries);
    }

    /**
     * Build result from cached rates
     */
    private function buildResultFromCache(array $cachedRates): Result
    {
        $result = $this->rateResultFactory->create();

        foreach ($cachedRates as $rateData) {
            $method = $this->rateMethodFactory->create();
            $method->setCarrier($rateData['carrier'] ?? 'fedexv3');
            $method->setCarrierTitle($rateData['carrier_title'] ?? $this->config->getTitle());
            $method->setMethod($rateData['method']);
            $method->setMethodTitle($rateData['method_title']);
            $method->setPrice($rateData['price']);
            $method->setCost($rateData['cost']);
            $result->append($method);
        }

        return $result;
    }

    /**
     * Check if result has valid rates
     */
    private function hasValidRates(Result $result): bool
    {
        $rates = $result->getAllRates();
        if (empty($rates)) {
            return false;
        }

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

    /**
     * Store transit times via service contract for Smart Shipping integration
     *
     * @param array $transitTimes Keyed by method code: ['FEDEX_GROUND' => ['business_days' => 3, ...]]
     * @param int|null $storeId
     */
    private function storeTransitTimesInRepository(array $transitTimes, ?int $storeId): void
    {
        try {
            // Convert transit times to format expected by repository
            $formattedData = [];
            foreach ($transitTimes as $methodCode => $data) {
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

            if ($this->config->isDebugEnabled($storeId)) {
                $this->_logger->debug('[FEDEXv3] Stored transit times via service contract', [
                    'carrier' => $this->_code,
                    'methods' => array_keys($formattedData),
                ]);
            }

        } catch (\Exception $e) {
            $this->_logger->warning('[FEDEXv3] Failed to store transit times: ' . $e->getMessage());
        }
    }

    /**
     * Get error result
     */
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

    /**
     * Check if carrier tracking is available
     */
    public function isTrackingAvailable(): bool
    {
        return true;
    }
}
