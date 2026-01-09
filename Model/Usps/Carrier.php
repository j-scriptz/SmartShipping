<?php
/**
 * Jscriptz SmartShipping - USPS Carrier
 *
 * Integrates with SmartShipping by storing transit times in the TransitTimeRepository.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model\Usps;

use Jscriptz\SmartShipping\Api\TransitTimeRepositoryInterface;
use Jscriptz\SmartShipping\Model\Usps\Cache\RateCache;
use Jscriptz\SmartShipping\Model\Usps\Client\ShippingOptionsClient;
use Jscriptz\SmartShipping\Model\Usps\Config\Source\MailClass;
use Jscriptz\SmartShipping\Model\Usps\Request\ShippingOptionsRequestBuilder;
use Jscriptz\SmartShipping\Model\Usps\Response\ShippingOptionsResponseParser;
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
    protected $_code = 'uspsv3';
    protected $_isFixed = false;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        private readonly Config $config,
        private readonly ShippingOptionsClient $shippingOptionsClient,
        private readonly ShippingOptionsRequestBuilder $requestBuilder,
        private readonly ShippingOptionsResponseParser $responseParser,
        private readonly RateCache $rateCache,
        private readonly ResultFactory $rateResultFactory,
        private readonly MethodFactory $rateMethodFactory,
        private readonly MailClass $mailClass,
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

        if (!$this->config->hasCredentials()) {
            $this->_logger->warning('[USPSv3] API credentials not configured');
            return false;
        }

        $weight = (float) $request->getPackageWeight();
        if ($weight > $this->config->getMaxWeight()) {
            if ($this->config->isDebugEnabled()) {
                $this->_logger->debug('[USPSv3] Package weight exceeds limit: ' . $weight);
            }
            return $this->getErrorResult(__('Package weight exceeds USPS limit'));
        }

        if (!$this->isCountryAllowed($request->getDestCountryId())) {
            if ($this->config->isDebugEnabled()) {
                $this->_logger->debug('[USPSv3] Country not allowed: ' . $request->getDestCountryId());
            }
            return false;
        }

        $storeId = $request->getStoreId() ? (int) $request->getStoreId() : null;

        // Check cache first
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

        // Make API request
        try {
            $apiRequest = $this->requestBuilder->build($request, $storeId);
            $apiResponse = $this->shippingOptionsClient->getShippingOptions($apiRequest, $storeId);
            $result = $this->responseParser->parse($apiResponse, $request, $storeId);

            $transitTimes = $this->responseParser->getExtractedTransitTimes();

            // Ensure all methods have cutoff data, even if API didn't provide transit times
            $transitTimes = $this->ensureAllMethodsHaveCutoffData($result, $transitTimes, $storeId);

            if (!empty($transitTimes)) {
                $this->storeTransitTimesInRepository($transitTimes, $storeId);
            }

            if ($this->config->isCacheEnabled($storeId) && $this->hasValidRates($result)) {
                $ratesArray = $this->rateCache->resultToArray($result);
                $this->rateCache->save($cacheKey, $ratesArray, $storeId, $transitTimes);
            }

            return $result;

        } catch (\Exception $e) {
            $this->_logger->error('[USPSv3] Shipping Options API Error: ' . $e->getMessage());

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

    /**
     * Get allowed shipping methods
     */
    public function getAllowedMethods(): array
    {
        $allowedMethods = $this->config->getAllowedMethods();

        if (empty($allowedMethods)) {
            return MailClass::MAIL_CLASSES;
        }

        $methods = [];
        foreach ($allowedMethods as $code) {
            $methods[$code] = $this->mailClass->getMailClassLabel($code);
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

        $specificCountries = $this->config->getSpecificCountries();
        return in_array($countryId, $specificCountries);
    }

    private function buildResultFromCache(array $cachedRates): Result
    {
        $result = $this->rateResultFactory->create();

        foreach ($cachedRates as $rateData) {
            $method = $this->rateMethodFactory->create();
            $method->setCarrier($rateData['carrier'] ?? 'uspsv3');
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

    private function storeTransitTimesInRepository(array $transitTimes, ?int $storeId): void
    {
        try {
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
                $this->_logger->debug('[USPSv3] Stored transit times', [
                    'carrier' => $this->_code,
                    'methods' => array_keys($formattedData),
                ]);
            }

        } catch (\Exception $e) {
            $this->_logger->warning('[USPSv3] Failed to store transit times: ' . $e->getMessage());
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
