<?php
/**
 * Jscriptz SmartShipping - Transit Time Repository
 *
 * Session-backed repository for transit time data. Transit times are ephemeral
 * per checkout session - they're calculated during rate collection and consumed
 * when displaying shipping options.
 */
declare(strict_types=1);

namespace Jscriptz\SmartShipping\Model;

use Jscriptz\SmartShipping\Api\Data\TransitTimeInterface;
use Jscriptz\SmartShipping\Api\TransitTimeRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\ObjectManagerInterface;

class TransitTimeRepository implements TransitTimeRepositoryInterface
{
    private const SESSION_KEY = 'smartshipping_transit_times';

    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly ObjectManagerInterface $objectManager
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(TransitTimeInterface $transitTime): void
    {
        $data = $this->getStoredData();
        $key = $this->buildKey($transitTime->getCarrierCode(), $transitTime->getMethodCode());

        $data[$key] = [
            TransitTimeInterface::CARRIER_CODE => $transitTime->getCarrierCode(),
            TransitTimeInterface::METHOD_CODE => $transitTime->getMethodCode(),
            TransitTimeInterface::MIN_DAYS => $transitTime->getMinDays(),
            TransitTimeInterface::MAX_DAYS => $transitTime->getMaxDays(),
            TransitTimeInterface::DELIVERY_DATE => $transitTime->getDeliveryDate(),
            TransitTimeInterface::DELIVERY_DAY => $transitTime->getDeliveryDay(),
            TransitTimeInterface::DELIVERY_TIME => $transitTime->getDeliveryTime(),
            TransitTimeInterface::GUARANTEED => $transitTime->isGuaranteed(),
            TransitTimeInterface::CUTOFF_HOUR => $transitTime->getCutoffHour(),
        ];

        $this->setStoredData($data);
    }

    /**
     * @inheritDoc
     */
    public function saveForCarrier(string $carrierCode, array $transitTimesData): void
    {
        $data = $this->getStoredData();

        foreach ($transitTimesData as $methodCode => $methodData) {
            $methodCodeStr = (string) $methodCode;
            $key = $this->buildKey($carrierCode, $methodCodeStr);

            $data[$key] = [
                TransitTimeInterface::CARRIER_CODE => $carrierCode,
                TransitTimeInterface::METHOD_CODE => $methodCodeStr,
                TransitTimeInterface::MIN_DAYS => (int) ($methodData['min'] ?? $methodData['business_days'] ?? 1),
                TransitTimeInterface::MAX_DAYS => (int) ($methodData['max'] ?? $methodData['business_days'] ?? 1),
                TransitTimeInterface::DELIVERY_DATE => $methodData['delivery_date'] ?? null,
                TransitTimeInterface::DELIVERY_DAY => $methodData['delivery_day'] ?? $methodData['delivery_day_of_week'] ?? null,
                TransitTimeInterface::DELIVERY_TIME => $methodData['delivery_time'] ?? null,
                TransitTimeInterface::GUARANTEED => (bool) ($methodData['guaranteed'] ?? false),
                TransitTimeInterface::CUTOFF_HOUR => isset($methodData['cutoff_hour']) ? (int) $methodData['cutoff_hour'] : null,
            ];
        }

        $this->setStoredData($data);
    }

    /**
     * @inheritDoc
     */
    public function getByCarrierMethod(string $carrierCode, string $methodCode): ?TransitTimeInterface
    {
        $data = $this->getStoredData();
        $key = $this->buildKey($carrierCode, $methodCode);

        if (!isset($data[$key])) {
            return null;
        }

        return $this->createFromArray($data[$key]);
    }

    /**
     * @inheritDoc
     */
    public function getByCarrier(string $carrierCode): array
    {
        $data = $this->getStoredData();
        $result = [];

        foreach ($data as $key => $item) {
            if (($item[TransitTimeInterface::CARRIER_CODE] ?? '') === $carrierCode) {
                $result[$item[TransitTimeInterface::METHOD_CODE]] = $this->createFromArray($item);
            }
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getAll(): array
    {
        $data = $this->getStoredData();
        $result = [];

        foreach ($data as $item) {
            $result[] = $this->createFromArray($item);
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function clearCarrier(string $carrierCode): void
    {
        $data = $this->getStoredData();

        foreach ($data as $key => $item) {
            if (($item[TransitTimeInterface::CARRIER_CODE] ?? '') === $carrierCode) {
                unset($data[$key]);
            }
        }

        $this->setStoredData($data);
    }

    /**
     * @inheritDoc
     */
    public function clearAll(): void
    {
        $this->setStoredData([]);
    }

    /**
     * @inheritDoc
     */
    public function create(): TransitTimeInterface
    {
        return $this->objectManager->create(TransitTimeInterface::class);
    }

    /**
     * Build storage key from carrier and method codes
     */
    private function buildKey(string $carrierCode, string|int $methodCode): string
    {
        return $carrierCode . '_' . (string) $methodCode;
    }

    /**
     * Get stored data from session
     */
    private function getStoredData(): array
    {
        $quoteId = $this->getQuoteId();
        if (!$quoteId) {
            return [];
        }

        $allData = $this->checkoutSession->getData(self::SESSION_KEY) ?? [];
        return $allData[$quoteId] ?? [];
    }

    /**
     * Set stored data in session
     */
    private function setStoredData(array $data): void
    {
        $quoteId = $this->getQuoteId();
        if (!$quoteId) {
            return;
        }

        $allData = $this->checkoutSession->getData(self::SESSION_KEY) ?? [];
        $allData[$quoteId] = $data;
        $this->checkoutSession->setData(self::SESSION_KEY, $allData);
    }

    /**
     * Get current quote ID
     */
    private function getQuoteId(): ?int
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            return $quote ? (int) $quote->getId() : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create TransitTime instance from array data
     */
    private function createFromArray(array $data): TransitTimeInterface
    {
        $transitTime = $this->create();
        $transitTime->setCarrierCode($data[TransitTimeInterface::CARRIER_CODE] ?? '');
        $transitTime->setMethodCode($data[TransitTimeInterface::METHOD_CODE] ?? '');
        $transitTime->setMinDays((int) ($data[TransitTimeInterface::MIN_DAYS] ?? 0));
        $transitTime->setMaxDays((int) ($data[TransitTimeInterface::MAX_DAYS] ?? 0));
        $transitTime->setDeliveryDate($data[TransitTimeInterface::DELIVERY_DATE] ?? null);
        $transitTime->setDeliveryDay($data[TransitTimeInterface::DELIVERY_DAY] ?? null);
        $transitTime->setDeliveryTime($data[TransitTimeInterface::DELIVERY_TIME] ?? null);
        $transitTime->setGuaranteed((bool) ($data[TransitTimeInterface::GUARANTEED] ?? false));
        $transitTime->setCutoffHour($data[TransitTimeInterface::CUTOFF_HOUR] ?? null);

        return $transitTime;
    }
}
